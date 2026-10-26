<?php
namespace Framework\Date;

use Framework\Utils\Numbers;

/**
 * The Time Zone Utils
 */
class TimeZone {

    /** @var list<float> */
    private static array $stackZones = [];
    private static float $serverZone = -3;
    private static float $timeDiff   = 0;



    /**
     * Sets the Time Zone in minutes
     * @param float $timeZone
     * @return float
     */
    public static function setTimeZone(float $timeZone): float {
        $timeZone = self::toHours($timeZone);
        self::$stackZones = [ $timeZone ];
        self::$timeDiff   = self::$serverZone - $timeZone;
        return self::$timeDiff;
    }

    /**
     * Pushes a Time Zone
     * @param float $timeZone
     * @return float
     */
    public static function pushTimeZone(float $timeZone): float {
        $timeZone = self::toHours($timeZone);
        self::$stackZones[] = $timeZone;
        self::$timeDiff     = self::$serverZone - $timeZone;
        return self::$timeDiff;
    }

    /**
     * Pops a Time Zone
     * @return float
     */
    public static function popTimeZone(): float {
        if (count(self::$stackZones) > 1) {
            $timeZone = array_pop(self::$stackZones);
        } elseif (count(self::$stackZones) === 1) {
            $timeZone = self::$stackZones[0];
        } else {
            $timeZone = self::$serverZone;
        }
        self::$timeDiff = self::$serverZone - $timeZone;
        return self::$timeDiff;
    }



    /**
     * Returns the current Time Difference
     * @return float
     */
    public static function getCurrentTimeDiff(): float {
        return self::$timeDiff;
    }

    /**
     * Returns the Time Difference for the given Time Zone
     * @param float $timeZone
     * @return float
     */
    public static function calcTimeDiff(float $timeZone): float {
        return self::$serverZone - $timeZone;
    }

    /**
     * Returns the given time in the User Time Zone
     * @param int  $value
     * @param bool $useTimeZone Optional.
     * @return int
     */
    public static function toUserTime(int $value, bool $useTimeZone = true): int {
        if ($value !== 0 && $useTimeZone) {
            return $value - (int)(self::$timeDiff * 3600);
        }
        return $value;
    }

    /**
     * Returns the given time in the Server Time Zone
     * @param int  $value
     * @param bool $useTimeZone Optional.
     * @return int
     */
    public static function toServerTime(int $value, bool $useTimeZone = true): int {
        if ($value !== 0 && $useTimeZone) {
            return $value + (int)(self::$timeDiff * 3600);
        }
        return $value;
    }



    /**
     * Returns the given Time Zone in hours, as it can come in hours or in minutes
     * @param float $timeZone
     * @return float
     */
    private static function toHours(float $timeZone): float {
        // The Time Zones go from -12 to +14 hours, so a bigger value can only be in minutes,
        // and one in minutes is never smaller than an hour unless it is the UTC
        if (abs($timeZone) > 14) {
            return $timeZone / 60;
        }
        return $timeZone;
    }

    /**
     * Returns the Time Zone as a string
     * @param float $timeZone
     * @return string
     */
    public static function toString(float $timeZone): string {
        $sign    = $timeZone < 0 ? "-" : "+";
        $time    = abs($timeZone * 60);
        $hours   = floor($time / 60);
        $minutes = $time - $hours * 60;

        return "GMT $sign$hours:" . Numbers::zerosPad($minutes, 2);
    }
}
