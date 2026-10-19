<?php
namespace Framework\Utils;

use Framework\Utils\Arrays;
use Framework\Utils\Numbers;
use Framework\Utils\Strings;

use Closure;
use Throwable;

/**
 * The Calculator
 */
class Calculator {

    // A Vector is its mode, its template and its numbers, like [[N|USD {0}|120]], where the
    // mode says if it is its last number or its text when it is not in a calculation
    private const VectorPattern = "/\[\[([^\[\]]*)\]\]/";
    private const NumberMode    = "N";
    private const TextMode      = "T";
    private const VectorChars   = [ "|", "[", "]" ];
    private const VectorEscapes = [ "&#124;", "&#91;", "&#93;" ];

    // A Condition is if(expression, content), and the one that is false is marked, so the
    // lines that only had it are removed
    private const ConditionPattern = "/\bif(\((?:[^()]++|(?1))*\))/";
    private const EmptyCondition   = "\x00";



    /**
     * Calculates the Expression
     * @param string $expression
     * @return int|float
     */
    public static function calculate(string $expression): int|float {
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
            return Numbers::toIntOrFloat($result);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Calculates the Expression once for each number of its Vectors
     * @param string $expression
     * @param bool   $withFormat Optional.
     * @return string
     */
    public static function calculateVectors(string $expression, bool $withFormat = false): string {
        $vectors = self::getVectors($expression);
        if (count($vectors) === 0) {
            $result = self::calculate($expression);
            return $withFormat ? Numbers::formatFloat($result, 2) : (string)$result;
        }

        // Each Vector gives its number at the same position, or its last one when it is shorter
        $total = 0;
        foreach ($vectors as $vector) {
            $total = max($total, count($vector["numbers"]));
        }

        $results = [];
        for ($index = 0; $index < $total; $index += 1) {
            $current = $expression;
            foreach ($vectors as $vector) {
                $number  = self::getNumber($vector["numbers"], $index);
                $current = Strings::replace($current, $vector["text"], (string)$number);
            }
            $results[] = (float)self::calculate($current);
        }

        // Without format the result is a number, unless the Vector is resolved as a text
        if (!$withFormat && !$vectors[0]["asText"]) {
            return (string)self::getNumber($results, -1);
        }
        return self::format($vectors[0]["template"], $results);
    }



    /**
     * Replaces each Condition in the text with its content when it is true, or removes it
     * @param string                        $text
     * @param (Closure(string):string)|null $getValue Optional.
     * @return string
     */
    public static function resolveConditions(string $text, ?Closure $getValue = null): string {
        $conditions = Strings::getAllMatches($text, self::ConditionPattern);
        foreach ($conditions as $condition) {
            // The matches also have the content of each one, without the "if"
            if (!Strings::startsWith($condition, "if(")) {
                continue;
            }

            // Only the first comma is used, so the content can have more commas
            $content = Strings::stripStartEnd($condition, "if(", ")");
            if (!Strings::contains($content, ",")) {
                continue;
            }

            $expression = Strings::substringBefore($content, ",", useFirst: true);
            $result     = Strings::trim(Strings::substringAfter($content, ",", useFirst: true));

            // Only the expression is given to get its value, so the content keeps its
            // variables and calculations for whoever replaces them next
            $value   = $getValue !== null ? $getValue($expression) : $expression;
            $replace = self::isTrue($value) ? $result : self::EmptyCondition;
            $text    = Strings::replace($text, $condition, $replace);
        }
        return self::removeEmptyConditions($text);
    }



    /**
     * Returns a Vector with the given template and numbers
     * @param string      $template
     * @param list<float> $numbers
     * @param bool        $resolveAsText Optional.
     * @return string
     */
    public static function createVector(
        string $template,
        array $numbers,
        bool $resolveAsText = false,
    ): string {
        // The template is escaped, so its text can not end the Vector or split it
        $template = Strings::replace($template, self::VectorChars, self::VectorEscapes);
        $mode     = $resolveAsText ? self::TextMode : self::NumberMode;
        $parts    = [ $mode, $template, ...Arrays::toStrings($numbers) ];
        return "[[" . Strings::join($parts, "|") . "]]";
    }

    /**
     * Returns the template of the given Vector with its numbers
     * @param string $vector
     * @return string
     */
    public static function formatVector(string $vector): string {
        $vectors = self::getVectors($vector);
        if (count($vectors) === 0) {
            return $vector;
        }
        return self::format($vectors[0]["template"], $vectors[0]["numbers"]);
    }

    /**
     * Replaces each Vector in the given text with its last number, or its text
     * @param string $text
     * @return string
     */
    public static function resolveVectors(string $text): string {
        foreach (self::getVectors($text) as $vector) {
            $value = (string)self::getNumber($vector["numbers"], -1);
            if ($vector["asText"]) {
                $value = self::format($vector["template"], $vector["numbers"]);
            }
            $text = Strings::replace($text, $vector["text"], $value);
        }
        return $text;
    }

    /**
     * Returns the Vectors in the given text
     * @param string $text
     * @return list<array{text:string,asText:bool,template:string,numbers:list<float>}>
     */
    private static function getVectors(string $text): array {
        $result = [];
        foreach (Strings::getAllMatches($text, self::VectorPattern) as $match) {
            // The matches also have the content of each one, without the brackets
            if (!Strings::startsWith($match, "[[")) {
                continue;
            }

            $content = Strings::stripStartEnd($match, "[[", "]]");
            $parts   = Strings::split($content, "|");
            $mode    = $parts[0] ?? "";
            if ($mode !== self::NumberMode && $mode !== self::TextMode) {
                continue;
            }

            $template = Strings::replace($parts[1] ?? "", self::VectorEscapes, self::VectorChars);
            $numbers  = [];
            foreach (Arrays::slice($parts, 2) as $part) {
                $numbers[] = (float)$part;
            }
            $result[] = [
                "text"     => $match,
                "asText"   => $mode === self::TextMode,
                "template" => $template,
                "numbers"  => $numbers,
            ];
        }
        return $result;
    }

    /**
     * Returns true if the given value of a Condition is true
     * @param string $value
     * @return bool
     */
    private static function isTrue(string $value): bool {
        $result = Strings::trim($value);
        if ($result === "") {
            return false;
        }
        if (is_numeric($result)) {
            return (float)$result !== 0.0;
        }
        return true;
    }

    /**
     * Removes the Conditions that are false, and the lines that are empty without them
     * @param string $text
     * @return string
     */
    private static function removeEmptyConditions(string $text): string {
        if (!Strings::contains($text, self::EmptyCondition)) {
            return $text;
        }

        $lines  = Strings::split($text, "\n");
        $result = [];
        foreach ($lines as $line) {
            if (!Strings::contains($line, self::EmptyCondition)) {
                $result[] = $line;
                continue;
            }

            // Skip the line only when there is nothing else in it
            $content = Strings::replace($line, self::EmptyCondition, "");
            if (Strings::trim($content) !== "") {
                $result[] = $content;
            }
        }
        return Strings::join($result, "\n");
    }

    /**
     * Returns the number at the given position, or the last one when there is none
     * @param list<float> $numbers
     * @param int         $index
     * @return float
     */
    private static function getNumber(array $numbers, int $index): float {
        return $numbers[$index] ?? $numbers[count($numbers) - 1] ?? 0.0;
    }

    /**
     * Returns the given template with each {n} replaced by the number at that position
     * @param string      $template
     * @param list<float> $numbers
     * @return string
     */
    private static function format(string $template, array $numbers): string {
        return Strings::replaceCallback(
            string:   $template,
            pattern:  "/\{(\d+)\}/",
            callback: function (array $match) use ($numbers): string {
                $index = isset($match[1]) ? (int)$match[1] : 0;
                return Numbers::formatPrice($numbers[$index] ?? 0);
            },
        );
    }
}
