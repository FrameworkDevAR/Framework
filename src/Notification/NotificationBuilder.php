<?php
namespace Framework\Notification;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\Package;
use Framework\Discovery\Attr\Priority;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Builder\Builder;
use Framework\Notification\NotificationMessage;
use Framework\Notification\NotificationSender;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Notification Builder
 * @phpstan-type NotificationMessageData array{
 *   key:   string,
 *   class: string,
 * }
 * @phpstan-type NotificationCodesResult array{
 *   codes:       list<string>,
 *   messages:    list<NotificationMessageData>,
 *   hasMessages: bool,
 *   total:       int,
 * }
 * @phpstan-type NotificationProviderData array{
 *   name:     string,
 *   constant: string,
 *   class:    string,
 * }
 * @phpstan-type NotificationProvidersResult array{
 *   providers: list<NotificationProviderData>,
 *   none:      string,
 *   total:     int,
 * }
 */
#[Priority(Priority::High)]
class NotificationBuilder implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $result  = Builder::generateCode("NotificationCode", self::collectNotifications());
        $result += Builder::generateCode("NotificationProvider", self::collectSenders());
        return $result;
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 2;
    }



    /**
     * Collects the Notifications from the Notification Messages
     * @return NotificationCodesResult
     */
    public static function collectNotifications(): array {
        $classes = Discovery::findClasses(
            parentClass:  NotificationMessage::class,
            forAll:       !Package::isFramework(),
            forFramework: true,
        );
        return self::collectCodes(self::collectMessages($classes));
    }

    /**
     * Collects the Notification Messages from the given Classes, by their Code
     * @param list<DiscoveryClass> $classes
     * @return array<string,string>
     */
    public static function collectMessages(array $classes): array {
        $result = [];
        foreach ($classes as $class) {
            $name = $class->getName();
            if (is_subclass_of($name, NotificationMessage::class)) {
                $code = Strings::substringAfter($name, "\\");
                $code = Strings::stripEnd($code, "Notification");
                $result[$code] = $name;
            }
        }
        return $result;
    }

    /**
     * Collects the Codes of the given Notification Messages
     * @param array<string,string> $messages
     * @return NotificationCodesResult
     */
    public static function collectCodes(array $messages): array {
        // Pad the codes so the values of the array line up
        $maxLength = 0;
        foreach ($messages as $code => $class) {
            $maxLength = max($maxLength, Strings::length($code) + 2);
        }

        $list = [];
        foreach ($messages as $code => $class) {
            $list[] = [
                "key"   => Strings::padRight("\"$code\"", $maxLength),
                "class" => $class,
            ];
        }

        return [
            "codes"       => array_keys($messages),
            "messages"    => $list,
            "hasMessages" => count($list) > 0,
            "total"       => count($messages),
        ];
    }

    /**
     * Collects the Senders, which are the Providers a push can go through
     * @return NotificationProvidersResult
     */
    public static function collectSenders(): array {
        $classes = Discovery::findClasses(
            interface:    NotificationSender::class,
            forAll:       !Package::isFramework(),
            forFramework: true,
        );

        $providers = [];
        $maxLength = Strings::length("None");

        foreach ($classes as $class) {
            // The name is what NOTIFICATION_PROVIDER takes, and it is the
            // class itself unless the class named itself something else
            $name        = $class->getConstant("Name");
            $name        = $name !== "" ? $name : Strings::substringAfter($class->getName(), "\\");
            $maxLength   = max($maxLength, Strings::length($name));
            $providers[] = [
                "name"     => $name,
                "constant" => $name,
                "class"    => $class->getName(),
            ];
        }

        $providers = Arrays::sortList($providers, function (array $a, array $b) {
            return $a["name"] <=> $b["name"];
        });

        // Pad the names so the arms of the match line up
        foreach ($providers as $index => $provider) {
            $providers[$index]["constant"] = Strings::padRight($provider["name"], $maxLength);
        }

        return [
            "providers" => $providers,
            "none"      => Strings::padRight("None", $maxLength),
            "total"     => count($providers),
        ];
    }
}
