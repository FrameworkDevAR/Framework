<?php
namespace Tests\Utils;

use Framework\Utils\Calculator;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CalculatorTest extends TestCase {

    #[DataProvider("providerCalculate")]
    public function testCalculate(string $expression, int|float $expected): void {
        $this->assertEquals($expected, Calculator::calculate($expression));
    }

    public static function providerCalculate(): array {
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

    #[DataProvider("providerCalculateVectors")]
    public function testCalculateVectors(string $expression, bool $withFormat, string $expected): void {
        $this->assertSame($expected, Calculator::calculateVectors($expression, $withFormat));
    }

    public static function providerCalculateVectors(): array {
        return [
            "no vector"               => [ "3 * 2", false, "6" ],
            "no vector with format"   => [ "1.5 * 3", true, "4,50" ],
            "a vector"                => [ "[[N|~~{0}~~ {1}|120|95]] * 2", false, "190" ],
            "a vector with format"    => [ "[[N|~~{0}~~ {1}|120|95]] * 2", true, "~~240,00~~ 190,00" ],
            "a vector of one"         => [ "[[N|USD {0}|120]] + 0.5", true, "USD 120,50" ],
            "two vectors"             => [ "[[N|{0} {1}|1|2]] + [[N|{0} {1}|10|20]]", true, "11,00 22,00" ],
            "a shorter vector"        => [ "[[N|{0} {1}|1|2]] + [[N|{0}|10]]", true, "11,00 12,00" ],
            "the format of the first" => [ "[[N|A{0} B{1}|1|2]] + [[N|{1}|10|20]]", true, "A11,00 B22,00" ],
            "a text vector"           => [ "[[T|A {0}, B {1}|1|2]] * 3", false, "A 3,00, B 6,00" ],
            "an escaped template"     => [ "[[N|&#91;A&#124;B&#93; {0}|2]] * 2", true, "[A|B] 4,00" ],
            "an unknown mode"         => [ "[[X|{0}|2]] * 2", false, "4" ],
            "an accented template"    => [ "[[T|Pensión {0}|150]] * 2", false, "Pensión 300,00" ],
        ];
    }

    #[DataProvider("providerResolveConditions")]
    public function testResolveConditions(string $text, string $expected): void {
        $this->assertSame($expected, Calculator::resolveConditions($text));
    }

    public static function providerResolveConditions(): array {
        return [
            "a true number"          => [ "a if(1, yes) b", "a yes b" ],
            "a false number"         => [ "a if(0, yes) b", "a  b" ],
            "a decimal zero"         => [ "a if(0.0, yes) b", "a  b" ],
            "a text"                 => [ "if(John, Hi John)", "Hi John" ],
            "an empty expression"    => [ "x if( , yes)", "x " ],
            "commas in the content"  => [ "if(1, a, b, c)", "a, b, c" ],
            "parentheses inside"     => [ "if(1, (a) and calc(2 * 3))", "(a) and calc(2 * 3)" ],
            "no comma"               => [ "if(1)", "if(1)" ],
            "a false line"           => [ "a\nif(0, b)\nc", "a\nc" ],
            "a false one with text"  => [ "a\nx if(0, b)\nc", "a\nx \nc" ],
            "an accented content"    => [ "if(1, Gracias, José)", "Gracias, José" ],
            "a word ending in if"    => [ "motif(1, x)", "motif(1, x)" ],
        ];
    }

    public function testTheValueOfAConditionComesFromTheGivenFunction(): void {
        $getValue = fn (string $expression): string => $expression === "{name}" ? "" : "1";
        $text     = "if({name}, Hi {name})\nif({other}, Bye)";
        $this->assertSame("Bye", Calculator::resolveConditions($text, $getValue));
    }

    #[DataProvider("providerCreateVector")]
    public function testCreateVector(string $template, array $numbers, bool $asText, string $expected): void {
        $this->assertSame($expected, Calculator::createVector($template, $numbers, $asText));
    }

    public static function providerCreateVector(): array {
        return [
            "two numbers" => [ "~~{0}~~ {1}", [ 120.0, 95.5 ], false, "[[N|~~{0}~~ {1}|120|95.5]]" ],
            "one number"  => [ "USD {0}", [ 120.0 ], false, "[[N|USD {0}|120]]" ],
            "no numbers"  => [ "none", [], false, "[[N|none]]" ],
            "as a text"   => [ "A {0}", [ 1.0 ], true, "[[T|A {0}|1]]" ],
            "escaped"     => [ "[A|B] {0}", [ 1.0 ], false, "[[N|&#91;A&#124;B&#93; {0}|1]]" ],
        ];
    }

    #[DataProvider("providerFormatVector")]
    public function testFormatVector(string $vector, string $expected): void {
        $this->assertSame($expected, Calculator::formatVector($vector));
    }

    public static function providerFormatVector(): array {
        return [
            "two numbers"  => [ "[[N|~~USD {0}~~ USD {1}|120|95]]", "~~USD 120,00~~ USD 95,00" ],
            "one number"   => [ "[[N|USD {0}|1500000]]", "USD 1.500.000" ],
            "as a text"    => [ "[[T|A {0}|1]]", "A 1,00" ],
            "escaped"      => [ Calculator::createVector("[A|B] {0}", [ 1.0 ]), "[A|B] 1,00" ],
            "not a vector" => [ "USD 120", "USD 120" ],
        ];
    }

    #[DataProvider("providerResolveVectors")]
    public function testResolveVectors(string $text, string $expected): void {
        $this->assertSame($expected, Calculator::resolveVectors($text));
    }

    public static function providerResolveVectors(): array {
        return [
            "a vector"      => [ "[[N|~~{0}~~ {1}|120|95]] * 2", "95 * 2" ],
            "two vectors"   => [ "[[N|{0}|1]] and [[N|{0} {1}|2|3]]", "1 and 3" ],
            "no numbers"    => [ "[[N|none]]", "0" ],
            "a text vector" => [ "Prices: [[T|A {0}, B {1}|1|2]]", "Prices: A 1,00, B 2,00" ],
            "no vector"     => [ "a [b] c", "a [b] c" ],
            "no mode"       => [ "a [[b]] c", "a [[b]] c" ],
        ];
    }
}
