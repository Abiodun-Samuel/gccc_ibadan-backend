<?php
use Carbon\Carbon;
use Illuminate\Support\Str;

if (!function_exists('getNextSunday')) {
    function getNextSunday(): Carbon
    {
        $today = Carbon::today();
        return $today->isSunday() ? $today : $today->next(\Carbon\CarbonInterface::SUNDAY);
    }
}
if (!function_exists('generateInitials')) {
    function generateInitials(string $firstName, string $lastName): string
    {
        $firstInitial = Str::upper(Str::substr($firstName, 0, 1));
        $lastInitial = Str::upper(Str::substr($lastName, 0, 1));
        return "$firstInitial$lastInitial";
    }
}

