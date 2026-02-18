<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\TheOneRegistration;
use Illuminate\Support\Collection;

class RegistrationService
{
    private const MAX_CAPACITY = 54;

    /**
     * Return all registrations and the total count (no pagination).
     */
    public function all(): array
    {
        $registrations = Registration::latest()->get();

        return [
            'total_registered' => $registrations->count(),
            'max_capacity'     => self::MAX_CAPACITY,
            'available_slots'  => max(0, self::MAX_CAPACITY - $registrations->count()),
            'registrations'    => $registrations,
        ];
    }

    /**
     * Register a new attendee. Enforces the 54-member cap.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(array $data): Registration
    {
        $totalRegistered = Registration::count();

        if ($totalRegistered >= self::MAX_CAPACITY) {
            abort(422, 'Registration is closed. The maximum capacity of ' . self::MAX_CAPACITY . ' attendees has been reached.');
        }

        return Registration::create($data);
    }

    /**
     * Update an existing registration (admin only).
     */
    public function update(Registration $registration, array $data): Registration
    {
        $registration->update($data);

        return $registration->fresh();
    }

    /**
     * Delete a registration (admin only).
     */
    public function delete(Registration $registration): void
    {
        $registration->delete();
    }

    /**
     * Total registered count.
     */
    public function totalRegistered(): int
    {
        return Registration::count();
    }
}
