<?php

namespace App\Enums;

use App\Enums\UnitEnum;

enum PermissionEnum: string
{
    case CREATE = 'create';
    case READ = 'read';
    case UPDATE = 'update';
    case DELETE = 'delete';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Build permissions per unit with CRUD.
     */
    public static function generateForUnits(): array
    {
        $permissions = [];

        foreach (UnitEnum::values() as $unit) {
            foreach (self::values() as $action) {
                $permissions[] = sprintf('%s-%s', $action, str_replace(' ', '-', strtolower($unit)));
            }
        }

        return $permissions;
    }
}
