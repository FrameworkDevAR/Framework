<?php
namespace Framework\Date;

use Framework\IO\Errors;
use Framework\Intl\NLS;
use Framework\Date\Date;
use Framework\Date\DateUtils;
use Framework\Date\TimeZone;
use Framework\Date\Type\DateFormat;
use Framework\Date\Type\TimeTableItem;
use Framework\Date\Type\TimeTableData;
use Framework\Utils\Dictionary;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * Several Time Table functions
 */
class TimeTable {

    /** @var list<TimeTableItem> */
    private array $timeTables;
    private bool  $startMonday;


    /**
     * Creates a new Time Table instance
     * @param list<TimeTableItem> $timeTables  Optional.
     * @param bool                $startMonday Optional.
     */
    private function __construct(array $timeTables = [], bool $startMonday = false) {
        $this->timeTables  = $timeTables;
        $this->startMonday = $startMonday;
    }

    /**
     * Creates a new Time Table instance
     * @param mixed $data
     * @param bool  $startMonday Optional.
     * @param bool  $allowEmpty  Optional.
     * @return TimeTable
     */
    public static function create(
        mixed $data,
        bool $startMonday = false,
        bool $allowEmpty = false,
    ): TimeTable {
        if ($data instanceof TimeTable) {
            return new TimeTable($data->timeTables, $data->startMonday);
        }

        if ($data instanceof Dictionary) {
            $data = $data->toArray();
        }
        if (!is_array($data)) {
            return new TimeTable();
        }

        // A single time table can be given on its own, without the list around it
        if (Arrays::isDict($data)) {
            $data = [ $data ];
        }

        $timeTables = [];
        foreach ($data as $elem) {
            $days = Arrays::getOneValue($elem, "days");
            $days = Arrays::sortList(Arrays::toInts($days));

            $from = Strings::toString(Arrays::getOneValue($elem, "from"));
            $to   = Strings::toString(Arrays::getOneValue($elem, "to"));

            if (count($days) === 0 && $from === "" && $to === "") {
                if (!$allowEmpty) {
                    continue;
                }
            }
            $timeTables[] = new TimeTableItem($days, $from, $to);
        }
        return new TimeTable($timeTables, $startMonday);
    }



    /**
     * Returns true if the Time Tables are empty
     * @return bool
     */
    public function isEmpty(): bool {
        return count($this->timeTables) === 0;
    }

    /**
     * Returns true if the Time Tables are not empty
      * @return bool
     */
    public function isNotEmpty(): bool {
        return count($this->timeTables) > 0;
    }

    /**
     * Returns true if the Time Tables are valid and adds the Errors
     * @param Errors $errors
     * @param bool   $withHolidays Optional.
     * @param bool   $isRequired   Optional.
     * @param string $fieldKey     Optional.
     * @return bool
     */
    public function isValid(
        Errors $errors,
        bool $withHolidays = false,
        bool $isRequired = false,
        string $fieldKey = "timeTables",
    ): bool {
        $hasError = false;

        foreach ($this->timeTables as $index => $timeTable) {
            if (!$isRequired && Arrays::isEmpty($timeTable->days)) {
                continue;
            }

            if ($isRequired && Arrays::isEmpty($timeTable->days)) {
                $errors->add("$fieldKey-$index-days", "GENERAL_ERROR_PERIOD_DAYS_EMPTY");
                $hasError = true;
            }
            foreach ($timeTable->days as $day) {
                if (!DateUtils::isValidDay($day, $withHolidays, $this->startMonday)) {
                    $errors->add(
                        error:   "$fieldKey-$index-days",
                        message: "GENERAL_ERROR_PERIOD_DAYS_INVALID",
                    );
                    $hasError = true;
                    break;
                }
            }
            if (!DateUtils::isValidHour($timeTable->from)) {
                $errors->add("$fieldKey-$index-from", "GENERAL_ERROR_PERIOD_FROM_TIME");
                $hasError = true;
            }
            if (!DateUtils::isValidHour($timeTable->to)) {
                $errors->add("$fieldKey-$index-to", "GENERAL_ERROR_PERIOD_TO_TIME");
                $hasError = true;
            }
            if ($timeTable->from !== "" && $timeTable->to !== "" &&
                !DateUtils::isValidHourPeriod($timeTable->from, $timeTable->to, allow24: true)
            ) {
                $errors->add("$fieldKey-$index-from", "GENERAL_ERROR_PERIOD_FROM_TO");
                $hasError = true;
            }
        }

        return !$hasError;
    }



    /**
     * Returns true if the Time Tables contain Holiday
     * @return bool
     */
    public function hasHoliday(): bool {
        foreach ($this->timeTables as $timeTable) {
            if ($timeTable->hasHoliday) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the Time Tables are in the Current time
     * @param int  $minuteGap Optional.
     * @param bool $isHoliday Optional.
     * @param bool $skipTime  Optional.
     * @return bool
     */
    public function isCurrent(
        int $minuteGap = 0,
        bool $isHoliday = false,
        bool $skipTime = false,
    ): bool {
        return $this->containsDate(Date::now(), $minuteGap, $isHoliday, $skipTime);
    }

    /**
     * Returns true if the Time Tables contain the given Date
     * @param Date $date
     * @param int  $minuteGap Optional.
     * @param bool $isHoliday Optional.
     * @param bool $skipTime  Optional.
     * @return bool
     */
    public function containsDate(
        Date $date,
        int $minuteGap = 0,
        bool $isHoliday = false,
        bool $skipTime = false,
    ): bool {
        if ($this->isEmpty()) {
            return false;
        }

        $weekDay    = $date->getDayOfWeek($this->startMonday);
        $nowMinutes = $date->toMinutes();

        foreach ($this->timeTables as $timeTable) {
            if ($isHoliday && !$timeTable->hasHoliday) {
                continue;
            }
            if (!$isHoliday && !Arrays::contains($timeTable->days, $weekDay)) {
                continue;
            }

            if ($skipTime) {
                return true;
            }

            $fromMinutes = DateUtils::timeToMinutes($timeTable->from);
            $toMinutes   = DateUtils::timeToMinutes($timeTable->to) - $minuteGap;
            if ($nowMinutes >= $fromMinutes && $nowMinutes <= $toMinutes) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the end time of the current day for the given Time Tables
     * @param Date|null $timeStamp Optional.
     * @return Date
     */
    public function getCurrentEndTime(?Date $timeStamp = null): Date {
        if ($this->isEmpty()) {
            return Date::empty();
        }

        $date       = Date::createOrNow($timeStamp);
        $dayStart   = $date->toDayStart();
        $resultDate = Date::empty();

        $weekDay    = $date->getDayOfWeek($this->startMonday);
        $nowMinutes = $date->toMinutes();

        foreach ($this->timeTables as $timeTable) {
            if (!Arrays::contains($timeTable->days, $weekDay)) {
                continue;
            }

            $fromMinutes = DateUtils::timeToMinutes($timeTable->from);
            $toMinutes   = DateUtils::timeToMinutes($timeTable->to);
            if ($nowMinutes >= $fromMinutes && $nowMinutes <= $toMinutes) {
                $resultDate = $dayStart->add(minutes: $toMinutes);
                break;
            }
        }

        return $resultDate;
    }

    /**
     * Returns the next start time for the given Time Tables
     * @param Date|null $timeStamp Optional.
     * @return Date
     */
    public function getNextStartTime(?Date $timeStamp = null): Date {
        if ($this->isEmpty()) {
            return Date::empty();
        }

        $maxDay     = $this->startMonday ? 8 : 7;
        $weeks      = [ 0, 7 ];

        $date       = Date::createOrNow($timeStamp);
        $weekStart  = $date->toWeekStart($this->startMonday)->toDayStart();
        $resultDate = Date::empty();

        foreach ($weeks as $week) {
            foreach ($this->timeTables as $timeTable) {
                $fromMinutes = DateUtils::timeToMinutes($timeTable->from);

                foreach ($timeTable->days as $day) {
                    if ($day >= $maxDay) {
                        continue;
                    }

                    $newDate = $weekStart->add(days: $day + $week);
                    $newDate = $newDate->add(minutes: $fromMinutes);

                    if ($newDate->isAfter($date) &&
                        ($resultDate->isEmpty() || $newDate->isBefore($resultDate))
                    ) {
                        $resultDate = $newDate;
                    }
                }
            }
        }

        return $resultDate;
    }



    /**
     * Returns the Time Table as list
     * @param string $closedText Optional.
     * @param string $timeZone   Optional.
     * @param string $isoCode    Optional.
     * @param bool   $allDays    Optional.
     * @return list<TimeTableData>
     */
    public function getList(
        string $closedText = "TIME_TABLE_NO_HOURS",
        string $timeZone = "",
        string $isoCode = "",
        bool $allDays = false,
    ): array {
        if ($this->isEmpty() && !$allDays) {
            return [];
        }

        $minDay = $this->startMonday ? 1 : 0;
        $maxDay = $this->startMonday ? 8 : 7;

        $schedules = [];
        $days      = [];
        foreach ($this->timeTables as $timeTable) {
            if (count($timeTable->days) === 0) {
                continue;
            }

            foreach ($timeTable->days as $day) {
                $weekTime = Date::now()
                    ->toWeekStart($this->startMonday)
                    ->add(days: $day)
                    ->toServerTime();

                $weekDate = $weekTime->toString(DateFormat::Dashes);

                $fromTime = Date::create($weekDate, $timeTable->from);
                $fromHour = $fromTime->toString(DateFormat::Time);

                $toTime   = Date::create($weekDate, $timeTable->to);
                $toHour   = $toTime->toString(DateFormat::Time);
                $id       = "$fromHour-$toHour";

                if (!isset($schedules[$id])) {
                    $schedules[$id] = new TimeTableData(
                        fromHour: $fromHour,
                        toHour:   $toHour,
                    );
                }
                $schedules[$id]->days[]  = $day;
                $schedules[$id]->dates[] = $day < $maxDay ? $fromTime : Date::empty();
                $days[$day] = 1;
            }
        }
        $schedules = Arrays::getValues($schedules);

        if ($allDays && count($days) < $maxDay) {
            $numbers = [];
            for ($i = $minDay; $i < $maxDay; $i += 1) {
                if (!isset($days[$i])) {
                    $numbers[] = $i;
                }
            }
            if (count($numbers) > 0) {
                $schedules[] = new TimeTableData(days: $numbers);
            }
        }

        foreach ($schedules as $id => $elem) {
            Arrays::sortList($schedules[$id]->days);
            Arrays::sortList($schedules[$id]->dates, function (Date $a, Date $b) {
                return $a->toTime() - $b->toTime();
            });

            if (count($elem->dates) > 0) {
                foreach ($elem->dates as $index => $dayTime) {
                    if ($dayTime->isNotEmpty()) {
                        $schedules[$id]->dayNames[] = $dayTime->getDayName(
                            startMonday: $this->startMonday,
                            language:    $isoCode,
                        );
                    } else {
                        $schedules[$id]->dayNames[] = DateUtils::getDayName(
                            day:         $elem->days[$index] ?? 0,
                            startMonday: $this->startMonday,
                            language:    $isoCode,
                        );
                    }
                }
            } else {
                foreach ($elem->days as $index => $day) {
                    $schedules[$id]->dayNames[] = DateUtils::getDayName(
                        day:         $day,
                        startMonday: $this->startMonday,
                        language:    $isoCode,
                    );
                }
            }
        }

        $zone = "";
        if ($timeZone !== "") {
            $zone = TimeZone::toString((float)$timeZone);
            $zone = " ($zone)";
        }

        $result = [];
        foreach ($schedules as $elem) {
            $amount   = count($elem->dayNames);
            $daysText = "";

            if ($amount > 7) {
                $daysText = NLS::getString("TIME_TABLE_ALL_HOLIDAYS", $isoCode);
            } elseif ($amount === 7) {
                $daysText = NLS::getString("TIME_TABLE_ALL_DAYS", $isoCode);
            } elseif ($amount === 2) {
                $daysText = NLS::joinWithAnd($elem->dayNames, $isoCode);
            } elseif ($amount === 1) {
                $daysText = $elem->dayNames[0] ?? "";
            } else {
                $parts = [];
                $count = 0;
                while ($count < $amount) {
                    $first = $count;
                    $last  = $count;
                    for ($i = $count + 1; $i < $amount; $i += 1) {
                        $number = $elem->days[$i] ?? 0;
                        if ($number === $maxDay) {
                            break;
                        }
                        if ($number - 1 !== ($elem->days[$last] ?? 0)) {
                            break;
                        }
                        $last = $i;
                    }
                    if ($last - $first > 1) {
                        $parts[] = NLS::format("TIME_TABLE_SOME_DAYS", [
                            $elem->dayNames[$first] ?? "",
                            $elem->dayNames[$last]  ?? "",
                        ], $isoCode);
                        $count = $last + 1;
                    } else {
                        $parts[] = $elem->dayNames[$first] ?? "";
                        $count  += 1;
                    }
                    $daysText = NLS::joinWithAnd($parts, $isoCode);
                }
            }

            $timeText = "";
            $toHour   = $elem->toHour;
            if ($toHour === "00:00") {
                $toHour = "24:00";
            }

            if ($elem->fromHour === "") {
                $timeText = NLS::getString($closedText, $isoCode);
            } else {
                $timeText = NLS::format(
                    key:      "TIME_TABLE_HOURS",
                    args:     [ $elem->fromHour, $toHour ],
                    language: $isoCode,
                );
            }

            $result[] = new TimeTableData(
                days:     $elem->days,
                dayNames: $elem->dayNames,
                fromHour: $elem->fromHour,
                toHour:   $toHour,
                daysText: $daysText,
                timeText: $timeText,
                zone:     $zone,
            );
        }

        return $result;
    }

    /**
     * Returns the Time Table as a string
     * @param string $timeZone Optional.
     * @param string $isoCode  Optional.
     * @return string
     */
    public function getText(string $timeZone = "", string $isoCode = ""): string {
        if ($this->isEmpty()) {
            return "";
        }

        $list   = $this->getList(timeZone: $timeZone, isoCode: $isoCode, allDays: true);
        $result = [];
        foreach ($list as $elem) {
            // The zone comes with the space before it, or empty when there is none
            $result[] = NLS::format("{0} {1}{2}", [
                $elem->daysText,
                $elem->timeText,
                $elem->zone,
            ], $isoCode);
        }

        return NLS::joinWithAnd($result, $isoCode);
    }

    /**
     * Encodes the Time Tables as a JSON
     * @return string
     */
    public function encode(): string {
        $list = [];
        foreach ($this->timeTables as $timeTable) {
            if (count($timeTable->days) > 0) {
                $list[] = $timeTable;
            }
        }
        return JSON::encode($list);
    }
}
