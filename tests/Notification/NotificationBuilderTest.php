<?php
namespace Tests\Notification;

use Framework\Builder\Builder;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Notification\NotificationBuilder;
use Framework\Notification\NotificationMessage;
use Framework\Notification\NotificationSender;
use Framework\System\NotificationProvider;
use Framework\Utils\Arrays;
use Framework\File\Storage;
use Tests\Notification\Fixture\TestNotification;
use Tests\Notification\Fixture\TestNotificationSender;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class NotificationBuilderTest extends TestCase {
    use TestHelpers;


    public function testCollectNotifications(): void {
        // The Framework has no Notification Messages, so there are no codes
        $result = NotificationBuilder::collectNotifications();
        $this->assertSame([], $result["codes"]);
        $this->assertSame([], $result["messages"]);
        $this->assertFalse($result["hasMessages"]);
        $this->assertSame(0, $result["total"]);
    }

    #[DataProvider("providerCollectMessages")]
    public function testCollectMessages(array $classes, array $expected): void {
        $classes = array_map(fn (string $class) => new DiscoveryClass($class), $classes);

        $this->assertSame($expected, NotificationBuilder::collectMessages($classes));
    }

    public static function providerCollectMessages(): array {
        return [
            "a message"      => [ [ TestNotification::class ], [ "Test" => TestNotification::class ] ],
            "the base class" => [ [ NotificationMessage::class ], [] ],
            "another class"  => [ [ TestNotificationSender::class ], [] ],
            "none"           => [ [], [] ],
        ];
    }

    #[DataProvider("providerCollectCodes")]
    public function testCollectCodes(array $messages, array $expectedCodes, array $expectedKeys): void {
        $result = NotificationBuilder::collectCodes($messages);

        $this->assertSame($expectedCodes, $result["codes"]);
        $this->assertSame($expectedKeys, Arrays::createArray($result["messages"], "key"));
        $this->assertSame(count($expectedKeys) > 0, $result["hasMessages"]);
        $this->assertSame(count($expectedCodes), $result["total"]);
    }

    public static function providerCollectCodes(): array {
        return [
            "one message"      => [
                [ "Invite" => "App\\InviteNotification" ],
                [ "Invite" ],
                [ "\"Invite\"" ],
            ],
            "several messages" => [
                [ "Reset" => "App\\ResetNotification", "TaskAssign" => "App\\TaskAssignNotification" ],
                [ "Reset", "TaskAssign" ],
                [ "\"Reset\"     ", "\"TaskAssign\"" ],
            ],
            "nothing"          => [ [], [], [] ],
        ];
    }

    /**
     * The Notification Messages of an App, and what the enum written for them holds
     * @param array<string,string> $messages
     * @param list<string>         $expected
     * @return void
     */
    #[DataProvider("providerGeneratedCode")]
    public function testTheGeneratedCodeParses(array $messages, array $expected): void {
        $template  = Storage::readFile(Package::getBasePath("src/Notification/Template/NotificationCode.mu"));
        $templates = $this->getPrivateStaticProperty(Builder::class, "templates");
        $this->setPrivateStaticProperty(Builder::class, "templates", [ "NotificationCode" => $template ]);

        try {
            $code = Builder::render("NotificationCode", NotificationBuilder::collectCodes($messages) + [
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
                [ "Invite" => "App\\Auth\\InviteNotification", "TaskAssign" => "App\\Task\\TaskAssignNotification" ],
                [
                    "    case Invite;",
                    "    case TaskAssign;",
                    "            \"Invite\"     => \\App\\Auth\\InviteNotification::class,",
                    "            \"TaskAssign\" => \\App\\Task\\TaskAssignNotification::class,",
                ],
            ],
            "without messages" => [
                [],
                [ "    case None;", "        return [];" ],
            ],
        ];
    }


    public function testDestroyCode(): void {
        // The Notification Codes and the Notification Providers
        $this->assertSame(2, NotificationBuilder::destroyCode());
    }

    public function testCollectSenders(): void {
        $result = NotificationBuilder::collectSenders();
        $names  = Arrays::createArray($result["providers"], "name");

        // Every Provider that can push is found, named after its class
        $this->assertSame([ "Firebase", "OneSignal" ], $names);
        $this->assertSame(count($names), $result["total"]);
    }

    public function testTheSendersAreTheClassesOfTheProviders(): void {
        $result = NotificationBuilder::collectSenders();

        foreach ($result["providers"] as $provider) {
            $this->assertTrue(
                is_subclass_of($provider["class"], NotificationSender::class),
                "{$provider["class"]} is not a NotificationSender",
            );
            $this->assertStringEndsWith("\\{$provider["name"]}", $provider["class"]);
        }
    }

    public function testEveryProviderOfTheEnumHasItsSender(): void {
        // Which is the enum the build writes from the senders found above
        foreach (NotificationProvider::cases() as $provider) {
            if ($provider === NotificationProvider::None) {
                $this->assertNull($provider->getSender());
                continue;
            }

            $sender = $provider->getSender();
            $this->assertNotNull($sender);
            $this->assertTrue(is_subclass_of($sender, NotificationSender::class));
        }
    }
}
