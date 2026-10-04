<?php
namespace Tests\Email;

use Framework\Email\EmailContent;
use Framework\Email\EmailMessage;
use Framework\System\EmailCode;

use Tests\Email\Fixture\TestEmail;
use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Email Contents, the text of each email in each language
 *
 * The texts come from the Email Messages, and the build of this repository has
 * none, so the migration is given the one of the tests, whose code is Test.
 */
class EmailContentLiveTest extends LiveTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `email_content`");
    }

    protected function tearDown(): void {
        TestEmail::$version = 0;
    }

    /**
     * Runs the migration with the given Email Messages, which prints what it did
     * @param array<string,class-string<EmailMessage>> $messages Optional.
     * @return string
     */
    private function migrateMessages(array $messages = [ "Test" => TestEmail::class ]): string {
        ob_start();
        try {
            EmailContent::migrateContents($messages);
        } finally {
            $output = ob_get_clean();
        }
        return (string)$output;
    }



    public function testAnEmailMessageBecomesARow(): void {
        $this->assertStringContainsString("Updated 1 emails", $this->migrateMessages());

        $content = EmailContent::get(EmailCode::Test, "en");
        $this->assertSame("Hello {{name}}", $content->subject);
        $this->assertSame("Hello <b>{{name}}</b>!\n\nWelcome to {{site}}.", $content->message);
        $this->assertSame("An email of the tests", $content->description);
        $this->assertSame("English", $content->languageName);
        $this->assertSame(0, $content->version);
        $this->assertSame(1, $content->position);
    }

    public function testThereIsNothingToUpdate(): void {
        // The migration of the build finds no Email Messages here
        ob_start();
        try {
            EmailContent::migrateData();
        } finally {
            $output = (string)ob_get_clean();
        }

        $this->assertStringContainsString("No emails updated", $output);
        $this->assertFalse(EmailContent::get(EmailCode::Test, "en")->exists());
    }

    /**
     * The version of the class and the one its row was left with, and what the row holds
     * after the class is migrated again
     * @param int    $classVersion
     * @param int    $rowVersion
     * @param string $subject
     * @param int    $version
     * @return void
     */
    #[DataProvider("providerVersions")]
    public function testTheVersionSaysWhichTextIsKept(
        int $classVersion,
        int $rowVersion,
        string $subject,
        int $version,
    ): void {
        TestEmail::$version = $classVersion;
        $this->migrateMessages();
        $this->query("UPDATE `email_content` SET `subject` = 'Edited', `version` = $rowVersion");

        $this->migrateMessages();

        $content = EmailContent::get(EmailCode::Test, "en");
        $this->assertSame($subject, $content->subject);
        $this->assertSame($version, $content->version);
        $this->assertSame(1, EmailContent::getEntityTotal());
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

    public function testAnEmailThatIsGoneIsRemoved(): void {
        $this->migrateMessages();
        $this->migrateMessages([]);

        $this->assertSame(0, EmailContent::getEntityTotal());
    }

    public function testALanguageFallsBackToTheRoot(): void {
        $this->migrateMessages();

        // Only en is set up here, and it is the root, so asking in any other
        // language comes back with it rather than with nothing
        $this->assertSame("Hello {{name}}", EmailContent::get(EmailCode::Test, "pt")->subject);
    }

    public function testNoCodeAtAllFindsNothing(): void {
        // The condition of an Enum drops the case that has no value, which
        // would leave the query asking for every code, so the code is looked
        // up by its name instead
        $this->migrateMessages();

        $this->assertFalse(EmailContent::get(EmailCode::None, "en")->exists());
    }



    /**
     * A message as it is written, and the HTML it is rendered into
     * @param string $message
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerRender")]
    public function testTheMessageIsRendered(string $message, string $expected): void {
        $this->assertSame($expected, EmailContent::render($message));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerRender(): array {
        return [
            "a line break"          => [ "One\nTwo", "One<br>Two" ],
            "a paragraph"           => [ "One\n\nTwo", "One<br><br>Two" ],
            "three breaks are two"  => [ "One\n\n\nTwo", "One<br><br>Two" ],
            "four breaks are two"   => [ "One\n\n\n\nTwo", "One<br><br>Two" ],
            "html is left as it is" => [ "<p>One</p>\n\n<p>Two</p>", "<p>One</p>\n\n<p>Two</p>" ],
            "an empty paragraph"    => [ "<p></p>\n\n<p>One</p>", "\n\n<p>One</p>" ],
            "nothing"               => [ "", "" ],
        ];
    }

    public function testTheMessageIsFilledIn(): void {
        $this->assertSame(
            "Hello Ana",
            EmailContent::render("Hello {{name}}", [ "name" => "Ana" ]),
        );
    }
}
