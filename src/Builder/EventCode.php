<?php
namespace Framework\Builder;

use Framework\Analysis\Attr\NotTested;
use Framework\Builder\Builder;
use Framework\Discovery\Discovery;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Discovery\Type\EventAttribute;
use Framework\Discovery\Type\FrameworkEvent;
use Framework\Discovery\Attr\Priority;
use Framework\Utils\Strings;

use ReflectionAttribute;

/**
 * The Event Code
 * @phpstan-type EventParam array{
 *   name: string,
 *   type: string,
 * }
 * @phpstan-type EventData array{
 *   name:      string,
 *   method:    string,
 *   params:    list<EventParam>,
 *   signature: string,
 *   arguments: string,
 *   handlers:  list<string>,
 * }
 * @phpstan-type EventResult array{
 *   events: list<EventData>,
 *   total:  int,
 * }
 */
#[Priority(Priority::Lowest)]
class EventCode implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $data = self::collectEvents(Discovery::findClasses());
        return Builder::generateCode("Event", $data);
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 1;
    }



    /**
     * Collects the Events used to generate the code
     * @param list<DiscoveryClass> $classes
     * @return EventResult
     */
    public static function collectEvents(array $classes): array {
        // Every Event is generated, even without Handlers, as the Framework triggers them
        // whether the App handles them or not
        $handlers = [];
        foreach ($classes as $class) {
            foreach ($class->getMethods() as $method) {
                // Each Event has its own Attribute, so a method can handle several
                $attributes = $method->getAttributes(
                    EventAttribute::class,
                    ReflectionAttribute::IS_INSTANCEOF,
                );
                foreach ($attributes as $attribute) {
                    $event   = $attribute->newInstance()->getEvent()->toString();
                    $handler = "{$class->getFullyQualifiedName()}::{$method->getName()}";
                    $handlers[$event][] = $handler;
                }
            }
        }

        $events = [];
        foreach (FrameworkEvent::getAll() as $event) {
            $params     = [];
            $signatures = [];
            $arguments  = [];
            foreach ($event->getParams() as $name => $type) {
                $params[]     = [ "name" => $name, "type" => $type ];
                $signatures[] = "$type \$$name";
                $arguments[]  = "\$$name";
            }

            $events[] = [
                "name"      => $event->toString(),
                "method"    => $event->getMethod(),
                "params"    => $params,
                "signature" => Strings::join($signatures, ", "),
                "arguments" => Strings::join($arguments, ", "),
                "handlers"  => $handlers[$event->toString()] ?? [],
            ];
        }

        return [
            "events" => $events,
            "total"  => count($events),
        ];
    }
}
