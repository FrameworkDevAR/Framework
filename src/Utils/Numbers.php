<?php
namespace Framework\Utils;

use Framework\Date\Date;
use Framework\Utils\Strings;

use Throwable;

/**
 * Several Numbers Utils
 */
class Numbers {

    /**
     * Returns true if the given value is a number and greater and/or equal to cero
     * @param mixed    $number
     * @param int|null $min    Optional.
     * @param int|null $max    Optional.
     * @return bool
     */
    public static function isValid(
        mixed $number,
        ?int $min = 1,
        ?int $max = null,
    ): bool {
        if (!is_numeric($number)) {
            return false;
        }
        if ($min !== null && $number < $min) {
            return false;
        }
        if ($max !== null && $number > $max) {
            return false;
        }
        return true;
    }

    /**
     * Returns the given number as an integer using the given decimals
     * @param mixed $value
     * @param int   $decimals Optional.
     * @return int
     */
    public static function toInt(mixed $value, int $decimals = 0): int {
        if ($value instanceof Date) {
            return $value->toTime();
        }
        if (is_numeric($value)) {
            $padding = pow(10, $decimals);
            return self::roundInt($value * $padding);
        }
        return 0;
    }

    /**
     * Returns the given number as a float using the given decimals
     * @param mixed $value
     * @param int   $decimals Optional.
     * @return float
     */
    public static function toFloat(mixed $value, int $decimals = 0): float {
        if (is_int($value)) {
            $padding = pow(10, $decimals);
            return $value / $padding;
        }
        if (is_float($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return floatval($value);
        }
        return 0;
    }

    /**
     * Returns the given number as an integer or a float
     * @param mixed $number
     * @return int|float
     */
    public static function toIntOrFloat(mixed $number): int|float {
        if (is_int($number)) {
            return $number;
        }
        if (is_float($number)) {
            return $number;
        }
        if (is_numeric($number)) {
            return floatval($number);
        }
        return 0;
    }


    /**
     * Returns true if the given number has decimals
     * @param int $number
     * @return bool
     */
    public static function hasDecimals(int|float $number): bool {
        return is_float($number) && floor($number) !== $number;
    }

    /**
     * Returns the length og the given Number
     * @param int $number
     * @return int
     */
    public static function length(int $number): int {
        return strlen((string)$number);
    }

    /**
     * Returns
     *   < 0 if number is less than other.
     *   > 0 if number is greater than other.
     *   0 if they are equal.
     * @param int|float $number
     * @param int|float $other
     * @param bool      $orderAsc Optional.
     * @return int|float
     */
    public static function compare(
        int|float $number,
        int|float $other,
        bool $orderAsc = true,
    ): int|float {
        return ($number - $other) * ($orderAsc ? 1 : -1);
    }

    /**
     * Rounds the given number to the given decimals
     * @param int|float $number
     * @param int       $decimals
     * @return float
     */
    public static function round(int|float $number, int $decimals): float {
        if (is_int($number)) {
            return (float)$number;
        }
        $padding = pow(10, $decimals);
        return round($number * $padding) / $padding;
    }

    /**
     * Rounds the given number to the nearest integer
     * @param int|float $number
     * @param bool      $useFloor Optional.
     * @param bool      $useCeil  Optional.
     * @return int
     */
    public static function roundInt(
        int|float $number,
        bool $useFloor = false,
        bool $useCeil = false,
    ): int {
        if (is_int($number)) {
            return $number;
        }
        if ($useFloor) {
            return (int)floor($number);
        }
        if ($useCeil) {
            return (int)ceil($number);
        }
        return (int)round($number);
    }

    /**
     * Returns a number with the given length
     * @param int $length Optional.
     * @return int
     */
    public static function random(int $length = 8): int {
        $min = (int)pow(10, $length - 1);
        $max = (int)pow(10, $length) - 1;

        // random_int is used as rand draws from the Mt19937 stream that each
        // process seeds with 32 bits, and the only caller is a WhatsApp pin
        return random_int($min, $max);
    }



    /**
     * Returns a number using the right format
     * @param int|float $number
     * @param int       $decimals       Optional.
     * @param int       $maxForDecimals Optional.
     * @param string    $default        Optional.
     * @return string
     */
    public static function formatInt(
        int|float $number,
        int $decimals = 0,
        int $maxForDecimals = 1000,
        string $default = "",
    ): string {
        $float = $decimals > 0 ? self::toFloat($number, $decimals) : $number;
        return self::formatFloat($float, $decimals, $maxForDecimals, $default);
    }

    /**
     * Returns a number using the right format
     * @param int|float $number
     * @param int       $decimals
     * @param int       $maxForDecimals     Optional.
     * @param string    $default            Optional.
     * @param string    $decimalSeparator   Optional.
     * @param string    $thousandsSeparator Optional.
     * @return string
     */
    public static function formatFloat(
        int|float $number,
        int $decimals,
        int $maxForDecimals = 1000,
        string $default = "",
        string $decimalSeparator = ",",
        string $thousandsSeparator = ".",
    ): string {
        $float = floatval($number);
        if ($float === 0.0) {
            return $default;
        }

        if (($maxForDecimals !== 0 && $float >= $maxForDecimals) || is_int($number)) {
            $decimals = 0;
        }
        return number_format($float, $decimals, $decimalSeparator, $thousandsSeparator);
    }



    /**
     * Clamps the given number between the min and max
     * @param int $number
     * @param int $min
     * @param int $max
     * @return int
     */
    public static function clampInt(int $number, int $min, int $max): int {
        return max($min, min($max, $number));
    }

    /**
     * Clamps the given number between the min and max
     * @param float $number
     * @param float $min
     * @param float $max
     * @return float
     */
    public static function clampFloat(float $number, float $min, float $max): float {
        return max($min, min($max, $number));
    }

    /**
     * Maps the given number that is in the from range to the to range
     * @param int|float $number
     * @param int|float $fromLow
     * @param int|float $fromHigh
     * @param int|float $toLow
     * @param int|float $toHigh
     * @return int|float
     */
    public static function map(
        int|float $number,
        int|float $fromLow,
        int|float $fromHigh,
        int|float $toLow,
        int|float $toHigh,
    ): int|float {
        $fromRange = $fromHigh - $fromLow;
        $toRange   = $toHigh - $toLow;
        if ($fromRange === 0) {
            return $toLow;
        }
        $scaleFactor = $toRange / $fromRange;

        // Re-zero the value within the from range
        $tmpValue = $number - $fromLow;
        // Rescale the value to the to range
        $tmpValue *= $scaleFactor;
        // Re-zero back to the to range
        return $tmpValue + $toLow;
    }

    /**
     * Returns a percent from the given values
     * @param int|float $number
     * @param int|float $total
     * @param int       $decimals Optional.
     * @return int|float
     */
    public static function percent(
        int|float $number,
        int|float $total,
        int $decimals = 0,
    ): int|float {
        return (int)$total === 0 ? 0 : self::round($number * 100 / $total, $decimals);
    }

    /**
     * Returns a division from the given values
     * @param int|float $numerator
     * @param int|float $divisor
     * @param int       $decimals  Optional.
     * @return float
     */
    public static function divide(
        int|float $numerator,
        int|float $divisor,
        int $decimals = 0,
    ): float {
        if ((int)$divisor === 0) {
            return 0.0;
        }
        return self::round($numerator / $divisor, $decimals);
    }

    /**
     * Returns a division from the given values as an integer
     * @param int  $numerator
     * @param int  $divisor
     * @param bool $useFloor  Optional.
     * @return int
     */
    public static function divideInt(int $numerator, int $divisor, bool $useFloor = false): int {
        if ($divisor === 0) {
            return 0;
        }
        return self::roundInt($numerator / $divisor, $useFloor);
    }

    /**
     * Applies the Discount to the given Number
     * @param int|float $number
     * @param int|float $percent
     * @return int|float
     */
    public static function applyDiscount(int|float $number, int|float $percent): int|float {
        if ($percent === 0 || $percent === 0.0) {
            return $number;
        }
        $percent  = max(0, min(100, $percent));
        $discount = (100 - $percent) / 100;
        return $number * $discount;
    }

    /**
     * Applies the Increment to the given Number
     * @param int|float $number
     * @param int|float $percent
     * @return int|float
     */
    public static function applyIncrement(int|float $number, int|float $percent): int|float {
        if ($percent === 0 || $percent === 0.0) {
            return $number;
        }
        $percent   = max(0, min(100, $percent));
        $increment = $percent / (100 - $percent);
        return $number + $number * $increment;
    }

    /**
     * Adds the Tax to the given Number
     * @param int|float $number
     * @param int|float $percent
     * @return int|float
     */
    public static function addTax(int|float $number, int|float $percent): int|float {
        // The Tax is a percent of the Number added over it, like a VAT, unlike the Increment,
        // which is the percent of the result
        if ($percent <= 0) {
            return $number;
        }
        return $number + $number * $percent / 100;
    }

    /**
     * Returns the Greatest Common Divisor
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function getCommonDivisor(int $a, int $b): int {
        while ($b !== 0) {
            $m = $a % $b;
            $a = $b;
            $b = $m;
        }
        return $a;
    }



    /**
     * Returns true if the given price is valid
     * @param int|float $number
     * @param int|null  $min      Optional.
     * @param int|null  $max      Optional.
     * @param int|null  $decimals Optional.
     * @return bool
     */
    public static function isValidFloat(
        int|float $number,
        ?int $min = 1,
        ?int $max = null,
        ?int $decimals = null,
    ): bool {
        $mult = 1;
        if ($decimals !== null) {
            $numberStr    = (string)$number;
            $decimalPos   = strrpos($numberStr, ".");
            $decimalCount = strlen($numberStr) - ($decimalPos !== false ? $decimalPos : 0) - 1;
            if (strrpos($numberStr, ".") > 0 && $decimalCount > $decimals) {
                return false;
            }
            $mult = pow(10, $decimals);
        }

        $integer = (int)($number * $mult);
        $multMin = $min !== null ? (int)($min * $mult) : $min;
        $multMax = $max !== null ? (int)($max * $mult) : $max;
        return self::isValid($integer, $multMin, $multMax);
    }

    /**
     * Returns true if the given price is valid
     * @param int|float $price
     * @param int|null  $min   Optional.
     * @param int|null  $max   Optional.
     * @return bool
     */
    public static function isValidPrice(int|float $price, ?int $min = 1, ?int $max = null): bool {
        return self::isValidFloat($price, $min, $max, 2);
    }

    /**
     * Rounds the given price to 2 decimals
     * @param int|float $price
     * @return int|float
     */
    public static function roundCents(int|float $price): int|float {
        return self::round($price, 2);
    }

    /**
     * Returns the given price in Cents
     * @param mixed $price
     * @return int
     */
    public static function toCents(mixed $price): int {
        return self::toInt($price, 2);
    }

    /**
     * Returns the given price in Dollars
     * @param mixed $price
     * @return float
     */
    public static function fromCents(mixed $price): float {
        return self::toFloat($price, 2);
    }

    /**
     * Returns a price using the right format
     * @param int|float $price
     * @param int       $decimals       Optional.
     * @param int       $maxForDecimals Optional.
     * @param string    $default        Optional.
     * @return string
     */
    public static function formatPrice(
        int|float $price,
        int $decimals = 2,
        int $maxForDecimals = 1000,
        string $default = "0",
    ): string {
        return self::formatFloat($price, $decimals, $maxForDecimals, $default);
    }

    /**
     * Returns a price using the right format
     * @param int    $cents
     * @param int    $decimals       Optional.
     * @param int    $maxForDecimals Optional.
     * @param string $default        Optional.
     * @return string
     */
    public static function formatCents(
        int $cents,
        int $decimals = 2,
        int $maxForDecimals = 1000,
        string $default = "0",
    ): string {
        $price = self::fromCents($cents);
        return self::formatFloat($price, $decimals, $maxForDecimals, $default);
    }

    /**
     * Returns a price string
     * @param int|float $price
     * @return string
     */
    public static function toPriceString(int|float $price): string {
        $millions = round($price / 1000000);
        if ($millions > 10) {
            return "\${$millions}m";
        }
        $kilos = round($price / 1000);
        if ($kilos > 10) {
            return "\${$kilos}k";
        }
        $price = round($price);
        return "\${$price}";
    }

    /**
     * Returns the memory in MB or GB with the units
     * @param int  $bytes
     * @param bool $inGigas Optional.
     * @return string
     */
    public static function toBytesString(int $bytes, bool $inGigas = false): string {
        $megaBytes = $inGigas ? $bytes * 1024 : $bytes;
        $teraBytes = floor($megaBytes / (1024 * 1024));
        if ($teraBytes >= 1) {
            return "$teraBytes TB";
        }
        $gigaBytes = floor($megaBytes / 1024);
        if ($inGigas || $gigaBytes >= 1) {
            return "$gigaBytes GB";
        }
        return "$megaBytes MB";
    }

    /**
     * Adds zeros to the start of the number
     * @param int|float $value
     * @param int       $amount
     * @return string
     */
    public static function zerosPad(int|float $value, int $amount): string {
        return str_pad((string)$value, $amount, "0", STR_PAD_LEFT);
    }



    /**
     * Calculates the distance between two coordinates
     * @param float $fromLatitude
     * @param float $fromLongitude
     * @param float $toLatitude
     * @param float $toLongitude
     * @return float
     */
    public static function coordinatesDistance(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float {
        $fromLatitude = deg2rad($fromLatitude);
        $toLatitude   = deg2rad($toLatitude);
        $angle        = deg2rad($fromLongitude - $toLongitude);

        $distanceA    = sin($fromLatitude) * sin($toLatitude);
        $distanceB    = cos($fromLatitude) * cos($toLatitude);
        $distance     = $distanceA + $distanceB * cos($angle);

        $result       = acos($distance);
        $result       = rad2deg($result);
        $result       = $result * 60 * 1.1515 * 1.609344;
        return $result;
    }

    /**
     * Calculates the Expression
     * @param string $expression
     * @return int|float
     */
    public static function calcExpression(string $expression): int|float {
        $expression = Strings::toLowerCase(trim($expression));

        // Check for functions
        $functions  = [ "floor", "ceil", "round" ];
        $function   = "";
        foreach ($functions as $func) {
            if (Strings::startsWith($expression, $func)) {
                $expression = Strings::substringAfter($expression, $func);
                $function   = $func;
            }
        }

        // Sanitize the input
        $expression = Strings::replacePattern($expression, "/[^0-9.,+\-\*\/\^%()]/", "");

        // Convert percentages to decimal
        $expression = Strings::replacePattern($expression, "/([+-])([0-9]+)(%)/", "(\$1\$2/100)");
        $expression = Strings::replacePattern($expression, "/([0-9]+)(%)/", "(\$1/100)");

        // Fixes some errors
        $expression = Strings::replacePattern($expression, "/,/", ".");
        $expression = Strings::replacePattern($expression, "/\.+/", ".");
        $expression = Strings::replacePattern($expression, "/\(\)/", "");
        $expression = Strings::replacePattern($expression, "/\+\-/", "-");
        $expression = Strings::replacePattern($expression, "/--/", "+");

        // There is no expression
        if ($expression === "") {
            return 0;
        }

        // Add the function
        if ($function !== "") {
            $expression = "$function($expression)";
        }

        // Calculate
        try {
            $result = @eval("return $expression;"); // phpcs:ignore
            return self::toIntOrFloat($result);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
