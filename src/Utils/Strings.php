<?php
// spell-checker: ignore NOQUOTES
namespace Framework\Utils;

use Framework\Date\Date;
use Framework\Date\Type\DateFormat;
use Framework\Enum\Enum;
use Framework\Utils\Arrays;

/**
 * Several String Utils
 * @phpstan-type Replacement list<string>|string
 */
class Strings {

    /**
     * Returns true if the given value is a string
     * @param mixed $string
     * @return bool
     */
    public static function isString(mixed $string): bool {
        return is_string($string);
    }

    /**
     * Returns true if the given value is a valid string
     * @param mixed $string
     * @return bool
     */
    public static function isValid(mixed $string): bool {
        $value = Strings::normalized($string);
        return $value !== "";
    }

    /**
     * Returns the given value as a string
     * @param mixed $value
     * @return string
     */
    public static function toString(mixed $value): string {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return strval($value);
        }
        if ($value instanceof Enum) {
            return $value->toString();
        }
        if ($value instanceof Date) {
            return $value->toString(DateFormat::ReverseSeconds);
        }
        return "";
    }

    /**
     * Returns the given value as a Trimmed String
     * @param mixed $value
     * @return string
     */
    public static function trim(mixed $value): string {
        return trim(self::toString($value));
    }

    /**
     * Returns the given value as a Normalized String
     * @param mixed $value
     * @return string
     */
    public static function normalized(mixed $value): string {
        $result = trim(self::toString($value));
        return self::replace($result, "\r\n", "\n");
    }

    /**
     * Returns the length og the given String
     * @param string $string
     * @return int
     */
    public static function length(string $string): int {
        return mb_strlen($string, "UTF-8");
    }



    /**
     * Returns true if the values are Equal
     * @param mixed $string
     * @param mixed $other
     * @param bool  $caseInsensitive Optional.
     * @param bool  $trimValues      Optional.
     * @return bool
     */
    public static function isEqual(
        mixed $string,
        mixed $other,
        bool $caseInsensitive = true,
        bool $trimValues = true,
    ): bool {
        if (!is_string($string)) {
            $string = self::toString($string);
        }
        if (!is_string($other)) {
            $other = self::toString($other);
        }

        if ($caseInsensitive) {
            $string = strtolower($string);
            $other  = strtolower($other);
        }
        if ($trimValues) {
            $string = trim($string);
            $other  = trim($other);
        }
        return $string === $other;
    }

    /**
     * Returns true if the given String is equal to any of the Needles
     * @param string $string
     * @param string ...$needles
     * @return bool
     */
    public static function equals(string $string, string ...$needles): bool {
        foreach ($needles as $needle) {
            if (self::isEqual($string, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the given String is equal to any of the Needles as Case Insensitive
     * @param string $string
     * @param string ...$needles
     * @return bool
     */
    public static function equalsCaseInsensitive(string $string, string ...$needles): bool {
        foreach ($needles as $needle) {
            if (self::isEqual($string, $needle, caseInsensitive: true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the given String contains any of the given Needles
     * @param string              $string
     * @param list<string>|string $needle
     * @param bool                $caseInsensitive Optional.
     * @param bool                $atLeastOne      Optional.
     * @return bool
     */
    public static function contains(
        string $string,
        array|string $needle,
        bool $caseInsensitive = false,
        bool $atLeastOne = true,
    ): bool {
        $needles = is_array($needle) ? $needle : [ $needle ];
        if ($caseInsensitive) {
            $string  = strtolower($string);
            $needles = array_map("strtolower", $needles);
        }

        $count = 0;
        foreach ($needles as $value) {
            if (str_contains($string, $value)) {
                $count += 1;
            }
        }

        if ($atLeastOne) {
            return $count > 0;
        }
        return $count === count($needles);
    }

    /**
     * Returns true if the given String starts any of the given Needles
     * @param string $string
     * @param string ...$needles
     * @return bool
     */
    public static function startsWith(string $string, string ...$needles): bool {
        foreach ($needles as $needle) {
            $length = strlen($needle);
            if (substr($string, 0, $length) === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the given String starts any of the given Needles as Case Insensitive
     * @param string $string
     * @param string ...$needles
     * @return bool
     */
    public static function startsWithCaseInsensitive(string $string, string ...$needles): bool {
        $string = strtolower($string);
        foreach ($needles as $needle) {
            $length = strlen($needle);
            if (substr($string, 0, $length) === strtolower($needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the given String ends with the given Needle
     * @param string $string
     * @param string ...$needles
     * @return bool
     */
    public static function endsWith(string $string, string ...$needles): bool {
        foreach ($needles as $needle) {
            $length = strlen($needle);
            if (substr($string, -$length) === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the given String ends with the given Needle as Case Insensitive
     * @param string $string
     * @param string ...$needles
     * @return bool
     */
    public static function endsWithCaseInsensitive(string $string, string ...$needles): bool {
        $string = strtolower($string);
        foreach ($needles as $needle) {
            $length = strlen($needle);
            if (substr($string, -$length) === strtolower($needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the given String matches the given Pattern
     * @param string $string
     * @param string $pattern
     * @return bool
     */
    public static function match(string $string, string $pattern): bool {
        $result = preg_match($pattern, $string);
        return $result !== false && $result > 0;
    }

    /**
     * Returns the first captured group of the Pattern in the given String
     * @param string $string
     * @param string $pattern
     * @return string
     */
    public static function getMatch(string $string, string $pattern): string {
        $matches = [];
        $result  = preg_match($pattern, $string, $matches);
        if ($result === false || $result === 0 || !isset($matches[1])) {
            return "";
        }
        return self::toString($matches[1]);
    }

    /**
     * Returns all the matches for the Pattern in the given String as an array
     * @param string $string
     * @param string $pattern
     * @return list<string>
     */
    public static function getAllMatches(string $string, string $pattern): array {
        $result = preg_match_all($pattern, $string, $matches);
        if ($result === false || $result === 0) {
            return [];
        }

        $list = [];
        foreach ($matches as $match) {
            $list = array_merge($list, $match);
        }
        return $list;
    }

    /**
     * Returns true if the given String is created with only the given Needle
     * @param string $string
     * @param string $needle
     * @return bool
     */
    public static function onlyOneCharacter(string $string, string $needle): bool {
        if ($string === "") {
            return false;
        }

        $chars = str_split($string);
        foreach ($chars as $char) {
            if ($char !== $needle) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns:
     *   < 0 if string is less than other.
     *   > 0 if string is greater than other.
     *   0 if they are equal.
     * @param string $string
     * @param string $other
     * @param bool   $orderAsc        Optional.
     * @param bool   $caseInsensitive Optional.
     * @return int
     */
    public static function compare(
        string $string,
        string $other,
        bool $orderAsc = true,
        bool $caseInsensitive = false,
    ): int {
        if ($caseInsensitive) {
            $result = strcasecmp($string, $other);
        } else {
            $result = strcmp($string, $other);
        }
        return $orderAsc ? $result : $result * -1;
    }



    /**
     * Returns a char from an index
     * @param int  $index
     * @param bool $upperCase Optional.
     * @return string
     */
    public static function getLetter(int $index, bool $upperCase = true): string {
        if ($index < 0 || $index > 25) {
            return "";
        }
        $start = $upperCase ? 65 : 97;
        return chr($index + $start);
    }

    /**
     * Returns a number from a letter
     * @param string $text
     * @return int
     */
    public static function getNumber(string $text): int {
        $letter = strtoupper(trim($text));
        if (self::match($letter, "/^[A-Z]$/")) {
            return ord($letter) - 64;
        }
        return 0;
    }

    /**
     * Repeats the given String the given times
     * @param string $string
     * @param int    $times
     * @return string
     */
    public static function repeat(string $string, int $times): string {
        if ($times <= 0) {
            return "";
        }
        return str_repeat($string, $times);
    }

    /**
     * Returns the amount of times the Needle is in the String
     * @param string $string
     * @param string $needle
     * @return int
     */
    public static function countOccurrences(string $string, string $needle): int {
        if ($string === "" || $needle === "") {
            return 0;
        }
        return substr_count($string, $needle);
    }

    /**
     * Returns a random String with the given length
     * @param int $length Optional.
     * @return string
     */
    public static function random(int $length = 50): string {
        if ($length < 1) {
            return "";
        }

        // Each byte becomes 2 hex chars, so the result is cut to the length asked for
        $bytes = random_bytes($length);
        return substr(bin2hex($bytes), 0, $length);
    }

    /**
     * Returns a random char from the given String
     * @param string $string
     * @return string
     */
    public static function randomChar(string $string): string {
        if ($string === "") {
            return "";
        }

        // random_int is the only source that can not be seeded: array_rand and
        // mt_rand share a stream that each process seeds with 32 bits, so a code
        // built with them is one of 4 billion and can be predicted from a few
        $index = random_int(0, strlen($string) - 1);
        return $string[$index];
    }

    /**
     * Generates a random String with the given options
     * @param int    $length        Optional.
     * @param string $availableSets Optional.
     * @param bool   $skipSimilar   Optional.
     * @return string
     */
    public static function randomCode(
        int $length = 8,
        string $availableSets = "lud",
        bool $skipSimilar = false,
    ): string {
        $sets   = [];
        $all    = "";
        $result = "";

        if (self::contains($availableSets, "a")) {
            $sets[] = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        } else {
            if (self::contains($availableSets, "l")) {
                $sets[] = "abcdefghijklmnopqrstuvwxyz";
            }
            if (self::contains($availableSets, "u")) {
                $sets[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            }
        }
        if (self::contains($availableSets, "d")) {
            $sets[] = "0123456789";
        }
        if (self::contains($availableSets, "s")) {
            $sets[] = "!@#$%&*?";
        }

        // A code that is read and then typed by hand leaves out the chars that look alike
        if ($skipSimilar) {
            foreach ($sets as $index => $set) {
                $sets[$index] = self::replace($set, [ "0", "O", "o", "1", "I", "l" ], "");
            }
        }

        foreach ($sets as $set) {
            $result .= self::randomChar($set);
            $all    .= $set;
        }

        $count = $length - count($sets);
        for ($i = 0; $i < $count; $i += 1) {
            $result .= self::randomChar($all);
        }

        // The first chars are one per set, so they are shuffled to hide that order,
        // and str_shuffle is not used as it draws from the same stream as mt_rand
        for ($i = strlen($result) - 1; $i > 0; $i -= 1) {
            $index          = random_int(0, $i);
            $char           = $result[$i];
            $result[$i]     = $result[$index];
            $result[$index] = $char;
        }
        return $result;
    }



    /**
     * Removes all non numbers from the String
     * @param string $value
     * @return string
     */
    public static function toNumber(string $value): string {
        return self::replacePattern($value, '/[^0-9]/', "");
    }

    /**
     * Replaces in the String the search with the replacement
     *
     * The replacement is only optional for a map of searches to
     * replacements, which carries its own
     * @param string                                   $string
     * @param list<string>|array<string,string>|string $search
     * @param list<string>|string|null                 $replace Optional.
     * @phpstan-param ($search is array<string,string> ? Replacement|null : Replacement) $replace
     * @return string
     */
    public static function replace(
        string $string,
        array|string $search,
        array|string|null $replace = null,
    ): string {
        if ($replace === null && is_array($search)) {
            $keys   = Arrays::toStrings(array_keys($search));
            $values = Arrays::getValues($search);
            return str_replace($keys, $values, $string);
        }

        if ($replace !== null) {
            return str_replace($search, $replace, $string);
        }

        // A search with no replacement is a call the analysis refuses, and
        // the least harmful answer to one is the string untouched
        return $string;
    }

    /**
     * Replaces in the start of the String the search with the replacement
     * @param string $string
     * @param string $search
     * @param string $replace
     * @return string
     */
    public static function replaceStart(string $string, string $search, string $replace): string {
        if (self::startsWith($string, $search)) {
            return substr_replace($string, $replace, 0, strlen($search));
        }
        return $string;
    }

    /**
     * Replaces in the end of the String the search with the replacement
     * @param string $string
     * @param string $search
     * @param string $replace
     * @return string
     */
    public static function replaceEnd(string $string, string $search, string $replace): string {
        if (self::endsWith($string, $search)) {
            return substr_replace($string, $replace, -strlen($search));
        }
        return $string;
    }

    /**
     * Replaces in the String the pattern with the replacement
     * @param string              $string
     * @param list<string>|string $pattern
     * @param list<string>|string $replacement
     * @param int                 $limit       Optional.
     * @return string
     */
    public static function replacePattern(
        string $string,
        array|string $pattern,
        array|string $replacement,
        int $limit = -1,
    ): string {
        $result = preg_replace($pattern, $replacement, $string, $limit);
        return $result !== null ? $result : "";
    }

    /**
     * Replaces in the String the pattern using the callback
     * @param string                    $string
     * @param list<string>|string       $pattern
     * @param callable(string[]):string $callback
     * @param int                       $limit    Optional.
     * @return string
     */
    public static function replaceCallback(
        string $string,
        array|string $pattern,
        callable $callback,
        int $limit = -1,
    ): string {
        $result = preg_replace_callback($pattern, $callback, $string, $limit);
        return $result !== null ? $result : "";
    }



    /**
     * Removes the Needle from the start of the String
     * @param string $string
     * @param string ...$needles
     * @return string
     */
    public static function stripStart(string $string, string ...$needles): string {
        foreach ($needles as $needle) {
            if (self::startsWith($string, $needle)) {
                $length = strlen($needle);
                return substr($string, $length, strlen($string) - $length);
            }
        }
        return $string;
    }

    /**
     * Removes the Needle from the end of the String
     * @param string $string
     * @param string ...$needles
     * @return string
     */
    public static function stripEnd(string $string, string ...$needles): string {
        foreach ($needles as $needle) {
            if (self::endsWith($string, $needle)) {
                $length = strlen($needle);
                return substr($string, 0, strlen($string) - $length);
            }
        }
        return $string;
    }

    /**
     * Removes the Needle from the start and end of the String
     * @param string $string
     * @param string $startNeedle
     * @param string $endNeedle
     * @return string
     */
    public static function stripStartEnd(
        string $string,
        string $startNeedle,
        string $endNeedle,
    ): string {
        $result = self::stripStart($string, $startNeedle);
        $result = self::stripEnd($result, $endNeedle);
        return $result;
    }

    /**
     * Pads left the String with the given string to the given length
     * @param string $string
     * @param int    $length
     * @param string $needle Optional.
     * @return string
     */
    public static function padLeft(string $string, int $length, string $needle = " "): string {
        return str_pad($string, $length, $needle, STR_PAD_LEFT);
    }

    /**
     * Pads right the String with the given string to the given length
     * @param string $string
     * @param int    $length
     * @param string $needle Optional.
     * @return string
     */
    public static function padRight(string $string, int $length, string $needle = " "): string {
        return str_pad($string, $length, $needle, STR_PAD_RIGHT);
    }

    /**
     * Adds the Prefix to the start of the String if not there
     * @param string $string
     * @param string $prefix
     * @return string
     */
    public static function addPrefix(string $string, string $prefix): string {
        if ($string === "") {
            return "";
        }
        if (!self::startsWith($string, $prefix)) {
            return $prefix . $string;
        }
        return $string;
    }

    /**
     * Adds the Suffix to the end of the String if not there
     * @param string $string
     * @param string $suffix
     * @return string
     */
    public static function addSuffix(string $string, string $suffix): string {
        if ($string === "") {
            return "";
        }
        if (!self::endsWith($string, $suffix)) {
            return $string . $suffix;
        }
        return $string;
    }

    /**
     * Adds the Prefix to the start and the Suffix to the end of the String if not there
     * @param string $string
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    public static function addPrefixSuffix(string $string, string $prefix, string $suffix): string {
        $result = self::addPrefix($string, $prefix);
        $result = self::addSuffix($result, $suffix);
        return $result;
    }



    /**
     * Returns a Substring from the Start to the Length
     * @param string   $string
     * @param int      $start
     * @param int|null $length Optional.
     * @param bool     $asUtf8 Optional.
     * @return string
     */
    public static function substring(
        string $string,
        int $start,
        ?int $length = null,
        bool $asUtf8 = false,
    ): string {
        if ($asUtf8) {
            return mb_substr($string, $start, $length, "utf-8");
        }
        return substr($string, $start, $length);
    }

    /**
     * Returns a Substring from the Needle to the end
     * @param string $string
     * @param string $needle
     * @param bool   $useFirst Optional.
     * @return string
     */
    public static function substringAfter(
        string $string,
        string $needle,
        bool $useFirst = false,
    ): string {
        $position = $useFirst ? strpos($string, $needle) : strrpos($string, $needle);
        if ($position === false) {
            return $string;
        }
        return substr($string, $position + strlen($needle));
    }

    /**
     * Returns a Substring from the start to the Needle
     * @param string $string
     * @param string $needle
     * @param bool   $useFirst Optional.
     * @return string
     */
    public static function substringBefore(
        string $string,
        string $needle,
        bool $useFirst = true,
    ): string {
        $position = $useFirst ? strpos($string, $needle) : strrpos($string, $needle);
        if ($position === false) {
            return $string;
        }
        return substr($string, 0, $position);
    }

    /**
     * Returns a Substring between the From and To
     * @param string $string
     * @param string $from
     * @param string $to
     * @return string
     */
    public static function substringBetween(string $string, string $from, string $to): string {
        $result = self::substringAfter($string, $from);
        $result = self::substringBefore($result, $to);
        return $result;
    }



    /**
     * Splits the given String at the given Needle
     * @param list<string>|string $string
     * @param string              $needle
     * @param bool                $trim      Optional.
     * @param bool                $skipEmpty Optional.
     * @return list<string>
     */
    public static function split(
        array|string $string,
        string $needle,
        bool $trim = false,
        bool $skipEmpty = false,
    ): array {
        if (is_array($string)) {
            return $string;
        }
        if ($string === "" || $needle === "") {
            return [];
        }

        $parts  = explode($needle, $string);
        $result = [];
        foreach ($parts as $part) {
            if ($trim) {
                $part = trim($part);
            }
            if (!$skipEmpty || $part !== "") {
                $result[] = $part;
            }
        }
        return $result;
    }

    /**
     * Split the String into Words
     * @param string $string
     * @return list<string>
     */
    public static function splitToWords(string $string): array {
        $option = PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY;
        $result = preg_split('/(\.\.\.\s?|[-.?!,;:(){}\[\]\'"]\s?)|\s/', $string, -1, $option);
        return $result !== false ? $result : [];
    }

    /**
     * Joins the given Strings using the given glue
     * @param mixed  $value
     * @param string $glue         Optional.
     * @param bool   $withoutEmpty Optional.
     * @return string
     */
    public static function join(
        mixed $value,
        string $glue = "",
        bool $withoutEmpty = false,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        $list = Arrays::toStrings($value, withoutEmpty: $withoutEmpty);
        return implode($glue, $list);
    }

    /**
     * Joins the given String keys using the given glue
     * @param mixed  $value
     * @param string $glue  Optional.
     * @return string
     */
    public static function joinKeys(mixed $value, string $glue = ""): string {
        if (!is_array($value)) {
            return is_string($value) ? $value : "";
        }
        return implode($glue, array_keys($value));
    }

    /**
     * Joins the given String values using the given glue
     * @param mixed  $value
     * @param string $key
     * @param string $glue  Optional.
     * @return string
     */
    public static function joinValues(mixed $value, string $key, string $glue = ""): string {
        if (!is_array($value)) {
            return is_string($value) ? $value : "";
        }

        $values = Arrays::toStrings($value, $key);
        return implode($glue, $values);
    }

    /**
     * Merges 2 strings with the given glue when possible
     * @param string $first
     * @param string $second
     * @param string $glue   Optional.
     * @return string
     */
    public static function merge(string $first, string $second, string $glue = " "): string {
        $result = "";
        if ($first !== "" && $second !== "") {
            $result = "{$first}{$glue}{$second}";
        } elseif ($first !== "") {
            $result = $first;
        } elseif ($second !== "") {
            $result = $second;
        }
        return $result;
    }



    /**
     * Transforms a String to lowercase
     * @param string $string
     * @return string
     */
    public static function toLowerCase(string $string): string {
        return strtolower($string);
    }

    /**
     * Transforms the first Character to LowerCase
     * @param string $string
     * @return string
     */
    public static function lowerCaseFirst(string $string): string {
        return lcfirst($string);
    }

    /**
     * Transforms a String to UPPERCASE
     * @param string $string
     * @return string
     */
    public static function toUpperCase(string $string): string {
        return strtoupper($string);
    }

    /**
     * Transforms the first Character to UpperCase
     * @param string $string
     * @return string
     */
    public static function upperCaseFirst(string $string): string {
        return ucfirst($string);
    }

    /**
     * Transforms a String to Title Case
     * @param string $string
     * @return string
     */
    public static function toTitleCase(string $string): string {
        return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
    }



    /**
     * Returns true if a String is CONSTANT_CASE
     * @param string $string
     * @return bool
     */
    public static function isConstantCase(string $string): bool {
        return self::match($string, "/^[A-Z_]+$/");
    }

    /**
     * Transforms a String to CONSTANT_CASE
     * @param string $string
     * @return string
     */
    public static function toConstantCase(string $string): string {
        if (self::isConstantCase($string)) {
            return $string;
        }

        if (self::isPascalCase($string) || self::isCamelCase($string)) {
            $result = self::toSnakeCase($string);
            $result = strtoupper($result);
            return $result;
        }

        $result = self::replacePattern($string, "/[\.:;\-_ ]+/", "_");
        $result = strtoupper($result);
        return $result;
    }

    /**
     * Returns true if a String is snake_case
     * @param string $string
     * @return bool
     */
    public static function isSnakeCase(string $string): bool {
        return self::match($string, "/^[a-z0-9]+(_[a-z0-9]+)*$/");
    }

    /**
     * Transforms a String to snake_case
     * @param string $string
     * @return string
     */
    public static function toSnakeCase(string $string): string {
        if (self::isSnakeCase($string)) {
            return $string;
        }

        if (self::isPascalCase($string) || self::isCamelCase($string)) {
            // Insert an underscore before any uppercase letter that is not
            // followed by another uppercase letter, or before an uppercase letter
            // that is followed by a lowercase letter.
            // This handles cases like "CamelCase" -> "camel_case" and "SomeID" -> "some_id".
            $result = self::replacePattern(
                $string,
                '/(?<!^)([A-Z][a-z]|(?<=[a-z])[A-Z])/',
                "_" . '$1'
            );

            // Insert an underscore before any sequence of two or more uppercase letters
            // that is preceded by a lowercase letter.
            // This handles cases like "someXMLData" -> "some_xml_data".
            $result = self::replacePattern($result, '/(?<=[a-z])([A-Z]{2,})/', "_" . '$1');

            return strtolower($result);
        }

        $result = self::replacePattern($string, "/[\.:;\- ]+/", "_");
        $result = strtolower($result);
        return $result;
    }

    /**
     * Returns true if a String is kebab-case
     * @param string $string
     * @return bool
     */
    public static function isKebabCase(string $string): bool {
        return self::match($string, "/^[a-z0-9]+(-[a-z0-9]+)*$/");
    }

    /**
     * Transforms a String to kebab-case
     * @param string $string
     * @return string
     */
    public static function toKebabCase(string $string): string {
        if (self::isKebabCase($string)) {
            return $string;
        }

        if (self::isPascalCase($string) || self::isCamelCase($string)) {
            // Insert a hyphen before any uppercase letter that is not
            // followed by another uppercase letter, or before an uppercase
            // letter that is followed by a lowercase letter.
            // This handles cases like "CamelCase" -> "camel-case" and "SomeID" -> "some-id".
            $result = self::replacePattern(
                $string,
                '/(?<!^)([A-Z][a-z]|(?<=[a-z])[A-Z])/',
                "-" . '$1',
            );

            // Insert a hyphen before any sequence of two or more uppercase letters
            // that is preceded by a lowercase letter.
            // This handles cases like "someXMLData" -> "some-xml-data".
            $result = self::replacePattern($result, '/(?<=[a-z])([A-Z]{2,})/', "-" . '$1');

            return strtolower($result);
        }

        $result = self::replacePattern($string, "/[\.:;\_ ]+/", "-");
        $result = strtolower($result);
        return $result;
    }

    /**
     * Returns true if a String is PascalCase
     * @param string $string
     * @return bool
     */
    public static function isPascalCase(string $string): bool {
        return self::match($string, "/^[A-Z]+[a-z][a-zA-Z0-9]*$/");
    }

    /**
     * Transforms a String to PascalCase
     * @param string $string
     * @return string
     */
    public static function toPascalCase(string $string): string {
        if (self::isPascalCase($string)) {
            return $string;
        }

        if (self::isCamelCase($string)) {
            return self::upperCaseFirst($string);
        }

        $result = self::replacePattern($string, "/[\.:;\-_]+/", " ");
        $result = strtolower($result);
        $result = ucwords($result);
        $result = str_replace(" ", "", $result);
        return $result;
    }

    /**
     * Returns true if a String is camelCase
     * @param string $string
     * @return bool
     */
    public static function isCamelCase(string $string): bool {
        return self::match($string, "/^[a-z][a-zA-Z0-9]*$/");
    }

    /**
     * Transforms a String to camelCase
     * @param string $string
     * @return string
     */
    public static function toCamelCase(string $string): string {
        if (self::isCamelCase($string)) {
            return $string;
        }

        if (self::isPascalCase($string)) {
            return self::lowerCaseFirst($string);
        }

        $result = self::replacePattern($string, "/[\.:;\-_]+/", " ");
        $result = strtolower($result);
        $result = ucwords($result);
        $result = str_replace(" ", "", $result);
        $result = lcfirst($result);
        return $result;
    }



    /**
     * Returns the HTML version of the given string
     * @param string $string
     * @return string
     */
    public static function toHtml(string $string): string {
        return str_replace("\n", "<br>", $string);
    }

    /**
     * Returns the given string without HTML
     * @param string $string
     * @return string
     */
    public static function removeHtml(string $string): string {
        $result = strip_tags($string, "<style>");
        $start  = strpos($result, "<style");
        if ($start === false) {
            return $result;
        }

        $end = strpos($result, "</style>");
        if ($end === false) {
            return $result;
        }

        $styles = substr($result, $start, $end + strlen("</style>") - $start);
        $result = str_replace($styles, "", $result);
        return $result;
    }

    /**
     * Returns true if the given string has HTML
     * @param string $string
     * @return bool
     */
    public static function hasHtml(string $string): bool {
        return $string !== self::removeHtml($string);
    }

    /**
     * Decodes the HTML entities in the given string
     * @param string $string
     * @return string
     */
    public static function decodeHtml(string $string): string {
        return html_entity_decode($string, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Returns a short version of the given string
     * @param string $string
     * @param int    $length Optional.
     * @param bool   $asUtf8 Optional.
     * @return string
     */
    public static function makeShort(
        string $string,
        int $length = 30,
        bool $asUtf8 = true,
    ): string {
        if ($length === 0) {
            return $string;
        }

        $first = explode("\n", $string)[0];
        if ($asUtf8) {
            if (mb_strlen($first, "utf-8") > $length) {
                return mb_substr($first, 0, $length - 3, "utf-8") . "...";
            }
            return $first;
        }

        $count  = $length;
        $result = $first;
        while (strlen($result) > $length) {
            $result = mb_substr($result, 0, $count, "utf-8");
            $count -= 1;
        }
        return $result;
    }

    /**
     * Returns true if the short version is different from the string
     * @param string $string
     * @param int    $length Optional.
     * @param bool   $asUtf8 Optional.
     * @return bool
     */
    public static function isShort(
        string $string,
        int $length = 30,
        bool $asUtf8 = true,
    ): bool {
        return self::makeShort($string, $length, $asUtf8) !== $string;
    }



    /**
     * Returns true if the given string is Alpha-Numeric
     * @param string   $string
     * @param bool     $withDashes Optional.
     * @param int|null $length     Optional.
     * @return bool
     */
    public static function isAlphaNum(
        string $string,
        bool $withDashes = false,
        ?int $length = null,
    ): bool {
        if ($length !== null && strlen($string) !== $length) {
            return false;
        }
        if ($withDashes) {
            $string = str_replace([ "-", "_" ], "", $string);
        }
        return ctype_alnum($string);
    }

    /**
     * Sanitizes a String
     * @param string $string
     * @param bool   $lowercase Optional.
     * @param bool   $anal      Optional.
     * @return string
     */
    public static function sanitize(
        string $string,
        bool $lowercase = true,
        bool $anal = false,
    ): string {
        $strip = [
            "~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")",
            "_", "=", "+", "[", "{", "]", "}", "\\", "|", ";", ":", "\"", "'",
            "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "â€”", "â€“", ",", "<", ".", ">", "/", "?",
        ];
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = self::replacePattern($clean, '/\s+/', "-");

        if ($anal) {
            $tilde = [ "á", "é", "í", "ó", "ú", "ü", "ñ", "Á", "É", "Í", "Ó", "Ú", "Ü", "Ñ" ];
            $with  = [ "a", "e", "i", "o", "u", "u", "n", "A", "E", "I", "O", "U", "U", "N" ];
            $clean = str_replace($tilde, $with, $clean);
            $clean = self::replacePattern($clean, "/[^a-zA-Z0-9\-]/", "");
        }

        if (!$lowercase) {
            return $clean;
        }
        if (function_exists("mb_strtolower")) {
            return mb_strtolower($clean, "UTF-8");
        }
        return strtolower($clean);
    }

    /**
     * Returns true if the given Text has an Emoji
     * @param string $string
     * @return bool
     */
    public static function hasEmoji(string $string): bool {
        $emojis = '/['
            // Note: Skip the next group to use tildes in Spanish
            // . '\x{0080}-\x{02AF}'      // Latin-1 Supplement, IPA Extensions
            . '\x{0300}-\x{03FF}'      // Combining Diacritical Marks, Greek
            . '\x{0600}-\x{06FF}'      // Arabic
            . '\x{0C00}-\x{0C7F}'      // Telugu
            . '\x{1DC0}-\x{1DFF}'      // Combining Diacritical Marks Supplement
            . '\x{1E00}-\x{1EFF}'      // Latin Extended Additional
            . '\x{2000}-\x{209F}'      // General Punctuation
            . '\x{20D0}-\x{214F}'      // Combining Diacritical Marks for Symbols
            . '\x{2190}-\x{23FF}'      // Arrows, Mathematical Operators
            . '\x{2460}-\x{25FF}'      // Enclosed Alphanumerics, Geometric Shapes
            . '\x{2600}-\x{27EF}'      // Miscellaneous Symbols, Dingbats
            . '\x{2900}-\x{29FF}'      // Supplemental Arrows-B
            . '\x{2B00}-\x{2BFF}'      // Miscellaneous Symbols and Arrows
            . '\x{2C60}-\x{2C7F}'      // Latin Extended-C
            . '\x{2E00}-\x{2E7F}'      // Supplemental Punctuation
            . '\x{3000}-\x{303F}'      // CJK Symbols and Punctuation
            . '\x{A490}-\x{A4CF}'      // Yi Radicals
            . '\x{E000}-\x{F8FF}'      // Private Use Area
            . '\x{FE00}-\x{FE0F}'      // Variation Selectors
            . '\x{FE30}-\x{FE4F}'      // CJK Compatibility Forms
            . '\x{1F000}-\x{1F02F}'    // Mahjong Tiles
            . '\x{1F0A0}-\x{1F0FF}'    // Playing Cards
            . '\x{1F100}-\x{1F64F}'    // Enclosed Alphanumeric Supplement, Emoticons
            . '\x{1F680}-\x{1F6FF}'    // Transport and Map Symbols
            . '\x{1F700}-\x{1F77F}'    // Alchemical Symbols
            . '\x{1F780}-\x{1F7FF}'    // Geometric Shapes Extended
            . '\x{1F800}-\x{1F8FF}'    // Supplemental Arrows-C
            . '\x{1F900}-\x{1F9FF}'    // Supplemental Symbols and Pictographs
            . '\x{1FA00}-\x{1FA6F}'    // Chess Symbols, Symbols and Pictographs Extended-A
            . '\x{1FA70}-\x{1FAFF}'    // Symbols and Pictographs Extended-B
            . '\x{20000}-\x{2FFFF}'    // CJK Unified Ideographs Extension B-C
            . ']/u';

        return self::match($string, $emojis);
    }

    /**
     * Returns true if the given Text has only Emojis
     * @param string $text
     * @return bool
     */
    public static function isOnlyEmojis(string $text): bool {
        $emojiPattern = '/^('
            . '[\x{1F600}-\x{1F64F}]|' // Emoticons
            . '[\x{1F680}-\x{1F6FF}]|' // Transport and Map
            . '[\x{1F300}-\x{1F5FF}]|' // Misc Symbols and Pictographs
            . '[\x{1F30D}-\x{1F567}]|'
            . '[\x{1F900}-\x{1F9FF}]|'
            . '[\x{1FA70}-\x{1FAF6}]|'
            . '[\x{2700}-\x{27BF}]|'   // Dingbats
            . '[\x{24C2}-\x{1F251}]'
            . ')+$/u';

        return self::match($text, $emojiPattern);
    }

    /**
     * Converts the Encoding from HTML to UTF8 of the given String
     * @param string $string
     * @return string
     */
    public static function convertEncoding(string $string): string {
        return mb_encode_numericentity(
            htmlspecialchars_decode(
                htmlentities($string, ENT_NOQUOTES, "UTF-8", double_encode: false),
                ENT_NOQUOTES
            ),
            [ 0x80, 0x10FFFF, 0, ~0 ],
            "UTF-8"
        );
    }

    /**
     * Returns the Base64 Encoded String
     * @param string $string
     * @return string
     */
    public static function base64Encode(string $string): string {
        return base64_encode($string);
    }

    /**
     * Returns the Base64 Decoded String
     * @param string $string
     * @return string
     */
    public static function base64Decode(string $string): string {
        $result = base64_decode($string, strict: true);
        return $result !== false ? $result : "";
    }
}
