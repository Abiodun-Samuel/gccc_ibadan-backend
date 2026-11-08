<?php

namespace App\Services;

use App\Models\HangoutRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class HangoutRegistrationService
{
    /** Optional fields: left empty on an update, they keep their saved value. */
    private const KEEP_WHEN_EMPTY = ['whatsapp_number', 'support_amount'];

    public const SORTABLE = ['created_at', 'updated_at', 'full_name', 'email', 'game', 'support_amount'];

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

    /**
     * How many have picked each game: every configured game, in order, even
     * with no one yet, then any game that was since removed from the list.
     *
     * @return array<int, array{game: string, count: int}>
     */
    public function countsByGame(): array
    {
        $counts = HangoutRegistration::query()
            ->selectRaw('game, COUNT(*) as aggregate')
            ->groupBy('game')
            ->pluck('aggregate', 'game');

        return collect($this->gameNames())
            ->merge($counts->keys())
            ->unique()
            ->map(fn(string $game) => ['game' => $game, 'count' => (int) ($counts[$game] ?? 0)])
            ->values()
            ->all();
    }

    /**
     * Register, or update the registration that already uses this email.
     *
     * @return array{0: HangoutRegistration, 1: bool} the registration, and whether it was just created
     */
    public function register(array $data): array
    {
        $data['email'] = Str::lower(trim($data['email']));

        if ($existing = HangoutRegistration::where('email', $data['email'])->first()) {
            return [$this->update($existing, $data), false];
        }

        try {
            return [HangoutRegistration::create($data), true];
        } catch (UniqueConstraintViolationException) {
            // The same email was registered at the same moment by another request.
            $existing = HangoutRegistration::where('email', $data['email'])->firstOrFail();

            return [$this->update($existing, $data), false];
        }
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
            ->when($filters['game'] ?? null, fn(Builder $q, string $game) => $q->where('game', $game));

        // Registrations without a pledge go last whichever way pledges are sorted.
        if ($sort === 'support_amount') {
            $query->orderByRaw('support_amount IS NULL');
        }

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    /**
     * Totals across every registration, for the admin overview.
     */
    public function summary(): array
    {
        return [
            'total' => HangoutRegistration::count(),
            'pledges' => HangoutRegistration::whereNotNull('support_amount')->count(),
            'total_pledged' => round((float) HangoutRegistration::sum('support_amount'), 2),
            'by_game' => $this->countsByGame(),
            'registration_open' => $this->isOpen(),
        ];
    }
}
