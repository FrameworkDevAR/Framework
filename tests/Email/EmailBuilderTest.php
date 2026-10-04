<?php
namespace Tests\Email;

use Framework\Application;
use Framework\Builder\Builder;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Email\EmailBuilder;
use Framework\Email\EmailMessage;
use Framework\Email\EmailSender;
use Framework\System\EmailProvider;
use Framework\Utils\Arrays;
use Framework\Intl\IntlConfig;
use Framework\File\Storage;
use Tests\Email\Fixture\TestEmail;
use Tests\Email\Fixture\TestEmailSender;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class EmailBuilderTest extends TestCase {
    use TestHelpers;

    private const FixtureDir = "tests/Email/.tmp_email_builder";

    private string $fixtureBase = "";

    /** @var array<string,mixed> */
    private array $original = [];


    protected function setUp(): void {
        foreach ([ "defaultLanguage", "emailsDir" ] as $prop) {
            $this->original[$prop] = $this->getPrivateStaticProperty(IntlConfig::class, $prop);
        }

        $this->fixtureBase = Application::getBasePath(self::FixtureDir);
        Storage::createDir($this->fixtureBase);
        IntlConfig::setEmailsDir(self::FixtureDir);
    }

    protected function tearDown(): void {
        foreach ($this->original as $prop => $value) {
            $this->setPrivateStaticProperty(IntlConfig::class, $prop, $value);
        }
        Storage::deleteDir($this->fixtureBase);
    }

    private function writeEmails(string $langCode, array $data): void {
        Storage::writeFile($this->fixtureBase . DIRECTORY_SEPARATOR . $langCode . ".json", json_encode($data));
    }


    #[DataProvider("providerCollectFiles")]
    public function testCollectFiles(array $files, array $expectedCodes): void {
        foreach ($files as $langCode => $data) {
            $this->writeEmails($langCode, $data);
        }

        $this->assertSame($expectedCodes, EmailBuilder::collectFiles());
    }

    public static function providerCollectFiles(): array {
        return [
            "multiple codes"      => [
                [
                    "en" => [
                        "WELCOME" => [ "subject" => "Welcome" ],
                        "RESET"   => [ "subject" => "Reset" ],
                    ],
                ],
                [ "WELCOME", "RESET" ],
            ],
            "single code"         => [
                [
                    "en" => [ "WELCOME" => [ "subject" => "Welcome" ] ],
                ],
                [ "WELCOME" ],
            ],
            "empty file"          => [ [ "en" => [] ], [] ],
            "missing file"        => [ [], [] ],
            "ignores other langs" => [
                [
                    "es" => [ "HOLA" => [ "subject" => "Hola" ] ],
                ],
                [],
            ],
        ];
    }

    public function testCollectEmails(): void {
        // The Framework has no Email Messages, so the codes are the ones of the files
        $this->writeEmails("en", [ "WELCOME" => [ "subject" => "Welcome" ] ]);

        $result = EmailBuilder::collectEmails();
        $this->assertSame([ "WELCOME" ], $result["codes"]);
        $this->assertSame([], $result["messages"]);
        $this->assertFalse($result["hasMessages"]);
        $this->assertSame(1, $result["total"]);
    }

    #[DataProvider("providerCollectMessages")]
    public function testCollectMessages(array $classes, array $expected): void {
        $classes = array_map(fn (string $class) => new DiscoveryClass($class), $classes);

        $this->assertSame($expected, EmailBuilder::collectMessages($classes));
    }

    public static function providerCollectMessages(): array {
        return [
            "a message"        => [ [ TestEmail::class ], [ "Test" => TestEmail::class ] ],
            "the base class"   => [ [ EmailMessage::class ], [] ],
            "another class"    => [ [ TestEmailSender::class ], [] ],
            "none"             => [ [], [] ],
        ];
    }

    /**
     * The Email Messages of an App, and what the enum written for them holds
     * @param array<string,string> $messages
     * @param list<string>         $expected
     * @return void
     */
    #[DataProvider("providerGeneratedCode")]
    public function testTheGeneratedCodeParses(array $messages, array $expected): void {
        $template  = Storage::readFile(Package::getBasePath("src/Email/Template/EmailCode.mu"));
        $templates = $this->getPrivateStaticProperty(Builder::class, "templates");
        $this->setPrivateStaticProperty(Builder::class, "templates", [ "EmailCode" => $template ]);

        try {
            $code = Builder::render("EmailCode", EmailBuilder::collectCodes([ "Reset" ], $messages) + [
                "namespace" => "Tests\\System",
            ]);
        } finally {
            $this->setPrivateStaticProperty(Builder::class, "templates", $templates);
        }

        token_get_all($code, TOKEN_PARSE);
        foreach ($expected as $line) {
            $this->assertStringContainsString($line, $code);
        }
    }

    /**
     * @return array<string,array{array<string,string>,list<string>}>
     */
    public static function providerGeneratedCode(): array {
        return [
            // The class is written whole and from the root, as the enum is in another namespace
            "with messages"    => [
                [ "Invite" => "App\\Auth\\InviteEmail", "TaskAssign" => "App\\Task\\TaskAssignEmail" ],
                [
                    "    case Reset;",
                    "    case Invite;",
                    "    case TaskAssign;",
                    "            \"Invite\"     => \\App\\Auth\\InviteEmail::class,",
                    "            \"TaskAssign\" => \\App\\Task\\TaskAssignEmail::class,",
                ],
            ],
            "without messages" => [
                [],
                [ "    case Reset;", "        return [];" ],
            ],
        ];
    }

    #[DataProvider("providerCollectCodes")]
    public function testCollectCodes(array $fileCodes, array $messages, array $expectedCodes, array $expectedKeys): void {
        $result = EmailBuilder::collectCodes($fileCodes, $messages);

        $this->assertSame($expectedCodes, $result["codes"]);
        $this->assertSame($expectedKeys, Arrays::createArray($result["messages"], "key"));
        $this->assertSame(count($expectedKeys) > 0, $result["hasMessages"]);
        $this->assertSame(count($expectedCodes), $result["total"]);
    }

    public static function providerCollectCodes(): array {
        return [
            "only files"       => [ [ "Reset" ], [], [ "Reset" ], [] ],
            "only messages"    => [
                [],
                [ "Reset" => "App\\ResetEmail", "TaskAssign" => "App\\TaskAssignEmail" ],
                [ "Reset", "TaskAssign" ],
                [ "\"Reset\"     ", "\"TaskAssign\"" ],
            ],
            "both, joined"     => [
                [ "Reset", "Invite" ],
                [ "Invite" => "App\\InviteEmail" ],
                [ "Reset", "Invite" ],
                [ "\"Invite\"" ],
            ],
            "nothing"          => [ [], [], [ "Test" ], [] ],
        ];
    }


    public function testDestroyCode(): void {
        // The Email Codes and the Email Providers
        $this->assertSame(2, EmailBuilder::destroyCode());
    }

    public function testCollectSenders(): void {
        $result = EmailBuilder::collectSenders();
        $names  = Arrays::createArray($result["providers"], "name");

        // Every Provider that can send is found, named after its class
        $this->assertSame(
            [ "Mailgun", "Mailjet", "Mandrill", "SMTP", "SendGrid" ],
            $names,
        );
        $this->assertSame(count($names), $result["total"]);
    }

    public function testTheSendersAreTheClassesOfTheProviders(): void {
        $result = EmailBuilder::collectSenders();

        foreach ($result["providers"] as $provider) {
            $this->assertTrue(
                is_subclass_of($provider["class"], EmailSender::class),
                "{$provider["class"]} is not an EmailSender",
            );
            $this->assertStringEndsWith("\\{$provider["name"]}", $provider["class"]);
        }
    }

    public function testEveryProviderOfTheEnumHasItsSender(): void {
        // Which is the enum the build writes from the senders found above
        foreach (EmailProvider::cases() as $provider) {
            if ($provider === EmailProvider::None) {
                $this->assertNull($provider->getSender());
                continue;
            }

            $sender = $provider->getSender();
            $this->assertNotNull($sender);
            $this->assertTrue(is_subclass_of($sender, EmailSender::class));
        }
    }
}
