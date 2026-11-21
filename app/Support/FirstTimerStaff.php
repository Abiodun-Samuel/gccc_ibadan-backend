<?php

namespace App\Support;

use App\Enums\RoleEnum;
use App\Enums\UnitEnum;
use App\Models\User;

/**
 * The people who look after first timers: admins, and members of the Follow Up Unit.
 * They alone may see the full first-timers list and add first timers from it.
 */
class FirstTimerStaff
{
    public static function allows(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(RoleEnum::ADMIN->value)
            || $user->units()->where('units.name', UnitEnum::FOLLOW_UP->value)->exists();
    }
}
