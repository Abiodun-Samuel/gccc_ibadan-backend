<?php

namespace App\Services;

use App\Models\HangoutRegistration;
use App\Models\HangoutRegistrationChild;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HangoutRegistrationService
{
    /** Optional fields: left empty on an update, they keep their saved value. */
    private const KEEP_WHEN_EMPTY = ['whatsapp_number', 'support_amount'];

    public const SORTABLE = ['created_at', 'updated_at', 'full_name', 'gender', 'email', 'game', 'menu', 'support_amount'];

    public const PHONE_MISMATCH = 'This email is already registered with a different phone number. Enter the phone number you registered with, or use “Update my registration”.';

    /** The most registrations one phone-number search returns. */
    public const LOOKUP_LIMIT = 10;

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(config('hangout.starts_at'));
    }

    public function closesAt(): CarbonImmutable
    {
        $closes = config('hangout.registration_closes_at');

        return $closes ? CarbonImmutable::parse($closes) : $this->startsAt();
    }

    public function isOpen(): bool
    {
        return CarbonImmutable::now()->lt($this->closesAt());
    }

    /** @return array<int, string> every game, in display order */
    public function gameNames(): array
    {
        return array_values(config('hangout.games', []));
    }

    /** @return array<int, string> the games only children can pick */
    public function childrenOnlyGames(): array
    {
        return array_values(array_intersect(config('hangout.children_only_games', []), $this->gameNames()));
    }

    /** @return array<int, string> the games an adult can pick for themselves */
    public function adultGameNames(): array
    {
        return array_values(array_diff($this->gameNames(), $this->childrenOnlyGames()));
    }

    public function maxChildren(): int
    {
        return (int) config('hangout.max_children', 10);
    }

    /** @return array<int, string> the meals people can pick, in display order */
    public function menuNames(): array
    {
        return array_values(config('hangout.menus', []));
    }

    /** @return array<int, string> the genders people can give, in display order */
    public function genderNames(): array
    {
        return array_values(config('hangout.genders', []));
    }

    /**
     * The last ten digits, so 0801 234 5678, 08012345678 and +234 801 234 5678
     * all compare equal.
     */
    public static function phoneKey(?string $phone): string
    {
        return substr(preg_replace('/\D+/', '', (string) $phone), -10);
    }

    public function phoneMatches(HangoutRegistration $registration, ?string $phone): bool
    {
        $given = self::phoneKey($phone);

        return strlen($given) >= 7 && hash_equals(self::phoneKey($registration->phone_number), $given);
    }

    /**
     * Register, or update the registration that already uses this email.
     *
     * Updating needs the phone number the registration was saved with, so
     * knowing someone's email isn't enough to change their registration. That
     * number is `current_phone` when the member is changing their phone, else
     * the `phone_number` they entered.
     *
     * `children`, when sent, replaces the children on the registration (an
     * empty list removes them all); left out, the saved children stay.
     *
     * @return array{0: HangoutRegistration, 1: bool} the registration (with its children), and whether it was just created
     *
     * @throws ValidationException when the email is taken and the phone doesn't match
     */
    public function register(array $data): array
    {
        $data['email'] = Str::lower(trim($data['email']));
        $proof = $data['current_phone'] ?? $data['phone_number'] ?? null;
        $children = array_key_exists('children', $data) ? array_values($data['children'] ?? []) : null;
        unset($data['current_phone'], $data['children']);

        return DB::transaction(function () use ($data, $proof, $children) {
            $existing = HangoutRegistration::where('email', $data['email'])->lockForUpdate()->first();
            $created = false;

            if (!$existing) {
                try {
                    $registration = HangoutRegistration::create($data);
                    $created = true;
                } catch (UniqueConstraintViolationException) {
                    // The same email was registered at the same moment by another request.
                    $existing = HangoutRegistration::where('email', $data['email'])->firstOrFail();
                }
            }

            if (!$created) {
                if (!$this->phoneMatches($existing, $proof)) {
                    throw ValidationException::withMessages(['phone_number' => self::PHONE_MISMATCH]);
                }
                $registration = $this->update($existing, $data);
            }

            if ($children !== null) {
                $registration->children()->delete();
                $registration->children()->createMany($children);
            }

            return [$registration->load('children'), $created];
        });
    }

    /**
     * A member's own registrations, by the email or the phone number they
     * registered with. Given both, both must match. An email belongs to one
     * registration; a phone number can be shared (a family, say), so a phone
     * search can find several.
     *
     * @return Collection<int, HangoutRegistration> newest first, at most LOOKUP_LIMIT, with their children
     */
    public function lookup(?string $email, ?string $phone): Collection
    {
        if (filled($email)) {
            $registration = HangoutRegistration::with('children')->where('email', Str::lower(trim($email)))->first();
            $matches = $registration && (blank($phone) || $this->phoneMatches($registration, $phone));

            return collect($matches ? [$registration] : []);
        }

        // A whole number only: a few digits mustn't match other people's.
        $key = self::phoneKey($phone);
        if (strlen($key) < 10) {
            return collect();
        }

        // Numbers are stored as typed, so their digits are compared here. One
        // event's registrations are few enough to read through.
        return collect(
            HangoutRegistration::query()
                ->with('children')
                ->lazyByIdDesc(200)
                ->filter(fn(HangoutRegistration $registration) => hash_equals(self::phoneKey($registration->phone_number), $key))
                ->take(self::LOOKUP_LIMIT)
                ->all()
        )->values();
    }

    private function update(HangoutRegistration $registration, array $data): HangoutRegistration
    {
        foreach (self::KEEP_WHEN_EMPTY as $field) {
            if (!isset($data[$field])) {
                unset($data[$field]);
            }
        }

        $registration->fill($data);
        // Touch the row even when nothing changed, so admins see it was resubmitted.
        $registration->isDirty() ? $registration->save() : $registration->touch();

        return $registration->refresh();
    }

    /**
     * Registrations matching the admin's filters, in the requested order,
     * each with its children. Filters apply to the registering adult; the
     * search also finds a registration by a child's name.
     */
    public function query(array $filters): Builder
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = HangoutRegistration::query()
            ->with('children')
            ->search($filters['search'] ?? null)
            ->support($filters['support'] ?? null)
            ->menu($filters['menu'] ?? null)
            ->gender($filters['gender'] ?? null)
            ->when($filters['game'] ?? null, fn(Builder $q, string $game) => $q->where('game', $game));

        // Registrations without a pledge (or a meal, or a gender) go last whichever way they're sorted.
        if (in_array($sort, ['support_amount', 'menu', 'gender'], true)) {
            $query->orderByRaw("{$sort} IS NULL");
        }

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    /**
     * How many adults and children have each value of a column: every
     * configured value, in order, even with no one yet, then any other value
     * still stored (a game since removed, say).
     *
     * @return array<int, array{name: string, count: int, adults: int, children: int}>
     */
    private function countsBy(string $column, array $configured): array
    {
        $tally = fn(Builder $query) => $query
            ->whereNotNull($column)
            ->selectRaw("{$column} as name, COUNT(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', 'name');
        $adults = $tally(HangoutRegistration::query());
        $children = $tally(HangoutRegistrationChild::query());

        return collect($configured)
            ->merge($adults->keys())
            ->merge($children->keys())
            ->unique()
            ->map(function (string $name) use ($adults, $children) {
                $a = (int) ($adults[$name] ?? 0);
                $c = (int) ($children[$name] ?? 0);

                return ['name' => $name, 'count' => $a + $c, 'adults' => $a, 'children' => $c];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array{game: string, count: int, adults: int, children: int}> */
    public function countsByGame(): array
    {
        return array_map(
            fn(array $row) => ['game' => $row['name']] + array_diff_key($row, ['name' => true]),
            $this->countsBy('game', $this->gameNames()),
        );
    }

    /**
     * A price entry's amount for 'adult' or 'child': that age's own price, or
     * the one shared 'amount'. Null when there isn't one.
     */
    private static function priceFor(array $price, string $age): ?float
    {
        $amount = array_key_exists($age, $price) ? $price[$age] : ($price['amount'] ?? null);

        return $amount === null ? null : (float) $amount;
    }

    /** One game's price for an 'adult' or a 'child', or null when there isn't one. */
    public function gamePrice(?string $game, string $age): ?float
    {
        return self::priceFor(config("hangout.game_prices.{$game}", []) ?? [], $age);
    }

    /**
     * What the picked games and the gate fees would cost at Agodi's prices
     * (config/hangout.php), adults and children each at their own price.
     * Admin only.
     */
    public function costEstimate(int $adults, int $children = 0): array
    {
        $prices = config('hangout.game_prices', []);

        $games = array_map(function (array $row) use ($prices) {
            $price = $prices[$row['game']] ?? [];
            $adultPrice = self::priceFor($price, 'adult');
            $childPrice = self::priceFor($price, 'child');
            $priced = $adultPrice !== null || $childPrice !== null;

            return [
                'game'        => $row['game'],
                'count'       => $row['count'],
                'adults'      => $row['adults'],
                'children'    => $row['children'],
                'adult_price' => $adultPrice,
                'child_price' => $childPrice,
                'confirmed'   => (bool) ($price['confirmed'] ?? false),
                'source'      => $price['source'] ?? null,
                'subtotal'    => $priced ? round(($adultPrice ?? 0) * $row['adults'] + ($childPrice ?? 0) * $row['children'], 2) : null,
                // People on this game whose age has no price, so aren't in the totals.
                'unpriced'    => ($adultPrice === null ? $row['adults'] : 0) + ($childPrice === null ? $row['children'] : 0),
            ];
        }, $this->countsByGame());

        $entry = config('hangout.entry_fee', []);
        $entryAdult = self::priceFor($entry, 'adult');
        $entryChild = self::priceFor($entry, 'child');
        $entryTotal = round(($entryAdult ?? 0) * $adults + ($entryChild ?? 0) * $children, 2);
        $gamesTotal = round(array_sum(array_map(fn(array $g) => $g['subtotal'] ?? 0, $games)), 2);

        return [
            'games'            => $games,
            'games_total'      => $gamesTotal,
            'entry_fee'        => [
                'adult_price' => $entryAdult,
                'child_price' => $entryChild,
                'adults'      => $adults,
                'children'    => $children,
                'confirmed'   => (bool) ($entry['confirmed'] ?? false),
                'source'      => $entry['source'] ?? null,
                'total'       => $entryTotal,
            ],
            'total'            => round($gamesTotal + $entryTotal, 2),
            'unpriced_players' => array_sum(array_column($games, 'unpriced')),
        ];
    }

    /**
     * Totals across every registration, for the admin overview. `total` is
     * the number of registrations (one per adult); `people` adds the children.
     */
    public function summary(): array
    {
        $adults = HangoutRegistration::count();
        $children = HangoutRegistrationChild::count();

        return [
            'total'             => $adults,
            'adults'            => $adults,
            'children'          => $children,
            'people'            => $adults + $children,
            'pledges'           => HangoutRegistration::whereNotNull('support_amount')->count(),
            'total_pledged'     => round((float) HangoutRegistration::sum('support_amount'), 2),
            'by_game'           => $this->countsByGame(),
            'by_menu'           => $this->countsBy('menu', $this->menuNames()),
            'without_menu'      => HangoutRegistration::whereNull('menu')->count(),
            'by_gender'         => $this->countsBy('gender', $this->genderNames()),
            'without_gender'    => HangoutRegistration::whereNull('gender')->count(),
            'estimate'          => $this->costEstimate($adults, $children),
            'registration_open' => $this->isOpen(),
        ];
    }
}
