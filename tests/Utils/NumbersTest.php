<?php
namespace Tests\Utils;

use Framework\Date\Date;
use Framework\Utils\Numbers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class NumbersTest extends TestCase {

    #[DataProvider("providerIsValid")]
    public function testIsValid(mixed $value, int|null $min, int|null $max, bool $expected): void {
        $this->assertSame($expected, Numbers::isValid($value, $min, $max));
    }

    public static function providerIsValid(): array {
        return [
            "valid nan"         => [ NAN, null, null, true ],
            "valid zero min"    => [ 0, 0, null, true ],
            "valid int"         => [ 5, null, null, true ],
            "valid float"       => [ 5.5, null, null, true ],
            "valid str int"     => [ "5", null, null, true ],
            "valid str float"   => [ "5.5", null, null, true ],

            "invalid alpha"     => [ "a", null, null, false ],
            "invalid null"      => [ null, null, null, false ],
            "invalid array"     => [ [], null, null, false ],
            "invalid object"    => [ new stdClass(), null, null, false ],
            "invalid true"      => [ true, null, null, false ],
            "invalid false"     => [ false, null, null, false ],
            "invalid empty"     => [ "", null, null, false ],
            "invalid space"     => [ " ", null, null, false ],

            "min check fail"    => [ 0, 1, null, false ],
            "min max ok"        => [ 5, 1, 10, true ],
            "min max fail low"  => [ 0, 1, 10, false ],
            "min max fail high" => [ 11, 1, 10, false ],
            "min max str"       => [ "10", 1, 10, true ],
        ];
    }


    #[DataProvider("providerToInt")]
    public function testToInt(mixed $value, int $decimals, int $expected): void {
        $this->assertEquals($expected, Numbers::toInt($value, $decimals));
    }

    public static function providerToInt(): array {
        return [
            "integer"              => [ 123, 0, 123 ],
            "float no decimals"    => [ 12.345, 0, 12 ],
            "float 1 decimal"      => [ 12.345, 1, 123 ],
            "float 2 decimals"     => [ 12.345, 2, 1235 ],
            "float 3 decimals"     => [ 12.345, 3, 12345 ],
            "numeric string"       => [ "123", 0, 123 ],
            "numeric string 2 dec" => [ "123.45", 2, 12345 ],
            "date instance"        => [ Date::create(1600000000), 0, 1600000000 ],
            "null"                 => [ null, 0, 0 ],
            "array"                => [ [], 0, 0 ],
            "object"               => [ new stdClass(), 0, 0 ],
            "trimmed string"       => [ " 123 ", 0, 123 ],
            "non numeric string"   => [ "123abc", 0, 0 ],
        ];
    }


    #[DataProvider("providerToFloat")]
    public function testToFloat(mixed $value, int $decimals, float $expected): void {
        $this->assertEquals($expected, Numbers::toFloat($value, $decimals));
    }

    public static function providerToFloat(): array {
        return [
            "int w decimals"  => [ 12345, 2, 123.45 ],
            "int no decimals" => [ 12345, 0, 12345 ],
            "from int"        => [ 123, 0, 123.0 ],
            "from float"      => [ 12.345, 0, 12.345 ],
            "num str"         => [ "123", 0, 123.0 ],
            "num str float"   => [ "123.45", 0, 123.45 ],
            "str w decimals"  => [ "12345", 2, 12345.0 ],
            "trimmed str"     => [ " 123.45 ", 0, 123.45 ],
            "null input"      => [ null, 0, 0 ],
            "array input"     => [ [], 0, 0 ],
            "obj input"       => [ new stdClass(), 0, 0 ],
        ];
    }


    #[DataProvider("providerToIntOrFloat")]
    public function testToIntOrFloat(mixed $value, int|float $expected, string $expectedType): void {
        $result = Numbers::toIntOrFloat($value);
        $this->assertEquals($expected, $result);
        $this->assertSame($expectedType, gettype($result));
    }

    public static function providerToIntOrFloat(): array {
        return [
            "integer input"        => [ 123, 123, "integer" ],
            "float input"          => [ 12.345, 12.345, "double" ],
            "numeric string int"   => [ "123", 123.0, "double" ],
            "numeric string float" => [ "123.45", 123.45, "double" ],
            "null input"           => [ null, 0, "integer" ],
            "array input"          => [ [], 0, "integer" ],
            "object input"         => [ new stdClass(), 0, "integer" ],
        ];
    }


    #[DataProvider("providerHasDecimals")]
    public function testHasDecimals(mixed $value, bool $expected): void {
        $this->assertSame($expected, Numbers::hasDecimals($value));
    }

    public static function providerHasDecimals(): array {
        return [
            "float with decimals" => [ 1.5, true ],
            "float no decimals"   => [ 2.0, false ],
            "integer"             => [ 1, false ],
            "zero"                => [ 0, false ],
        ];
    }


    #[DataProvider("providerLength")]
    public function testLength(int $value, int $expected): void {
        $this->assertEquals($expected, Numbers::length($value));
    }

    public static function providerLength(): array {
        return [
            "zero"         => [ 0, 1 ],
            "positive int" => [ 123, 3 ],
            "negative int" => [ -12, 3 ],
            "four digits"  => [ 1000, 4 ],
        ];
    }


    #[DataProvider("providerCompare")]
    public function testCompare(mixed $a, mixed $b, bool $orderAsc, int|float $expected): void {
        $this->assertEquals($expected, Numbers::compare($a, $b, $orderAsc));
    }

    public static function providerCompare(): array {
        return [
            "integers ascending"  => [ 5, 3, true, 2 ],
            "integers descending" => [ 3, 5, true, -2 ],
            "floats ascending"    => [ 5.5, 3.2, true, 2.3 ],
            "floats descending"   => [ 3.2, 5.5, true, -2.3 ],
            "mixed ascending"     => [ 5, 3.5, true, 1.5 ],
            "mixed descending"    => [ 3.5, 5, true, -1.5 ],
            "desc order 5 3"      => [ 5, 3, false, -2 ],
            "desc order 3 5"      => [ 3, 5, false, 2 ],
            "desc order 5.5 3.2"  => [ 5.5, 3.2, false, -2.3 ],
            "desc order 3.2 5.5"  => [ 3.2, 5.5, false, 2.3 ],
        ];
    }


    #[DataProvider("providerRound")]
    public function testRound(mixed $value, int $decimals, float $expected): void {
        $this->assertEquals($expected, Numbers::round($value, $decimals));
    }

    public static function providerRound(): array {
        return [
            "integer input"      => [ 5, 2, 5.0 ],
            "rounding down"      => [ 1.234, 2, 1.23 ],
            "rounding up"        => [ 1.235, 2, 1.24 ],
            "three decimals"     => [ 1.2345, 3, 1.235 ],
            "zero decimals down" => [ 1.4, 0, 1.0 ],
            "zero decimals up"   => [ 1.6, 0, 2.0 ],
        ];
    }


    #[DataProvider("providerRoundInt")]
    public function testRoundInt(mixed $value, bool $useFloor, int $expected): void {
        $this->assertSame($expected, Numbers::roundInt($value, $useFloor));
    }

    public static function providerRoundInt(): array {
        return [
            "integer input"          => [ 5, false, 5 ],
            "rounding down"          => [ 1.4, false, 1 ],
            "rounding up"            => [ 1.6, false, 2 ],
            "negative rounding down" => [ -1.4, false, -1 ],
            "negative rounding up"   => [ -1.6, false, -2 ],
            "floor positive"         => [ 1.9, true, 1 ],
            "floor negative"         => [ -1.1, true, -2 ],
        ];
    }


    public function testRandom(): void {
        // default length (8)
        $r = Numbers::random();
        $this->assertIsInt($r);
        $this->assertEquals(8, Numbers::length($r));

        // various lengths: check several samples to avoid flakiness
        foreach ([ 1, 2, 5, 8 ] as $len) {
            for ($i = 0; $i < 5; $i++) {
                $val = Numbers::random($len);
                $this->assertIsInt($val);
                $this->assertEquals($len, Numbers::length($val));
            }
        }
    }


    #[DataProvider("providerFormatInt")]
    public function testFormatInt(mixed $value, int $decimals, int $maxForDecimals, string $default, string $expected): void {
        $this->assertEquals($expected, Numbers::formatInt($value, $decimals, $maxForDecimals, $default));
    }

    public static function providerFormatInt(): array {
        return [
            "simple decimals"        => [ 12345, 2, 1000, "", "123,45" ],
            "zero returns default"   => [ 0, 2, 1000, "-", "-" ],
            "thousands sep decimals" => [ 1234567, 2, 1000, "", "12.346" ],
            "no decimals"            => [ 123, 0, 1000, "", "123" ],
            "more decimals"          => [ 12345, 3, 1000, "", "12,345" ],
            "float basic"            => [ 123.45, 2, 1000, "", "123,45" ],
            "float large"            => [ 1234.5, 2, 1000, "", "1.235" ],
            "float rounding"         => [ 123.99, 0, 1000, "", "124" ],
            "float large no dec"     => [ 12345.6, 0, 1000, "", "12.346" ],
            "large drops decimals"   => [ 1234500, 2, 1000, "", "12.345" ],
            "max zero keeps dec"     => [ 1234500, 2, 0, "", "12.345,00" ],
            "max 1000"               => [ 1234500, 2, 1000, "", "12.345" ],
            "max 100k"               => [ 1234500, 2, 100000, "", "12.345,00" ],
            "max equals val"         => [ 1234500, 2, 12345, "", "12.345" ],
            "max greater val"        => [ 1234500, 2, 12346, "", "12.345,00" ],
            "small max 1k"           => [ 123450, 2, 1000, "", "1.235" ],
            "small max 10k"          => [ 123450, 2, 10000, "", "1.234,50" ],
            "small max 1234"         => [ 123450, 2, 1234, "", "1.235" ],
            "small max 1235"         => [ 123450, 2, 1235, "", "1.234,50" ],
        ];
    }


    #[DataProvider("providerFormatFloat")]
    public function testFormatFloat(mixed $value, int $decimals, int $maxForDecimals, string $default, string $decimalSeparator, string $thousandsSeparator, string $expected): void {
        $this->assertEquals($expected, Numbers::formatFloat($value, $decimals, $maxForDecimals, $default, $decimalSeparator, $thousandsSeparator));
    }

    public static function providerFormatFloat(): array {
        return [
            "normal preserves dec" => [ 12.345, 2, 1000, "", ",", ".", "12,35" ],
            "exceeds max"          => [ 1234.56, 2, 1000, "", ",", ".", "1.235" ],
            "max zero keeps dec"   => [ 1234.56, 2, 0, "", ",", ".", "1.234,56" ],
            "int no dec"           => [ 123, 2, 1000, "", ",", ".", "123" ],
            "diff decimals"        => [ 12.3456, 3, 1000, "", ",", ".", "12,346" ],
            "below max keeps dec"  => [ 999.99, 2, 1000, "", ",", ".", "999,99" ],
            "at max drops dec"     => [ 1000.0, 2, 1000, "", ",", ".", "1.000" ],
            "above max drops dec"  => [ 1234.56, 2, 1000, "", ",", ".", "1.235" ],
            "large max keeps dec"  => [ 1234.56, 2, 10000, "", ",", ".", "1.234,56" ],
            "zero default"         => [ 0, 2, 1000, "-", ",", ".", "-" ],
            "custom seps"          => [ 1234.56, 2, 0, "", ".", ",", "1,234.56" ],
            "large int thousands"  => [ 1234567, 0, 1000, "", ",", ".", "1.234.567" ],
        ];
    }


    #[DataProvider("providerClampInt")]
    public function testClampInt(mixed $value, int $min, int $max, int $expected): void {
        $this->assertEquals($expected, Numbers::clampInt($value, $min, $max));
    }

    public static function providerClampInt(): array {
        return [
            "below min"      => [ 0, 1, 5, 1 ],
            "above max"      => [ 10, 1, 5, 5 ],
            "within range"   => [ 3, 1, 5, 3 ],
            "equal to min"   => [ 1, 1, 5, 1 ],
            "equal to max"   => [ 5, 1, 5, 5 ],
            "negative below" => [ -3, -2, 2, -2 ],
            "negative above" => [ 3, -2, 2, 2 ],
        ];
    }


    #[DataProvider("providerClampFloat")]
    public function testClampFloat(mixed $value, float $min, float $max, float $expected): void {
        $this->assertEquals($expected, Numbers::clampFloat($value, $min, $max));
    }

    public static function providerClampFloat(): array {
        return [
            "below min"         => [ 0.5, 1.0, 3.0, 1.0 ],
            "above max"         => [ 4.2, 1.0, 3.0, 3.0 ],
            "within range"      => [ 2.5, 1.0, 3.0, 2.5 ],
            "equal to min"      => [ 1.0, 1.0, 3.0, 1.0 ],
            "equal to max"      => [ 3.0, 1.0, 3.0, 3.0 ],
            "negative below"    => [ -3.0, -2.0, 2.0, -2.0 ],
            "negative above"    => [ 3.5, -2.0, 2.0, 2.0 ],
            "decimals in range" => [ 1.25, 1.0, 2.0, 1.25 ],
        ];
    }


    #[DataProvider("providerMap")]
    public function testMap(mixed $value, int|float $fromLow, int|float $fromHigh, int|float $toLow, int|float $toHigh, int|float $expected): void {
        $this->assertEquals($expected, Numbers::map($value, $fromLow, $fromHigh, $toLow, $toHigh));
    }

    public static function providerMap(): array {
        return [
            "simple mapping"         => [ 5, 0, 10, 0, 100, 50 ],
            "different output range" => [ 5, 0, 10, 100, 200, 150 ],
            "fractional inputs"      => [ 2.5, 0, 5, 0, 10, 5 ],
            "negative ranges"        => [ -5, -10, 0, 0, 100, 50 ],
            "reversed output range"  => [ 15, 10, 20, 1, 0, 0.5 ],
            "value at low bound"     => [ 10, 10, 20, 0, 1, 0 ],
            "value at high bound"    => [ 20, 10, 20, 0, 1, 1 ],
            "from range zero"        => [ 0, 0, 0, 10, 20, 10 ],
        ];
    }


    #[DataProvider("providerPercent")]
    public function testPercent(mixed $numerator, mixed $total, int $decimals, int|float $expected): void {
        $this->assertEquals($expected, Numbers::percent($numerator, $total, $decimals));
    }

    public static function providerPercent(): array {
        return [
            "basic percent"           => [ 2, 5, 0, 40 ],
            "zero total"              => [ 5, 0, 0, 0 ],
            "simple fraction quarter" => [ 1, 4, 0, 25 ],
            "decimals 2 one third"    => [ 1, 3, 2, 33.33 ],
            "decimals 2 two thirds"   => [ 2, 3, 2, 66.67 ],
            "decimals 3 one third"    => [ 1, 3, 3, 33.333 ],
            "negative numerator"      => [ -1, 4, 0, -25 ],
            "negative with decimals"  => [ -1, 3, 2, -33.33 ],
            "zero numerator"          => [ 0, 100, 0, 0 ],
            "zero numerator float"    => [ 0.0, 100.0, 0, 0 ],
            "zero both"               => [ 0, 0, 0, 0 ],
            "mixed float int 1"       => [ 2.0, 5, 0, 40 ],
            "mixed int float 1"       => [ 2, 5.0, 0, 40 ],
            "mixed both float"        => [ 2.0, 5.0, 0, 40 ],
        ];
    }


    #[DataProvider("providerDivide")]
    public function testDivide(mixed $numerator, mixed $divisor, int $decimals, float $expected): void {
        $result = Numbers::divide($numerator, $divisor, $decimals);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public static function providerDivide(): array {
        return [
            "int div decimals" => [ 5, 2, 2, 2.5 ],
            "exact div"        => [ 4, 2, 0, 2.0 ],
            "float 1 dec"      => [ 5.0, 2.0, 1, 2.5 ],
            "float 2 dec"      => [ 7.0, 3.0, 2, 2.33 ],
            "round 2 dec"      => [ 7, 3, 2, 2.33 ],
            "round 3 dec"      => [ 7, 3, 3, 2.333 ],
            "zero num"         => [ 0, 5, 0, 0.0 ],
            "zero div"         => [ 5, 0, 0, 0.0 ],
            "neg num 1 dec"    => [ -5, 2, 1, -2.5 ],
            "neg num 2 dec"    => [ -7, 3, 2, -2.33 ],
        ];
    }


    #[DataProvider("providerDivideInt")]
    public function testDivideInt(mixed $numerator, mixed $divisor, bool $useFloor, int $expected): void {
        $result = Numbers::divideInt($numerator, $divisor, $useFloor);
        $this->assertIsInt($result);
        $this->assertEquals($expected, $result);
    }

    public static function providerDivideInt(): array {
        return [
            "round 5 2"        => [ 5, 2, false, 3 ],
            "floor 5 2"        => [ 5, 2, true, 2 ],
            "exact 10 2"       => [ 10, 2, false, 5 ],
            "exact 10 2 floor" => [ 10, 2, true, 5 ],
            "round 10 3"       => [ 10, 3, false, 3 ],
            "floor 10 3"       => [ 10, 3, true, 3 ],
            "round 9 4"        => [ 9, 4, false, 2 ],
            "floor 9 4"        => [ 9, 4, true, 2 ],
            "round 11 4"       => [ 11, 4, false, 3 ],
            "floor 7 4"        => [ 7, 4, true, 1 ],
            "round 7 4"        => [ 7, 4, false, 2 ],
            "zero num"         => [ 0, 5, false, 0 ],
            "zero div"         => [ 5, 0, false, 0 ],
            "zero both"        => [ 0, 0, false, 0 ],
            "neg num"          => [ -5, 2, false, -3 ],
            "neg num floor"    => [ -5, 2, true, -3 ],
            "neg div"          => [ 5, -2, false, -3 ],
            "neg div floor"    => [ 5, -2, true, -3 ],
        ];
    }


    #[DataProvider("providerApplyDiscount")]
    public function testApplyDiscount(mixed $value, int|float $percent, int|float $expected): void {
        $this->assertEquals($expected, Numbers::applyDiscount($value, $percent));
    }

    public static function providerApplyDiscount(): array {
        return [
            "simple discount"      => [ 100, 10, 90 ],
            "zero pct"             => [ 100, 0, 100 ],
            "zero pct float"       => [ 100, 0.0, 100 ],
            "full discount"        => [ 100, 100, 0 ],
            "discount over 100"    => [ 100, 150, 0 ],
            "decimal pct"          => [ 101, 5.5, 95.445 ],
            "neg discount clamped" => [ 100, -10, 100 ],
            "zero val discount"    => [ 0, 50, 0 ],
            "zero val float disc"  => [ 0.0, 50, 0.0 ],
        ];
    }


    #[DataProvider("providerApplyIncrement")]
    public function testApplyIncrement(mixed $value, int|float $percent, int|float $expected, float $delta = 0.0001): void {
        if ($delta > 0) {
            $this->assertEqualsWithDelta($expected, Numbers::applyIncrement($value, $percent), $delta);
        } else {
            $this->assertEquals($expected, Numbers::applyIncrement($value, $percent));
        }
    }

    public static function providerApplyIncrement(): array {
        return [
            "zero percent"     => [ 100, 0, 100, 0 ],
            "negative percent" => [ 100, -10, 100, 0 ],
            "nine percent"     => [ 100, 9, 109.8901098901, 0.0001 ],
            "ten percent"      => [ 100, 10, 111.1111111111, 0.0001 ],
            "decimal percent"  => [ 200, 2.5, 205.1282051282, 0.0001 ],
            "zero price"       => [ 0, 50, 0, 0 ],
            "mixed int float"  => [ 10, 50, 20, 0.0001 ],
        ];
    }


    #[DataProvider("providerAddTax")]
    public function testAddTax(int|float $value, int|float $percent, int|float $expected): void {
        $this->assertEqualsWithDelta($expected, Numbers::addTax($value, $percent), 0.0001);
    }

    public static function providerAddTax(): array {
        return [
            "vat"              => [ 274.56, 21, 332.2176 ],
            "ten percent"      => [ 100, 10, 110 ],
            "decimal percent"  => [ 200, 2.5, 205 ],
            "zero percent"     => [ 100, 0, 100 ],
            "negative percent" => [ 100, -10, 100 ],
            "over 100 percent" => [ 100, 150, 250 ],
            "zero price"       => [ 0, 21, 0 ],
        ];
    }


    #[DataProvider("providerGetCommonDivisor")]
    public function testGetCommonDivisor(int $a, int $b, int $expected): void {
        $this->assertEquals($expected, Numbers::getCommonDivisor($a, $b));
    }

    public static function providerGetCommonDivisor(): array {
        return [
            "integers 48 18" => [ 48, 18, 6 ],
            "integers 17 13" => [ 17, 13, 1 ],
            "integers 60 48" => [ 60, 48, 12 ],
            "zero a"         => [ 0, 5, 5 ],
            "zero b"         => [ 5, 0, 5 ],
            "zero both"      => [ 0, 0, 0 ],
        ];
    }


    #[DataProvider("providerIsValidFloat")]
    public function testIsValidFloat(mixed $value, int $min, ?int $max, int $decimals, bool $expected): void {
        $this->assertSame($expected, Numbers::isValidFloat($value, $min, $max, $decimals));
    }

    public static function providerIsValidFloat(): array {
        return [
            "valid 1 23 dec 2"        => [ 1.23, 1, null, 2, true ],
            "invalid 1 234 dec 2"     => [ 1.234, 1, null, 2, false ],
            "valid int dec 0"         => [ 5, 1, 10, 0, true ],
            "invalid int below min"   => [ 0, 1, 10, 0, false ],
            "valid 1 2 dec 1"         => [ 1.2, 1, 2, 1, true ],
            "invalid 0 5 below min"   => [ 0.5, 1, null, 1, false ],
            "invalid 1 2345 dec 3"    => [ 1.2345, 1, null, 3, false ],
            "valid 100 00 in range"   => [ 100.00, 1, 200, 2, true ],
            "invalid 201 0 above max" => [ 201.0, 1, 200, 1, false ],
            "valid eq min max"        => [ 5, 5, 5, 0, true ],
            "invalid 5 1 not eq"      => [ 5.1, 5, 5, 1, false ],
        ];
    }


    #[DataProvider("providerIsValidPrice")]
    public function testIsValidPrice(mixed $value, int $min, ?int $max, bool $expected): void {
        $this->assertSame($expected, Numbers::isValidPrice($value, $min, $max));
    }

    public static function providerIsValidPrice(): array {
        return [
            "valid 1 23"             => [ 1.23, 1, null, true ],
            "invalid 0 99"           => [ 0.99, 1, null, false ],
            "invalid 1 234"          => [ 1.234, 1, null, false ],
            "valid 100 in range"     => [ 100.00, 1, 200, true ],
            "invalid 201 above max"  => [ 201.00, 1, 200, false ],
            "valid int at max"       => [ 5, 1, 5, true ],
            "invalid 5 01 above max" => [ 5.01, 1, 5, false ],
            "valid zero allowed"     => [ 0, 0, 100, true ],
            "invalid 0 01 above min" => [ 0.01, 1, 100, false ],
        ];
    }


    #[DataProvider("providerRoundCents")]
    public function testRoundCents(mixed $value, float|int $expected): void {
        $this->assertEquals($expected, Numbers::roundCents($value));
    }

    public static function providerRoundCents(): array {
        return [
            "rounding down"          => [ 1.234, 1.23 ],
            "rounding up at 5"       => [ 1.235, 1.24 ],
            "small values to zero"   => [ 0.004, 0 ],
            "negative rounding down" => [ -1.234, -1.23 ],
            "negative rounding up"   => [ -1.235, -1.24 ],
            "integer input"          => [ 123, 123.0 ],
            "cross integer boundary" => [ 1.999, 2.0 ],
            "preserve one decimal"   => [ 1.2, 1.2 ],
        ];
    }


    #[DataProvider("providerToCents")]
    public function testToCents(mixed $value, int $expected): void {
        $this->assertEquals($expected, Numbers::toCents($value));
    }

    public static function providerToCents(): array {
        return [
            "basic float"         => [ 12.34, 1234 ],
            "rounding extra dec"  => [ 12.345, 1235 ],
            "integer input"       => [ 12, 1200 ],
            "negative float"      => [ -1.23, -123 ],
            "negative rounding"   => [ -1.235, -124 ],
            "numeric string"      => [ "12.34", 1234 ],
            "trimmed string"      => [ " 12.34 ", 1234 ],
            "numeric string int"  => [ "12", 1200 ],
            "null input"          => [ null, 0 ],
            "array input"         => [ [], 0 ],
            "object input"        => [ new stdClass(), 0 ],
            "non numeric string"  => [ "abc", 0 ],
        ];
    }


    #[DataProvider("providerFromCents")]
    public function testFromCents(mixed $value, float $expected): void {
        $this->assertEquals($expected, Numbers::fromCents($value));
    }

    public static function providerFromCents(): array {
        return [
            "integer cents"          => [ 1234, 12.34 ],
            "float input"            => [ 1234.0, 1234.0 ],
            "numeric string decimal" => [ "12.34", 12.34 ],
            "numeric string no dec"  => [ "1234", 1234.0 ],
            "trimmed string"         => [ " 12.34 ", 12.34 ],
            "negative int"           => [ -1234, -12.34 ],
            "negative string"        => [ "-1234", -1234.0 ],
            "null input"             => [ null, 0.0 ],
            "array input"            => [ [], 0.0 ],
            "object input"           => [ new stdClass(), 0.0 ],
            "non numeric string"     => [ "abc", 0.0 ],
        ];
    }


    #[DataProvider("providerFormatPrice")]
    public function testFormatPrice(mixed $value, int $decimals, int $maxForDecimals, string $default, string $expected): void {
        $this->assertEquals($expected, Numbers::formatPrice($value, $decimals, $maxForDecimals, $default));
    }

    public static function providerFormatPrice(): array {
        return [
            "norm 2dec"           => [ 123.5, 2, 1000, "0", "123,50" ],
            "zero default"        => [ 0, 2, 1000, "0", "0" ],
            "zero custom default" => [ 0, 2, 1000, "-", "-" ],
            "large drop dec"      => [ 1234567.891, 2, 1000, "0", "1.234.568" ],
            "neg values"          => [ -12.345, 2, 1000, "0", "-12,35" ],
            "diff dec"            => [ 12.3456, 3, 1000, "0", "12,346" ],
            "int no dec"          => [ 123, 2, 1000, "0", "123" ],
            "max below"           => [ 999.99, 2, 1000, "0", "999,99" ],
            "max at threshold"    => [ 1000.0, 2, 1000, "0", "1.000" ],
        ];
    }


    #[DataProvider("providerFormatCents")]
    public function testFormatCents(mixed $value, int $decimals, int $maxForDecimals, string $default, string $expected): void {
        $this->assertEquals($expected, Numbers::formatCents($value, $decimals, $maxForDecimals, $default));
    }

    public static function providerFormatCents(): array {
        return [
            "basic cents"          => [ 1234, 2, 1000, "0", "12,34" ],
            "small cents"          => [ 100, 2, 1000, "0", "1,00" ],
            "zero cents"           => [ 0, 2, 1000, "0", "0" ],
            "zero custom default"  => [ 0, 2, 1000, "-", "-" ],
            "negative cents"       => [ -1234, 2, 1000, "0", "-12,34" ],
            "thousands separator"  => [ 123456, 2, 1000, "0", "1.235" ],
            "one decimal"          => [ 1234, 1, 1000, "0", "12,3" ],
            "three decimals"       => [ 123456, 3, 1000, "0", "1.235" ],
            "numeric string input" => [ "1234", 2, 1000, "0", "12,34" ],
        ];
    }


    #[DataProvider("providerToPriceString")]
    public function testToPriceString(mixed $value, string $expected): void {
        $this->assertEquals($expected, Numbers::toPriceString($value));
    }

    public static function providerToPriceString(): array {
        return [
            "small int"          => [ 123, "$123" ],
            "small float"        => [ 123.4, "$123" ],
            "threshold at 10000" => [ 10000, "$10000" ],
            "kilos above 10"     => [ 10500, "$11k" ],
            "kilos exact 12000"  => [ 12000, "$12k" ],
            "millions above 10"  => [ 12345678, "$12m" ],
        ];
    }


    #[DataProvider("providerToBytesString")]
    public function testToBytesString(mixed $value, bool $inGigas, string $expected): void {
        $this->assertEquals($expected, Numbers::toBytesString($value, $inGigas));
    }

    public static function providerToBytesString(): array {
        return [
            "bytes 512 mb"     => [ 512, false, "512 MB" ],
            "bytes 1024 gb"    => [ 1024, false, "1 GB" ],
            "bytes 2048 gb"    => [ 2048, false, "2 GB" ],
            "bytes 1048576 tb" => [ 1024 * 1024, false, "1 TB" ],
            "gigas 1 gb"       => [ 1, true, "1 GB" ],
            "gigas 2048 tb"    => [ 2048, true, "2 TB" ],
            "gigas 500 gb"     => [ 500, true, "500 GB" ],
        ];
    }


    #[DataProvider("providerZerosPad")]
    public function testZerosPad(mixed $value, int $length, string $expected): void {
        $this->assertEquals($expected, Numbers::zerosPad($value, $length));
    }

    public static function providerZerosPad(): array {
        return [
            "pad integers"               => [ 5, 3, "005" ],
            "pad floats"                 => [ 12.34, 6, "012.34" ],
            "negative number"            => [ -5, 3, "0-5" ],
            "amount smaller than length" => [ 1234, 3, "1234" ],
            "pad zero"                   => [ 0, 4, "0000" ],
        ];
    }


    #[DataProvider("providerCoordinatesDistance")]
    public function testCoordinatesDistance(float $lat1, float $lon1, float $lat2, float $lon2, float $expected, float $delta): void {
        $this->assertEqualsWithDelta($expected, Numbers::coordinatesDistance($lat1, $lon1, $lat2, $lon2), $delta);
    }

    public static function providerCoordinatesDistance(): array {
        return [
            "same point"           => [ 0, 0, 0, 0, 0, 0 ],
            "one degree longitude" => [ 0, 0, 0, 1, 111.32, 1 ],
            "paris london"         => [ 48.8566, 2.3522, 51.5074, -0.1278, 343, 10 ],
            "new york london"      => [ 40.7128, -74.0060, 51.5074, -0.1278, 5570, 50 ],
            "symmetry ab"          => [ 40.7128, -74.0060, 51.5074, -0.1278, 5570, 50 ],
            "symmetry ba"          => [ 51.5074, -0.1278, 40.7128, -74.0060, 5570, 50 ],
        ];
    }


    #[DataProvider("providerCalcExpression")]
    public function testCalcExpression(string $expression, int|float $expected): void {
        $this->assertEquals($expected, Numbers::calcExpression($expression));
    }

    public static function providerCalcExpression(): array {
        return [
            "basic arithmetic"          => [ "2+3*4", 14 ],
            "text mixed with numbers"   => [ "a2+3b*4c", 14 ],
            "text mixed with spaces"    => [ " 2 + abc3*4 ", 14 ],
            "floor with parentheses"    => [ "floor(2.7)", 2 ],
            "floor without parentheses" => [ "floor2.7", 2 ],
            "floor with space"          => [ "floor 2.7", 2 ],
            "ceil with parentheses"     => [ "ceil(2.1)", 3 ],
            "round with parentheses"    => [ "round(1.6)", 2 ],
            "function name with text"   => [ "floor abc2.9", 2 ],
            "percent basic"             => [ "100*5%", 5 ],
            "percent 200"               => [ "200*10%", 20 ],
            "percent negative 1"        => [ "100*-5%", -5 ],
            "percent negative 2"        => [ "200*-10%", -20 ],
            "comma decimal separator"   => [ "1,5+2,5", 4 ],
            "plus minus becomes minus"  => [ "10+-5", 5 ],
            "minus minus becomes plus"  => [ "10--5", 15 ],
            "power operator"            => [ "10**5", 100_000 ],
            "backslash removed"         => [ "2\\+3", 5 ],
            "empty expression"          => [ "", 0 ],
            "invalid abc"               => [ "abc", 0 ],
            "invalid operator sequence" => [ "2+*3", 0 ],
            "invalid function argument" => [ "round(abc)", 0 ],
        ];
    }
}
