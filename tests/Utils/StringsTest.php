<?php
// spell-checker: ignore  abbab, bcdef, preabc, tést, xyxyxy, áñgel, Áñgel
namespace Tests\Utils;

use Framework\Date\Date;
use Framework\Enum\Enum;
use Framework\Enum\IsEnum;
use Framework\Utils\Strings;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

enum TestStringEnum implements Enum {
    use IsEnum;

    case None;
    case String;
    case Number;
}

class StringsTest extends TestCase {
    use TestHelpers;

    #[DataProvider("providerIsString")]
    public function testIsString(mixed $value, bool $expected): void {
        $this->assertEquals($expected, Strings::isString($value));
    }

    public static function providerIsString(): array {
        return [
            "empty"   => [ "", true ],
            "char"    => [ "x", true],
            "numeric" => [ "123", true ],
            "integer" => [ 1, false ],
            "null"    => [ null, false ],
            "boolean" => [ false, false ],
            "object"  => [ new stdClass(), false ],
        ];
    }


    #[DataProvider("providerIsValid")]
    public function testIsValid(mixed $string, bool $expected): void {
        $this->assertEquals($expected, Strings::isValid($string));
    }

    public static function providerIsValid(): array {
        return [
            "empty"   => [ "", false ],
            "space"   => [ " ", false ],
            "char"    => [ "x", true],
            "numeric" => [ "123", true ],
            "spaced"  => [ " xx ", true ],
            "null"    => [ null, false ],
            "boolean" => [ false, false ],
            "object"  => [ new stdClass(), false ],
        ];
    }


    #[DataProvider("providerToString")]
    public function testToString(mixed $value, string $expected): void {
        $this->assertEquals($expected, Strings::toString($value));
    }

    public static function providerToString(): array {
        return [
            "string"  => [ "x", "x" ],
            "integer" => [ 123, "123" ],
            "float"   => [ 12.3, "12.3" ],
            "object"  => [ new stdClass(), "" ],
            "date"    => [ Date::createTime(1, 1, 2020, 12, 34, 56), "2020-01-01 12:34:56" ],
            "enum"    => [ TestStringEnum::String, "String" ],
        ];
    }


    #[DataProvider("providerTrim")]
    public function testTrim(mixed $value, string $expected): void {
        $this->assertEquals($expected, Strings::trim($value));
    }

    public static function providerTrim(): array {
        return [
            "space both" => [ " a ", "a" ],
            "space left" => [ " a", "a" ],
            "integer"    => [ 1, "1" ],
            "float"      => [ 1.2, "1.2" ],
            "null"       => [ null, "" ],
        ];
    }


    #[DataProvider("providerNormalized")]
    public function testNormalized(mixed $value, string $expected): void {
        $this->assertEquals($expected, Strings::normalized($value));
    }

    public static function providerNormalized(): array {
        return [
            "with whitespace" => [ " x\r\nline ", "x\nline" ],
            "single char"     => [ "a", "a" ],
            "null"            => [ null, "" ],
        ];
    }


    #[DataProvider("providerLength")]
    public function testLength(mixed $value, int $expected): void {
        $this->assertEquals($expected, Strings::length($value));
    }

    public static function providerLength(): array {
        return [
            "utf8 char" => [ "oración", 7 ],
            "emoji"     => [ "😄", 1 ],
            "mixed"     => [ "hello 😄", 7 ],
            "empty"     => [ "", 0 ],
        ];
    }


    #[DataProvider("providerIsEqual")]
    public function testIsEqual(mixed $value, mixed $compare, bool $ignoreCase, bool $trim, bool $expected): void {
        $this->assertEquals($expected, Strings::isEqual($value, $compare, $ignoreCase, $trim));
    }

    public static function providerIsEqual(): array {
        return [
            "default insensitive trim" => [ " A ", "a", true, true, true ],
            "case sensitive"           => [ " A ", "a", false, true, false ],
            "no trim"                  => [ " A ", "a", true, false, false ],
            "case sensitive no trim"   => [ "A ", "A", false, false, false ],
            "numeric conversion"       => [ 123, " 123 ", true, true, true ],
            "date conversion"          => [ Date::createTime(1, 1, 2020, 12, 34, 56), "2020-01-01 12:34:56", true, true, true ],
            "enum conversion"          => [ TestStringEnum::String, "String", true, true, true ],
            "null empty string"        => [ null, "", true, true, true ],
            "false empty string"       => [ false, "", true, true, true ],
            "different strings"        => [ "A", "B", false, true, false ],
        ];
    }


    #[DataProvider("providerEquals")]
    public function testEquals(mixed $value, array $compare, bool $expected): void {
        $this->assertEquals($expected, Strings::equals($value, ...$compare));
    }

    public static function providerEquals(): array {
        return [
            "empty equals empty"       => [ "", [ "" ], true ],
            "one equals one"           => [ "one", [ "one" ], true ],
            "one equals in list"       => [ "one", [ "two", "one" ], true ],
            "one equals first in list" => [ "one", [ "one", "two" ], true ],

            "empty not dash"           => [ "", [ "-" ], false ],
            "empty not in list"        => [ "", [ "two", "three" ], false ],
            "one not in list"          => [ "one", [ "two", "three" ], false ],
        ];
    }


    #[DataProvider("providerEqualsCaseInsensitive")]
    public function testEqualsCaseInsensitive(mixed $value, array $compare, bool $expected): void {
        $this->assertEquals($expected, Strings::equalsCaseInsensitive($value, ...$compare));
    }

    public static function providerEqualsCaseInsensitive(): array {
        return [
            "empty equals empty"      => [ "", [ "" ], true ],
            "one equals case variant" => [ "One", [ "oNe" ], true ],
            "one equals in list"      => [ "one", [ "one", "two" ], true ],
            "one equals case in list" => [ "One", [ "oNe", "two" ], true ],

            "empty not dash"          => [ "", [ "-" ], false ],
            "empty not in list"       => [ "", [ "two", "three" ], false ],
            "one not two"             => [ "One", [ "Two" ], false ],
            "one not in three list"   => [ "One", [ "Two", "Three" ], false ],
        ];
    }


    #[DataProvider("providerContains")]
    public function testContains(string $value, string|array $needle, bool $ignoreCase, bool $atLeastOne, bool $expected): void {
        $this->assertEquals($expected, Strings::contains($value, $needle, $ignoreCase, $atLeastOne));
    }

    public static function providerContains(): array {
        return [
            "one ci ok"     => [ "Hello World", "world", true, true, true ],
            "one cs no"     => [ "Hello World", "world", false, true, false ],

            "arr any ok"    => [ "abc", [ "x", "b" ], false, true, true ],
            "arr any no"    => [ "abc", [ "x", "y" ], false, true, false ],

            "arr all ok"    => [ "abc", [ "a", "b" ], false, false, true ],
            "arr all no"    => [ "abc", [ "a", "x" ], false, false, false ],

            "arr ci all ok" => [ "AbCd", [ "a", "C" ], true, false, true ],
            "arr one ok"    => [ "abc", [ "b" ], false, true, true ],
        ];
    }


    #[DataProvider("providerStartsWith")]
    public function testStartsWith(string $value, array $needles, bool $expected): void {
        $this->assertEquals($expected, Strings::startsWith($value, ...$needles));
    }

    public static function providerStartsWith(): array {
        return [
            "single match"        => [ "prefix_value", [ "prefix" ], true ],
            "single no match"     => [ "nope", [ "pre" ], false ],
            "multiple any match"  => [ "prefix_value", [ "no", "prefix" ], true ],
            "multiple none match" => [ "nope", [ "pre", "xx" ], false ],
        ];
    }


    #[DataProvider("providerStartsWithCaseInsensitive")]
    public function testStartsWithCaseInsensitive(string $value, array $needles, bool $expected): void {
        $this->assertEquals($expected, Strings::startsWithCaseInsensitive($value, ...$needles));
    }

    public static function providerStartsWithCaseInsensitive(): array {
        return [
            "single match"       => [ "AbC", [ "a" ], true ],
            "multiple any match" => [ "AbC", [ "x", "A" ], true ],
            "multiple none"      => [ "AbC", [ "x", "y" ], false ],
        ];
    }


    #[DataProvider("providerEndsWith")]
    public function testEndsWith(string $value, array $needles, bool $expected): void {
        $this->assertEquals($expected, Strings::endsWith($value, ...$needles));
    }

    public static function providerEndsWith(): array {
        return [
            "single match"        => [ "file.php", [ ".php" ], true ],
            "single no match"     => [ "file.txt", [ ".php" ], false ],
            "multiple any match"  => [ "index.html", [ ".php", ".html" ], true ],
            "case sensitive no"   => [ "readme.MD", [ ".md", ".txt" ], false ],
            "case sensitive yes"  => [ "readme.md", [ ".md", ".txt" ], true ],
            "multiple none match" => [ "file", [ ".php", ".html" ], false ],
        ];
    }


    #[DataProvider("providerEndsWithCaseInsensitive")]
    public function testEndsWithCaseInsensitive(string $value, array $needles, bool $expected): void {
        $this->assertEquals($expected, Strings::endsWithCaseInsensitive($value, ...$needles));
    }

    public static function providerEndsWithCaseInsensitive(): array {
        return [
            "single match"        => [ "AbC", [ "c" ], true ],
            "multiple any match"  => [ "readme.MD", [ ".md", ".txt" ], true ],
            "multiple html match" => [ "index.HTML", [ ".php", ".html" ], true ],
            "multiple none match" => [ "file", [ ".php", ".txt" ], false ],
        ];
    }


    #[DataProvider("providerMatch")]
    public function testMatch(string $value, string $pattern, bool $expected): void {
        $result = $this->runWithSuppressedWarnings(
            fn() => Strings::match($value, $pattern),
            suppress: true,
        );
        $this->assertEquals($expected, $result);
    }

    public static function providerMatch(): array {
        return [
            "anchored numeric match"   => [ "123", "/^[0-9]+$/", true ],
            "anchored numeric no"      => [ "a1", "/^[0-9]+$/", false ],
            "unanchored digit search"  => [ "abc123", "/\\d+/", true ],
            "case insensitive match"   => [ "HELLO", "/hello/i", true ],
            "pattern matches nothing"  => [ "b", "/^a$/", false ],
            "invalid regexp plain"     => [ "anything", "invalid", false ],
            "invalid regexp delimited" => [ "anything", "/]invalid/", false ],
        ];
    }


    // The pattern may be invalid, which is a warning preg_match writes
    #[DataProvider("providerGetMatch")]
    public function testGetMatch(string $value, string $pattern, string $expected): void {
        $result = $this->runWithSuppressedWarnings(
            fn() => Strings::getMatch($value, $pattern),
            suppress: true,
        );
        $this->assertSame($expected, $result);
    }

    public static function providerGetMatch(): array {
        return [
            "the group is returned"   => [ "abc123", "/([0-9]+)/", "123" ],
            "the first group wins"    => [ "2024-05", "/([0-9]+)-([0-9]+)/", "2024" ],
            "only the group returns"  => [ "id: 42", "/id: ([0-9]+)/", "42" ],
            "the match is not first"  => [ "a1b22", "/([0-9]+)/", "1" ],
            "a pattern with no group" => [ "abc", "/abc/", "" ],
            "an unmatched group"      => [ "ab", "/a(x)?b/", "" ],
            "nothing matches"         => [ "abc", "/([0-9]+)/", "" ],
            "an empty string"         => [ "", "/([0-9]+)/", "" ],
            "invalid regexp"          => [ "anything", "/]invalid/", "" ],
        ];
    }


    #[DataProvider("providerGetAllMatches")]
    public function testGetAllMatches(string $value, string $pattern, array $expected): void {
        $this->assertEquals($expected, Strings::getAllMatches($value, $pattern));
    }

    public static function providerGetAllMatches(): array {
        return [
            "simple digit matches" => [ "a1b22", "/\\d+/", [ "1", "22" ] ],
            "no matches"           => [ "abc", "/\\d+/", [] ],

            "with groups"          => [ "abbab", "/(a)(b+)/", [
                "abb", "ab", // full matches
                "a", "a",    // group 1 matches
                "bb", "b"    // group 2 matches
            ] ],

            "single group"         => [ "a1b22", "/(\\d+)/", [
                "1", "22", // full matches
                "1", "22"  // group matches
            ] ],

            "invalid regexp"       => [ "anything", "/]invalid/", [] ],
        ];
    }


    #[DataProvider("providerOnlyOneCharacter")]
    public function testOnlyOneCharacter(string $value, string $character, bool $expected): void {
        $this->assertEquals($expected, Strings::onlyOneCharacter($value, $character));
    }

    public static function providerOnlyOneCharacter(): array {
        return [
            "valid repeated char"      => [ "aaa", "a", true ],
            "empty string"             => [ "", "a", false ],
            "multi char target"        => [ "aaa", "aa", false ],
            "contains other character" => [ "aba", "a", false ],
            "contains spaces"          => [ "a b a", "a", false ],
        ];
    }


    #[DataProvider("providerCompare")]
    public function testCompare(string $value, string $compare, bool $orderAsc, bool $ignoreCase, int $expectedSign): void {
        $actual = Strings::compare($value, $compare, $orderAsc, $ignoreCase);
        $this->assertSame($expectedSign, $actual <=> 0);
    }

    public static function providerCompare(): array {
        return [
            // basic ordering
            "b gt a asc" => [ "b", "a", true, false, 1 ],
            "a lt b asc" => [ "a", "b", true, false, -1 ],

            // equal strings -> zero
            "same eq same" => [ "same", "same", true, false, 0 ],

            // reverse ordering when orderAsc = false
            "b lt a desc" => [ "b", "a", false, false, -1 ],
            "a gt b desc" => [ "a", "b", false, false, 1 ],

            // case-insensitive comparisons
            "a lt B ci" => [ "a", "B", true, true, -1 ],
            "B gt a ci" => [ "B", "a", true, true, 1 ],

            // combination: orderAsc = false with case-insensitive
            "a gt B desc ci" => [ "a", "B", false, true, 1 ],
            "B lt a desc ci" => [ "B", "a", false, true, -1 ],
        ];
    }


    #[DataProvider("providerGetLetter")]
    public function testGetLetter(int $index, bool $uppercase, string $expected): void {
        $this->assertEquals($expected, Strings::getLetter($index, $uppercase));
    }

    public static function providerGetLetter(): array {
        return [
            "upper a"          => [ 0, true, "A" ],
            "upper c"          => [ 2, true, "C" ],
            "lower a"          => [ 0, false, "a" ],
            "lower c"          => [ 2, false, "c" ],
            "invalid high"     => [ 200, true, "" ],
            "invalid negative" => [ -1, true, "" ],
        ];
    }


    #[DataProvider("providerGetNumber")]
    public function testGetNumber(string $value, int $expected): void {
        $this->assertEquals($expected, Strings::getNumber($value));
    }

    public static function providerGetNumber(): array {
        return [
            "upper a"        => [ "A", 1 ],
            "lower c"        => [ "c", 3 ],
            "invalid dash"   => [ "-", 0 ],
            "invalid double" => [ "AA", 0 ],
            "invalid cc"     => [ "cc", 0 ],
        ];
    }


    #[DataProvider("providerRepeat")]
    public function testRepeat(string $value, int $count, string $expected): void {
        $this->assertEquals($expected, Strings::repeat($value, $count));
    }

    public static function providerRepeat(): array {
        return [
            "single char x3" => [ "x", 3, "xxx" ],
            "multi char x3"  => [ "xy", 3, "xyxyxy" ],
            "zero count"     => [ "x", 0, "" ],
            "negative count" => [ "x", -1, "" ],
        ];
    }



    #[DataProvider("providerCountOccurrences")]
    public function testCountOccurrences(string $value, string $needle, int $expected): void {
        $this->assertEquals($expected, Strings::countOccurrences($value, $needle));
    }

    public static function providerCountOccurrences(): array {
        return [
            "simple"         => [ "Hello World", "o", 2 ],
            "words"          => [ "one two three two", "two", 2 ],
            "overlapping"    => [ "aaaa", "aa", 2 ],
            "larger needle"  => [ "abc", "abcd", 0 ],
            "no occurrences" => [ "abc", "x", 0 ],
            "empty string"   => [ "", "o", 0 ],
            "empty needle"   => [ "Hello World", "", 0 ],
        ];
    }


    public function testRandom(): void {
        $r = Strings::random(5);
        $this->assertIsString($r);
        $this->assertEquals(5, strlen($r));
    }


    #[DataProvider("providerRandomChar")]
    public function testRandomChar(string $chars, string|array $expected): void {
        $actual = Strings::randomChar($chars);
        if (is_array($expected)) {
            $this->assertContains($actual, $expected);
        } else {
            $this->assertEquals($expected, $actual);
        }
    }

    public static function providerRandomChar(): array {
        return [
            "empty"  => [ "", "" ],
            "single" => [ "a", "a" ],
            "many"   => [ "abc", [ "a", "b", "c" ] ],
        ];
    }


    #[DataProvider("providerRandomCode")]
    public function testRandomCode(?int $length, ?string $set, int $expectedLength, ?string $expectedPattern, ?string $expectedValue = null): void {
        if ($length === null && $set === null) {
            $code = Strings::randomCode();
        } elseif ($set === null) {
            $code = Strings::randomCode($length);
        } else {
            $code = Strings::randomCode($length, $set);
        }

        $this->assertIsString($code);
        $this->assertEquals($expectedLength, strlen($code));

        if ($expectedValue !== null) {
            $this->assertEquals($expectedValue, $code);
        }

        if ($expectedPattern !== null && $code !== "") {
            $this->assertMatchesRegularExpression($expectedPattern, $code);
        }
    }

    public static function providerRandomCode(): array {
        return [
            "default values"     => [ null, null, 8, '/^[a-zA-Z0-9]+$/', null ],
            "letters and digits" => [ 6, "ld", 6, '/^[a-z0-9]+$/', null ],
            "letters any case"   => [ 10, "a", 10, '/^[a-zA-Z]+$/', null ],
            "lowercase only"     => [ 8, "l", 8, '/^[a-z]+$/', null ],
            "uppercase only"     => [ 8, "u", 8, '/^[A-Z]+$/', null ],
            "digits only"        => [ 8, "d", 8, '/^[0-9]+$/', null ],
            "symbols only"       => [ 8, "s", 8, '/^[!@#\$%&\*\?]+$/', null ],
            "empty set"          => [ 8, "", 0, null, "" ],
            "invalid set"        => [ 8, "x", 0, null, "" ],
        ];
    }

    #[DataProvider("providerRandomCodeSkipsSimilar")]
    public function testRandomCodeSkipsTheSimilarChars(string $set, string $pattern): void {
        // The chars are drawn at random, so many codes are made for a missing skip to show
        for ($i = 0; $i < 200; $i += 1) {
            $code = Strings::randomCode(30, $set, skipSimilar: true);
            $this->assertEquals(30, strlen($code));
            $this->assertMatchesRegularExpression($pattern, $code);
            $this->assertDoesNotMatchRegularExpression('/[0Oo1Il]/', $code);
        }
    }

    public static function providerRandomCodeSkipsSimilar(): array {
        return [
            "uppercase and digits" => [ "ud", '/^[A-Z2-9]+$/' ],
            "letters and digits"   => [ "lud", '/^[a-zA-Z2-9]+$/' ],
            "any case letters"     => [ "a", '/^[a-zA-Z]+$/' ],
            "digits only"          => [ "d", '/^[2-9]+$/' ],
        ];
    }


    #[DataProvider("providerRandomIsNotSeeded")]
    public function testTheRandomValuesDoNotFollowTheMtSeed(callable $generate): void {
        // These drew from array_rand and str_shuffle before, which share the
        // Mt19937 stream that each process seeds with only 32 bits, so the same
        // seed gave the same value and every code was one of 4 billion
        $values = [];
        for ($i = 0; $i < 2; $i += 1) {
            mt_srand(42);
            $value = "";
            for ($j = 0; $j < 20; $j += 1) {
                $value .= $generate();
            }
            $values[] = $value;
        }
        $this->assertNotSame($values[0], $values[1]);
    }

    public static function providerRandomIsNotSeeded(): array {
        return [
            "random"     => [ fn () => Strings::random(20) ],
            "randomChar" => [ fn () => Strings::randomChar("abcdefghijklmnopqrstuvwxyz0123456789") ],
            "randomCode" => [ fn () => Strings::randomCode(15) ],
        ];
    }


    #[DataProvider("providerToNumber")]
    public function testToNumber(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toNumber($value));
    }

    public static function providerToNumber(): array {
        return [
            "mixed alnum" => [ "a1b2", "12" ],
            "no digits"   => [ "abc", "" ],
        ];
    }


    #[DataProvider("providerReplace")]
    public function testReplace(string $value, string|array $search, string|array|null $replace, string $expected): void {
        if (is_array($search) && $replace === null) {
            $this->assertEquals($expected, Strings::replace($value, $search));
        } else {
            $this->assertEquals($expected, Strings::replace($value, $search, $replace));
        }
    }

    public static function providerReplace(): array {
        return [
            // simple scalar replacement
            "scalar single" => [ "foo", "foo", "bar", "bar" ],

            // a search with no replacement is a call the analysis refuses,
            // and the runtime answers it with the string untouched
            "scalar no replacement" => [ "hello", "l", null, "hello" ],

            "scalar multi"  => [ "foo foo", "foo", "bar", "bar bar" ],

            // mapping replacement: keys replaced by their values
            "mapping match"    => [ "a:b", [ "a" => "one", "b" => "two" ], null, "one:two" ],
            "mapping no match" => [ "abc", [ "x" => "y" ], null, "abc" ],

            // array search with single replacement string
            "array search single replace" => [ "abc", [ "a", "b" ], "X", "XXc" ],

            // array search with array replacement
            "array search array replace" => [ "abc", [ "a", "b" ], [ "x", "y" ], "xyc" ],

            // empty replace
            "empty replace" => [ "abc", "a", "", "bc" ],
        ];
    }


    #[DataProvider("providerReplaceStart")]
    public function testReplaceStart(string $value, string $search, string $replace, string $expected): void {
        $this->assertEquals($expected, Strings::replaceStart($value, $search, $replace));
    }

    public static function providerReplaceStart(): array {
        return [
            "basic ok"      => [ "fooBAR", "foo", "bar", "barBAR" ],
            "no prefix"     => [ "fooBAR", "no", "x", "fooBAR" ],
            "full match"    => [ "foo", "foo", "bar", "bar" ],
            "empty in"      => [ "", "foo", "bar", "" ],
            "case ok"       => [ "FooBAR", "Foo", "bar", "barBAR" ],
            "case no match" => [ "fooBAR", "Foo", "bar", "fooBAR" ],
        ];
    }


    #[DataProvider("providerReplaceEnd")]
    public function testReplaceEnd(string $value, string $search, string $replace, string $expected): void {
        $this->assertEquals($expected, Strings::replaceEnd($value, $search, $replace));
    }

    public static function providerReplaceEnd(): array {
        return [
            "basic suffix replacement" => [ "fooBAR", "BAR", "BAZ", "fooBAZ" ],
            "no suffix noop"           => [ "fooBAR", "no", "x", "fooBAR" ],
            "full string replacement"  => [ "bar", "bar", "baz", "baz" ],
            "empty input"              => [ "", "x", "y", "" ],
            "case sensitive match"     => [ "fooBar", "Bar", "BAZ", "fooBAZ" ],
            "case sensitive no match"  => [ "fooBAR", "Bar", "BAZ", "fooBAR" ],
        ];
    }


    #[DataProvider("providerReplacePattern")]
    public function testReplacePattern(string $value, string|array $pattern, string|array $replace, ?int $limit, string $expected): void {
        if ($limit === null) {
            $actual = Strings::replacePattern($value, $pattern, $replace);
        } else {
            $actual = Strings::replacePattern($value, $pattern, $replace, $limit);
        }
        $this->assertEquals($expected, $actual);
    }

    public static function providerReplacePattern(): array {
        return [
            "vowel rep"      => [ "hello", "/[eo]/", "_", null, "h_ll_" ],
            "digit rep"      => [ "a1b22", "/\\d+/", "N", null, "aNbN" ],
            "limit first"    => [ "a1b22", "/\\d+/", "N", 1, "aNb22" ],
            "group back ref" => [ "a1b2", "/(\\d)/", "[$1]", null, "a[1]b[2]" ],
            "ci rep"         => [ "hello", "/h/i", "J", null, "Jello" ],
            "arr pat arr rep"=> [ "hello123", [ "/[eo]/", "/\\d+/" ], [ "_", "N" ], null, "h_ll_N" ],
        ];
    }


    #[DataProvider("providerReplaceCallback")]
    public function testReplaceCallback(string $value, string|array $pattern, callable $callback, ?int $limit, string $expected): void {
        if ($limit === null) {
            $actual = Strings::replaceCallback($value, $pattern, $callback);
        } else {
            $actual = Strings::replaceCallback($value, $pattern, $callback, $limit);
        }
        $this->assertEquals($expected, $actual);
    }

    public static function providerReplaceCallback(): array {
        return [
            "basic wrap matches" => [
                "a1b2",
                "/(\\d+)/",
                function($m) { return "[" . $m[0] . "]"; },
                null,
                "a[1]b[2]"
            ],
            "limit_first_only" => [
                "a1b22",
                "/(\\d+)/",
                function($m) { return "[" . $m[0] . "]"; },
                1,
                "a[1]b22"
            ],
            "double_numeric_values" => [
                "a2b3",
                "/(\\d+)/",
                function($m) { return (string)((int)$m[0] * 2); },
                null,
                "a4b6"
            ],
            "array_patterns" => [
                "a1e2",
                ["/[ae]/", "/(\\d)/"],
                function($m) {
                    if (ctype_digit($m[0])) {
                        return "{" . $m[0] . "}";
                    }
                    return strtoupper($m[0]);
                },
                null,
                "A{1}E{2}"
            ],
        ];
    }


    #[DataProvider("providerStripStart")]
    public function testStripStart(string $value, array $needles, string $expected): void {
        $this->assertEquals($expected, Strings::stripStart($value, ...$needles));
    }

    public static function providerStripStart(): array {
        return [
            "basic start"       => [ "pre_value", [ "pre_" ], "value" ],
            "multi first match" => [ "prefix_value", [ "prefix_", "pre" ], "value" ],
            "no match"          => [ "prefix_value", [ "x", "y" ], "prefix_value" ],
            "empty input"       => [ "", [ "pre" ], "" ],
            "order short first" => [ "prefix_value", [ "pre", "prefix_" ], "fix_value" ],
        ];
    }


    #[DataProvider("providerStripEnd")]
    public function testStripEnd(string $value, array $needles, string $expected): void {
        $this->assertEquals($expected, Strings::stripEnd($value, ...$needles));
    }

    public static function providerStripEnd(): array {
        return [
            "basic end"   => [ "pre_suf", [ "_suf" ], "pre" ],
            "multi any"   => [ "file.php", [ ".php", ".txt" ], "file" ],
            "none match"  => [ "file.php", [ ".x", ".y" ], "file.php" ],
            "empty input" => [ "", [ "x" ], "" ],
            "short first" => [ "file.txt", [ "t", ".txt" ], "file.tx" ],
        ];
    }


    #[DataProvider("providerStripStartEnd")]
    public function testStripStartEnd(string $value, string $start, string $end, string $expected): void {
        $this->assertEquals($expected, Strings::stripStartEnd($value, $start, $end));
    }

    public static function providerStripStartEnd(): array {
        return [
            "basic start end removal" => [ "[mid]", "[", "]", "mid" ],
            "longer delimiters"       => [ "<<text>>", "<<", ">>", "text" ],
            "empty input"             => [ "", "[", "]", "" ],
            "only start matches"      => [ "pre_foo", "pre_", "]", "foo" ],
            "only end matches"        => [ "bar_suf", "[", "_suf", "bar" ],
        ];
    }


    #[DataProvider("providerPadLeft")]
    public function testPadLeft(string $value, int $length, string $needle, string $expected): void {
        $this->assertEquals($expected, Strings::padLeft($value, $length, $needle));
    }

    public static function providerPadLeft(): array {
        return [
            "basic numeric padding"  => [ "1", 3, "0", "001" ],
            "length less than value" => [ "abcd", 3, "0", "abcd" ],
            "length equal value"     => [ "abcd", 4, "0", "abcd" ],
            "multi character needle" => [ "1", 5, "ab", "abab1" ],
            "default space padding"  => [ "x", 3, " ", "  x" ],
        ];
    }


    #[DataProvider("providerPadRight")]
    public function testPadRight(string $value, int $length, ?string $needle, string $expected): void {
        if ($needle === null) {
            $this->assertEquals($expected, Strings::padRight($value, $length));
        } else {
            $this->assertEquals($expected, Strings::padRight($value, $length, $needle));
        }
    }

    public static function providerPadRight(): array {
        return [
            "basic right padding"    => [ "1", 3, " ", "1  " ],
            "length less than value" => [ "hello", 3, " ", "hello" ],
            "length equal value"     => [ "hello", 5, " ", "hello" ],
            "multi character needle" => [ "1", 4, "xy", "1xyx" ],
            "default space padding"  => [ "x", 3, null, "x  " ],
        ];
    }


    #[DataProvider("providerAddPrefix")]
    public function testAddPrefix(string $value, string $prefix, string $expected): void {
        $this->assertEquals($expected, Strings::addPrefix($value, $prefix));
    }

    public static function providerAddPrefix(): array {
        return [
            "empty string"           => [ "", "pre_", "" ],
            "empty prefix"           => [ "x", "", "x" ],
            "missing prefix"         => [ "x", "pre_", "pre_x" ],
            "no duplicate prefix"    => [ "pre_x", "pre_", "pre_x" ],
            "multi character prefix" => [ "x", "Mr ", "Mr x" ],
        ];
    }


    #[DataProvider("providerAddSuffix")]
    public function testAddSuffix(string $value, string $suffix, string $expected): void {
        $this->assertEquals($expected, Strings::addSuffix($value, $suffix));
    }

    public static function providerAddSuffix(): array {
        return [
            "empty string"           => [ "", "_suf", "" ],
            "empty suffix"           => [ "x", "", "x" ],
            "missing suffix"         => [ "x", "_suf", "x_suf" ],
            "no duplicate suffix"    => [ "x_suf", "_suf", "x_suf" ],
            "multi character suffix" => [ "x", " Jr.", "x Jr." ],
        ];
    }


    #[DataProvider("providerAddPrefixSuffix")]
    public function testAddPrefixSuffix(string $value, string $prefix, string $suffix, string $expected): void {
        $this->assertEquals($expected, Strings::addPrefixSuffix($value, $prefix, $suffix));
    }

    public static function providerAddPrefixSuffix(): array {
        return [
            "empty"    => [ "", "pre_", "_suf", "" ],
            "add both" => [ "x", "pre_", "_suf", "pre_x_suf" ],
            "has pre"  => [ "pre_x", "pre_", "_suf", "pre_x_suf" ],
            "has suf"  => [ "x_suf", "pre_", "_suf", "pre_x_suf" ],
            "has both" => [ "pre_x_suf", "pre_", "_suf", "pre_x_suf" ],
        ];
    }


    #[DataProvider("providerSubstring")]
    public function testSubstring(string $value, int $start, ?int $length, bool $asUtf8, string $expected): void {
        if ($length === null) {
            $actual = Strings::substring($value, $start, asUtf8: $asUtf8);
        } else {
            $actual = Strings::substring($value, $start, $length, $asUtf8);
        }
        $this->assertEquals($expected, $actual);
    }

    public static function providerSubstring(): array {
        return [
            "basic with length"    => [ "abcdef", 1, 3, false, "bcd" ],
            "without length"       => [ "abcdef", 1, null, false, "bcdef" ],
            "negative start"       => [ "abcdef", -3, null, false, "def" ],
            "utf8 aware substring" => [ "tést", 1, 2, true, "és" ],
        ];
    }


    #[DataProvider("providerSubstringAfter")]
    public function testSubstringAfter(string $value, string $needle, bool $useFirst, string $expected): void {
        $this->assertEquals($expected, Strings::substringAfter($value, $needle, $useFirst));
    }

    public static function providerSubstringAfter(): array {
        return [
            "default uses last"      => [ "a.b.c", ".", false, "c" ],
            "use first occurrence"   => [ "a.b.c", ".", true, "b.c" ],
            "needle at end"          => [ "a.", ".", false, "" ],
            "needle not found"       => [ "abc", ".", false, "abc" ],
            "empty needle use first" => [ "abc", "", true, "abc" ],
        ];
    }


    #[DataProvider("providerSubstringBefore")]
    public function testSubstringBefore(string $value, string $needle, ?bool $useFirst, string $expected): void {
        if ($useFirst === null) {
            $actual = Strings::substringBefore($value, $needle);
        } else {
            $actual = Strings::substringBefore($value, $needle, $useFirst);
        }
        $this->assertEquals($expected, $actual);
    }

    public static function providerSubstringBefore(): array {
        return [
            "default uses first" => [ "a.b.c", ".", null, "a" ],
            "use last"           => [ "a.b.c", ".", false, "a.b" ],
            "needle not found"   => [ "abc", ".", true, "abc" ],
            "needle at start"    => [ ".a", ".", true, "" ],
            "empty needle"       => [ "abc", "", true, "" ],
        ];
    }


    #[DataProvider("providerSubstringBetween")]
    public function testSubstringBetween(string $value, string $start, string $end, string $expected): void {
        $this->assertEquals($expected, Strings::substringBetween($value, $start, $end));
    }

    public static function providerSubstringBetween(): array {
        return [
            "basic between"      => [ "x[start]mid[end]y", "[start]", "[end]", "mid" ],
            "longer delimiters"  => [ "<<text>>", "<<", ">>", "text" ],
            "missing delimiters" => [ "nope", "[", "]", "nope" ],
            "empty string"       => [ "", "[", "]", "" ],
        ];
    }


    #[DataProvider("providerSplit")]
    public function testSplit(mixed $value, string $needle, bool $trim, bool $skipEmpty, array $expected): void {
        $this->assertEquals($expected, Strings::split($value, $needle, trim: $trim, skipEmpty: $skipEmpty));
    }

    public static function providerSplit(): array {
        return [
            "basic split trim skip empty" => [ "a,,b", ",", true, true, [ "a", "b" ] ],
            "empty string"                => [ "", ",", true, true, [] ],
            "empty needle"                => [ "abc", "", true, true, [] ],
            "array input unchanged"       => [ [ "x", "y" ], ",", true, true, [ "x", "y" ] ],
            "needle not present"          => [ "abc", "|", true, true, [ "abc" ] ],

            "raw no trim no skip"         => [ " a , , b ", ",", false, false, [ " a ", " ", " b " ] ],
            "raw trim no skip"            => [ " a , , b ", ",", true, false, [ "a", "", "b" ] ],
            "raw no trim skip"            => [ " a , , b ", ", ", false, true, [ " a ", "b " ] ],
            "raw trim skip"               => [ " a , , b ", ",", true, true, [ "a", "b" ] ],

            "multi character needle"      => [ "a--b--c", "--", true, true, [ "a", "b", "c" ] ],

            "trailing sep keep empty"     => [ "a,b,", ",", false, false, [ "a", "b", "" ] ],
            "trailing sep skip empty"     => [ "a,b,", ",", false, true, [ "a", "b" ] ],

            "needle equals full keep"     => [ ",", ",", false, false, [ "", "" ] ],
            "needle equals full skip"     => [ ",", ",", false, true, [] ],
        ];
    }


    #[DataProvider("providerSplitToWords")]
    public function testSplitToWords(string $value, array $expectedContains, ?array $expectedExact = null): void {
        $words = Strings::splitToWords($value);
        foreach ($expectedContains as $expectedWord) {
            $this->assertContains($expectedWord, $words);
        }
        if ($expectedExact !== null) {
            $this->assertEquals($expectedExact, $words);
        }
    }

    public static function providerSplitToWords(): array {
        return [
            "hello world" => [ "Hello, world!", [ "Hello", "world" ], null ],
            "punctuation" => [ "Wait... what?", [ "Wait", "what" ], null ],
            "empty"       => [ "", [], [] ],
        ];
    }


    #[DataProvider("providerJoin")]
    public function testJoin(mixed $value, ?string $glue, ?bool $withoutEmpty, string $expected): void {
        if ($glue === null && $withoutEmpty === null) {
            $actual = Strings::join($value);
        } elseif ($glue === null) {
            $actual = Strings::join($value, withoutEmpty: $withoutEmpty);
        } elseif ($withoutEmpty === null) {
            $actual = Strings::join($value, $glue);
        } else {
            $actual = Strings::join($value, $glue, withoutEmpty: $withoutEmpty);
        }
        $this->assertEquals($expected, $actual);
    }

    public static function providerJoin(): array {
        return [
            "basic with glue"      => [ [ "a", "b" ], ", ", null, "a, b" ],
            "without glue"         => [ [ "a", "b" ], null, null, "ab" ],
            "without empty"        => [ [ "a", "", "b" ], ",", true, "a,b" ],

            "numeric array"        => [ [ 1, 2 ], ", ", null, "1, 2" ],
            "numeric with zero"    => [ [ 1, 0, 2 ], ", ", true, "1, 2" ],
            "float array"          => [ [ 1.2, 2.3 ], ", ", null, "1.2, 2.3" ],

            "non array string"     => [ "x", null, null, "x" ],
            "non array non string" => [ 123, null, null, "" ],
        ];
    }


    #[DataProvider("providerJoinKeys")]
    public function testJoinKeys(mixed $value, string $expected): void {
        $this->assertEquals($expected, Strings::joinKeys($value));
    }

    public static function providerJoinKeys(): array {
        return [
            "assoc keys"        => [ [ "a" => 1, "b" => 2 ], "ab" ],
            "list numeric keys" => [ [ 1, 2 ], "01" ],
            "string input"      => [ "x", "x" ],
            "non array input"   => [ 123, "" ],
        ];
    }


    #[DataProvider("providerJoinValues")]
    public function testJoinValues(mixed $value, string $key, string $glue, string $expected): void {
        $this->assertEquals($expected, Strings::joinValues($value, $key, $glue));
    }

    public static function providerJoinValues(): array {
        return [
            "basic join"           => [ [[ "n" => 1 ], [ "n" => 2 ]], "n", ", ", "1, 2" ],
            "missing key entry"    => [ [[ "n" => 1 ], []], "n", ", ", "1, " ],
            "string input"         => [ "x", "n", ", ", "x" ],
            "non array non string" => [ 123, "n", ", ", "" ],
        ];
    }


    #[DataProvider("providerMerge")]
    public function testMerge(string $first, string $second, string $glue, string $expected): void {
        $this->assertEquals($expected, Strings::merge($first, $second, $glue));
    }

    public static function providerMerge(): array {
        return [
            "both values"  => [ "A", "B", " ", "A B" ],
            "first empty"  => [ "", "B", " ", "B" ],
            "second empty" => [ "A", "", " ", "A" ],
            "both empty"   => [ "", "", " ", "" ],
        ];
    }


    #[DataProvider("providerToLowerCase")]
    public function testToLowerCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toLowerCase($value));
    }

    public static function providerToLowerCase(): array {
        return [
            "hello world" => [ "Hello World", "hello world" ],
            "mixed case"  => [ "Mixed CASE", "mixed case" ],
            "empty"       => [ "", "" ],
            "single char" => [ "A", "a" ],
        ];
    }


    #[DataProvider("providerLowerCaseFirst")]
    public function testLowerCaseFirst(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::lowerCaseFirst($value));
    }

    public static function providerLowerCaseFirst(): array {
        return [
            "basic"       => [ "Hello", "hello" ],
            "empty"       => [ "", "" ],
            "single char" => [ "H", "h" ],
        ];
    }


    #[DataProvider("providerToUpperCase")]
    public function testToUpperCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toUpperCase($value));
    }

    public static function providerToUpperCase(): array {
        return [
            "hello world" => [ "hello world", "HELLO WORLD" ],
            "mixed case"  => [ "Mixed case", "MIXED CASE" ],
            "empty"       => [ "", "" ],
            "single char" => [ "a", "A" ],
        ];
    }


    #[DataProvider("providerUpperCaseFirst")]
    public function testUpperCaseFirst(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::upperCaseFirst($value));
    }

    public static function providerUpperCaseFirst(): array {
        return [
            "basic"       => [ "hello", "Hello" ],
            "empty"       => [ "", "" ],
            "single char" => [ "h", "H" ],
        ];
    }


    #[DataProvider("providerToTitleCase")]
    public function testToTitleCase(string $value, string $expected): void {
        $this->assertSame($expected, Strings::toTitleCase($value));
    }

    public static function providerToTitleCase(): array {
        return [
            "each word"       => [ "hello world", "Hello World" ],
            "lowercases rest" => [ "aBc dEf", "Abc Def" ],
            "from upper"      => [ "HELLO WORLD", "Hello World" ],
            "unicode"         => [ "áñgel maría", "Áñgel María" ],
            "single word"     => [ "name", "Name" ],
            "empty"           => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsConstantCase")]
    public function testIsConstantCase(string $value, bool $expected): void {
        $this->assertEquals($expected, Strings::isConstantCase($value));
    }

    public static function providerIsConstantCase(): array {
        return [
            "upper only"      => [ "ABCDEF", true ],
            "with underscore" => [ "ABC_DEF", true ],

            "mixed case"      => [ "AbC_DEF", false ],
            "with dash"       => [ "ABC-def", false ],
            "empty"           => [ "", false ],
            "numeric only"    => [ "123", false ],
        ];
    }


    #[DataProvider("providerToConstantCase")]
    public function testToConstantCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toConstantCase($value));
    }

    public static function providerToConstantCase(): array {
        return [
            // already in constant case -> should remain unchanged
            "already constant" => [ "SOME_CONSTANT", "SOME_CONSTANT" ],

            // converts snake_case to CONSTANT_CASE
            "snake case" => [ "some_constant", "SOME_CONSTANT" ],

            // converts kebab-case to CONSTANT_CASE
            "kebab case" => [ "some-constant", "SOME_CONSTANT" ],

            // converts camelCase to CONSTANT_CASE
            "camel case" => [ "someConstant", "SOME_CONSTANT" ],

            // converts PascalCase to CONSTANT_CASE
            "pascal case" => [ "SomeConstant", "SOME_CONSTANT" ],
            "pascal with acronym mid" => [ "SomeHEYData", "SOME_HEY_DATA" ],
            "pascal with acronym start" => [ "HEYSomeData", "HEY_SOME_DATA" ],

            // converts various delimiters to CONSTANT_CASE
            "space delimiter" => [ "Hello world", "HELLO_WORLD" ],
            "dot delimiter"   => [ "hello.world", "HELLO_WORLD" ],
            "colon delimiter" => [ "hello:world", "HELLO_WORLD" ],
            "semi delimiter"  => [ "hello;world", "HELLO_WORLD" ],

            // edge cases
            "single letter" => [ "A", "A" ],
            "empty"         => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsSnakeCase")]
    public function testIsSnakeCase(string $value, bool $expected): void {
        $this->assertEquals($expected, Strings::isSnakeCase($value));
    }

    public static function providerIsSnakeCase(): array {
        return [
            "valid hello world"  => [ "hello_world", true ],
            "valid single char"  => [ "a", true ],

            "invalid uppercase"  => [ "Hello_world", false ],
            "invalid camel case" => [ "helloWorld", false ],
            "invalid space"      => [ "hello world", false ],
            "invalid dash"       => [ "hello-world", false ],
            "invalid empty"      => [ "", false ],
        ];
    }


    #[DataProvider("providerToSnakeCase")]
    public function testToSnakeCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toSnakeCase($value));
    }

    public static function providerToSnakeCase(): array {
        return [
            // already in snake_case -> should remain unchanged
            "already snake" => [ "some_hey", "some_hey" ],

            // converts CONSTANT_CASE to snake_case
            "constant case" => [ "SOME_HEY", "some_hey" ],

            // converts kebab-case to snake_case
            "kebab case" => [ "some-hey", "some_hey" ],

            // converts camelCase to snake_case
            "camel case" => [ "someHey", "some_hey" ],

            // converts PascalCase to snake_case
            "pascal case" => [ "SomeHey", "some_hey" ],
            "pascal with acronym mid" => [ "SomeHEYData", "some_hey_data" ],
            "pascal with acronym start" => [ "HEYSomeData", "hey_some_data" ],

            // converts various delimiters to snake_case
            "space delimiter" => [ "Hello world", "hello_world" ],
            "dash delimiter" => [ "hello-world", "hello_world" ],
            "dot delimiter" => [ "hello.world", "hello_world" ],
            "colon delimiter" => [ "hello:world", "hello_world" ],
            "semi delimiter" => [ "hello;world", "hello_world" ],

            // edge cases
            "single letter" => [ "A", "a" ],
            "empty" => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsKebabCase")]
    public function testIsKebabCase(string $value, bool $expected): void {
        $this->assertEquals($expected, Strings::isKebabCase($value));
    }

    public static function providerIsKebabCase(): array {
        return [
            "valid hello world"  => [ "hello-world", true ],
            "valid single char"  => [ "a", true ],

            "invalid uppercase"  => [ "Hello-world", false ],
            "invalid camel case" => [ "helloWorld", false ],
            "invalid space"      => [ "hello world", false ],
            "invalid snake case" => [ "hello_world", false ],
            "invalid empty"      => [ "", false ],
        ];
    }


    #[DataProvider("providerToKebabCase")]
    public function testToKebabCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toKebabCase($value));
    }

    public static function providerToKebabCase(): array {
        return [
            // already in kebab-case -> should remain unchanged
            "already kebab" => [ "some-hey", "some-hey" ],

            // converts CONSTANT_CASE to kebab-case
            "constant case" => [ "SOME_HEY", "some-hey" ],

            // converts snake_case to kebab-case
            "snake case" => [ "some_hey", "some-hey" ],

            // converts camelCase to kebab-case
            "camel case" => [ "someHey", "some-hey" ],

            // converts PascalCase to kebab-case
            "pascal case" => [ "SomeHey", "some-hey" ],
            "pascal with acronym mid" => [ "SomeHEYData", "some-hey-data" ],
            "pascal with acronym start" => [ "HEYSomeData", "hey-some-data" ],

            // converts various delimiters to kebab-case
            "space delimiter" => [ "Hello world", "hello-world" ],
            "dot delimiter" => [ "hello.world", "hello-world" ],
            "colon delimiter" => [ "hello:world", "hello-world" ],
            "semi delimiter" => [ "hello;world", "hello-world" ],

            // edge cases
            "single letter" => [ "A", "a" ],
            "empty" => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsPascalCase")]
    public function testIsPascalCase(string $value, bool $expected): void {
        $this->assertEquals($expected, Strings::isPascalCase($value));
    }

    public static function providerIsPascalCase(): array {
        return [
            "hello world"         => [ "HelloWorld", true ],
            "ab"                  => [ "Ab", true ],
            "pascal with acronym" => [ "SomeHEYData", true ],
            "acronym prefix"      => [ "HEYSomeData", true ],

            "camel case invalid"  => [ "helloWorld", false ],
            "space invalid"       => [ "Hello World", false ],
            "dash invalid"        => [ "Hello-World", false ],
            "empty invalid"       => [ "", false ],
        ];
    }


    #[DataProvider("providerToPascalCase")]
    public function testToPascalCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toPascalCase($value));
    }

    public static function providerToPascalCase(): array {
        return [
            // already in PascalCase -> should remain unchanged
            "already pascal" => [ "HelloWorld", "HelloWorld" ],

            // converts CONSTANT_CASE to PascalCase
            "constant case" => [ "SOME_HEY", "SomeHey" ],

            // converts snake_case to PascalCase
            "snake case" => [ "some_hey", "SomeHey" ],

            // converts kebab-case to PascalCase
            "kebab case" => [ "some-hey", "SomeHey" ],

            // converts camelCase to PascalCase
            "camel case" => [ "someHey", "SomeHey" ],
            "camel with acronym" => [ "someHEYData", "SomeHEYData" ],

            // converts various delimiters to PascalCase
            "single word" => [ "hello", "Hello" ],
            "space delimiter" => [ "Hello world", "HelloWorld" ],
            "dot delimiter" => [ "hello.world", "HelloWorld" ],
            "colon delimiter" => [ "hello:world", "HelloWorld" ],
            "semi delimiter" => [ "hello;world", "HelloWorld" ],

            // edge cases
            "single letter" => [ "a", "A" ],
            "empty" => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsCamelCase")]
    public function testIsCamelCase(string $value, bool $expected): void {
        $this->assertEquals($expected, Strings::isCamelCase($value));
    }

    public static function providerIsCamelCase(): array {
        return [
            "valid camel"       => [ "helloWorld", true ],
            "valid single char" => [ "a", true ],

            "invalid pascal"    => [ "HelloWorld", false ],
            "invalid space"     => [ "hello world", false ],
            "invalid kebab"     => [ "hello-world", false ],
            "invalid empty"     => [ "", false ],
        ];
    }


    #[DataProvider("providerToCamelCase")]
    public function testToCamelCase(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toCamelCase($value));
    }

    public static function providerToCamelCase(): array {
        return [
            // already in camelCase -> should remain unchanged
            "already camel" => [ "helloWorld", "helloWorld" ],

            // converts CONSTANT_CASE to camelCase
            "constant case" => [ "SOME_HEY", "someHey" ],

            // converts snake_case to camelCase
            "snake case" => [ "some_hey", "someHey" ],

            // converts kebab-case to camelCase
            "kebab case" => [ "some-hey", "someHey" ],

            // converts PascalCase to camelCase
            "pascal case" => [ "SomeHey", "someHey" ],

            // converts various delimiters to camelCase
            "single word" => [ "Hello", "hello" ],
            "space delimiter" => [ "Hello world", "helloWorld" ],
            "dash delimiter" => [ "hello-world", "helloWorld" ],
            "dot delimiter" => [ "hello.world", "helloWorld" ],
            "colon delimiter" => [ "hello:world", "helloWorld" ],
            "semi delimiter" => [ "hello;world", "helloWorld" ],

            // edge cases
            "single letter" => [ "A", "a" ],
            "empty" => [ "", "" ],
        ];
    }


    #[DataProvider("providerToHtml")]
    public function testToHtml(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::toHtml($value));
    }

    public static function providerToHtml(): array {
        return [
            "line break"   => [ "a\nb", "a<br>b" ],
            "single break" => [ "\n", "<br>" ],
            "plain text"   => [ "ab", "ab" ],
            "empty"        => [ "", "" ],
        ];
    }


    #[DataProvider("providerRemoveHtml")]
    public function testRemoveHtml(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::removeHtml($value));
    }

    public static function providerRemoveHtml(): array {
        return [
            "basic"               => [ "<b>abc</b>", "abc" ],
            "empty"               => [ "", "" ],
            "style at start"      => [ "<style>body{}</style>abc", "abc" ],
            "style without end"   => [ "<style>body{}abc", "<style>body{}abc" ],
            "style in the middle" => [ "pre<style>body{}</style>abc", "preabc" ],
        ];
    }


    #[DataProvider("providerHasHtml")]
    public function testHasHtml(string $value, bool $expected): void {
        $this->assertSame($expected, Strings::hasHtml($value));
    }

    public static function providerHasHtml(): array {
        return [
            "basic"             => [ "<b>abc</b>", true ],
            "line break"        => [ "<br>", true ],
            "empty tag"         => [ "a<>b", true ],
            "style block"       => [ "<style>body{}</style>abc", true ],
            "style without end" => [ "<style>body{}abc", false ],
            "plain text"        => [ "abc", false ],
            "an entity"         => [ "&amp;", false ],
            "a lone less than"  => [ "5 < 6 and 7 > 6", false ],
            "empty"             => [ "", false ],
        ];
    }


    #[DataProvider("providerDecodeHtml")]
    public function testDecodeHtml(string $value, string $expected): void {
        $this->assertEquals($expected, Strings::decodeHtml($value));
    }

    public static function providerDecodeHtml(): array {
        return [
            "ampersand entity"  => [ "&amp;", "&" ],
            "less than entity"  => [ "&lt;", "<" ],
            "numeric A"         => [ "&#65;", "A" ],
            "numeric e acute"   => [ "&#233;", "é" ],
            "hex A"             => [ "&#x41;", "A" ],
            "hex e acute upper" => [ "&#xE9;", "é" ],
        ];
    }


    #[DataProvider("providerMakeShort")]
    public function testMakeShort(string $value, int $length, string $expected, ?bool $asUtf8 = null): void {
        if ($asUtf8 === null) {
            $actual = Strings::makeShort($value, $length);
        } else {
            $actual = Strings::makeShort($value, $length, $asUtf8);
        }
        $this->assertEquals($expected, $actual);
    }

    public static function providerMakeShort(): array {
        return [
            "len0 original"   => [ "anything", 0, "anything", null ],
            "short unchanged" => [ "short", 10, "short", null ],
            "trunc utf8 def"  => [ "abcdef", 5, "ab...", null ],
            "nl first line"   => [ "first\nsecond", 10, "first", null ],
            "utf8 exact"      => [ "tést", 4, "tést", null ],
            "utf8 trunc"      => [ "tést", 2, "tés...", null ],
            "non utf8 trunc"  => [ "abcdefgh", 5, "abcde", false ],
        ];
    }


    #[DataProvider("providerIsShort")]
    public function testIsShort(string $value, int $length, bool $expected): void {
        $this->assertEquals($expected, Strings::isShort($value, $length));
    }

    public static function providerIsShort(): array {
        return [
            "long string"  => [ str_repeat("x", 50), 10, true ],
            "short string" => [ "abcdefgh", 3, true ],
        ];
    }


    #[DataProvider("providerIsAlphaNum")]
    public function testIsAlphaNum(string $value, bool $allowDashUnderscore, ?int $length, bool $expected): void {
        if ($length === null) {
            $actual = Strings::isAlphaNum($value, $allowDashUnderscore);
        } else {
            $actual = Strings::isAlphaNum($value, $allowDashUnderscore, $length);
        }
        $this->assertSame($expected, $actual);
    }

    public static function providerIsAlphaNum(): array {
        return [
            "basic"      => [ "abc123", false, null, true ],
            "space"      => [ "abc 123", false, null, false ],
            "letters"    => [ "ABC", false, null, true ],
            "numbers"    => [ "123", false, null, true ],
            "dash no"    => [ "abc-123", false, null, false ],
            "under no"   => [ "abc_123", false, null, false ],
            "dash yes"   => [ "abc-123", true, null, true ],
            "under yes"  => [ "abc_123", true, null, true ],
            "mixed ok"   => [ "a-b_c", true, null, true ],
            "len ok"     => [ "abcd", false, 4, true ],
            "len no"     => [ "abcd", false, 3, false ],
            "sep len ok" => [ "a-b_c", true, 5, true ],
            "sep len no" => [ "a-b_c", false, 5, false ],
            "empty"      => [ "", false, null, false ],
        ];
    }


    #[DataProvider("providerSanitize")]
    public function testSanitize(string $value, bool $lowercase, bool $anal, string $expected): void {
        $this->assertEquals($expected, Strings::sanitize($value, lowercase: $lowercase, anal: $anal));
    }

    public static function providerSanitize(): array {
        return [
            "basic lowercase"    => [ "Hello!!", true, false, "hello" ],
            "anal mode"          => [ "Hello World!!", true, true, "hello-world" ],
            "preserve case"      => [ "Hello!!", false, false, "Hello" ],
            "accents preserved"  => [ "ÁÉÍ", true, false, "áéí" ],
            "anal accents ascii" => [ "Olé Niño", true, true, "ole-nino" ],
            "underscore removed" => [ "a_b c", true, false, "ab-c" ],
            "slash removed"      => [ "a/b c", true, false, "ab-c" ],
            "collapse spaces"    => [ "Many   Spaces   Here", true, false, "many-spaces-here" ],
        ];
    }


    #[DataProvider("providerHasEmoji")]
    public function testHasEmoji(string $value, bool $expected): void {
        $this->assertSame($expected, Strings::hasEmoji($value));
    }

    public static function providerHasEmoji(): array {
        return [
            "emoji in text"    => [ "hello 😄", true ],
            "single emoji"     => [ "😄", true ],
            "flag emoji"       => [ "Flags 🇺🇸 are cool", true ],
            "family emoji"     => [ "Family: 👨‍👩‍👧‍👦", true ],
            "skin tone emoji"  => [ "Skin tone 👍🏽", true ],
            "single skin tone" => [ "👍🏽", true ],
            "zwj emoji"        => [ "👩‍❤️‍👩", true ],
            "no emoji"         => [ "no emoji here", false ],
            "empty"            => [ "", false ],
        ];
    }


    #[DataProvider("providerIsOnlyEmojis")]
    public function testIsOnlyEmojis(string $value, bool $expected): void {
        $this->assertSame($expected, Strings::isOnlyEmojis($value));
    }

    public static function providerIsOnlyEmojis(): array {
        return [
            // valid emoji-only strings
            "double emoji"     => [ "😄😄", true ],
            "single emoji"     => [ "😄", true ],
            "emoji skin tone"  => [ "👍🏽", true ],

            // invalid cases
            "text with emoji"  => [ "hi 😄", false ],
            "emoji with space" => [ "😄 😄", false ],
            "emoji with text"  => [ "😄a", false ],
            "empty"            => [ "", false ],
        ];
    }


    #[DataProvider("providerConvertEncoding")]
    public function testConvertEncoding(string $value, string $expected): void {
        $actual = Strings::convertEncoding($value);

        $this->assertIsString($actual);
        $this->assertEquals($expected, $actual);
    }

    public static function providerConvertEncoding(): array {
        return [
            "raw accented character" => [ "é", "&eacute;" ],
            "named entity"           => [ "&eacute;", "&eacute;" ],
            "multi character"        => [ "Olé", "Ol&eacute;" ],
            "ascii only"             => [ "A", "A" ],
            "numeric entity"         => [ "&#233;", "&#233;" ],
        ];
    }


    #[DataProvider("providerBase64Encode")]
    public function testBase64Encode(string $input, string $expected): void {
        $this->assertEquals($expected, Strings::base64Encode($input));
    }

    public static function providerBase64Encode(): array {
        return [
            "simple ascii"  => [ "hi", "aGk=" ],
            "empty input"   => [ "", "" ],
            "with padding"  => [ "foobar", "Zm9vYmFy" ],
            "utf8 string"   => [ "tést", base64_encode("tést") ],
            "binary string" => [ "\x00\x01\xFF", base64_encode("\x00\x01\xFF") ],
            "round trip"    => [ "hello world", base64_encode("hello world") ],
        ];
    }


    #[DataProvider("providerBase64Decode")]
    public function testBase64Decode(string $input, string $expected): void {
        $this->assertEquals($expected, Strings::base64Decode($input));
    }

    public static function providerBase64Decode(): array {
        return [
            "simple ascii"      => [ base64_encode("hi"), "hi" ],
            "empty input"       => [ "", "" ],
            "invalid base64"    => [ "not-base64!!", "" ],
            "newline invalid"   => [ base64_encode("x") . "\n", "x" ],
            "utf8 round trip"   => [ base64_encode("tést"), "tést" ],
            "binary round trip" => [ base64_encode("\x00\x01\xFF"), "\x00\x01\xFF" ],
        ];
    }
}
