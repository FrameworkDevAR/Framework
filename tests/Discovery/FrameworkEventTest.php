<?php
namespace Tests\Discovery;

use Framework\Discovery\Attr\EmailQueued;
use Framework\Discovery\Attr\NotificationQueued;
use Framework\Discovery\Type\EventAttribute;
use Framework\Discovery\Type\FrameworkEvent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Framework Event
 */
class FrameworkEventTest extends TestCase {

    /**
     * The method of an Event is its name with the first letter in lower case
     * @param FrameworkEvent $event
     * @param string         $method
     * @return void
     */
    #[DataProvider("providerMethods")]
    public function testTheMethodIsTheNameInCamelCase(FrameworkEvent $event, string $method): void {
        $this->assertSame($method, $event->getMethod());
    }

    /**
     * One case per Event
     * @return array<string,array{FrameworkEvent,string}>
     */
    public static function providerMethods(): array {
        return [
            "none is empty" => [ FrameworkEvent::None, "" ],
            "email"         => [ FrameworkEvent::EmailQueued, "emailQueued" ],
            "notification"  => [ FrameworkEvent::NotificationQueued, "notificationQueued" ],
        ];
    }

    /**
     * Each Event has its fixed Params, by name and type
     * @param FrameworkEvent       $event
     * @param array<string,string> $params
     * @return void
     */
    #[DataProvider("providerParams")]
    public function testEachEventHasItsParams(FrameworkEvent $event, array $params): void {
        $this->assertSame($params, $event->getParams());
    }

    /**
     * One case per Event
     * @return array<string,array{FrameworkEvent,array<string,string>}>
     */
    public static function providerParams(): array {
        return [
            "none"         => [ FrameworkEvent::None, [] ],
            "email"        => [ FrameworkEvent::EmailQueued, [ "emailQueueID" => "int" ] ],
            "notification" => [
                FrameworkEvent::NotificationQueued,
                [ "notificationQueueID" => "int" ],
            ],
        ];
    }

    /**
     * Each Attribute handles the Event it is named after
     * @param EventAttribute $attribute
     * @param FrameworkEvent $event
     * @return void
     */
    #[DataProvider("providerAttributes")]
    public function testEachAttributeHandlesItsEvent(
        EventAttribute $attribute,
        FrameworkEvent $event,
    ): void {
        $this->assertSame($event, $attribute->getEvent());
    }

    /**
     * One case per Attribute
     * @return array<string,array{EventAttribute,FrameworkEvent}>
     */
    public static function providerAttributes(): array {
        return [
            "email"        => [ new EmailQueued(), FrameworkEvent::EmailQueued ],
            "notification" => [ new NotificationQueued(), FrameworkEvent::NotificationQueued ],
        ];
    }
}
