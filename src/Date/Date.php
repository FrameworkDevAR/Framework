<?php
namespace Framework\Date;

use Framework\Date\DateUtils;
use Framework\Date\Type\DateType;
use Framework\Date\Type\DateFormat;
use Framework\Date\TimeZone;
use Framework\Utils\Numbers;
use Framework\Utils\Strings;

use JsonSerializable;

/**
 * Date class
 */
class Date implements JsonSerializable {

    private bool $hasDate;
    private bool $isValid;
    private int $timestamp;
    private string $hour;


    /**
     * Creates a new Date instance
     * @param mixed  $date Optional.
     * @param string $hour Optional.
     */
    private function __construct(mixed $date = null, string $hour = "") {
        $this->hour = $hour;

        // Use the Date from the other Date
        if ($date instanceof Date) {
            $this->hasDate   = $date->hasDate;
            $this->isValid   = $date->isValid();
            $this->timestamp = $date->toTime();
            return;
        }

        // Use the Timestamp from the number that can be negative or positive
        if (Numbers::isValid($date, null)) {
            $number = Numbers::toInt($date);
            if ($number !== 0) {
                $this->hasDate   = true;
                $this->isValid   = true;
                $this->timestamp = $number;
                return;
            }

            // If the number is 0, create an invalid Date
            $this->hasDate   = false;
            $this->isValid   = false;
            $this->timestamp = 0;
            return;
        }

        // Try to parse the Date from the string
        if (is_string($date) && $date !== "") {
            $dateTime = $date;
            if ($hour !== "") {
                $dateTime = "$date $hour";
            }
            $timestamp = strtotime($dateTime);
            if ($timestamp !== false) {
                $this->hasDate   = true;
                $this->isValid   = true;
                $this->timestamp = $timestamp;
                return;
            }

            // If the string is not a valid Date, create an invalid Date
            $this->hasDate   = false;
            $this->isValid   = false;
            $this->timestamp = 0;
            return;
        }

        // If the Date is not valid, create an empty Date
        $this->hasDate   = false;
        $this->isValid   = true;
        $this->timestamp = 0;
    }

    /**
     * Creates an empty Date instance
     * @return Date
     */
    public static function empty(): Date {
        return new Date();
    }

    /**
     * Create a Date instance for the current time
     * @return Date
     */
    public static function now(): Date {
        return new Date(time());
    }

    /**
     * Creates a new Date instance
     * @param mixed  $date Optional.
     * @param string $hour Optional.
     * @return Date
     */
    public static function create(mixed $date = null, string $hour = ""): Date {
        return new Date($date, $hour);
    }

    /**
     * Creates a Date instance from the given value or now
     * @param mixed  $date Optional.
     * @param string $hour Optional.
     * @return Date
     */
    public static function createOrNow(mixed $date = null, string $hour = ""): Date {
        $result = new Date($date, $hour);
        if ($result->isNotEmpty()) {
            return $result;
        }
        return Date::now();
    }

    /**
     * Creates a Date instance parsing the given string
     * @param string $text
     * @param string $language Optional.
     * @return Date
     */
    public static function parse(string $text, string $language = ""): Date {
        $string = Strings::toString($text);
        if ($string === "") {
            return Date::empty();
        }
        $dateTime = DateUtils::parseDate($string, $language);
        return new Date($dateTime);
    }

    /**
     * Creates a Date instance from the given date and time parts
     * @param int $day
     * @param int $month
     * @param int $year
     * @param int $hour   Optional.
     * @param int $minute Optional.
     * @param int $second Optional.
     * @return Date
     */
    public static function createTime(
        int $day,
        int $month,
        int $year,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
    ): Date {
        $time = mktime($hour, $minute, $second, $month, $day, $year);
        return new Date($time);
    }

    /**
     * Returns the maximum Date from the given Dates
     * @param Date ...$dates
     * @return Date
     */
    public static function max(Date ...$dates): Date {
        if (count($dates) === 0) {
            return Date::empty();
        }
        $result = array_shift($dates);
        foreach ($dates as $date) {
            if ($date->toTime() > $result->toTime()) {
                $result = $date;
            }
        }
        return $result;
    }



    /**
     * Returns true if the Date is empty (time stamp is 0 or less)
     * @return bool
     */
    public function isEmpty(): bool {
        return !$this->hasDate;
    }

    /**
     * Returns true if the Date is not empty (time stamp is greater than 0)
     * @return bool
     */
    public function isNotEmpty(): bool {
        return $this->hasDate;
    }

    /**
     * Returns true if the Date is valid
     * @return bool
     */
    public function isValid(): bool {
        return $this->isValid;
    }



    /**
     * Returns a new Date changing the current one with the given values
     * @param int|null $day    Optional.
     * @param int|null $month  Optional.
     * @param int|null $year   Optional.
     * @param int|null $hour   Optional.
     * @param int|null $minute Optional.
     * @param int|null $second Optional.
     * @return Date
     */
    public function set(
        ?int $day = null,
        ?int $month = null,
        ?int $year = null,
        ?int $hour = null,
        ?int $minute = null,
        ?int $second = null,
    ): Date {
        // Handed back as it is rather than as the empty one, so a date that
        // could not be read keeps saying it is invalid
        if ($this->isEmpty()) {
            return $this;
        }
        $time = mktime(
            $hour   !== null ? $hour   : $this->getHour(),
            $minute !== null ? $minute : $this->getMinute(),
            $second !== null ? $second : $this->getSecond(),
            $month  !== null ? $month  : $this->getMonth(),
            $day    !== null ? $day    : $this->getDay(),
            $year   !== null ? $year   : $this->getYear(),
        );
        return new Date($time, $this->hour);
    }

    /**
     * Returns a new Date instance setting the Hour and Minute from the given string
     * @param string $time
     * @return Date
     */
    public function setHourMinute(string $time): Date {
        $parts = Strings::split($time, ":");
        if (!DateUtils::isValidHour($time) || count($parts) !== 2) {
            return Date::empty();
        }

        return $this->set(
            hour:   (int)$parts[0],
            minute: (int)$parts[1],
        );
    }

    /**
     * Returns a new Date instance adding the given amounts
     * @param int $days    Optional.
     * @param int $weeks   Optional.
     * @param int $months  Optional.
     * @param int $years   Optional.
     * @param int $hours   Optional.
     * @param int $minutes Optional.
     * @param int $seconds Optional.
     * @return Date
     */
    public function add(
        int $days = 0,
        int $weeks = 0,
        int $months = 0,
        int $years = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
    ): Date {
        return $this->set(
            day:    $this->getDay()    + $days + $weeks * 7,
            month:  $this->getMonth()  + $months,
            year:   $this->getYear()   + $years,
            hour:   $this->getHour()   + $hours,
            minute: $this->getMinute() + $minutes,
            second: $this->getSecond() + $seconds,
        );
    }

    /**
     * Returns a new Date instance subtracting the given amounts
     * @param int $days    Optional.
     * @param int $weeks   Optional.
     * @param int $months  Optional.
     * @param int $years   Optional.
     * @param int $hours   Optional.
     * @param int $minutes Optional.
     * @param int $seconds Optional.
     * @return Date
     */
    public function subtract(
        int $days = 0,
        int $weeks = 0,
        int $months = 0,
        int $years = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
    ): Date {
        return $this->add(
            days:    -$days,
            weeks:   -$weeks,
            months:  -$months,
            years:   -$years,
            hours:   -$hours,
            minutes: -$minutes,
            seconds: -$seconds,
        );
    }



    /**
     * Returns the Timestamp
     * @return int
     */
    public function toTime(): int {
        return $this->timestamp;
    }

    /**
     * Returns the Day, Month and Year as a number
     * @return int
     */
    public function toNumber(): int {
        return (
            $this->getYear() * 10000 +
            $this->getMonth() * 100 +
            $this->getDay()
        );
    }

    /**
     * Returns the Hours and Minutes as total Minutes
     * @return int
     */
    public function toMinutes(): int {
        return $this->getHour() * 60 + $this->getMinute();
    }

    /**
     * Returns a new Date changing the current one to Server Time
     * @param bool $useTimeZone Optional.
     * @return Date
     */
    public function toServerTime(bool $useTimeZone = true): Date {
        // Handed back as it is rather than as the empty one, so a date that
        // could not be read keeps saying it is invalid
        if ($this->isEmpty()) {
            return $this;
        }
        $timestamp = TimeZone::toServerTime($this->timestamp, $useTimeZone);
        return new Date($timestamp, $this->hour);
    }

    /**
     * Returns a new Date moving the current one from Server Time to the given Time Zone
     * @param float|string $timeZone
     * @return Date
     */
    public function toTimeZone(float|string $timeZone): Date {
        // An empty Time Zone is one that was never set, so the time of the server is kept
        if ($this->isEmpty() || $timeZone === "") {
            return $this;
        }
        $timeDiff  = TimeZone::calcTimeDiff(Numbers::toFloat($timeZone));
        $timestamp = $this->timestamp - (int)round($timeDiff * 3600);
        return new Date($timestamp, $this->hour);
    }

    /**
     * Returns a Date instance for the given Day Moment
     * @param DateType $dateType
     * @return Date
     */
    public function toDayMoment(DateType $dateType): Date {
        return match ($dateType) {
            DateType::None   => $this,
            DateType::Start  => $this->toDayStart(),
            DateType::Middle => $this->toDayMiddle(),
            DateType::End    => $this->toDayEnd(),
        };
    }

    /**
     * Returns a Date instance set to the start of the day
     * @return Date
     */
    public function toDayStart(): Date {
        return $this->set(hour: 0, minute: 0, second: 0);
    }

    /**
     * Returns a Date instance set to the middle of the day
     * @return Date
     */
    public function toDayMiddle(): Date {
        return $this->set(hour: 12, minute: 0, second: 0);
    }

    /**
     * Returns a Date instance set to the end of the day
     * @return Date
     */
    public function toDayEnd(): Date {
        return $this->set(hour: 23, minute: 59, second: 59);
    }

    /**
     * Returns a Date instance set to the start of the week
     * @param bool $startMonday Optional.
     * @return Date
     */
    public function toWeekStart(bool $startMonday = false): Date {
        $dayOfWeek = $this->getDayOfWeek($startMonday);
        return $this->subtract(days: $dayOfWeek);
    }

    /**
     * Returns a Date instance set to the end of the week
     * @param bool $startMonday Optional.
     * @return Date
     */
    public function toWeekEnd(bool $startMonday = false): Date {
        $dayOfWeek = $this->getDayOfWeek($startMonday);
        return $this->add(days: 6 - $dayOfWeek);
    }

    /**
     * Returns a Date instance set to the start of the month
     * @return Date
     */
    public function toMonthStart(): Date {
        return $this->set(day: 1);
    }

    /**
     * Returns a Date instance set to the end of the month
     * @return Date
     */
    public function toMonthEnd(): Date {
        $monthDays = $this->getMonthDays();
        return $this->set(day: $monthDays);
    }

    /**
     * Returns a Date instance set to the start of the year
     * @return Date
     */
    public function toYearStart(): Date {
        return $this->set(day: 1, month: 1);
    }

    /**
     * Returns a Date instance set to the end of the year
     * @return Date
     */
    public function toYearEnd(): Date {
        return $this->set(day: 31, month: 12);
    }



    /**
     * Returns the Year
     * @return int
     */
    public function getYear(): int {
        // Y: A full numeric representation of a year, 4 digits
        return (int)$this->format("Y");
    }

    /**
     * Returns the amount of days in the Year
     * @return int
     */
    public function getYearDays(): int {
        if ($this->isEmpty()) {
            return 0;
        }
        // L: Whether it's a leap year (1 if it is a leap year, 0 otherwise)
        return 365 + (int)$this->format("L");
    }



    /**
     * Returns the Month number
     * @return int
     */
    public function getMonth(): int {
        // n: Numeric representation of a month, without leading zeros (1 to 12)
        return (int)$this->format("n");
    }

    /**
     * Returns the Month with leading zero (01 to 12)
     * @return string
     */
    public function getMonthZero(): string {
        // m: Numeric representation of a month, with leading zeros (01 to 12)
        return $this->format("m");
    }

    /**
     * Returns the amount of days in the Month
     * @return int
     */
    public function getMonthDays(): int {
        // t: Number of days in the given month
        return (int)$this->format("t");
    }

    /**
     * Returns the name of the current Month
     * @param int    $length      Optional.
     * @param bool   $inUpperCase Optional.
     * @param string $language    Optional.
     * @return string
     */
    public function getMonthName(
        int $length = 0,
        bool $inUpperCase = false,
        string $language = "",
    ): string {
        return DateUtils::getMonthName(
            month:       $this->getMonth(),
            length:      $length,
            inUpperCase: $inUpperCase,
            language:    $language,
        );
    }



    /**
     * Returns the Week of the month
     * @return int
     */
    public function getWeekOfMonth(): int {
        return Numbers::roundInt($this->getDay() / 7, useCeil: true);
    }

    /**
     * Returns the Week of the year
     * @return int
     */
    public function getWeekOfYear(): int {
        // W: ISO-8601 week number of year, weeks starting on Monday (01 to 53)
        return (int)$this->format("W");
    }



    /**
     * Returns the Day of the month
     * @return int
     */
    public function getDay(): int {
        // j: Day of the month without leading zeros (1 to 31)
        return (int)$this->format("j");
    }

    /**
     * Returns the Day with leading 0 (01 to 31)
     * @return string
     */
    public function getDayZero(): string {
        // d: Day of the month, 2 digits with leading zeros (01 to 31)
        return $this->format("d");
    }

    /**
     * Returns the Day of the week
     * @param bool $startMonday Optional.
     * @return int
     */
    public function getDayOfWeek(bool $startMonday = false): int {
        if ($this->isEmpty()) {
            return 0;
        }
        if ($startMonday) {
            // N: ISO-8601 numeric representation of the day of the week
            //    (1 for Monday through 7 for Sunday)
            return (int)$this->format("N");
        }
        // w: Numeric representation of the day of the week
        //    (0 for Sunday through 6 for Saturday)
        return (int)$this->format("w");
    }

    /**
     * Returns the name of the current Day of the week
     * @param bool   $startMonday Optional.
     * @param int    $length      Optional.
     * @param bool   $inUpperCase Optional.
     * @param string $language    Optional.
     * @return string
     */
    public function getDayName(
        bool $startMonday = false,
        int $length = 0,
        bool $inUpperCase = false,
        string $language = "",
    ): string {
        return DateUtils::getDayName(
            day:         $this->getDayOfWeek($startMonday),
            startMonday: $startMonday,
            length:      $length,
            inUpperCase: $inUpperCase,
            language:    $language,
        );
    }

    /**
     * Returns the name of the current Day and Month
     * @param int    $monthLength Optional.
     * @param bool   $inUpperCase Optional.
     * @param string $language    Optional.
     * @return string
     */
    public function getDayMonth(
        int $monthLength = 0,
        bool $inUpperCase = false,
        string $language = "",
    ): string {
        if ($this->isEmpty()) {
            return "";
        }
        $day   = $this->getDayZero();
        $month = $this->getMonthName($monthLength, $inUpperCase, $language);
        return "$day $month";
    }



    /**
     * Returns the Hour
     * @return int
     */
    public function getHour(): int {
        // G: 24-hour format of an hour without leading zeros (0 to 23)
        return (int)$this->format("G");
    }

    /**
     * Returns the Minute
     * @return int
     */
    public function getMinute(): int {
        // i: Minutes with leading zeros (00 to 59)
        return (int)$this->format("i");
    }

    /**
     * Returns the Second
     * @return int
     */
    public function getSecond(): int {
        // s: Seconds with leading zeros (00 to 59)
        return (int)$this->format("s");
    }



    /**
     * Returns true if the current Date is today
     * @return bool
     */
    public function isToday(): bool {
        $now = Date::now();
        return (
            $this->getYear()  === $now->getYear() &&
            $this->getMonth() === $now->getMonth() &&
            $this->getDay()   === $now->getDay()
        );
    }

    /**
     * Returns true if the current Date is in the past
     * @return bool
     */
    public function isPast(): bool {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->timestamp < time();
    }

    /**
     * Returns true if the current Date is in the future
     * @return bool
     */
    public function isFuture(): bool {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->timestamp > time();
    }

    /**
     * Returns true if the current Date is in the current Month
     * @return bool
     */
    public function isCurrentMonth(): bool {
        $now = Date::now();
        return (
            $this->getYear()  === $now->getYear() &&
            $this->getMonth() === $now->getMonth()
        );
    }

    /**
     * Returns true if the current Date is not equal to another Date
     * @param Date $date
     * @return bool
     */
    public function isNotEqual(Date $date): bool {
        return $this->timestamp !== $date->toTime();
    }

    /**
     * Returns true if the current Date is equal to another Date
     * @param Date $date
     * @return bool
     */
    public function isEqual(Date $date): bool {
        return $this->timestamp === $date->toTime();
    }

    /**
     * Returns true if the current Date is in the same Day as another Date
     * @param Date $date
     * @return bool
     */
    public function isEqualDay(Date $date): bool {
        if ($this->isEmpty() || $date->isEmpty()) {
            return false;
        }
        return (
            $this->getYear()  === $date->getYear() &&
            $this->getMonth() === $date->getMonth() &&
            $this->getDay()   === $date->getDay()
        );
    }

    /**
     * Returns true if the current Date is before another Date
     * @param Date $date
     * @return bool
     */
    public function isBefore(Date $date): bool {
        if ($this->isEmpty() || $date->isEmpty()) {
            return false;
        }
        return $this->timestamp < $date->toTime();
    }

    /**
     * Returns true if the current Date is before another Date or equal to it
     * @param Date $date
     * @return bool
     */
    public function isBeforeOrEqual(Date $date): bool {
        return $this->isBefore($date) || $this->isEqual($date);
    }

    /**
     * Returns true if the current Date is after another Date
     * @param Date $date
     * @return bool
     */
    public function isAfter(Date $date): bool {
        if ($this->isEmpty() || $date->isEmpty()) {
            return false;
        }
        return $this->timestamp > $date->toTime();
    }

    /**
     * Returns true if the current Date is after another Date or equal to it
     * @param Date $date
     * @return bool
     */
    public function isAfterOrEqual(Date $date): bool {
        return $this->isAfter($date) || $this->isEqual($date);
    }

    /**
     * Returns true if the current Date is between two other Dates
     * @param Date $from
     * @param Date $to
     * @return bool
     */
    public function isBetween(Date $from, Date $to): bool {
        if ($this->isEmpty() || $from->isEmpty() || $to->isEmpty()) {
            return false;
        }
        return $this->timestamp >= $from->toTime() && $this->timestamp <= $to->toTime();
    }

    /**
     * Returns true if the current Date is at the start of the day (00:00:00)
     * @return bool
     */
    public function isAtDayStart(): bool {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->getHour() === 0 &&
            $this->getMinute() === 0 &&
            $this->getSecond() === 0;
    }

    /**
     * Returns true if the period between this date and the end date is valid
     * @param Date $endDate
     * @return bool
     */
    public function isValidPeriod(Date $endDate): bool {
        if ($this->isEmpty() || $endDate->isEmpty()) {
            return true;
        }
        return $this->isNotEmpty() && $endDate->isNotEmpty() &&
            $this->toDayStart()->isBefore($endDate->toDayEnd());
    }



    /**
     * Returns true if the hour is not empty
     * @return bool
     */
    public function hasHour(): bool {
        return $this->hour !== "";
    }

    /**
     * Returns the hour
     * @return string
     */
    public function getHourText(): string {
        return $this->hour;
    }

    /**
     * Returns true if the hour is valid
     * @param list<int>|null $minutes Optional.
     * @return bool
     */
    public function isValidHour(?array $minutes = null): bool {
        return DateUtils::isValidHour($this->hour, $minutes);
    }

    /**
     * Returns true if the period between this hour and the end hour is valid
     * @param Date $endDate
     * @return bool
     */
    public function isValidHourPeriod(Date $endDate): bool {
        if (!$this->hasHour() || !$endDate->hasHour()) {
            return true;
        }
        return DateUtils::isValidHourPeriod($this->hour, $endDate->hour);
    }



    /**
     * Returns the difference in days between the current Date and another Date
     * @param Date $date
     * @return int
     */
    public function getDaysDiff(Date $date): int {
        $diffSeconds = $this->getSecondsDiff($date);
        return (int)floor($diffSeconds / 86400);
    }

    /**
     * Returns the difference in weeks between the current Date and another Date
     * @param Date $date
     * @return int
     */
    public function getWeeksDiff(Date $date): int {
        $diffSeconds = $this->getSecondsDiff($date);
        return (int)floor($diffSeconds / 604800);
    }

    /**
     * Returns the difference in hours between the current Date and another Date
     * @param Date $date
     * @return int
     */
    public function getHoursDiff(Date $date): int {
        $diffSeconds = $this->getSecondsDiff($date);
        return (int)floor($diffSeconds / 3600);
    }

    /**
     * Returns the difference in minutes between the current Date and another Date
     * @param Date $date
     * @return int
     */
    public function getMinutesDiff(Date $date): int {
        $diffSeconds = $this->getSecondsDiff($date);
        return (int)floor($diffSeconds / 60);
    }

    /**
     * Returns the difference in seconds between the current Date and another Date
     * @param Date $date
     * @return int
     */
    public function getSecondsDiff(Date $date): int {
        if ($this->isEmpty() || $date->isEmpty()) {
            return 0;
        }
        return abs($this->timestamp - $date->toTime());
    }

    /**
     * Returns the Age in years from the current Date to another Date
     * @param Date|null $now Optional.
     * @return int
     */
    public function getAge(?Date $now = null): int {
        if ($this->isEmpty()) {
            return 0;
        }
        if ($now === null || $now->isEmpty()) {
            $now = Date::now();
        }

        $ageYear  = $this->getYear();
        $ageMonth = $this->getMonth();
        $ageDay   = $this->getDay();

        $nowYear  = $now->getYear();
        $nowMonth = $now->getMonth();
        $nowDay   = $now->getDay();

        $result   = $nowYear - $ageYear;
        if ($ageMonth > $nowMonth || ($ageMonth === $nowMonth && $ageDay > $nowDay)) {
            $result -= 1;
        }
        return $result;
    }



    /**
     * Returns the Date as a string using the given format
     * @param string $format
     * @return string
     */
    public function format(string $format): string {
        if ($this->isEmpty()) {
            return "";
        }
        return date($format, $this->timestamp);
    }

    /**
     * Returns the Date as a string using the given DateFormat
     * @param DateFormat $format
     * @return string
     */
    public function toString(DateFormat $format): string {
        return $this->format($format->value);
    }

    /**
     * Returns the Date as an ISO 8601 string
     * @return string
     */
    public function toISOString(): string {
        // c: ISO 8601 date (e.g. 2004-02-12T15:19:21+00:00)
        return $this->format("c");
    }

    /**
     * Returns the Date as a UTC date string
     * @return string
     */
    public function toUTCString(): string {
        // P: Difference to Greenwich time (GMT) with colon
        //    between hours and minutes (e.g. +02:00)
        $timeZone = $this->format("P");
        return Strings::replace($this->format("c"), $timeZone, "Z");
    }

    /**
     * Returns the Hour Period as a string
     * @param Date $date
     * @return string
     */
    public function toHourPeriodString(Date $date): string {
        $thisTime  = $this->toString(DateFormat::Time);
        $otherTime = $date->toString(DateFormat::Time);

        if ($this->isEmpty() || $date->isEmpty()) {
            return "";
        }
        if ($this->isBefore($date)) {
            return "$thisTime - $otherTime";
        }
        return "$otherTime - $thisTime";
    }



    /**
     * Implements the JSON Serializable Interface
     * @return mixed
     */
    #[\Override]
    public function jsonSerialize(): mixed {
        return $this->timestamp;
    }
}
