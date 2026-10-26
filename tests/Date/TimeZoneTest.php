<?php
namespace Tests\Date;

use Framework\Date\TimeZone;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TimeZoneTest extends TestCase {
    use TestHelpers;

    protected function setUp(): void {
        // reset internal static state before each test
        $this->setPrivateStaticProperty(TimeZone::class, "stackZones", []);
        $this->setPrivateStaticProperty(TimeZone::class, "serverZone", -3.0);
        $this->setPrivateStaticProperty(TimeZone::class, "timeDiff", 0.0);
    }


    #[DataProvider("setTimeZoneProvider")]
    public function testSetTimeZone(float $input, float $expected): void {
        $result = TimeZone::setTimeZone($input);
        $this->assertSame($expected, $result);
    }

    public static function setTimeZoneProvider(): array {
        return [
            // The Time Zone can come in minutes or in hours, and the server is at -3
            "minutes"           => [ -180.0, 0.0 ],
            "hours"             => [ -3.0, 0.0 ],
            "one hour in mins"  => [ 60.0, -4.0 ],
            "half hour in hrs"  => [ 5.5, -8.5 ],
            "utc"               => [ 0.0, -3.0 ],
        ];
    }


    #[DataProvider("pushPopProvider")]
    public function testPushAndPopTimeZone(array $initialStack, float $pushValue, float $expectedAfterPush, float $expectedAfterPop): void {
        // set initial stack
        $this->setPrivateStaticProperty(TimeZone::class, "stackZones", $initialStack);

        $extraPop = TimeZone::popTimeZone();
        $this->assertSame(0.0, $extraPop);

        $afterPush = TimeZone::pushTimeZone($pushValue);
        $this->assertSame($expectedAfterPush, $afterPush);

        $afterPop = TimeZone::popTimeZone();
        $this->assertSame($expectedAfterPop, $afterPop);
    }

    public static function pushPopProvider(): array {
        return [
            "empty stack" => [ [], -180.0, 0.0, 0.0 ],
            "one item"    => [ [ -3.0 ], -120.0, -1.0, -1.0 ],
            "one hour"    => [ [ -3.0 ], 60.0, -4.0, -4.0 ],
        ];
    }


    #[DataProvider("currentDiffProvider")]
    public function testGetCurrentTimeDiff(float $value): void {
        $this->setPrivateStaticProperty(TimeZone::class, "timeDiff", $value);
        $this->assertSame($value, TimeZone::getCurrentTimeDiff());
    }

    public static function currentDiffProvider(): array {
        return [
            "zero"     => [ 0.0 ],
            "negative" => [ -2.5 ],
        ];
    }


    #[DataProvider("calcProvider")]
    public function testCalcTimeDiff(float $input, float $expected): void {
        $this->assertSame($expected, TimeZone::calcTimeDiff($input));
    }

    public static function calcProvider(): array {
        return [
            "same as server" => [ -3.0, 0.0 ],
            "zero"           => [ 0.0, -3.0 ],
            "positive"       => [ 2.5, -5.5 ],
        ];
    }


    #[DataProvider("toUserProvider")]
    public function testToUserTime(float $timeDiff, int $input, bool $useTimeZone, int $expected): void {
        $this->setPrivateStaticProperty(TimeZone::class, "timeDiff", $timeDiff);
        $this->assertSame($expected, TimeZone::toUserTime($input, $useTimeZone));
    }

    public static function toUserProvider(): array {
        return [
            "zero value"  => [ 1.0, 0, true, 0 ],
            "apply diff"  => [ -1.0, 3600, true, 7200 ],
            "ignore zone" => [ -1.0, 3600, false, 3600 ],
        ];
    }


    #[DataProvider("toServerProvider")]
    public function testToServerTime(float $timeDiff, int $input, bool $useTimeZone, int $expected): void {
        $this->setPrivateStaticProperty(TimeZone::class, "timeDiff", $timeDiff);
        $this->assertSame($expected, TimeZone::toServerTime($input, $useTimeZone));
    }

    public static function toServerProvider(): array {
        return [
            "zero value"  => [ 1.0, 0, true, 0 ],
            "apply diff"  => [ -1.0, 3600, true, 0 ],
            "ignore zone" => [ -1.0, 3600, false, 3600 ],
        ];
    }


    #[DataProvider("toStringProvider")]
    public function testToString(float $input, string $expected): void {
        $this->assertSame($expected, TimeZone::toString($input));
    }

    public static function toStringProvider(): array {
        return [
            "negative hours" => [ -3.0, "GMT -3:00" ],
            "positive half"  => [ 5.5, "GMT +5:30" ],
            "zero"           => [ 0.0, "GMT +0:00" ],
        ];
    }
}
