<?php

namespace App\Helpers;

class DateTimeHelper
{
    public static function minutesToHours(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        $result = '';
        if ($hours > 0) {
            $result .= $hours . 'h';
        }
        if ($mins > 0 || $hours === 0) {
            $result .= $mins . 'm';
        }

        return $result;
    }
}
