<?php
namespace Framework\Notification;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\Package;
use Framework\Discovery\Attr\Priority;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Builder\Builder;
use Framework\Intl\IntlConfig;
use Framework\Notification\NotificationSender;
use Framework\System\Language;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

/**
 * The Notification Builder
 * @phpstan-type NotificationCodesResult array{
 *   codes: list<string>,
 *   total: int,
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
     * Collects the Notifications from the Notification files
     * @return NotificationCodesResult
     */
    public static function collectNotifications(): array {
        $languages = Language::getAll();
        $data      = new Dictionary();

        foreach ($languages as $language => $languageName) {
            $data = IntlConfig::loadNotifications($language);
            if ($data->isNotEmpty()) {
                break;
            }
        }

        $codes = [];
        foreach ($data as $notificationCode => $notification) {
            $codes[] = $notificationCode;
        }

        return [
            "codes" => $codes,
            "total" => count($codes),
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
