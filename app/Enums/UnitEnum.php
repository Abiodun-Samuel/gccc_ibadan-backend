<?php

namespace App\Enums;

enum UnitEnum: string
{
    case PRAYER = 'Prayer Unit';
    case WORSHIP = 'Worship Team';
    case MEDIA = 'Media Unit';
    case FOLLOW_UP = 'Follow Up Unit';
    case WELFARE = 'Welfare Unit';
    case CHILDREN = 'Children Department';
    case SOUND = 'Sound Team';
    case SANITATION = 'Sanitation Unit';
    case USHERING = 'Ushering Unit';
    case FSP = 'FSP';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
