<?php
namespace Tests\Date;

use Framework\IO\Errors;
use Framework\Date\Date;
use Framework\Date\DateUtils;
use Framework\Date\TimeTable;
use Framework\Intl\NLS;
use Framework\Utils\Dictionary;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TimeTableTest extends TestCase {
    use TestHelpers;

    #[DataProvider("createProvider")]
    public function testCreate(mixed $input, int $expectedCount): void {
        $tt   = TimeTable::create($input);
        $list = $tt->getList();
        $this->assertCount($expectedCount, $list);

        if ($expectedCount > 0) {
            $this->assertSame("10:00", $list[0]->fromHour);
            $this->assertSame("12:00", $list[0]->toHour);
        }
    }

    public static function createProvider(): array {
        return [
            "invalid input"    => [ "nope", 0 ],
            "empty array"      => [[], 0 ],
            "empty values"     => [
                [[ "days" => [], "from" => "", "to" => "" ]],
                0,
            ],
            "single table"     => [
                [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]],
                1,
            ],
            "multiple tables"  => [
                [
                    [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ],
                    [ "days" => [ 2 ], "from" => "13:00", "to" => "15:00" ],
                ],
                2,
            ],
            "dictionary input" => [
                new Dictionary([
                    "0" => [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ],
                    "1" => [ "days" => [ 2 ], "from" => "13:00", "to" => "15:00" ],
                ]),
                2,
            ],
            // A single table can be given on its own, without the list around it
            "a bare table"     => [
                [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ],
                1,
            ],
            "a bare empty one" => [
                [ "days" => [], "from" => "", "to" => "" ],
                0,
            ],
            "a bare dictionary" => [
                new Dictionary([ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]),
                1,
            ],
            "timetable object" => [
                TimeTable::create([
                    [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ],
                    [ "days" => [ 2 ], "from" => "13:00", "to" => "15:00" ],
                ]),
                2,
            ],
        ];
    }


    public function testIsEmpty(): void {
        $ttEmpty = TimeTable::create([]);
        $this->assertTrue($ttEmpty->isEmpty());
        $this->assertFalse($ttEmpty->isNotEmpty());

        $tt = TimeTable::create([[ "days" => [1], "from" => "10:00", "to" => "12:00" ]]);
        $this->assertFalse($tt->isEmpty());
        $this->assertTrue($tt->isNotEmpty());
    }


    #[DataProvider("isValidProvider")]
    public function testIsValid(array $input, bool $withHolidays, bool $isRequired, bool $expectedHasErrors, string $expectedKey = ""): void {
        $tt     = TimeTable::create($input, allowEmpty: true);
        $errors = new Errors();
        $valid  = $tt->isValid($errors, $withHolidays, $isRequired);

        $this->assertSame(!$expectedHasErrors, $valid);
        if ($expectedHasErrors && $expectedKey !== "") {
            $this->assertTrue($errors->has($expectedKey));
        }
    }

    public static function isValidProvider(): array {
        return [
            "valid"          => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], false, false, false, "" ],
            "skipping days"  => [ [[ "days" => [], "from" => "", "to" => "" ]], false, false, false, "" ],
            "missing days"   => [ [[ "days" => [], "from" => "", "to" => "" ]], false, true, true, "timeTables-0-days" ],
            "invalid from"   => [ [[ "days" => [ 1 ], "from" => "25:00", "to" => "26:00" ]], false, false, true, "timeTables-0-from" ],
            "invalid to"     => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "26:00" ]], false, false, true, "timeTables-0-to" ],
            "invalid period" => [ [[ "days" => [ 1 ], "from" => "12:00", "to" => "10:00" ]], false, false, true, "timeTables-0-from" ],
            "holiday good"   => [ [[ "days" => [ 7 ], "from" => "10:00", "to" => "12:00" ]], true, false, false, "" ],
            "holiday bad"    => [ [[ "days" => [ 8 ], "from" => "10:00", "to" => "12:00" ]], true, false, true, "timeTables-0-days" ],
        ];
    }


    #[DataProvider("hasHolidayProvider")]
    public function testHasHoliday(array $input, bool $expected): void {
        $tt = TimeTable::create($input);
        $this->assertSame($expected, $tt->hasHoliday());
    }

    public static function hasHolidayProvider(): array {
        return [
            "no holiday"   => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], false ],
            "with holiday" => [ [[ "days" => [ 8 ], "from" => "10:00", "to" => "12:00" ]], true ],
        ];
    }


    #[DataProvider("isCurrentProvider")]
    public function testIsCurrent(array $input, int $minuteGap, bool $isHoliday, bool $skipTime, bool $expected): void {
        $tt = TimeTable::create($input);
        $this->assertSame($expected, $tt->isCurrent($minuteGap, $isHoliday, $skipTime));
    }

    public static function isCurrentProvider(): array {
        return [
            "empty"        => [ [], 0, false, false, false ],
            "all days"     => [ [[ "days" => [ 0, 1, 2, 3, 4, 5, 6 ], "from" => "00:00", "to" => "23:59" ]], 0, false, true, true ],
            "holiday only" => [ [[ "days" => [ 8 ], "from" => "00:00", "to" => "23:59" ]], 0, true, true, true ],
        ];
    }


    #[DataProvider("containsDateProvider")]
    public function testContainsDate(array $input, string $date, string $time, int $minuteGap, bool $isHoliday, bool $skipTime, bool $expected): void {
        $tt = TimeTable::create($input);
        $d = Date::create($date, $time);
        $this->assertSame($expected, $tt->containsDate($d, $minuteGap, $isHoliday, $skipTime));
    }

    public static function containsDateProvider(): array {
        return [
            "inside"             => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:00", 0, false, false, true ],
            "outside"            => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "09:00", 0, false, false, false ],
            "skip time"          => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "03:00", 0, false, true, true ],
            "minute gap"         => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:40", 15, false, false, true ],
            "minute gap outside" => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:50", 15, false, false, false ],
            "not contains"       => [ [[ "days" => [ 2 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:00", 0, false, false, false ],
            "holiday"            => [ [[ "days" => [ 8 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:00", 0, true, false, true ],
            "holiday is holiday" => [ [[ "days" => [ 7 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "03:00", 0, true, false, false ],
            "holiday skip time"  => [ [[ "days" => [ 8 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "03:00", 0, true, true, true ],
        ];
    }


    #[DataProvider("currentEndProvider")]
    public function testGetCurrentEndTime(array $input, string $date, string $time, bool $isEmpty): void {
        $tt = TimeTable::create($input);
        $d  = Date::create($date, $time);
        $ed = $tt->getCurrentEndTime($d);

        if ($isEmpty) {
            $this->assertTrue($ed->isEmpty());
        } else {
            $expected = Date::create($date, "00:00")->add(minutes: DateUtils::timeToMinutes($input[0]["to"]));
            $this->assertSame($expected->toTime(), $ed->toTime());
        }
    }

    public static function currentEndProvider(): array {
        return [
            "normal"      => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ] ], "2023-01-02", "11:00", false ],
            "exact start" => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ] ], "2023-01-02", "10:00", false ],
            "mid day"     => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ] ], "2023-01-02", "11:30", false ],
            "exact end"   => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ] ], "2023-01-02", "12:00", false ],
            "not current" => [ [[ "days" => [ 2 ], "from" => "10:00", "to" => "12:00" ] ], "2023-01-02", "13:00", true ],
            "empty"       => [ [], "2023-01-02", "11:00", true ],
            "empty days"  => [ [[ "days" => [], "from" => "", "to" => "" ]], "2023-01-02", "11:00", true ],
        ];
    }


    #[DataProvider("nextStartProvider")]
    public function testGetNextStartTime(array $input, string $date, string $time, bool $isEmpty): void {
        $tt = TimeTable::create($input);
        $d  = Date::create($date, $time);
        $ed = $tt->getNextStartTime($d);

        if ($isEmpty) {
            $this->assertTrue($ed->isEmpty());
            return;
        }

        // compute expected using the same logic as TimeTable::getNextStartTime
        $weeks = [0, 7];
        $weekStart = $d->toWeekStart(false)->toDayStart();
        $expected = Date::empty();
        foreach ($weeks as $week) {
            foreach ($input[0]["days"] as $day) {
                if ($day >= 7) {
                    continue;
                }
                $fromMinutes = DateUtils::timeToMinutes($input[0]["from"]);
                $newDate = $weekStart->add(days: $day + $week)->add(minutes: $fromMinutes);
                if ($newDate->isAfter($d) && ($expected->isEmpty() || $newDate->isBefore($expected))) {
                    $expected = $newDate;
                }
            }
        }

        $this->assertSame($expected->toTime(), $ed->toTime());
    }

    public static function nextStartProvider(): array {
        return [
            "same day before"      => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "08:00", false ],
            "same day exact start" => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "10:00", false ],
            "same day during"      => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:00", false ],
            "same day after"       => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "13:00", false ],
            "from sunday"          => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-01", "05:00", false ],
            "invalid day"          => [ [[ "days" => [ 8 ], "from" => "10:00", "to" => "12:00" ]], "2023-01-02", "11:00", true ],
            "empty"                => [ [], "2023-01-02", "11:00", true ],
            "empty days"           => [ [[ "days" => [], "from" => "", "to" => "" ]], "2023-01-02", "11:00", true ],
        ];
    }


    #[DataProvider("listProvider")]
    public function testGetList(array $input, string $closedText, string $timeZone, string $isoCode, bool $allDays, bool $isEmpty): void {
        $tt = TimeTable::create($input);
        $list = $tt->getList($closedText, $timeZone, $isoCode, $allDays);
        $this->assertIsArray($list);

        if ($isEmpty) {
            if ($allDays) {
                $this->assertNotEmpty($list);
                $this->assertSame("", $list[0]->fromHour);
            } else {
                $this->assertEmpty($list);
            }
        } else {
            $this->assertNotEmpty($list);
            $this->assertSame("10:00", $list[0]->fromHour);

            if ($timeZone !== "") {
                $this->assertNotSame("", $list[0]->zone);
            }
        }
    }

    public static function listProvider(): array {
        return [
            "empty no allDays" => [ [], "TIME_TABLE_NO_HOURS", "", "", false, true ],
            "empty allDays"    => [ [], "CLOSED_TEXT", "", "", true, true ],
            "empty days"       => [ [[ "days" => [], "from" => "10:00", "to" => "12:00" ]], "TIME_TABLE_NO_HOURS", "", "", false, true ],
            "two days"         => [ [ [ "days" => [ 1, 2 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "seven days"       => [ [ [ "days" => [ 0,1,2,3,4,5,6 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "all holidays"     => [ [ [ "days" => [ 0,1,2,3,4,5,6,8 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "some days range"  => [ [ [ "days" => [ 1,2,3,5 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "other days range" => [ [ [ "days" => [ 5,6,7 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "to midnight"      => [ [ [ "days" => [ 1 ], "from" => "10:00", "to" => "00:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "day7"             => [ [ [ "days" => [ 7 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "one no zone"      => [ [ [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "", false, false ],
            "one with zone"    => [ [ [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "1.5", "", false, false ],
            "holiday allDays"  => [ [ [ "days" => [ 8 ], "from" => "10:00", "to" => "12:00" ] ], "TIME_TABLE_NO_HOURS", "", "es", true, false ],
        ];
    }


    #[DataProvider("getTextProvider")]
    public function testGetText(array $input, string $timeZone, string $expected): void {
        $text = TimeTable::create($input)->getText($timeZone, "en");
        $this->assertSame($expected, $text);
    }

    public static function getTextProvider(): array {
        $monday = [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ];
        $week   = [ "days" => [ 1, 2, 3, 4, 5 ], "from" => "09:00", "to" => "18:00" ];
        $every  = [ "days" => [ 0, 1, 2, 3, 4, 5, 6 ], "from" => "08:00", "to" => "00:00" ];

        // The days without hours are listed as closed after the ones with them
        return [
            "empty"        => [ [], "", "" ],
            "one day"      => [ [ $monday ], "", "Monday 10:00 to 12:00 and Sunday and Tuesday to Saturday Closed" ],
            "with a zone"  => [ [ $monday ], "-3", "Monday 10:00 to 12:00 (GMT -3:00) and Sunday and Tuesday to Saturday Closed (GMT -3:00)" ],
            "a range"      => [ [ $week ], "", "Monday to Friday 09:00 to 18:00 and Sunday and Saturday Closed" ],
            "every day"    => [ [ $every ], "", "Every day 08:00 to 24:00" ],
            "a zone again" => [ [ $every ], "1.5", "Every day 08:00 to 24:00 (GMT +1:30)" ],
        ];
    }


    public function testGetTextInALanguage(): void {
        // A language with strings of its own, so one that is dropped would fall back to English
        $original = $this->getPrivateStaticProperty(NLS::class, "data");
        $this->setPrivateStaticProperty(NLS::class, "data", [
            "xx" => new Dictionary([
                "DATE_TIME_DAYS"       => [ "Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado" ],
                "GENERAL_AND"          => "y",
                "TIME_TABLE_HOURS"     => "{0} a {1} h",
                "TIME_TABLE_NO_HOURS"  => "Cerrado",
                "TIME_TABLE_SOME_DAYS" => "{0} a {1}",
            ]),
        ] + (is_array($original) ? $original : []));

        try {
            $text = TimeTable::create([
                [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ],
            ])->getText("", "xx");
        } finally {
            $this->setPrivateStaticProperty(NLS::class, "data", $original);
        }
        $this->assertSame("Lunes 10:00 a 12:00 h y Domingo y Martes a Sábado Cerrado", $text);
    }


    #[DataProvider("encodeProvider")]
    public function testEncode(array $input, bool $expectNonEmpty): void {
        $tt = TimeTable::create($input);
        $encoded = $tt->encode();

        if ($expectNonEmpty) {
            $this->assertStringContainsString("10:00", $encoded);
        } else {
            $this->assertSame("[]", $encoded);
        }
    }

    public static function encodeProvider(): array {
        return [
            "empty"    => [ [], false ],
            "one"      => [ [[ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ]], true ],
            "multiple" => [
                [
                    [ "days" => [ 1 ], "from" => "10:00", "to" => "12:00" ],
                    [ "days" => [ 2 ], "from" => "13:00", "to" => "15:00" ],
                ],
                true,
            ],
        ];
    }
}
