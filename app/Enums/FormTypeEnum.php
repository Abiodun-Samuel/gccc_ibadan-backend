<?php

namespace App\Enums;

enum FormTypeEnum: string
{
    case PRAYER = 'prayer';
    case QUESTION = 'question';
    case TESTIMONY = 'testimony';
    case OTHERS = 'others';

    /**
     * Get all enum values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
