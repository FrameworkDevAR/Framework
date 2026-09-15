<?php
namespace Framework\Notification;

use Framework\Application;
use Framework\Notification\NotificationResult;
use Framework\Notification\NotificationSender;
use Framework\System\Config;
use Framework\System\NotificationProvider;
use Framework\Utils\Strings;

/**
 * The Notification Provider
 */
class Notification {

    /** @var class-string<NotificationSender>|null */
    private static ?string $sender = null;


    /**
     * Sets the Sender to use, rather than the one of the config
     * @param class-string<NotificationSender>|null $sender Optional.
     * @return void
     */
    public static function setSender(?string $sender = null): void {
        self::$sender = $sender;
    }



    /**
     * Sends the Notification to every device there is
     * @param string $title
     * @param string $message
     * @param string $url
     * @param string $dataType
     * @param int    $dataID
     * @return array{NotificationResult,string}
     */
    public static function sendToAll(
        string $title,
        string $message,
        string $url,
        string $dataType,
        int $dataID,
    ): array {
        if (!Config::isNotificationActive()) {
            return [ NotificationResult::InactiveSend, "" ];
        }

        $sender = self::getSender();
        if ($sender === null) {
            return [ NotificationResult::NoProvider, "" ];
        }

        $externalID = $sender::sendToAll(
            $title,
            $message,
            self::getUrl($url),
            self::getIcon(),
            $dataType,
            $dataID,
        );
        return self::getResult($externalID);
    }

    /**
     * Sends the Notification to the given devices
     * @param string       $title
     * @param string       $message
     * @param string       $url
     * @param string       $dataType
     * @param int          $dataID
     * @param list<string> $playerIDs
     * @param int          $badge     Optional.
     * @return array{NotificationResult,string}
     */
    public static function sendToSome(
        string $title,
        string $message,
        string $url,
        string $dataType,
        int $dataID,
        array $playerIDs,
        int $badge = 0,
    ): array {
        if (!Config::isNotificationActive()) {
            return [ NotificationResult::InactiveSend, "" ];
        }
        if (count($playerIDs) === 0) {
            return [ NotificationResult::NoDevices, "" ];
        }

        $sender = self::getSender();
        if ($sender === null) {
            return [ NotificationResult::NoProvider, "" ];
        }

        $externalID = $sender::sendToSome(
            $title,
            $message,
            self::getUrl($url),
            self::getIcon(),
            $dataType,
            $dataID,
            $playerIDs,
            $badge,
        );
        return self::getResult($externalID);
    }



    /**
     * Returns the Sender to send through, or null if there is none
     * @return class-string<NotificationSender>|null
     */
    private static function getSender(): ?string {
        $provider = NotificationProvider::fromValue(Config::getNotificationProvider());
        return self::$sender ?? $provider->getSender();
    }

    /**
     * Turns what the Sender answered into a Result
     * @param string $externalID
     * @return array{NotificationResult,string}
     */
    private static function getResult(string $externalID): array {
        if ($externalID === "") {
            return [ NotificationResult::ProviderError, "" ];
        }
        return [ NotificationResult::Sent, $externalID ];
    }

    /**
     * Returns the Icon shown on the notification, as a full url
     * @return string
     */
    private static function getIcon(): string {
        $icon = Config::getNotificationIcon();
        if ($icon === "") {
            return "";
        }
        return Application::getUrl($icon);
    }

    /**
     * Returns the url the notification opens, as a full one
     * @param string $url
     * @return string
     */
    private static function getUrl(string $url): string {
        if (Strings::startsWith($url, "http")) {
            return $url;
        }
        return Config::getUrl($url);
    }
}
