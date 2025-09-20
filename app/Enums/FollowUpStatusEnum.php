<?php

namespace App\Enums;

enum FollowUpStatusEnum: string
{
    case INVITED_AGAIN = 'Invited Again';
    case SECOND_TIMER = 'Second Timer';
    case THIRD_TIMER = 'Third Timer';
    case FOURTH_TIMER = 'Fourth Timer';
    case CONTACTED = 'Contacted';
    case NOT_CONTACTED = 'Not Contacted';
    case INTEGRATED = 'Integrated';
    case VISITING = 'Visiting';
    case OPT_OUT = 'Opt-out';
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
