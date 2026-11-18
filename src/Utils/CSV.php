<?php
namespace Framework\Utils;

use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The CSV Utils
 */
class CSV {

    /**
     * Parses a CSV line
     * @param string $line
     * @param string $separator Optional.
     * @return list<string>
     */
    public static function parse(string $line, string $separator = ","): array {
        $result = str_getcsv($line, $separator, escape: "\\");
        return Arrays::toStrings($result);
    }

    /**
     * Converts an array or string to a CSV string
     * @param list<string>|string $value
     * @param string              $separator Optional.
     * @return string
     */
    public static function encode(array|string $value, string $separator = ","): string {
        if (is_array($value)) {
            $parts = self::withoutBlanks(Arrays::toStrings($value));
            return Strings::join($parts, $separator);
        }

        if ($separator === "") {
            return $value;
        }
        $parts = Strings::split($value, $separator);
        $parts = self::withoutBlanks($parts);
        return Strings::join($parts, $separator);
    }

    /**
     * Removes only the genuinely blank cells, so a 0 in one survives
     * @param list<string> $parts
     * @return list<string>
     */
    private static function withoutBlanks(array $parts): array {
        return array_values(array_filter($parts, fn(string $part) => $part !== ""));
    }

    /**
     * Converts an array or string to a CSV array
     * @param list<string>|string $value
     * @param string              $separator Optional.
     * @param list<string>        $fields    Optional.
     * @return array<int|string,string>|list<array<int|string,string>>
     */
    public static function decode(
        array|string $value,
        string $separator = ",",
        array $fields = [],
    ): array {
        // If is already an array just prase it in case is associative
        if (is_array($value)) {
            return self::normalize($value, $fields);
        }

        // If the value is a single line, convert it to a list of strings
        if (!Strings::contains($value, "\n")) {
            // The separator must be a single character to decode
            if ($separator === "" || Strings::length($separator) > 1) {
                return [ $value ];
            }

            $csv = self::parse($value, $separator);
            return self::normalize($csv, $fields);
        }

        // If the value contains multiple lines, decode it as a file
        return self::decodeFile($value, $separator, $fields);
    }

    /**
     * Decodes a CSV File
     * @param string       $value
     * @param string       $separator Optional.
     * @param list<string> $fields    Optional.
     * @return list<array<int|string,string>>
     */
    public static function decodeFile(
        string $value,
        string $separator = ",",
        array $fields = [],
    ): array {
        $lines  = Strings::split($value, "\n");
        $result = [];
        foreach ($lines as $line) {
            if (trim($line) !== "") {
                $csv = self::parse($line, $separator);
                $result[] = self::normalize($csv, $fields);
            }
        }
        return $result;
    }

    /**
     * Normalizes a CSV Line
     * @param array<int,string> $value
     * @param list<string>      $fields
     * @return array<int|string,string>
     */
    private static function normalize(array $value, array $fields): array {
        if (count($fields) === 0) {
            return $value;
        }

        $result = [];
        foreach ($fields as $index => $field) {
            $result[$field] = $value[$index] ?? "";
        }
        return $result;
    }



    /**
     * Reads a CSV file
     * @param string $path
     * @param string $separator  Optional.
     * @param bool   $skipHeader Optional.
     * @return list<array<int|string,string>|list<array<int|string,string>>>
     */
    public static function readFile(
        string $path,
        string $separator = ",",
        bool $skipHeader = false,
    ): array {
        $content = Storage::readFile($path);
        if ($content === "") {
            return [];
        }

        $lines  = Strings::split($content, "\n", trim: true);
        $result = [];
        foreach ($lines as $index => $line) {
            if ($line !== "" && ($index > 0 || !$skipHeader)) {
                $result[] = self::decode($line, $separator);
            }
        }
        return $result;
    }

    /**
     * Writes a CSV File
     * @param string       $path
     * @param list<string> $contents
     * @param string       $separator Optional.
     * @return bool
     */
    public static function writeFile(
        string $path,
        array $contents,
        string $separator = ",",
    ): bool {
        if (!Storage::fileExists($path)) {
            return false;
        }

        $lines = [];
        foreach ($contents as $row) {
            $lines[] = self::encode($row, $separator);
        }
        return Storage::writeFile($path, Strings::join($lines, "\n"));
    }
}
