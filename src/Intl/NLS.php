<?php
namespace Framework\Intl;

use Framework\IO\Select;
use Framework\Intl\IntlConfig;
use Framework\System\Config;
use Framework\System\Language;
use Framework\Enum\Enum;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\Numbers;
use Framework\Utils\Strings;

/**
 * The Internalization Strings
 */
class NLS {

    /** @var array<string,Dictionary> */
    private static array  $data     = [];
    private static string $language = "root";
    private static bool   $reported = false;



    /**
     * Gets the Language
     * @return string
     */
    public static function getLanguage(): string {
        return self::$language;
    }

    /**
     * Sets the Language
     * @param string $language
     * @return void
     */
    public static function setLanguage(string $language): void {
        self::$language = $language;
    }



    /**
     * Loads an NLS Language
     * @param string $language Optional.
     * @return Dictionary
     */
    private static function load(string $language = ""): Dictionary {
        if ($language === "") {
            $language = self::$language;
        }

        // Any strings file can be read by its code, even one that is not
        // listed as a Language, so the file is tried before the code is
        // turned into a listed one
        $langCode = Strings::toLowerCase($language);
        if ($langCode === "root") {
            $langCode = Language::getRootCode();
        }

        if (isset(self::$data[$langCode])) {
            return self::$data[$langCode];
        }

        $data = IntlConfig::loadStrings($langCode);
        if ($data->isNotEmpty()) {
            self::$data[$langCode] = $data;
            return $data;
        }

        // The code has no file of its own, so the root answers for it
        $rootCode = Language::getRootCode();
        if ($rootCode !== $langCode) {
            return self::load($rootCode);
        }

        // Not even the root has a file, so every string is empty, which is only reported once
        if (!self::$reported) {
            self::$reported = true;
            $path = IntlConfig::getStringsPath();
            trigger_error("There is no Strings file for \"$langCode\" in $path", E_USER_WARNING);
        }
        return new Dictionary();
    }

    /**
     * Returns a string from the data
     * @param string $key
     * @param string $language Optional.
     * @return string
     */
    public static function getString(string $key, string $language = ""): string {
        if ($key === "") {
            return "";
        }

        $data = self::load($language);
        if (!$data->hasValue($key)) {
            return $key;
        }
        return $data->getString($key);
    }

    /**
     * Returns a string from the data at the given index
     * @param string          $key
     * @param Enum|int|string $index
     * @param string          $language Optional.
     * @return string
     */
    public static function getIndex(
        string $key,
        Enum|int|string $index,
        string $language = "",
    ): string {
        $data  = self::load($language);
        $dict  = $data->getDict($key);
        $index = Strings::toString($index);
        return $dict->getString($index);
    }

    /**
     * Returns a List from the data
     * @param string $key
     * @param string $language Optional.
     * @return list<string>
     */
    public static function getList(string $key, string $language = ""): array {
        $data = self::load($language);
        $dict = $data->getDict($key);
        return $dict->toStrings();
    }

    /**
     * Returns a Map from the data
     * @param string $key
     * @param string $language Optional.
     * @return array<string,string>
     */
    public static function getMap(string $key, string $language = ""): array {
        $data = self::load($language);
        $dict = $data->getDict($key);
        return $dict->toStringsMap();
    }

    /**
     * Returns a string from the data
     * @param string $key
     * @param string $language Optional.
     * @return list<Select>
     */
    public static function getSelect(string $key, string $language = ""): array {
        $data = self::load($language);
        $dict = $data->getDict($key);
        $map  = $dict->toStringsMap();
        return Select::createFromArray($map);
    }

    /**
     * Returns all the strings from the data
     * @param list<string> $keys
     * @param string       $language Optional.
     * @return list<string>
     */
    public static function getAll(array $keys, string $language = ""): array {
        $result = [];
        foreach ($keys as $key) {
            if ($key !== "") {
                $result[] = self::getString($key, $language);
            }
        }
        return $result;
    }



    /**
     * Creates a url from the given arguments
     * @param list<mixed> $args
     * @param string      $language Optional.
     * @return string
     */
    public static function url(array $args, string $language = ""): string {
        $result = [];
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $result[] = self::getString($arg, $language);
            } elseif (is_int($arg)) {
                $result[] = $arg;
            }
        }
        return Config::getUrl(...$result);
    }

    /**
     * Creates a url from the given arguments
     * @param string      $urlKey
     * @param list<mixed> $args
     * @param string      $language Optional.
     * @return string
     */
    public static function urlPath(
        string $urlKey,
        array $args,
        string $language = "",
    ): string {
        $result = [];
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $result[] = self::getString($arg, $language);
            } elseif (is_int($arg)) {
                $result[] = $arg;
            }
        }
        return Config::getUrlWithKey($urlKey, ...$result);
    }

    /**
     * Returns a formatted string
     * @param string      $key
     * @param list<mixed> $args
     * @param string      $language Optional.
     * @return string
     */
    public static function format(
        string $key,
        array $args,
        string $language = "",
    ): string {
        $subject = self::getString($key, $language);
        return Strings::replaceCallback(
            string:   $subject,
            pattern:  "/\{(\d+)\}/",
            callback: function (array $match) use ($args): string {
                $key = isset($match[1]) ? (int)$match[1] : 0;
                return Strings::toString($args[$key] ?? "");
            },
        );
    }

    /**
     * Format and Joins the given strings to form a sentence
     * @param string       $key
     * @param list<string> $strings
     * @param bool         $useOr    Optional.
     * @param string       $language Optional.
     * @return string
     */
    public static function formatJoin(
        string $key,
        array $strings,
        bool $useOr = false,
        string $language = "",
    ): string {
        $args = [ self::join($strings, $useOr, $language) ];
        return self::format($key, $args, $language);
    }

    /**
     * Returns a formatted string using the correct plural string
     * @param string      $key
     * @param int         $count
     * @param list<mixed> $args     Optional.
     * @param string      $language Optional.
     * @return string
     */
    public static function pluralize(
        string $key,
        int $count,
        array $args = [],
        string $language = "",
    ): string {
        $suffix = $count === 1 ? "_SINGULAR" : "_PLURAL";
        $args   = array_merge([ $count ], $args);
        return self::format($key . $suffix, $args, $language);
    }

    /**
     * Returns a formatted string using the correct plural string
     * @param string       $key
     * @param list<string> $strings
     * @param bool         $useOr    Optional.
     * @param string       $language Optional.
     * @return string
     */
    public static function pluralizeList(
        string $key,
        array $strings,
        bool $useOr = false,
        string $language = "",
    ): string {
        $suffix = count($strings) === 1 ? "_SINGULAR" : "_PLURAL";
        $args   = [ self::join($strings, $useOr, $language) ];
        return self::format($key . $suffix, $args, $language);
    }

    /**
     * Joins the given strings to form a sentence
     * @param list<string> $strings
     * @param bool         $useOr    Optional.
     * @param string       $language Optional.
     * @return string
     */
    public static function join(
        array $strings,
        bool $useOr = false,
        string $language = "",
    ): string {
        $strings = Arrays::getValues($strings);
        if (count($strings) === 0) {
            return "";
        }

        $count = count($strings);
        if ($count === 1) {
            return $strings[0];
        }

        $glue   = self::getString($useOr ? "GENERAL_OR" : "GENERAL_AND", $language);
        $result = $strings[0];
        for ($i = 1; $i < $count; $i += 1) {
            $string  = $strings[$i] ?? "";
            $result .= ($i < $count - 1 ? ", " : " $glue ") . $string;
        }
        return $result;
    }

    /**
     * Joins the given strings to form a sentence with "and"
     * @param list<string> $strings
     * @param string       $language Optional.
     * @return string
     */
    public static function joinWithAnd(array $strings, string $language = ""): string {
        return self::join($strings, useOr: false, language: $language);
    }

    /**
     * Joins the given strings to form a sentence with "or"
     * @param list<string> $strings
     * @param string       $language Optional.
     * @return string
     */
    public static function joinWithOr(array $strings, string $language = ""): string {
        return self::join($strings, useOr: true, language: $language);
    }

    /**
     * Returns the Yes/No string
     * @param bool   $value
     * @param string $language Optional.
     * @return string
     */
    public static function toYesNo(bool $value, string $language = ""): string {
        return self::getIndex("SELECT_YES_NO", $value ? 1 : 0, $language);
    }

    /**
     * Returns a formatted number
     * @param int|float $number
     * @param int       $decimals       Optional.
     * @param int       $maxForDecimals Optional.
     * @param string    $language       Optional.
     * @return string
     */
    public static function formatNumber(
        int|float $number,
        int $decimals = 2,
        int $maxForDecimals = 1000,
        string $language = "",
    ): string {
        return Numbers::formatFloat(
            number:             $number,
            decimals:           $decimals,
            maxForDecimals:     $maxForDecimals,
            decimalSeparator:   self::getString("NUMBER_DECIMAL_SEPARATOR", $language),
            thousandsSeparator: self::getString("NUMBER_THOUSANDS_SEPARATOR", $language),
        );
    }
}
