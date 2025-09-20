<?php

namespace App\Enums;

enum UnitEnum: string
{
    case PRAYER = 'Prayer';
    case WORSHIP = 'Worship';
    case MEDIA = 'Media';
    case FOLLOW_UP = 'Follow Up';
    case WELFARE = 'Welfare';
    case CHILDREN = 'Children';
    case SOUND = 'Sound';
    case SANITATION = 'Sanitation';
    case USHERING = 'Ushering';
    case FSP = 'FSP';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public static function labels(): array
    {
        return [
            self::MEDIA->value => 'Media',
            self::PRAYER->value => 'Prayer',
            self::FOLLOW_UP->value => 'Follow Up',
            self::WORSHIP->value => 'Worship',
            self::WELFARE->value => 'Welfare',
            self::CHILDREN->value => 'Children',
            self::SOUND->value => 'Sound',
            self::SANITATION->value => 'Sanitation',
            self::USHERING->value => 'Ushering',
            self::FSP->value => 'FSP',
        ];
    }
}
