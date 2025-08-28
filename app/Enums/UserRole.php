<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case LEADER = 'leader';
    case MEMBER = 'member';
    case FIRST_TIMER = 'first_timer';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
