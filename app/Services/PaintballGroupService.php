<?php

namespace App\Services;

use App\Models\HangoutPaintballGroup;
use App\Models\HangoutRegistration;
use App\Models\HangoutRegistrationChild;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The paintball groups: everyone who picked paintball (adults and the
 * children they bring), in groups that play against each other.
 *
 * Groups are kept. A player is given a group once and keeps it: updating
 * their registration doesn't move them, and nor does anyone else
 * registering. They leave it only by switching to another game (and get a
 * new group if they come back), or when an admin moves them or rearranges
 * every group.
 *
 * A new player joins a group that still has fewer than `min_group_size`
 * players: of those, the one with the fewest children (for a child), then
 * the fewest of their gender, then the fewest players. When every group has
 * enough, they start the next one. So groups fill up one after another,
 * as many as there are players for.
 *
 * Players who picked paintball before any group existed are arranged evenly
 * (see arrange()), as the page showed them before groups were kept. Admins
 * can do the same for everyone at any time.
 */
class PaintballGroupService
{
    /** A player is an adult on their own registration, or a child on a parent's. */
    public const KINDS = ['adult', 'child'];

    public function game(): string
    {
        return (string) config('hangout.paintball.game', 'Paintball');
    }

    public function minGroupSize(): int
    {
        return max(1, (int) config('hangout.paintball.min_group_size', 5));
    }

    /**
     * Every group, in the order they were made: number, id, name, Bible
     * reference and motto, its counts and its members. Each member is
     * `kind` and `id` (which adult or child), name, gender, whether they're a
     * child, and whose registration they're on. Anyone still without a group
     * is given one first. Read fresh each time: nothing is kept between calls.
     *
     * @return array<int, array{id: int, number: int, name: string, reference: ?string, motto: ?string, size: int, women: int, men: int, children: int, members: array<int, array>}>
     */
    public function groups(): array
    {
        $this->assignNewPlayers();

        return $this->load();
    }

    /**
     * The groups that someone on this registration (the adult or one of
     * their children) is in, with everyone's name and gender only, and each
     * member on this registration marked `yours`. Pass `$groups` (from
     * groups()) when showing several registrations, to read them once.
     */
    public function forRegistration(HangoutRegistration $registration, ?array $groups = null): array
    {
        $yours = fn(array $member) => $member['registration_id'] === $registration->id;

        return collect($groups ?? $this->groups())
            ->filter(fn(array $group) => collect($group['members'])->contains($yours))
            ->map(function (array $group) use ($yours) {
                unset($group['id']);
                $group['members'] = array_map(fn(array $member) => [
                    'full_name' => $member['full_name'],
                    'gender'    => $member['gender'],
                    'child'     => $member['child'],
                    'yours'     => $yours($member),
                ], $group['members']);

                return $group;
            })
            ->values()
            ->all();
    }

    /**
     * Every group for admins: each member's kind and id (to move them), name,
     * gender, whether they're a child, and the registration's name and phone
     * (the parent's, for a child).
     */
    public function forAdmin(): array
    {
        $groups = $this->groups();
        $ids = collect($groups)->flatMap(fn(array $group) => array_column($group['members'], 'registration_id'))->unique()->values();
        $contacts = HangoutRegistration::whereIn('id', $ids)->get(['id', 'full_name', 'phone_number'])->keyBy('id');

        return array_map(function (array $group) use ($contacts) {
            $group['members'] = array_map(fn(array $member) => [
                'kind'         => $member['kind'],
                'id'           => $member['id'],
                'full_name'    => $member['full_name'],
                'gender'       => $member['gender'],
                'child'        => $member['child'],
                'parent'       => $member['child'] ? $contacts->get($member['registration_id'])?->full_name : null,
                'phone_number' => $contacts->get($member['registration_id'])?->phone_number,
            ], $group['members']);

            return $group;
        }, $groups);
    }

    /**
     * Gives a group to every paintball player who hasn't one yet, in the
     * order they registered. Nobody who has a group is moved.
     */
    public function assignNewPlayers(): void
    {
        $waiting = fn(string $kind) => $this->playersQuery($kind)->whereNull('paintball_group_id')->exists();
        if (!$waiting('adult') && !$waiting('child')) {
            return;
        }

        DB::transaction(function () {
            // Locked first, so two people registering at once can't both start a new group.
            $groups = HangoutPaintballGroup::query()->orderBy('id')->lockForUpdate()->get();
            $waiting = $this->players(assigned: false);
            if ($waiting->isEmpty()) {
                return;
            }

            if ($groups->isEmpty()) {
                // No groups yet: the very first player, or everyone who picked
                // paintball before groups were kept.
                $this->save($this->arrange($waiting), $groups);

                return;
            }

            $tally = $this->tally($groups);
            $placed = [];
            foreach ($waiting as $player) {
                $placed[$this->place($player, $tally)][] = $player;
            }
            $this->assign($placed);
        }, 3);
    }

    /**
     * Admin: arrange everyone who plays paintball into even groups again, as
     * many as there can be with at least `min_group_size` each (see
     * arrange()). Existing groups keep their names; any left over are removed.
     */
    public function rearrange(): void
    {
        DB::transaction(function () {
            $groups = HangoutPaintballGroup::query()->orderBy('id')->lockForUpdate()->get();
            $players = $this->players();
            if ($players->isNotEmpty()) {
                $this->save($this->arrange($players), $groups);
            }
        }, 3);
    }

    /**
     * Admin: a new, empty group. Without a name, it takes the next Bible name
     * not yet used, with its verse and motto.
     */
    public function createGroup(array $attributes = []): HangoutPaintballGroup
    {
        if (blank($attributes['name'] ?? null)) {
            $attributes = $this->nextName(HangoutPaintballGroup::pluck('name')->all());
        }

        return HangoutPaintballGroup::create(Arr::only($attributes, ['name', 'reference', 'motto']));
    }

    /**
     * Admin: remove a group nobody is in.
     *
     * @throws ValidationException when players are still in it
     */
    public function deleteGroup(HangoutPaintballGroup $group): void
    {
        $inIt = fn(string $kind) => $this->playersQuery($kind)->where('paintball_group_id', $group->id)->exists();
        if ($inIt('adult') || $inIt('child')) {
            throw ValidationException::withMessages(['group' => "Move the players in {$group->name} to other groups first."]);
        }

        $group->delete();
    }

    /**
     * Admin: move one adult or child into a group. They stay there when they
     * update their registration, like any other player.
     *
     * @return string the player's name
     *
     * @throws ValidationException when they don't play paintball (any more)
     */
    public function move(string $kind, int $id, HangoutPaintballGroup $group): string
    {
        $player = $this->playersQuery($kind)->whereKey($id)->first(['id', 'full_name']);
        if (!$player) {
            throw ValidationException::withMessages(['player' => 'That player isn’t playing paintball any more. Refresh to see the latest groups.']);
        }

        // Straight to the table, so the registration's "last updated" stays the member's own.
        $this->playersQuery($kind)->whereKey($id)->toBase()->update(['paintball_group_id' => $group->id]);

        return $player->full_name;
    }

    /** The adults ('adult') or children ('child') who picked paintball. */
    private function playersQuery(string $kind): Builder
    {
        $model = $kind === 'child' ? HangoutRegistrationChild::class : HangoutRegistration::class;

        return $model::query()->where('game', $this->game());
    }

    /**
     * Adults and children who picked paintball, in the order they
     * registered: all of them, only those with a group, or only those without.
     */
    private function players(?bool $assigned = null): Collection
    {
        $filter = fn(Builder $query) => match ($assigned) {
            true    => $query->whereNotNull('paintball_group_id'),
            false   => $query->whereNull('paintball_group_id'),
            default => $query,
        };

        $adults = $filter($this->playersQuery('adult'))
            ->get(['id', 'full_name', 'gender', 'paintball_group_id', 'created_at'])
            ->map(fn(HangoutRegistration $r) => [
                'kind' => 'adult', 'id' => $r->id, 'full_name' => $r->full_name, 'gender' => $r->gender, 'child' => false,
                'registration_id' => $r->id, 'group_id' => $r->paintball_group_id,
                'order' => [$r->created_at?->getTimestamp() ?? 0, 0, $r->id],
            ]);

        $children = $filter($this->playersQuery('child'))
            ->get(['id', 'hangout_registration_id', 'full_name', 'gender', 'paintball_group_id', 'created_at'])
            ->map(fn(HangoutRegistrationChild $c) => [
                'kind' => 'child', 'id' => $c->id, 'full_name' => $c->full_name, 'gender' => $c->gender, 'child' => true,
                'registration_id' => $c->hangout_registration_id, 'group_id' => $c->paintball_group_id,
                'order' => [$c->created_at?->getTimestamp() ?? 0, 1, $c->id],
            ]);

        return $adults->concat($children)->sortBy('order')->values();
    }

    /** The groups as stored, numbered in order, each with its members: adults first, then children, in the order they registered. */
    private function load(): array
    {
        $members = $this->players(assigned: true)->groupBy('group_id');

        return HangoutPaintballGroup::query()->orderBy('id')->get()->values()
            ->map(function (HangoutPaintballGroup $group, int $index) use ($members) {
                $list = collect($members->get($group->id, []))
                    ->sortBy(fn(array $member) => [$member['child'] ? 1 : 0, ...$member['order']])
                    ->map(fn(array $member) => Arr::except($member, ['order', 'group_id']))
                    ->values()
                    ->all();

                return [
                    'id'        => $group->id,
                    'number'    => $index + 1,
                    'name'      => $group->name,
                    'reference' => $group->reference,
                    'motto'     => $group->motto,
                    'size'      => count($list),
                    'women'     => count(array_filter($list, fn(array $m) => $m['gender'] === 'Female')),
                    'men'       => count(array_filter($list, fn(array $m) => $m['gender'] === 'Male')),
                    'children'  => count(array_filter($list, fn(array $m) => $m['child'])),
                    'members'   => $list,
                ];
            })
            ->all();
    }

    private static function genderOf(array $player): string
    {
        return in_array($player['gender'], ['Female', 'Male'], true) ? $player['gender'] : 'unknown';
    }

    private static function emptyTally(): array
    {
        return ['size' => 0, 'children' => 0, 'Female' => 0, 'Male' => 0, 'unknown' => 0];
    }

    private static function count(array &$tally, array $player): void
    {
        $tally['size']++;
        $tally[self::genderOf($player)]++;
        $tally['children'] += $player['child'] ? 1 : 0;
    }

    /** Each group's counts, by group id in the order the groups were made. */
    private function tally(Collection $groups): array
    {
        $tally = [];
        foreach ($groups as $group) {
            $tally[$group->id] = self::emptyTally();
        }
        foreach ($this->players(assigned: true) as $player) {
            if (isset($tally[$player['group_id']])) {
                self::count($tally[$player['group_id']], $player);
            }
        }

        return $tally;
    }

    /**
     * The group a new player joins (see the class comment), starting a new
     * one when every group already has enough players.
     *
     * @return int the group's id
     */
    private function place(array $player, array &$tally): int
    {
        $open = array_filter($tally, fn(array $t) => $t['size'] < $this->minGroupSize());
        if (!$open) {
            $group = $this->createGroup();
            $tally[$group->id] = self::emptyTally();
            $open = [$group->id => $tally[$group->id]];
        }

        $gender = self::genderOf($player);
        $best = null;
        $bestScore = null;
        foreach ($open as $id => $t) {
            $score = [$player['child'] ? $t['children'] : 0, $t[$gender], $t['size'], $id];
            if ($bestScore === null || $score < $bestScore) {
                [$best, $bestScore] = [$id, $score];
            }
        }
        self::count($tally[$best], $player);

        return $best;
    }

    /**
     * Even groups for these players: as many as there can be with at least
     * `min_group_size` each (all in one while there are fewer than twice
     * that), sizes within one of each other, and the groups' counts of
     * children, of women and of men each within one too.
     *
     * Places each player in turn, children first (girls, boys, then any
     * without a gender), then adults the same way. Each goes to the group,
     * among those with room, that has:
     *   1. the fewest children (for a child), so children are spread evenly;
     *   2. the fewest people of their gender, so women and men are mixed evenly;
     *   3. the fewest people, so the groups fill up evenly;
     *   4. the lowest number, so the same players always give the same groups.
     *
     * @return array<int, array<int, array>> the players in each group
     */
    private function arrange(Collection $players): array
    {
        $count = $players->count();
        if ($count === 0) {
            return [];
        }
        $groupCount = max(1, intdiv($count, $this->minGroupSize()));
        // Every group gets `floor` players; `extra` of them (whichever fill up
        // that way) get one more. Not fixing which in advance lets the extra
        // places go where they keep the mix even.
        $floor = intdiv($count, $groupCount);
        $extra = $count % $groupCount;

        $ordered = $players->sortBy(fn(array $p) => [
            $p['child'] ? 0 : 1,
            match ($p['gender']) { 'Female' => 0, 'Male' => 1, default => 2 },
            ...$p['order'],
        ])->values();

        $placed = array_fill(0, $groupCount, []);
        $tally = array_fill(0, $groupCount, self::emptyTally());
        foreach ($ordered as $player) {
            $gender = self::genderOf($player);
            $full = count(array_filter($tally, fn(array $t) => $t['size'] > $floor));
            $best = null;
            $bestScore = null;
            foreach ($tally as $i => $t) {
                $hasRoom = $t['size'] < $floor || ($t['size'] === $floor && $full < $extra);
                if (!$hasRoom) {
                    continue;
                }
                $score = [$player['child'] ? $t['children'] : 0, $t[$gender], $t['size'], $i];
                if ($bestScore === null || $score < $bestScore) {
                    [$best, $bestScore] = [$i, $score];
                }
            }
            $placed[$best][] = $player;
            self::count($tally[$best], $player);
        }

        return $placed;
    }

    /**
     * Saves an arrangement: the first group's players into the first
     * existing group, and so on, making groups when there aren't enough and
     * removing any left over (they're empty by then).
     */
    private function save(array $arranged, Collection $groups): void
    {
        $groups = $groups->values();
        $placed = [];
        foreach ($arranged as $index => $players) {
            $group = $groups->get($index) ?? $this->createGroup();
            $placed[$group->id] = $players;
        }
        $this->assign($placed);

        $groups->slice(count($arranged))->each->delete();
    }

    /**
     * Puts players into groups: [group id => players]. Straight to the
     * tables, so registrations' "last updated" stays the members' own.
     */
    private function assign(array $placed): void
    {
        foreach ($placed as $groupId => $players) {
            foreach (self::KINDS as $kind) {
                $ids = array_column(array_filter($players, fn(array $p) => $p['kind'] === $kind), 'id');
                if ($ids) {
                    $this->playersQuery($kind)->whereIn('id', $ids)->toBase()->update(['paintball_group_id' => $groupId]);
                }
            }
        }
    }

    /**
     * The first Bible name in the list that no group has, with its verse and
     * motto. After a full round, the names come again with "II", then "III".
     */
    private function nextName(array $taken): array
    {
        $taken = array_map('mb_strtolower', $taken);
        $names = array_values(config('hangout.paintball.group_names', []));

        for ($round = 1; $names && $round <= 30; $round++) {
            foreach ($names as $entry) {
                $name = $entry['name'] . ($round > 1 ? ' ' . self::roman($round) : '');
                if (!in_array(mb_strtolower($name), $taken, true)) {
                    return ['name' => $name, 'reference' => $entry['reference'] ?? null, 'motto' => $entry['motto'] ?? null];
                }
            }
        }

        for ($number = count($taken) + 1; in_array("group {$number}", $taken, true); $number++);

        return ['name' => "Group {$number}", 'reference' => null, 'motto' => null];
    }

    private static function roman(int $number): string
    {
        $out = '';
        foreach (['X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $numeral => $value) {
            while ($number >= $value) {
                $out .= $numeral;
                $number -= $value;
            }
        }

        return $out;
    }
}
