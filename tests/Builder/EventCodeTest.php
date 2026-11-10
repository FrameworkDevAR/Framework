<?php
namespace Tests\Builder;

use Framework\Builder\EventCode;
use Framework\Discovery\Attr\EmailQueued;
use Framework\Discovery\Attr\NotificationQueued;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Discovery\Type\FrameworkEvent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TestEventNoHandlers {
    public static function helper(int $emailQueueID): void {
    }
}

class TestEventEmail {
    #[EmailQueued]
    public static function onQueued(int $emailQueueID): void {
    }
}

class TestEventBoth {
    #[EmailQueued]
    public static function alsoOnQueued(int $emailQueueID): void {
    }

    #[NotificationQueued]
    public static function onNotification(int $notificationQueueID): void {
    }
}

class TestEventTwice {
    #[EmailQueued]
    #[NotificationQueued]
    public static function onAny(int $queueID): void {
    }
}


class EventCodeTest extends TestCase {

    /**
     * Builds the DiscoveryClasses for the given class names
     * @param list<string> $classNames
     * @return list<DiscoveryClass>
     */
    private static function toClasses(array $classNames): array {
        $result = [];
        foreach ($classNames as $className) {
            $result[] = new DiscoveryClass($className);
        }
        return $result;
    }

    /**
     * Returns the collected Event with the given name
     * @param list<string> $classNames
     * @param string       $name
     * @return array<string,mixed>
     */
    private static function getEvent(array $classNames, string $name): array {
        $result = EventCode::collectEvents(self::toClasses($classNames));
        foreach ($result["events"] as $event) {
            if ($event["name"] === $name) {
                return $event;
            }
        }
        return [];
    }



    /**
     * Every Event is generated, with its fixed signature, whatever handles it
     * @param list<string> $classNames
     * @return void
     */
    #[DataProvider("providerEveryEvent")]
    public function testEveryEventIsGenerated(array $classNames): void {
        $result = EventCode::collectEvents(self::toClasses($classNames));

        $this->assertSame(count(FrameworkEvent::getAll()), $result["total"]);
        $this->assertSame([
            "name"      => "EmailQueued",
            "method"    => "emailQueued",
            "params"    => [[ "name" => "emailQueueID", "type" => "int" ]],
            "signature" => "int \$emailQueueID",
            "arguments" => "\$emailQueueID",
        ], array_diff_key(self::getEvent($classNames, "EmailQueued"), [ "handlers" => 1 ]));
    }

    public static function providerEveryEvent(): array {
        return [
            "no classes"   => [ [] ],
            "no handlers"  => [ [ TestEventNoHandlers::class ] ],
            "with handler" => [ [ TestEventEmail::class ] ],
        ];
    }

    /**
     * The classes discovered, the Event and the Handlers it calls
     * @param list<string> $classNames
     * @param string       $name
     * @param list<string> $handlers
     * @return void
     */
    #[DataProvider("providerHandlers")]
    public function testTheHandlersAreCalledInside(array $classNames, string $name, array $handlers): void {
        $this->assertSame($handlers, self::getEvent($classNames, $name)["handlers"]);
    }

    public static function providerHandlers(): array {
        return [
            "an event nobody handles is empty" => [
                [ TestEventNoHandlers::class ], "EmailQueued", [],
            ],
            "one handler" => [
                [ TestEventEmail::class ], "EmailQueued",
                [ "\\" . TestEventEmail::class . "::onQueued" ],
            ],
            "several handlers in discovery order" => [
                [ TestEventEmail::class, TestEventBoth::class ], "EmailQueued",
                [ "\\" . TestEventEmail::class . "::onQueued", "\\" . TestEventBoth::class . "::alsoOnQueued" ],
            ],
            "one method can handle several" => [
                [ TestEventTwice::class ], "NotificationQueued",
                [ "\\" . TestEventTwice::class . "::onAny" ],
            ],
            "each handler goes to its event" => [
                [ TestEventBoth::class ], "NotificationQueued",
                [ "\\" . TestEventBoth::class . "::onNotification" ],
            ],
        ];
    }

    public function testDestroyCode(): void {
        $this->assertSame(1, EventCode::destroyCode());
    }
}
