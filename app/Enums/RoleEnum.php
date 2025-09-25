<?php

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case LEADER = 'leader';
    case MEMBER = 'member';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
