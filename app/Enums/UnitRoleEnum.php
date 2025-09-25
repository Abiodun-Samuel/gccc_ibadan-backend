<?php

namespace App\Enums;

enum UnitRoleEnum: string
{
    case LEADER = 'leader';
    case ASSISTANT = 'assistant';
    case MEMBER = 'member';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
