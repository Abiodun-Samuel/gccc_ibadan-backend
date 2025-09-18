<?php
use Carbon\Carbon;

if (!function_exists('getNextSunday')) {
    /**
     * Get the next Sunday's date.
     * If today is Sunday, return today's date.
     *
     * @return \Carbon\Carbon
     */
    function getNextSunday(): Carbon
    {
        $today = Carbon::today();
        return $today->isSunday() ? $today : $today->next(\Carbon\CarbonInterface::SUNDAY);
    }
}
