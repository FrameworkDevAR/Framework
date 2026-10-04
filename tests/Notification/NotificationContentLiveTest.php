<?php
namespace Tests\Notification;

use Framework\Notification\NotificationContent;
use Framework\Notification\NotificationMessage;

use Tests\Notification\Fixture\TestNotification;
use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Notification Contents, the text of each push in each language
 *
 * The texts come from the Notification Messages, and the build of this repository
 * has none, so the migration is given the one of the tests, whose code is Test.
 */
class NotificationContentLiveTest extends LiveTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `notification_content`");
    }

    protected function tearDown(): void {
        TestNotification::$version = 0;
    }

    /**
     * Runs the migration with the given Notification Messages, which prints what it did
     * @param array<string,class-string<NotificationMessage>> $messages Optional.
     * @return string
     */
    private function migrateMessages(array $messages = [ "Test" => TestNotification::class ]): string {
        ob_start();
        try {
            NotificationContent::migrateContents($messages);
        } finally {
            $output = ob_get_clean();
        }
        return (string)$output;
    }



    public function testANotificationMessageBecomesARow(): void {
        $this->assertStringContainsString("Updated 1 notifications", $this->migrateMessages());

        $content = NotificationContent::get("Test", "en");
        $this->assertSame("Hello {{name}}", $content->title);
        $this->assertSame("You have a new message, {{name}}.", $content->message);
        $this->assertSame("A notification of the tests", $content->description);
        $this->assertSame("English", $content->languageName);
        $this->assertSame(0, $content->version);
        $this->assertSame(1, $content->position);
    }

    public function testThereIsNothingToUpdate(): void {
        // The migration of the build finds no Notification Messages here
        ob_start();
        try {
            NotificationContent::migrateData();
        } finally {
            $output = (string)ob_get_clean();
        }

        $this->assertStringContainsString("No notifications updated", $output);
        $this->assertFalse(NotificationContent::get("Test", "en")->exists());
    }

    /**
     * The version of the class and the one its row was left with, and what the row holds
     * after the class is migrated again
     * @param int    $classVersion
     * @param int    $rowVersion
     * @param string $title
     * @param int    $version
     * @return void
     */
    #[DataProvider("providerVersions")]
    public function testTheVersionSaysWhichTextIsKept(
        int $classVersion,
        int $rowVersion,
        string $title,
        int $version,
    ): void {
        TestNotification::$version = $classVersion;
        $this->migrateMessages();
        $this->query("UPDATE `notification_content` SET `title` = 'Edited', `version` = $rowVersion");

        $this->migrateMessages();

        $content = NotificationContent::get("Test", "en");
        $this->assertSame($title, $content->title);
        $this->assertSame($version, $content->version);
        $this->assertSame(1, NotificationContent::getEntityTotal());
    }

    /**
     * @return array<string,array{int,int,string,int}>
     */
    public static function providerVersions(): array {
        return [
            "with no version the class is written again" => [ 0, 3, "Hello {{name}}", 0 ],
            "a lower one is replaced by the class"       => [ 2, 1, "Hello {{name}}", 2 ],
            "the same one is kept"                       => [ 2, 2, "Edited", 2 ],
            "a higher one is kept"                       => [ 2, 3, "Edited", 3 ],
        ];
    }

    /**
     * The version of the class and the title given, and what the row holds after the edit
     * @param int    $classVersion
     * @param string $title
     * @param bool   $isEdited
     * @param string $expected
     * @param int    $version
     * @return void
     */
    #[DataProvider("providerEdit")]
    public function testAContentWithAVersionIsEdited(
        int $classVersion,
        string $title,
        bool $isEdited,
        string $expected,
        int $version,
    ): void {
        TestNotification::$version = $classVersion;
        $this->migrateMessages();
        $content = NotificationContent::get("Test", "en");

        $this->assertSame($isEdited, NotificationContent::edit($content->id, $title, $content->message));

        $content = NotificationContent::get("Test", "en");
        $this->assertSame($expected, $content->title);
        $this->assertSame($version, $content->version);
    }

    /**
     * @return array<string,array{int,string,bool,string,int}>
     */
    public static function providerEdit(): array {
        return [
            "one with no version is left alone" => [ 0, "Edited", false, "Hello {{name}}", 0 ],
            "a change raises the version"       => [ 2, "Edited", true, "Edited", 3 ],
            "the same texts leave it as it was" => [ 2, "Hello {{name}}", true, "Hello {{name}}", 2 ],
        ];
    }

    public function testAContentThatIsNotThereIsNotEdited(): void {
        $this->assertFalse(NotificationContent::edit(0, "Edited", "Edited"));
    }

    public function testANotificationThatIsGoneIsRemoved(): void {
        $this->migrateMessages();
        $this->migrateMessages([]);

        $this->assertSame(0, NotificationContent::getEntityTotal());
    }

    public function testALanguageFallsBackToTheRoot(): void {
        $this->migrateMessages();

        // Only en is set up here, and it is the root, so asking in any other
        // language comes back with it rather than with nothing
        $this->assertSame("Hello {{name}}", NotificationContent::get("Test", "pt")->title);
    }



    /**
     * A message as it is written, and what it is rendered into
     * @param string              $message
     * @param array<string,mixed> $data
     * @param string              $expected
     * @return void
     */
    #[DataProvider("providerRender")]
    public function testTheMessageIsRendered(
        string $message,
        array $data,
        string $expected,
    ): void {
        $this->assertSame($expected, NotificationContent::render($message, $data));
    }

    /**
     * @return array<string,array{string,array<string,mixed>,string}>
     */
    public static function providerRender(): array {
        return [
            "a value"             => [ "Hello {{name}}", [ "name" => "Ana" ], "Hello Ana" ],
            "one that is missing" => [ "Hello {{name}}", [], "Hello " ],
            "nothing to fill in"  => [ "Hello", [ "name" => "Ana" ], "Hello" ],
            "the breaks are kept" => [ "One\nTwo", [], "One\nTwo" ],
            "nothing"             => [ "", [], "" ],
        ];
    }
}
