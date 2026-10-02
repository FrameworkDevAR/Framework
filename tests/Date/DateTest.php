<?php
namespace Tests\Date;

use Framework\Date\Date;
use Framework\Date\Type\DateType;
use Framework\Date\Type\DateFormat;
use Framework\Date\TimeZone;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DateTest extends TestCase {

    public function testEmpty(): void {
        $d = Date::empty();
        $this->assertTrue($d->isEmpty());
    }

    public function testNow(): void {
        $before = time();
        $d      = Date::now();
        $after  = time();

        $this->assertTrue($d->isNotEmpty());

        // Bracketed rather than compared to a second reading of the clock, which
        // is a different second whenever the two calls fall either side of one
        $this->assertGreaterThanOrEqual($before, $d->toTime());
        $this->assertLessThanOrEqual($after, $d->toTime());
    }


    #[DataProvider("providerCreate")]
    public function testCreate(mixed $input, string $hour, int $expected): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expected, $d->toTime());
    }

    public static function providerCreate(): array {
        // Use fixed timestamps for reproducible tests
        return [
            // Date instance input
            "date"                => [ Date::create(1609459200), "", 1609459200 ],
            // number input (2021-01-01 00:00:00 UTC0)
            "numeric"             => [ 1609459200, "", 1609459200 ],
            // dashes date input (no hour)
            "dashes"              => [ "2021-01-01", "", 1609455600 ],
            // dashes date with hour as string (appended)
            "dashes hour"         => [ "2021-01-01", "15:30", 1609511400 ],
            // dashes date invalid hour as numeric
            "dashes invalid hour" => [ "2021-01-01", "15", 0 ],
            // slashes date input (no hour)
            "slashes"             => [ "01/01/2021", "", 1609455600 ],
            // special strings
            "today"               => [ "today", "", strtotime(date("Y-m-d")) ],
            "tomorrow"            => [ "tomorrow", "", strtotime(date("Y-m-d", strtotime("+1 day"))) ],
            // negative timestamp (should be treated as valid timestamp, not empty)
            "negative timestamp"  => [ -100000, "", -100000 ],
            // invalid inputs -> empty date (timestamp 0)
            "empty string"        => [ "", "", 0 ],
            "invalid date string" => [ "invalid-date-string", "", 0 ],
            "zero timestamp"      => [ 0, "", 0 ],
        ];
    }


    #[DataProvider("providerCreateOrNow")]
    public function testCreateOrNow($input, string $hour, bool $expectNow): void {
        if ($expectNow) {
            $before = time();
            $d      = Date::createOrNow($input, $hour);
            $after  = time();

            $this->assertTrue($d->isNotEmpty());
            $this->assertGreaterThanOrEqual($before, $d->toTime());
            $this->assertLessThanOrEqual($after, $d->toTime());
        } else {
            $d = Date::createOrNow($input, $hour);
            $this->assertTrue($d->isNotEmpty());
            $this->assertSame(Date::create($input, $hour)->toTime(), $d->toTime());
        }
    }

    public static function providerCreateOrNow(): array {
        return [
            // valid inputs should create same date as create()
            "date"                => [ Date::create(1609459200), "", false ],
            "numeric"             => [ 1609459200, "", false ],
            "string"              => [ "2021-01-01", "", false ],
            "string hour"         => [ "2023-01-01", "15:30", false ],
            "string invalid hour" => [ "2023-01-01", "15", true ],
            "negative timestamp"  => [ -100000, "", false ],
            // invalid inputs should return current date
            "empty string"        => [ "", "", true ],
            "invalid date string" => [ "invalid-date-string", "", true ],
            "zero timestamp"      => [ 0, "", true ],
        ];
    }


    #[DataProvider("providerParse")]
    public function testParse(string $input, int $expY, int $expM, int $expD): void {
        $d = Date::parse($input);
        $this->assertSame($expY, $d->getYear());
        $this->assertSame($expM, $d->getMonth());
        $this->assertSame($expD, $d->getDay());
    }

    public static function providerParse(): array {
        return [
            "dashes"      => [ "2020-03-05", 2020, 3, 5 ],
            "dashes time" => [ "2020-03-05 15:30:00", 2020, 3, 5 ],
            "iso z"       => [ "2020-03-05T00:00:00Z", 2020, 3, 5 ],
            "slashes"     => [ "2020/03/05", 2020, 3, 5 ],
            "text"        => [ "March 5, 2020", 2020, 3, 5 ],
            "other text"  => [ "5 Mar 2020", 2020, 3, 5 ],
            "invalid"     => [ "invalid", 0, 0, 0 ],
            "empty"       => [ "", 0, 0, 0 ],
        ];
    }


    #[DataProvider("providerCreateTime")]
    public function testCreateTime(int $day, int $month, int $year, int $hour, int $minute, int $second, array $expect): void {
        $d = Date::createTime($day, $month, $year, $hour, $minute, $second);
        $this->assertSame($expect["year"], $d->getYear());
        $this->assertSame($expect["month"], $d->getMonth());
        $this->assertSame($expect["day"], $d->getDay());
        $this->assertSame($expect["hour"], $d->getHour());
        $this->assertSame($expect["minute"], $d->getMinute());
        $this->assertSame($expect["second"], $d->getSecond());
    }

    public static function providerCreateTime(): array {
        return [
            "basic"    => [ 2, 4, 2020, 15, 30, 5, [ "year" => 2020, "month" => 4, "day" => 2, "hour" => 15, "minute" => 30, "second" => 5 ]],
            "midnight" => [ 1, 1, 2000, 0, 0, 0, [ "year" => 2000, "month" => 1, "day" => 1, "hour" => 0, "minute" => 0, "second" => 0 ]],
            "leap day" => [ 29, 2, 2020, 12, 0, 0, [ "year" => 2020, "month" => 2, "day" => 29, "hour" => 12, "minute" => 0, "second" => 0 ]],
            "year end" => [ 31, 12, 1999, 23, 59, 59, [ "year" => 1999, "month" => 12, "day" => 31, "hour" => 23, "minute" => 59, "second" => 59 ]],
            "invalid"  => [ 31, 2, 2020, 12, 0, 0, [ "year" => 2020, "month" => 3, "day" => 2, "hour" => 12, "minute" => 0, "second" => 0 ]],
            "empty"    => [ 0, 0, 0, 0, 0, 0, [ "year" => 1999, "month" => 11, "day" => 30, "hour" => 0, "minute" => 0, "second" => 0 ]],
            "negative" => [ -10, -10, -10, 0, 0, 0, [ "year" => -11, "month" => 1, "day" => 21, "hour" => 0, "minute" => 0, "second" => 0 ]],
        ];
    }


    #[DataProvider("providerMax")]
    public function testMax(array $dates, int $expected): void {
        $result = Date::max(...$dates);
        $this->assertSame($expected, $result->toTime());
    }

    public static function providerMax(): array {
        return [
            "basic"           => [[ Date::create(1000), Date::create(2000), Date::create(1500) ], 2000],
            "different order" => [[ Date::create(1500), Date::create(1000), Date::create(2000) ], 2000],
            "same values"     => [[ Date::create(2000), Date::create(2000) ], 2000],
            "includes empty"  => [[ Date::create(0), Date::create(1000), Date::create(500) ], 1000],
            "empty array"     => [[], 0],
        ];
    }


    #[DataProvider("providerIsEmpty")]
    public function testIsEmpty(mixed $input, string $hour, bool $expect): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expect, $d->isEmpty());
    }

    public static function providerIsEmpty(): array {
        return [
            "date"                => [ Date::create(1609459200), "", false ],
            "numeric"             => [ 1609459200, "", false ],
            "string"              => [ "2021-01-01", "", false ],
            "string hour"         => [ "2023-01-01", "15:30", false ],
            "string invalid hour" => [ "2023-01-01", "15", true ],
            // invalid inputs should return empty date
            "empty string"        => [ "", "", true ],
            "invalid date string" => [ "invalid-date-string", "", true ],
        ];
    }


    #[DataProvider("providerIsNotEmpty")]
    public function testIsNotEmpty(mixed $input, string $hour, bool $expect): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expect, $d->isNotEmpty());
    }

    public static function providerIsNotEmpty(): array {
        return [
            "date"                => [ Date::create(1609459200), "", true ],
            "numeric"             => [ 1609459200, "", true ],
            "string"              => [ "2021-01-01", "", true ],
            "string hour"         => [ "2023-01-01", "15:30", true ],
            "string invalid hour" => [ "2023-01-01", "15", false ],
            // invalid inputs should return empty date
            "empty string"        => [ "", "", false ],
            "invalid date string" => [ "invalid-date-string", "", false ],
        ];
    }


    #[DataProvider("providerIsValid")]
    public function testIsValid(mixed $input, string $hour, bool $expected): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expected, $d->isValid());
    }

    public static function providerIsValid(): array {
        return [
            "null"                => [ null, "", true ],
            "empty string"        => [ "", "", true ],
            "valid timestamp"     => [ 1609459200, "", true ],
            "negative timestamp"  => [ -100000, "", true ],
            "zero timestamp"      => [ 0, "", false ],
            "valid date"          => [ "2021-01-01", "", true ],
            "valid date hour"     => [ "2021-01-01", "15:30", true ],
            "invalid date"        => [ "not-a-date", "", false ],
            "invalid hour"        => [ "2021-01-01", "15", false ],
            "invalid date object" => [ Date::create("not-a-date"), "", false ],
        ];
    }


    public function testAnInvalidDateStaysInvalid(): void {
        // The transformations used to normalize an empty date to the empty
        // one, which is valid, and a date that could not be read lost its
        // invalid flag on the way through them. A request then read garbage
        // as nothing typed at all
        $garbage = Date::create("not-a-date");

        $this->assertFalse($garbage->toServerTime()->isValid());
        $this->assertFalse($garbage->toTimeZone(1)->isValid());
        $this->assertFalse($garbage->set(hour: 0)->isValid());
        $this->assertTrue($garbage->toServerTime()->isEmpty());
    }

    public function testAnEmptyDateStaysEmpty(): void {
        // A date that was never given keeps being the valid empty one
        $empty = Date::empty();

        $this->assertTrue($empty->toServerTime()->isValid());
        $this->assertTrue($empty->set(hour: 0)->isValid());
        $this->assertTrue($empty->toServerTime()->isEmpty());
        $this->assertTrue($empty->toTimeZone(1)->isEmpty());
    }

    #[DataProvider("providerToTimeZone")]
    public function testTheDateMovesToTheTimeZone(float|string $timeZone, string $expected): void {
        // The server is at -3, so a Time Zone ahead of it can already be the next day
        $date   = Date::create("2026-10-01 21:10:00");
        $result = $date->toTimeZone($timeZone)->toString(DateFormat::ReverseSeconds);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string,array{float|string,string}>
     */
    public static function providerToTimeZone(): array {
        return [
            "the server"   => [ -3,     "2026-10-01 21:10:00" ],
            "behind it"    => [ -5,     "2026-10-01 19:10:00" ],
            "the next day" => [ 1,      "2026-10-02 01:10:00" ],
            "a half hour"  => [ 5.5,    "2026-10-02 05:40:00" ],
            "a string"     => [ "1",    "2026-10-02 01:10:00" ],
            "a negative"   => [ "-5",   "2026-10-01 19:10:00" ],
            "not set"      => [ "",     "2026-10-01 21:10:00" ],
            // UTC is a Time Zone like any other, and not one that was never set
            "utc"          => [ 0,      "2026-10-02 00:10:00" ],
            "utc as text"  => [ "0",    "2026-10-02 00:10:00" ],
        ];
    }

    public function testABadHourKeepsItsText(): void {
        // The hour string survives the transformations, so the validation
        // can still say the hour is the part that is wrong
        $date = Date::create("2021-01-01", "24:00");

        $this->assertTrue($date->toServerTime()->hasHour());
        $this->assertFalse($date->toServerTime()->isValidHour());
    }

    #[DataProvider("providerSet")]
    public function testSet(array $setArgs, Date $date): void {
        $d = $date->set(...$setArgs);

        $expected = array_merge([
            "year"   => $date->getYear(),
            "month"  => $date->getMonth(),
            "day"    => $date->getDay(),
            "hour"   => $date->getHour(),
            "minute" => $date->getMinute(),
            "second" => $date->getSecond(),
        ], $setArgs);

        $this->assertSame($expected["year"], $d->getYear());
        $this->assertSame($expected["month"], $d->getMonth());
        $this->assertSame($expected["day"], $d->getDay());
        $this->assertSame($expected["hour"], $d->getHour());
        $this->assertSame($expected["minute"], $d->getMinute());
        $this->assertSame($expected["second"], $d->getSecond());
    }

    public static function providerSet(): array {
        $date = Date::createTime(1, 1, 2020, 5, 6, 7);

        return [
            "year only"   => [[ "year" => 2021 ], $date ],
            "month only"  => [[ "month" => 12 ], $date ],
            "day only"    => [[ "day" => 15 ], $date ],
            "hour only"   => [[ "hour" => 10 ], $date ],
            "minute only" => [[ "minute" => 30 ], $date ],
            "second only" => [[ "second" => 45 ], $date ],
            "some fields" => [[ "month" => 12, "minute" => 30 ], $date ],
            "all fields"  => [[ "year" => 1999, "month" => 11, "day" => 30, "hour" => 0, "minute" => 1, "second" => 2 ], $date ],
            "no fields"   => [[], $date ],
            "empty"       => [[], Date::empty() ],
        ];
    }


    #[DataProvider("providerSetHourMinute")]
    public function testSetHourMinute(string $input, int $expectHour, int $expectMinute, bool $expectEmpty): void {
        $d = Date::createTime(1, 1, 2020, 5, 6, 7);
        $d3 = $d->setHourMinute($input);

        if ($expectEmpty) {
            $this->assertTrue($d3->isEmpty());
        } else {
            $this->assertSame($expectHour, $d3->getHour());
            $this->assertSame($expectMinute, $d3->getMinute());
        }
    }

    public static function providerSetHourMinute(): array {
        return [
            "leading zero"    => [ "08:20", 8, 20, false ],
            "no leading zero" => [ "8:5", 8, 5, false ],
            "midnight"        => [ "00:00", 0, 0, false ],
            "max"             => [ "23:59", 23, 59, false ],
            // invalid hour/minute should return empty date
            "date"            => [ "2020-01-01", 0, 0, true ],
            "date time"       => [ "2020-01-01 15:30", 0, 0, true ],
            "invalid empty"   => [ "", 0, 0, true ],
            "invalid text"    => [ "not-a-time", 0, 0, true ],
        ];
    }


    #[DataProvider("providerAdd")]
    public function testAdd(array $addArgs, array $base, array $expect): void {
        $d = Date::createTime($base["day"], $base["month"], $base["year"], $base["hour"], $base["minute"], $base["second"]);
        $d2 = $d->add(...$addArgs);

        $this->assertSame($expect["year"], $d2->getYear());
        $this->assertSame($expect["month"], $d2->getMonth());
        $this->assertSame($expect["day"], $d2->getDay());
        $this->assertSame($expect["hour"], $d2->getHour());
        $this->assertSame($expect["minute"], $d2->getMinute());
        $this->assertSame($expect["second"], $d2->getSecond());
    }

    public static function providerAdd(): array {
        $base = [ "year" => 2020, "month" => 10, "day" => 10, "hour" => 0, "minute" => 0, "second" => 0 ];

        return [
            "add_days"     => [[ "days" => 5  ], $base, [ "year" => 2020, "month" => 10, "day" => 15, "hour" => 0, "minute" => 0,  "second" => 0  ]],
            "add_weeks"    => [[ "weeks" => 2  ], $base, [ "year" => 2020, "month" => 10, "day" => 24, "hour" => 0, "minute" => 0,  "second" => 0  ]],
            "add_months"   => [[ "months" => 1  ], $base, [ "year" => 2020, "month" => 11, "day" => 10, "hour" => 0, "minute" => 0,  "second" => 0  ]],
            "add_years"    => [[ "years" => 2  ], $base, [ "year" => 2022, "month" => 10, "day" => 10, "hour" => 0, "minute" => 0,  "second" => 0  ]],
            "add_hours"    => [[ "hours" => 5  ], $base, [ "year" => 2020, "month" => 10, "day" => 10, "hour" => 5, "minute" => 0,  "second" => 0  ]],
            "add_minutes"  => [[ "minutes" => 30 ], $base, [ "year" => 2020, "month" => 10, "day" => 10, "hour" => 0, "minute" => 30, "second" => 0  ]],
            "add_seconds"  => [[ "seconds" => 30 ], $base, [ "year" => 2020, "month" => 10, "day" => 10, "hour" => 0, "minute" => 0,  "second" => 30 ]],
            "add_negative" => [[ "days" => -5 ], $base, [ "year" => 2020, "month" => 10, "day" => 5,  "hour" => 0, "minute" => 0,  "second" => 0  ]],
            "add_multiple" => [[ "days" => 1, "hours" => 2, "minutes" => 15 ], $base, [ "year" => 2020, "month" => 10, "day" => 11, "hour" => 2, "minute" => 15, "second" => 0 ]],
        ];
    }


    #[DataProvider("providerSubtract")]
    public function testSubtract(array $subArgs, array $base, array $expect): void {
        $d = Date::createTime($base["day"], $base["month"], $base["year"], $base["hour"], $base["minute"], $base["second"]);
        $d2 = $d->subtract(...$subArgs);

        $this->assertSame($expect["year"], $d2->getYear());
        $this->assertSame($expect["month"], $d2->getMonth());
        $this->assertSame($expect["day"], $d2->getDay());
        $this->assertSame($expect["hour"], $d2->getHour());
        $this->assertSame($expect["minute"], $d2->getMinute());
        $this->assertSame($expect["second"], $d2->getSecond());
    }

    public static function providerSubtract(): array {
        $base = [ "year" => 2020, "month" => 10, "day" => 10, "hour" => 0, "minute" => 0, "second" => 0 ];

        return [
            "sub_days"     => [[ "days" => 5  ], $base, [ "year" => 2020, "month" => 10, "day" => 5,  "hour" => 0,  "minute" => 0,  "second" => 0  ]],
            "sub_weeks"    => [[ "weeks" => 2  ], $base, [ "year" => 2020, "month" => 9,  "day" => 26, "hour" => 0,  "minute" => 0,  "second" => 0  ]],
            "sub_months"   => [[ "months" => 1  ], $base, [ "year" => 2020, "month" => 9,  "day" => 10, "hour" => 0,  "minute" => 0,  "second" => 0  ]],
            "sub_years"    => [[ "years" => 2  ], $base, [ "year" => 2018, "month" => 10, "day" => 10, "hour" => 0,  "minute" => 0,  "second" => 0  ]],
            "sub_hours"    => [[ "hours" => 2  ], $base, [ "year" => 2020, "month" => 10, "day" => 9,  "hour" => 22, "minute" => 0,  "second" => 0  ]],
            "sub_minutes"  => [[ "minutes" => 30 ], $base, [ "year" => 2020, "month" => 10, "day" => 9,  "hour" => 23, "minute" => 30, "second" => 0  ]],
            "sub_seconds"  => [[ "seconds" => 30 ], $base, [ "year" => 2020, "month" => 10, "day" => 9,  "hour" => 23, "minute" => 59, "second" => 30 ]],
            "sub_negative" => [[ "days" => -5 ], $base, [ "year" => 2020, "month" => 10, "day" => 15, "hour" => 0,  "minute" => 0,  "second" => 0  ]],
            "sub_multiple" => [[ "days" => 1, "hours" => 2, "minutes" => 15 ], $base, [ "year" => 2020, "month" => 10, "day" => 8, "hour" => 21, "minute" => 45, "second" => 0 ]],
        ];
    }


    #[DataProvider("providerToTime")]
    public function testToTime(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toTime());
    }

    public static function providerToTime(): array {
        return [
            "null"      => [ null, 0 ],
            "empty"     => [ "", 0 ],
            "invalid"   => [ "invalid", 0 ],
            "timestamp" => [ 1000, 1000 ],
            "string"    => [ "1000", 1000 ],
            "dashes"    => [ "2020-02-03", 1580684400 ],
            "date"      => [ Date::createTime(4, 5, 2021, 7, 8, 9), 1620104889 ],
        ];
    }


    #[DataProvider("providerToNumber")]
    public function testToNumber(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toNumber());
    }

    public static function providerToNumber(): array {
        return [
            "null"      => [ null, 0 ],
            "empty"     => [ "", 0 ],
            "invalid"   => [ "invalid", 0 ],
            "timestamp" => [ 1000, 19700101 ],
            "dashes"    => [ "1970-01-01", 19700101 ],
            "future"    => [ "2000-01-01", 20000101 ],
            "date"      => [ Date::createTime(4, 5, 2021, 7, 8, 9), 20210504 ],
        ];
    }


    #[DataProvider("providerToMinutes")]
    public function testToMinutes(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toMinutes());
    }

    public static function providerToMinutes(): array {
        return [
            "null"        => [ null, 0 ],
            "empty"       => [ "", 0 ],
            "invalid"     => [ "invalid", 0 ],
            "midnight"    => [ "1970-01-01 00:00:00", 0 ],
            "one minute"  => [ "1970-01-01 00:01:00", 1 ],
            "hours minds" => [ "1970-01-01 02:30:00", 150 ],
            "date"        => [ Date::createTime(4, 5, 2021, 4, 20, 0), 4 * 60 + 20 ],
        ];
    }


    #[DataProvider("providerToServerTime")]
    public function testToServerTime(int $input, bool $useTimeZone, int $expected): void {
        $d = Date::create($input);
        $d2 = $d->toServerTime($useTimeZone);
        $this->assertSame($expected, $d2->toTime());
    }

    public static function providerToServerTime(): array {
        return [
            "zero true"       => [ 0, true, TimeZone::toServerTime(0, true) ],
            "timestamp true"  => [ 1609459200, true, TimeZone::toServerTime(1609459200, true) ],
            "timestamp false" => [ 1609459200, false, TimeZone::toServerTime(1609459200, false) ],
        ];
    }


    #[DataProvider("providerToDayMoment")]
    public function testToDayMoment(mixed $input, DateType $type, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toDayMoment($type)->toTime());
    }

    public static function providerToDayMoment(): array {
        return [
            "null"        => [ null, DateType::None, 0 ],
            "empty"       => [ "", DateType::Start, 0 ],
            "invalid"     => [ "not-a-date", DateType::Middle, 0 ],
            "day none"    => [ "2020-02-03 12:34:56", DateType::None, 1580729696 ],
            "day start"   => [ "2020-02-03 12:34:56", DateType::Start, 1580684400 ],
            "day middle"  => [ "2020-02-03 12:34:56", DateType::Middle, 1580727600 ],
            "day end"     => [ "2020-02-03 12:34:56", DateType::End, 1580770799 ],
            "date start"  => [ Date::createTime(3, 2, 2020, 12, 34, 56), DateType::Start, 1580684400 ],
            "date middle" => [ Date::createTime(3, 2, 2020, 12, 34, 56), DateType::Middle, 1580727600 ],
            "date end"    => [ Date::createTime(3, 2, 2020, 12, 34, 56), DateType::End, 1580770799 ],
        ];
    }


    #[DataProvider("providerToDayStart")]
    public function testToDayStart(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toDayStart()->toTime());
    }

    public static function providerToDayStart(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 1580684400 ],
            "day time" => [ "2020-02-03 12:34:56", 1580684400 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 1580684400 ],
        ];
    }


    #[DataProvider("providerToDayMiddle")]
    public function testToDayMiddle(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toDayMiddle()->toTime());
    }

    public static function providerToDayMiddle(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 1580727600 ],
            "day time" => [ "2020-02-03 12:34:56", 1580727600 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 1580727600 ],
        ];
    }


    #[DataProvider("providerToDayEnd")]
    public function testToDayEnd(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toDayEnd()->toTime());
    }

    public static function providerToDayEnd(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 1580770799 ],
            "day time" => [ "2020-02-03 12:34:56", 1580770799 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 1580770799 ],
        ];
    }


    #[DataProvider("providerToWeekStart")]
    public function testToWeekStart(mixed $input, bool $startMonday, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toWeekStart($startMonday)->toTime());
    }

    public static function providerToWeekStart(): array {
        return [
            "null"        => [ null, false, 0 ],
            "empty"       => [ "", false, 0 ],
            "invalid"     => [ "not-a-date", false, 0 ],
            "day sunday"  => [ "2020-02-03 12:34:56", false, 1580643296 ],
            "day monday"  => [ "2020-02-03 12:34:56", true, 1580643296 ],
            "date sunday" => [ Date::createTime(3, 2, 2020, 12, 34, 56), false, 1580643296 ],
            "date monday" => [ Date::createTime(3, 2, 2020, 12, 34, 56), true, 1580643296 ],
        ];
    }


    #[DataProvider("providerToWeekEnd")]
    public function testToWeekEnd(mixed $input, bool $startMonday, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toWeekEnd($startMonday)->toTime());
    }

    public static function providerToWeekEnd(): array {
        return [
            "null"        => [ null, false, 0 ],
            "empty"       => [ "", false, 0 ],
            "invalid"     => [ "not-a-date", false, 0 ],
            "day sunday"  => [ "2020-02-03 12:34:56", false, 1581161696 ],
            "day monday"  => [ "2020-02-03 12:34:56", true, 1581161696 ],
            "date sunday" => [ Date::createTime(3, 2, 2020, 12, 34, 56), false, 1581161696 ],
            "date monday" => [ Date::createTime(3, 2, 2020, 12, 34, 56), true, 1581161696 ],
        ];
    }


    #[DataProvider("providerToMonthStart")]
    public function testToMonthStart(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toMonthStart()->toTime());
    }

    public static function providerToMonthStart(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 1580511600 ],
            "day time" => [ "2020-02-03 12:34:56", 1580556896 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 1580556896 ],
        ];
    }


    #[DataProvider("providerToMonthEnd")]
    public function testToMonthEnd(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toMonthEnd()->toTime());
    }

    public static function providerToMonthEnd(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day jan"  => [ "2020-01-05 12:34:56", 1580470496 ],
            "day feb"  => [ "2020-02-05 12:34:56", 1582976096 ],
            "day mar"  => [ "2020-03-05 12:34:56", 1585650896 ],
            "date apr" => [ Date::createTime(5, 4, 2020, 0, 0, 0), 1588197600 ],
        ];
    }


    #[DataProvider("providerToYearStart")]
    public function testToYearStart(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toYearStart()->toTime());
    }

    public static function providerToYearStart(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 1577833200 ],
            "day time" => [ "2020-02-03 12:34:56", 1577878496 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 1577878496 ],
        ];
    }


    #[DataProvider("providerToYearEnd")]
    public function testToYearEnd(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toYearEnd()->toTime());
    }

    public static function providerToYearEnd(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 1609369200 ],
            "day time" => [ "2020-02-03 12:34:56", 1609414496 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 1609414496 ],
        ];
    }


    #[DataProvider("providerGetYear")]
    public function testGetYear(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getYear());
    }

    public static function providerGetYear(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 2020 ],
            "day time" => [ "2020-02-03 12:34:56", 2020 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 2020 ],
        ];
    }


    #[DataProvider("providerGetYearDays")]
    public function testGetYearDays(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getYearDays());
    }

    public static function providerGetYearDays(): array {
        return [
            "null"      => [ null, 0 ],
            "empty"     => [ "", 0 ],
            "invalid"   => [ "not-a-date", 0 ],
            "non leap"  => [ "2019-06-01", 365 ],
            "leap"      => [ "2020-02-03", 366 ],
            "date leap" => [ Date::createTime(29, 2, 2020), 366 ],
        ];
    }


    #[DataProvider("providerGetMonth")]
    public function testGetMonth(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getMonth());
    }

    public static function providerGetMonth(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 2 ],
            "day time" => [ "2020-02-03 12:34:56", 2 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 2 ],
        ];
    }


    #[DataProvider("providerGetMonthZero")]
    public function testGetMonthZero(mixed $input, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getMonthZero());
    }

    public static function providerGetMonthZero(): array {
        return [
            "null"     => [ null, "" ],
            "empty"    => [ "", "" ],
            "invalid"  => [ "not-a-date", "" ],
            "day"      => [ "2020-02-03", "02" ],
            "day time" => [ "2020-02-03 12:34:56", "02" ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), "02" ],
        ];
    }


    #[DataProvider("providerGetMonthDays")]
    public function testGetMonthDays(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getMonthDays());
    }

    public static function providerGetMonthDays(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "jan"      => [ "2020-01-05 12:34:56", 31 ],
            "feb leap" => [ "2020-02-03", 29 ],
            "jun"      => [ "2019-06-01", 30 ],
            "date"     => [ Date::createTime(29, 2, 2020), 29 ],
        ];
    }


    #[DataProvider("providerGetMonthName")]
    public function testGetMonthName(mixed $input, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getMonthName());
    }

    public static function providerGetMonthName(): array {
        return [
            "null"     => [ null, "" ],
            "empty"    => [ "", "" ],
            "invalid"  => [ "not-a-date", "" ],
            "day"      => [ "2020-02-03", "February" ],
            "day time" => [ "2020-02-03 12:34:56", "February" ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), "February" ],
        ];
    }


    #[DataProvider("providerGetWeekOfMonth")]
    public function testGetWeekOfMonth(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getWeekOfMonth());
    }

    public static function providerGetWeekOfMonth(): array {
        return [
            "null"          => [ null, 0 ],
            "empty"         => [ "", 0 ],
            "invalid"       => [ "not-a-date", 0 ],
            "month start"   => [ "2020-02-03", 1 ],
            "month end"     => [ "2020-02-29", 5 ],
            "iso year prev" => [ "2021-01-01", 1 ],
            "iso year new"  => [ "2021-01-04", 1 ],
            "date"          => [ Date::createTime(31, 12, 2020), 5 ],
        ];
    }


    #[DataProvider("providerGetWeekOfYear")]
    public function testGetWeek(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getWeekOfYear());
    }

    public static function providerGetWeekOfYear(): array {
        return [
            "null"          => [ null, 0 ],
            "empty"         => [ "", 0 ],
            "invalid"       => [ "not-a-date", 0 ],
            "month start"   => [ "2020-02-03", 6 ],
            "month end"     => [ "2020-02-29", 9 ],
            "iso year prev" => [ "2021-01-01", 53 ],
            "iso year new"  => [ "2021-01-04", 1 ],
            "date"          => [ Date::createTime(31, 12, 2020), 53 ],
        ];
    }


    #[DataProvider("providerGetDay")]
    public function testGetDay(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getDay());
    }

    public static function providerGetDay(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 3 ],
            "day time" => [ "2020-02-03 12:34:56", 3 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 3 ],
        ];
    }


    #[DataProvider("providerGetDayZero")]
    public function testGetDayZero(mixed $input, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getDayZero());
    }

    public static function providerGetDayZero(): array {
        return [
            "null"     => [ null, "" ],
            "empty"    => [ "", "" ],
            "invalid"  => [ "not-a-date", "" ],
            "day"      => [ "2020-02-03", "03" ],
            "day time" => [ "2020-02-03 12:34:56", "03" ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), "03" ],
        ];
    }


    #[DataProvider("providerGetDayOfWeek")]
    public function testGetDayOfWeek(mixed $input, bool $startMonday, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getDayOfWeek($startMonday));
    }

    public static function providerGetDayOfWeek(): array {
        return [
            "null"       => [ null, false, 0 ],
            "empty"      => [ "", false, 0 ],
            "invalid"    => [ "not-a-date", false, 0 ],
            "monday sun" => [ "2020-02-03", false, 1 ],
            "monday mon" => [ "2020-02-03", true, 1 ],
            "sunday sun" => [ "2020-02-02", false, 0 ],
            "sunday mon" => [ "2020-02-02", true, 7 ],
        ];
    }


    #[DataProvider("providerGetDayName")]
    public function testGetDayName(mixed $input, bool $startMonday, int $length, bool $inUpperCase, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getDayName($startMonday, $length, $inUpperCase));
    }

    public static function providerGetDayName(): array {
        return [
            "monday"       => [ "2020-02-03", false, 0, false, "Monday" ],
            "monday short" => [ "2020-02-03", false, 3, false, "Mon" ],
            "monday upper" => [ "2020-02-03", false, 0, true,  "MONDAY" ],
        ];
    }


    #[DataProvider("providerGetDayMonth")]
    public function testGetDayMonth(mixed $input, int $monthLength, bool $inUpperCase, string $language, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getDayMonth($monthLength, $inUpperCase, $language));
    }

    public static function providerGetDayMonth(): array {
        return [
            "null"        => [ null, 0, false, "", "" ],
            "empty"       => [ "", 0, false, "", "" ],
            "invalid"     => [ "not-a-date", 0, false, "", "" ],
            "day default" => [ "2020-02-03", 0, false, "", "03 February" ],
            "day short"   => [ "2020-02-03", 3, false, "", "03 Feb" ],
            "day upper"   => [ "2020-02-03", 0, true,  "", "03 FEBRUARY" ],
        ];
    }


    #[DataProvider("providerGetHour")]
    public function testGetHour(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getHour());
    }

    public static function providerGetHour(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 0 ],
            "day time" => [ "2020-02-03 12:34:56", 12 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 12 ],
        ];
    }


    #[DataProvider("providerGetMinute")]
    public function testGetMinute(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getMinute());
    }

    public static function providerGetMinute(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 0 ],
            "day time" => [ "2020-02-03 12:34:56", 34 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 34 ],
        ];
    }


    #[DataProvider("providerGetSecond")]
    public function testGetSecond(mixed $input, int $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->getSecond());
    }

    public static function providerGetSecond(): array {
        return [
            "null"     => [ null, 0 ],
            "empty"    => [ "", 0 ],
            "invalid"  => [ "not-a-date", 0 ],
            "day"      => [ "2020-02-03", 0 ],
            "day time" => [ "2020-02-03 12:34:56", 56 ],
            "date"     => [ Date::createTime(3, 2, 2020, 12, 34, 56), 56 ],
        ];
    }


    #[DataProvider("providerIsToday")]
    public function testIsToday(mixed $input, bool $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->isToday());
    }

    public static function providerIsToday(): array {
        return [
            "null"      => [ null, false ],
            "empty"     => [ "", false ],
            "invalid"   => [ "not-a-date", false ],
            "today"     => [ date("Y-m-d"), true ],
            "yesterday" => [ date("Y-m-d", strtotime("-1 day")), false ],
        ];
    }


    #[DataProvider("providerIsPast")]
    public function testIsPast(mixed $input, bool $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->isPast());
    }

    public static function providerIsPast(): array {
        return [
            "null"    => [ null, false ],
            "empty"   => [ "", false ],
            "invalid" => [ "not-a-date", false ],
            "past"    => [ date("Y-m-d", strtotime("-1 day")), true ],
            "future"  => [ date("Y-m-d", strtotime("+1 day")), false ],
        ];
    }


    #[DataProvider("providerIsFuture")]
    public function testIsFuture(mixed $input, bool $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->isFuture());
    }

    public static function providerIsFuture(): array {
        return [
            "null"    => [ null, false ],
            "empty"   => [ "", false ],
            "invalid" => [ "not-a-date", false ],
            "past"    => [ date("Y-m-d", strtotime("-1 day")), false ],
            "future"  => [ date("Y-m-d", strtotime("+1 day")), true ],
        ];
    }


    #[DataProvider("providerIsCurrentMonth")]
    public function testIsCurrentMonth(mixed $input, bool $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->isCurrentMonth());
    }

    public static function providerIsCurrentMonth(): array {
        return [
            "null"    => [ null, false ],
            "empty"   => [ "", false ],
            "invalid" => [ "not-a-date", false ],
            "current" => [ date("Y-m-d"), true ],
            "past"    => [ date("Y-m-d", strtotime("first day of last month")), false ],
            "future"  => [ date("Y-m-d", strtotime("first day of next month")), false ],
        ];
    }


    #[DataProvider("providerIsNotEqual")]
    public function testIsNotEqual(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isNotEqual($d2));
    }

    public static function providerIsNotEqual(): array {
        return [
            "both null"      => [ null, null, false ],
            "both empty"     => [ "", "", false ],
            "both invalid"   => [ "not-a-date", "not-a-date", false ],
            "one invalid"    => [ "2020-02-03", "not-a-date", true ],
            "same date"      => [ "2020-02-03", "2020-02-03", false ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", false ],
            "same date obj"  => [ Date::createTime(3, 2, 2020, 12, 34, 56), Date::createTime(3, 2, 2020, 12, 34, 56), false ],
            "different"      => [ "2020-02-03", "2021-02-03", true ],
            "different time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:57", true ],
        ];
    }


    #[DataProvider("providerIsEqual")]
    public function testIsEqual(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isEqual($d2));
    }

    public static function providerIsEqual(): array {
        return [
            "both null"      => [ null, null, true ],
            "both empty"     => [ "", "", true ],
            "both invalid"   => [ "not-a-date", "not-a-date", true ],
            "one invalid"    => [ "2020-02-03", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", true ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", true ],
            "same date obj"  => [ Date::createTime(3, 2, 2020, 12, 34, 56), Date::createTime(3, 2, 2020, 12, 34, 56), true ],
            "different"      => [ "2020-02-03", "2021-02-03", false ],
            "different time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:57", false ],
        ];
    }


    #[DataProvider("providerIsEqualDay")]
    public function testIsEqualDay(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isEqualDay($d2));
    }

    public static function providerIsEqualDay(): array {
        return [
            "both null"      => [ null, null, false ],
            "both empty"     => [ "", "", false ],
            "both invalid"   => [ "not-a-date", "not-a-date", false ],
            "one invalid"    => [ "2020-02-03", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", true ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", true ],
            "same date obj"  => [ Date::createTime(3, 2, 2020, 12, 34, 56), Date::createTime(3, 2, 2020, 12, 34, 56), true ],
            "different day"  => [ "2020-02-03", "2020-02-04", false ],
            "different time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:57", true ],
        ];
    }


    #[DataProvider("providerIsBefore")]
    public function testIsBefore(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isBefore($d2));
    }

    public static function providerIsBefore(): array {
        return [
            "both null"      => [ null, null, false ],
            "both empty"     => [ "", "", false ],
            "both invalid"   => [ "not-a-date", "not-a-date", false ],
            "one invalid"    => [ "2020-02-03", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", false ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", false ],
            "before"         => [ "2020-02-03", "2021-02-03", true ],
            "after"          => [ "2021-02-03", "2020-02-03", false ],
        ];
    }


    #[DataProvider("providerIsBeforeOrEqual")]
    public function testIsBeforeOrEqual(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isBeforeOrEqual($d2));
    }

    public static function providerIsBeforeOrEqual(): array {
        return [
            "both null"      => [ null, null, true ],
            "both empty"     => [ "", "", true ],
            "both invalid"   => [ "not-a-date", "not-a-date", true ],
            "one invalid"    => [ "2020-02-03", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", true ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", true ],
            "before"         => [ "2020-02-03", "2021-02-03", true ],
            "after"          => [ "2021-02-03", "2020-02-03", false ],
        ];
    }


    #[DataProvider("providerIsAfter")]
    public function testIsAfter(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isAfter($d2));
    }

    public static function providerIsAfter(): array {
        return [
            "both null"      => [ null, null, false ],
            "both empty"     => [ "", "", false ],
            "both invalid"   => [ "not-a-date", "not-a-date", false ],
            "one invalid"    => [ "2020-02-03", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", false ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", false ],
            "before"         => [ "2020-02-03", "2021-02-03", false ],
            "after"          => [ "2021-02-03", "2020-02-03", true ],
        ];
    }


    #[DataProvider("providerIsAfterOrEqual")]
    public function testIsAfterOrEqual(mixed $input1, mixed $input2, bool $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->isAfterOrEqual($d2));
    }

    public static function providerIsAfterOrEqual(): array {
        return [
            "both null"      => [ null, null, true ],
            "both empty"     => [ "", "", true ],
            "both invalid"   => [ "not-a-date", "not-a-date", true ],
            "one invalid"    => [ "2020-02-03", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", true ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", true ],
            "before"         => [ "2020-02-03", "2021-02-03", false ],
            "after"          => [ "2021-02-03", "2020-02-03", true ],
        ];
    }


    #[DataProvider("providerIsBetween")]
    public function testIsBetween(mixed $input, mixed $start, mixed $end, bool $expected): void {
        $d = Date::create($input);
        $s = Date::create($start);
        $e = Date::create($end);
        $this->assertSame($expected, $d->isBetween($s, $e));
    }

    public static function providerIsBetween(): array {
        return [
            "null"           => [ null, null, null, false ],
            "empty"          => [ "", "", "", false ],
            "invalid"        => [ "not-a-date", "not-a-date", "not-a-date", false ],
            "one invalid"    => [ "2020-02-03", "not-a-date", "2020-02-04", false ],
            "two invalid"    => [ null, "not-a-date", "not-a-date", false ],
            "same date"      => [ "2020-02-03", "2020-02-03", "2020-02-03", true ],
            "same date time" => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", "2020-02-03 12:34:56", true ],
            "between"        => [ "2020-02-03", "2020-02-01", "2020-02-05", true ],
            "before"         => [ "2020-02-01", "2020-02-03", "2020-02-05", false ],
            "after"          => [ "2020-02-06", "2020-02-03", "2020-02-05", false ],
        ];
    }


    #[DataProvider("providerIsAtDayStart")]
    public function testIsAtDayStart(mixed $input, bool $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->isAtDayStart());
    }

    public static function providerIsAtDayStart(): array {
        return [
            "null"     => [ null, false ],
            "empty"    => [ "", false ],
            "invalid"  => [ "not-a-date", false ],
            "day"      => [ "2020-02-03", true ],
            "day time" => [ "2020-02-03 12:34:56", false ],
            "date"     => [ Date::createTime(3, 2, 2020, 0, 0, 0), true ],
        ];
    }


    #[DataProvider("providerIsValidPeriod")]
    public function testIsValidPeriod(mixed $start, mixed $end, bool $expected): void {
        $d1 = Date::create($start);
        $d2 = Date::create($end);
        $this->assertSame($expected, $d1->isValidPeriod($d2));
    }

    public static function providerIsValidPeriod(): array {
        return [
            "both empty"     => [ null, null, true ],
            "start empty"    => [ null, "2020-02-03", true ],
            "end empty"      => [ "2020-02-03", null, true ],
            "same day"       => [ "2020-02-03", "2020-02-03", true ],
            "start before"   => [ "2020-02-03", "2020-02-04", true ],
            "start after"    => [ "2020-02-04", "2020-02-03", false ],
            "same timestamp" => [ "2020-02-03 12:00:00", "2020-02-03 12:00:00", true ],
            "invalid start"  => [ "not-a-date", "2020-02-03", true ],
            "invalid end"    => [ "2020-02-03", "not-a-date", true ],
        ];
    }


    public function testKeepsHourText(): void {
        $d = Date::create("2020-02-03", "10:30");

        $this->assertSame("10:30", $d->set(day: 4)->getHourText());
        $this->assertSame("10:30", $d->add(days: 1)->getHourText());
        $this->assertSame("10:30", $d->subtract(days: 1)->getHourText());
        $this->assertSame("10:30", $d->setHourMinute("12:45")->getHourText());
        $this->assertSame("10:30", $d->toDayStart()->getHourText());
        $this->assertSame("10:30", $d->toServerTime(false)->getHourText());
    }


    #[DataProvider("providerHasHour")]
    public function testHasHour(mixed $input, string $hour, bool $expected): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expected, $d->hasHour());
    }

    public static function providerHasHour(): array {
        return [
            "no input"         => [ null, "", false ],
            "date without"     => [ "2020-02-03", "", false ],
            "date with hour"   => [ "2020-02-03", "10:30", true ],
            "date time string" => [ "2020-02-03 10:30:00", "", false ],
            "invalid hour"     => [ "2020-02-03", "25:00", true ],
        ];
    }


    #[DataProvider("providerGetHourText")]
    public function testGetHourText(mixed $input, string $hour, string $expected): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expected, $d->getHourText());
    }

    public static function providerGetHourText(): array {
        return [
            "empty"          => [ null, "", "" ],
            "valid hour"     => [ "2020-02-03", "10:30", "10:30" ],
            "invalid hour"   => [ "2020-02-03", "25:00", "25:00" ],
            "malformed hour" => [ "2020-02-03", "abc", "abc" ],
        ];
    }


    #[DataProvider("providerIsValidHour")]
    public function testIsValidHour(mixed $input, string $hour, ?array $minutes, bool $expected): void {
        $d = Date::create($input, $hour);
        $this->assertSame($expected, $d->isValidHour($minutes));
    }

    public static function providerIsValidHour(): array {
        return [
            "empty"              => [ null, "", null, false ],
            "valid"              => [ "2020-02-03", "10:30", null, true ],
            "valid minute allow" => [ "2020-02-03", "10:30", [ 0, 30 ], true ],
            "minute not allowed" => [ "2020-02-03", "10:45", [ 0, 30 ], false ],
            "invalid hour"       => [ "2020-02-03", "24:00", null, false ],
            "invalid minute"     => [ "2020-02-03", "10:60", null, false ],
            "malformed"          => [ "2020-02-03", "10", null, false ],
        ];
    }


    #[DataProvider("providerIsValidHourPeriod")]
    public function testIsValidHourPeriod(string $startHour, string $endHour, bool $expected): void {
        $d1 = Date::create("2020-02-03", $startHour);
        $d2 = Date::create("2020-02-03", $endHour);
        $this->assertSame($expected, $d1->isValidHourPeriod($d2));
    }

    public static function providerIsValidHourPeriod(): array {
        return [
            "both empty"    => [ "", "", true ],
            "start empty"   => [ "", "10:00", true ],
            "end empty"     => [ "10:00", "", true ],
            "valid period"  => [ "10:00", "11:00", true ],
            "same hour"     => [ "10:00", "10:00", false ],
            "reverse"       => [ "11:00", "10:00", false ],
            "midnight"      => [ "00:00", "10:00", false ],
            "invalid start" => [ "25:00", "10:00", false ],
            "invalid end"   => [ "10:00", "25:00", false ],
        ];
    }


    #[DataProvider("providerGetDaysDiff")]
    public function testGetDaysDiff(mixed $input1, mixed $input2, int $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->getDaysDiff($d2));
    }

    public static function providerGetDaysDiff(): array {
        return [
            "both null"      => [ null, null, 0 ],
            "both empty"     => [ "", "", 0 ],
            "both invalid"   => [ "not-a-date", "not-a-date", 0 ],
            "one invalid"    => [ "2020-02-03", "not-a-date", 0 ],
            "same date"      => [ "2020-02-03", "2020-02-03", 0 ],
            "same date time" => [ "2020-02-03 11:34:56", "2020-02-03 12:34:56", 0 ],
            "diff one day"   => [ "2020-02-03", "2020-02-04", 1 ],
            "diff five days" => [ "2020-02-01", "2020-02-06", 5 ],
        ];
    }


    #[DataProvider("providerGetWeeksDiff")]
    public function testGetWeeksDiff(mixed $input1, mixed $input2, int $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->getWeeksDiff($d2));
    }

    public static function providerGetWeeksDiff(): array {
        return [
            "both null"      => [ null, null, 0 ],
            "both empty"     => [ "", "", 0 ],
            "both invalid"   => [ "not-a-date", "not-a-date", 0 ],
            "one invalid"    => [ "2020-02-03", "not-a-date", 0 ],
            "same date"      => [ "2020-02-03", "2020-02-03", 0 ],
            "same date time" => [ "2020-02-03 11:34:56", "2020-02-03 12:34:56", 0 ],
            "diff one week"  => [ "2020-02-03", "2020-02-10", 1 ],
            "diff two weeks" => [ "2020-02-03", "2020-02-17", 2 ],
        ];
    }


    #[DataProvider("providerGetHoursDiff")]
    public function testGetHoursDiff(mixed $input1, mixed $input2, int $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->getHoursDiff($d2));
    }

    public static function providerGetHoursDiff(): array {
        return [
            "both null"      => [ null, null, 0 ],
            "both empty"     => [ "", "", 0 ],
            "both invalid"   => [ "not-a-date", "not-a-date", 0 ],
            "one invalid"    => [ "2020-02-03", "not-a-date", 0 ],
            "same date"      => [ "2020-02-03", "2020-02-03", 0 ],
            "same date time" => [ "2020-02-03 11:34:56", "2020-02-03 12:34:56", 1 ],
            "diff one hour"  => [ "2020-02-03 11:00:00", "2020-02-03 12:00:00", 1 ],
            "diff two hours" => [ "2020-02-03 10:00:00", "2020-02-03 12:00:00", 2 ],
        ];
    }


    #[DataProvider("providerGetMinutesDiff")]
    public function testGetMinutesDiff(mixed $input1, mixed $input2, int $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->getMinutesDiff($d2));
    }

    public static function providerGetMinutesDiff(): array {
        return [
            "both null"        => [ null, null, 0 ],
            "both empty"       => [ "", "", 0 ],
            "both invalid"     => [ "not-a-date", "not-a-date", 0 ],
            "one invalid"      => [ "2020-02-03", "not-a-date", 0 ],
            "same date"        => [ "2020-02-03", "2020-02-03", 0 ],
            "same date time"   => [ "2020-02-03 12:00:00", "2020-02-03 12:34:00", 34 ],
            "diff one minute"  => [ "2020-02-03 12:00:00", "2020-02-03 12:01:00", 1 ],
            "diff two minutes" => [ "2020-02-03 12:00:00", "2020-02-03 12:02:00", 2 ],
        ];
    }


    #[DataProvider("providerGetSecondsDiff")]
    public function testGetSecondsDiff(mixed $input1, mixed $input2, int $expected): void {
        $d1 = Date::create($input1);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->getSecondsDiff($d2));
    }

    public static function providerGetSecondsDiff(): array {
        return [
            "both null"        => [ null, null, 0 ],
            "both empty"       => [ "", "", 0 ],
            "both invalid"     => [ "not-a-date", "not-a-date", 0 ],
            "one invalid"      => [ "2020-02-03", "not-a-date", 0 ],
            "same date"        => [ "2020-02-03", "2020-02-03", 0 ],
            "same date time"   => [ "2020-02-03 12:00:00", "2020-02-03 12:00:34", 34 ],
            "diff one second"  => [ "2020-02-03 12:00:00", "2020-02-03 12:00:01", 1 ],
            "diff two seconds" => [ "2020-02-03 12:00:00", "2020-02-03 12:00:02", 2 ],
        ];
    }


    #[DataProvider("providerGetAge")]
    public function testGetAge(mixed $input, mixed $reference, int $expected): void {
        $d = Date::create($input);
        $r = Date::create($reference);
        $this->assertSame($expected, $d->getAge($r));
    }

    public static function providerGetAge(): array {
        return [
            "null"             => [ null, null, 0 ],
            "empty"            => [ "", "", 0 ],
            "invalid"          => [ "not-a-date", "not-a-date", 0 ],
            "same date time"   => [ "2020-02-03 12:34:56", "2020-02-03 12:34:56", 0 ],
            "age zero"         => [ "2020-02-03", "2020-02-03", 0 ],
            "age one"          => [ "2019-02-03", "2020-02-03", 1 ],
            "age five"         => [ "2015-02-03", "2020-02-03", 5 ],
            "age not birthday" => [ "2015-02-04", "2020-02-03", 4 ],
            "age old"          => [ "1950-02-03", "2020-02-03", 70 ],
            "default now"      => [ "2020-02-03", null, (int)date("Y") - 2020 ],
        ];
    }


    #[DataProvider("providerFormat")]
    public function testFormat(mixed $input, string $format, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->format($format));
    }

    public static function providerFormat(): array {
        return [
            "null"     => [ null, "Y-m-d", "" ],
            "empty"    => [ "", "Y-m-d", "" ],
            "invalid"  => [ "not-a-date", "Y-m-d", "" ],
            "day"      => [ "2020-02-03", "Y-m-d", "2020-02-03" ],
            "day time" => [ "2020-02-03 12:34:56", "Y-m-d H:i:s", "2020-02-03 12:34:56" ],
        ];
    }


    #[DataProvider("providerToString")]
    public function testToString(mixed $input, DateFormat $format, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toString($format));
    }

    /**
     * One case per DateFormat, so a format added later is not left untested
     * @return array<string,array{mixed,DateFormat,string}>
     */
    public static function providerToString(): array {
        $expected = [
            "Time"           => "12:34",
            "Dashes"         => "03-02-2020",
            "DashesTime"     => "03-02-2020 12:34",
            "DashesSeconds"  => "03-02-2020 12:34:56",
            "Reverse"        => "2020-02-03",
            "ReverseTime"    => "2020-02-03 12:34",
            "ReverseSeconds" => "2020-02-03 12:34:56",
            "Slashes"        => "03/02/2020",
            "SlashesTime"    => "03/02/2020 12:34",
            "SlashesSeconds" => "03/02/2020 12:34:56",
            "Dots"           => "03.02.2020",
            "DotsTime"       => "03.02.2020 12:34",
            "DotsSeconds"    => "03.02.2020 12:34:56",
        ];

        $result = [
            "null"    => [ null, DateFormat::Time, "" ],
            "empty"   => [ "", DateFormat::Time, "" ],
            "invalid" => [ "not-a-date", DateFormat::Time, "" ],
        ];
        foreach (DateFormat::cases() as $format) {
            // A format nobody listed would otherwise go untested
            if (!isset($expected[$format->name])) {
                throw new AssertionFailedError("The DateFormat {$format->name} has no expected result");
            }
            $result[$format->name] = [ "2020-02-03 12:34:56", $format, $expected[$format->name] ];
        }
        return $result;
    }


    public function testTheJsonIsTheTimestamp(): void {
        $d = Date::create("2020-02-03 12:34:56");

        // It is what a Model sends, so it goes out as the number and not as an object
        $this->assertSame($d->toTime(), $d->jsonSerialize());
        $this->assertSame((string)$d->toTime(), json_encode($d));
    }


    #[DataProvider("providerToISOString")]
    public function testToISOString(mixed $input, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toISOString());
    }

    public static function providerToISOString(): array {
        return [
            "null"     => [ null, "" ],
            "empty"    => [ "", "" ],
            "invalid"  => [ "not-a-date", "" ],
            "day"      => [ "2020-02-03", "2020-02-03T00:00:00+01:00" ],
            "day time" => [ "2020-02-03 12:34:56", "2020-02-03T12:34:56+01:00" ],
        ];
    }


    #[DataProvider("providerToUTCString")]
    public function testToUTCString(mixed $input, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, $d->toUTCString());
    }

    public static function providerToUTCString(): array {
        return [
            "null"     => [ null, "" ],
            "empty"    => [ "", "" ],
            "invalid"  => [ "not-a-date", "" ],
            "day"      => [ "2020-02-03", "2020-02-03T00:00:00Z" ],
            "day time" => [ "2020-02-03 12:34:56", "2020-02-03T12:34:56Z" ],
        ];
    }


    #[DataProvider("providerToHourPeriodString")]
    public function testToHourPeriodString(mixed $input, mixed $input2, string $expected): void {
        $d1 = Date::create($input);
        $d2 = Date::create($input2);
        $this->assertSame($expected, $d1->toHourPeriodString($d2));
    }

    public static function providerToHourPeriodString(): array {
        return [
            "null"    => [ null, null, "" ],
            "empty"   => [ "", "", "" ],
            "invalid" => [ "not-a-date", "", "" ],
            "day"     => [ "2020-02-03", "2020-02-04", "00:00 - 00:00" ],
            "before"  => [ "2020-02-03 10:34:56", "2020-02-03 12:56:34", "10:34 - 12:56" ],
            "after"   => [ "2020-02-03 12:34:56", "2020-02-03 10:56:34", "10:56 - 12:34" ],
        ];
    }


    #[DataProvider("providerJsonSerialize")]
    public function testJsonSerialize(mixed $input, string $expected): void {
        $d = Date::create($input);
        $this->assertSame($expected, json_encode($d));
    }

    public static function providerJsonSerialize(): array {
        return [
            "null"     => [ null, "0" ],
            "empty"    => [ "", "0" ],
            "invalid"  => [ "not-a-date", "0" ],
            "day"      => [ "2020-02-03", "1580684400" ],
            "day time" => [ "2020-02-03 12:34:56", "1580729696" ],
            "old date" => [ "1920-02-03", "-1575075600" ],
            "old time" => [ "1920-02-03 12:34:56", "-1575030304" ],
        ];
    }


    public function testDateTypeName(): void {
        $this->assertSame("none", DateType::None->getName());
        $this->assertSame("start", DateType::Start->getName());
        $this->assertSame("middle", DateType::Middle->getName());
        $this->assertSame("end", DateType::End->getName());
    }
}
