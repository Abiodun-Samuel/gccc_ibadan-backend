<?php

namespace App\Services;

use App\Models\HangoutRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HangoutRegistrationService
{
    /** Optional fields: left empty on an update, they keep their saved value. */
    private const KEEP_WHEN_EMPTY = ['whatsapp_number', 'support_amount'];

    public const SORTABLE = ['created_at', 'updated_at', 'full_name', 'email', 'game', 'menu', 'support_amount'];

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

    /** @return array<int, string> the games people can pick, in display order */
    public function gameNames(): array
    {
        return array_values(config('hangout.games', []));
    }

    /** @return array<int, string> the meals people can pick, in display order */
    public function menuNames(): array
    {
        return array_values(config('hangout.menus', []));
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
     * @return array{0: HangoutRegistration, 1: bool} the registration, and whether it was just created
     *
     * @throws ValidationException when the email is taken and the phone doesn't match
     */
    public function register(array $data): array
    {
        $data['email'] = Str::lower(trim($data['email']));
        $proof = $data['current_phone'] ?? $data['phone_number'] ?? null;
        unset($data['current_phone']);

        $existing = HangoutRegistration::where('email', $data['email'])->first();
        if (!$existing) {
            try {
                return [HangoutRegistration::create($data), true];
            } catch (UniqueConstraintViolationException) {
                // The same email was registered at the same moment by another request.
                $existing = HangoutRegistration::where('email', $data['email'])->firstOrFail();
            }
        }

        if (!$this->phoneMatches($existing, $proof)) {
            throw ValidationException::withMessages(['phone_number' => self::PHONE_MISMATCH]);
        }

        return [$this->update($existing, $data), false];
    }

    /**
     * A member's own registrations, by the email or the phone number they
     * registered with. Given both, both must match. An email belongs to one
     * registration; a phone number can be shared (a family, say), so a phone
     * search can find several.
     *
     * @return Collection<int, HangoutRegistration> newest first, at most LOOKUP_LIMIT
     */
    public function lookup(?string $email, ?string $phone): Collection
    {
        if (filled($email)) {
            $registration = HangoutRegistration::where('email', Str::lower(trim($email)))->first();
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
     * Registrations matching the admin's filters, in the requested order.
     */
    public function query(array $filters): Builder
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = HangoutRegistration::query()
            ->search($filters['search'] ?? null)
            ->support($filters['support'] ?? null)
            ->menu($filters['menu'] ?? null)
            ->when($filters['game'] ?? null, fn(Builder $q, string $game) => $q->where('game', $game));

        // Registrations without a pledge (or a meal) go last whichever way they're sorted.
        if (in_array($sort, ['support_amount', 'menu'], true)) {
            $query->orderByRaw("{$sort} IS NULL");
        }

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    /**
     * How many registrations have each value of a column: every configured
     * value, in order, even with no one yet, then any other value still stored
     * (a game since removed, say).
     *
     * @return array<int, array{name: string, count: int}>
     */
    private function countsBy(string $column, array $configured): array
    {
        $counts = HangoutRegistration::query()
            ->whereNotNull($column)
            ->selectRaw("{$column} as name, COUNT(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', 'name');

        return collect($configured)
            ->merge($counts->keys())
            ->unique()
            ->map(fn(string $name) => ['name' => $name, 'count' => (int) ($counts[$name] ?? 0)])
            ->values()
            ->all();
    }

    /** @return array<int, array{game: string, count: int}> */
    public function countsByGame(): array
    {
        return array_map(
            fn(array $row) => ['game' => $row['name'], 'count' => $row['count']],
            $this->countsBy('game', $this->gameNames()),
        );
    }

    /**
     * What the picked games and the gate fees would cost at Agodi's prices
     * (config/hangout.php). Admin only.
     */
    public function costEstimate(int $total): array
    {
        $prices = config('hangout.game_prices', []);

        $games = array_map(function (array $row) use ($prices) {
            $price = $prices[$row['game']] ?? null;
            $amount = isset($price['amount']) ? (float) $price['amount'] : null;

            return [
                'game'      => $row['game'],
                'count'     => $row['count'],
                'price'     => $amount,
                'confirmed' => (bool) ($price['confirmed'] ?? false),
                'source'    => $price['source'] ?? null,
                'subtotal'  => $amount !== null ? round($amount * $row['count'], 2) : null,
            ];
        }, $this->countsByGame());

        $entry = config('hangout.entry_fee', []);
        $entryAmount = isset($entry['amount']) ? (float) $entry['amount'] : null;
        $entryTotal = $entryAmount !== null ? round($entryAmount * $total, 2) : 0.0;
        $gamesTotal = round(array_sum(array_map(fn(array $g) => $g['subtotal'] ?? 0, $games)), 2);

        return [
            'games'           => $games,
            'games_total'     => $gamesTotal,
            'entry_fee'       => [
                'price'     => $entryAmount,
                'confirmed' => (bool) ($entry['confirmed'] ?? false),
                'source'    => $entry['source'] ?? null,
                'total'     => $entryTotal,
            ],
            'total'           => round($gamesTotal + $entryTotal, 2),
            // Players whose game has no price yet, so isn't in the totals.
            'unpriced_players' => array_sum(array_map(fn(array $g) => $g['price'] === null ? $g['count'] : 0, $games)),
        ];
    }

    /**
     * Totals across every registration, for the admin overview.
     */
    public function summary(): array
    {
        $total = HangoutRegistration::count();

        return [
            'total'             => $total,
            'pledges'           => HangoutRegistration::whereNotNull('support_amount')->count(),
            'total_pledged'     => round((float) HangoutRegistration::sum('support_amount'), 2),
            'by_game'           => $this->countsByGame(),
            'by_menu'           => $this->countsBy('menu', $this->menuNames()),
            'without_menu'      => HangoutRegistration::whereNull('menu')->count(),
            'estimate'          => $this->costEstimate($total),
            'registration_open' => $this->isOpen(),
        ];
    }
}
