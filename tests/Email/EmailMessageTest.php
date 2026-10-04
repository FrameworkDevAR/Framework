<?php
namespace Tests\Email;

use Framework\Email\EmailMessage;
use Framework\Provider\Mustache;
use Framework\System\Config;
use Framework\System\EmailCode;
use Tests\Email\Fixture\TestEmail;
use Tests\Email\Fixture\TestTemplateEmail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Email Messages, an email written as a class with its texts inside
 */
class EmailMessageTest extends TestCase {

    public function testTheCodeIsTheNameOfTheClassWithoutTheSuffix(): void {
        $this->assertSame(EmailCode::Test, TestEmail::getCode());
        $this->assertSame(EmailCode::None, TestTemplateEmail::getCode());
    }

    public function testThePropertiesHaveTheirDefaults(): void {
        $this->assertSame("An email of the tests", TestEmail::$description);
        $this->assertNull(TestEmail::$template);
        $this->assertFalse(TestEmail::$sendNow);
        $this->assertSame("An email with a template", TestTemplateEmail::$description);
        $this->assertTrue(TestTemplateEmail::$sendNow);
    }

    #[DataProvider("providerGetTexts")]
    public function testTheTextsAreTheOnesOfTheLanguage(string $language, string $subject, string $body): void {
        $this->assertSame($subject, TestEmail::getSubjectText($language));
        $this->assertSame($body, TestEmail::getBodyText($language));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerGetTexts(): array {
        return [
            "trimmed lines" => [ "en", "Hello {{name}}", "Hello <b>{{name}}</b>!\n\nWelcome to {{site}}." ],
            "another one"   => [ "es", "Hola {{name}}", "Hola {{name}}" ],
            "the root one"  => [ "it", "Hello {{name}}", "Hello <b>{{name}}</b>!\n\nWelcome to {{site}}." ],
        ];
    }

    #[DataProvider("providerGetSubject")]
    public function testTheSubjectIsRenderedWithTheData(string $language, string $name, string $expected): void {
        $this->assertSame($expected, TestEmail::getSubject($language, [ "name" => $name ]));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerGetSubject(): array {
        return [
            "english" => [ "en", "John", "Hello John" ],
            "spanish" => [ "es", "Juan", "Hola Juan" ],
            "escaped" => [ "en", "<b>", "Hello &lt;b&gt;" ],
        ];
    }

    public function testAMessageIsMadeOfParagraphs(): void {
        $site = Config::getName();
        $body = TestEmail::getBody("en", [ "name" => "John" ]);

        $this->assertSame("Hello <b>John</b>!<br><br>Welcome to $site.", $body);
    }

    public function testATemplateIsRenderedWithTheTextsAndTheData(): void {
        $body = TestTemplateEmail::getBody("en", [ "namespace" => "Tests\\Email", "codes" => [ "Welcome" ] ]);

        $this->assertStringContainsString("namespace Tests\\Email;", $body);
        $this->assertStringContainsString("case Welcome;", $body);
    }

    #[DataProvider("providerPartials")]
    public function testThePartialsRenderTheirData(string $partial, array $data, string $expected): void {
        $partials = EmailMessage::getPartials();

        $this->assertArrayHasKey($partial, $partials);
        $result = Mustache::render("{{> $partial}}", $data, $partials);
        if ($expected === "") {
            $this->assertSame("", trim($result));
        } else {
            $this->assertStringContainsString($expected, $result);
        }
    }

    /**
     * @return array<string,array{string,array<string,mixed>,string}>
     */
    public static function providerPartials(): array {
        return [
            "a quote"   => [ "Quote", [ "quote" => "<i>Said</i>" ], "><i>Said</i></div>" ],
            "no quote"  => [ "Quote", [], "" ],
            "a button"  => [ "Button", [ "button" => [ "url" => "https://a.test", "text" => "Open" ] ], ">Open</a>" ],
            "no button" => [ "Button", [], "" ],
            "an avatar" => [ "Avatar", [ "avatar" => [ "initials" => "JD", "color" => "#000" ] ], ">JD</td>" ],
            "a footer"  => [ "Footer", [ "footer" => "The footer" ], ">The footer</div>" ],
        ];
    }

    public function testTheCardWrapsItsContent(): void {
        $partials = EmailMessage::getPartials();

        $result = Mustache::render("{{< Card}}{{\$content}}Inside{{/content}}{{/Card}}", [], $partials);
        $this->assertStringContainsString("<td style=\"padding:18px 20px\">Inside</td>", $result);
    }
}
