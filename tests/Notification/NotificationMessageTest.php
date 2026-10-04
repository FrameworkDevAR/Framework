<?php
namespace Tests\Notification;

use Tests\Notification\Fixture\TestNotification;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A Notification Message, with its texts by language
 */
class NotificationMessageTest extends TestCase {

    public function testTheCodeIsTheNameOfTheClassWithoutTheSuffix(): void {
        $this->assertSame("Test", TestNotification::getCode());
    }

    public function testThePropertiesHaveTheirDefaults(): void {
        $this->assertSame("A notification of the tests", TestNotification::$description);
        $this->assertSame(0, TestNotification::$version);
        $this->assertSame([], TestNotification::$variables);
    }

    /**
     * A language, and the texts written for it, which are the ones of the root when it has none
     * @param string $language
     * @param string $title
     * @param string $message
     * @return void
     */
    #[DataProvider("providerTexts")]
    public function testTheTextsAreTheOnesOfTheLanguage(string $language, string $title, string $message): void {
        $this->assertSame($title, TestNotification::getTitleText($language));
        $this->assertSame($message, TestNotification::getMessageText($language));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerTexts(): array {
        return [
            // The text is written indented inside the class, and comes out trimmed
            "the root one"   => [ "en", "Hello {{name}}", "You have a new message, {{name}}." ],
            "another one"    => [ "es", "Hola {{name}}", "Tienes un mensaje nuevo, {{name}}." ],
            "one it has not" => [ "pt", "Hello {{name}}", "You have a new message, {{name}}." ],
        ];
    }

    /**
     * A language and the data, and the texts rendered with it
     * @param string $language
     * @param string $name
     * @param string $title
     * @param string $message
     * @return void
     */
    #[DataProvider("providerRender")]
    public function testTheTextsAreRenderedWithTheData(
        string $language,
        string $name,
        string $title,
        string $message,
    ): void {
        $data = [ "name" => $name ];

        $this->assertSame($title, TestNotification::getTitle($language, $data));
        $this->assertSame($message, TestNotification::getMessage($language, $data));
    }

    /**
     * @return array<string,array{string,string,string,string}>
     */
    public static function providerRender(): array {
        return [
            "in english" => [ "en", "John", "Hello John", "You have a new message, John." ],
            "in spanish" => [ "es", "Ana", "Hola Ana", "Tienes un mensaje nuevo, Ana." ],
        ];
    }
}
