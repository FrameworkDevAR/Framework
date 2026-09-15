<?php
namespace Tests\Notification\Fixture;

use Framework\Notification\NotificationSender;

/**
 * A Sender that keeps the notifications rather than pushing them
 *
 * The build writes the Providers from the classes in the source, and this is
 * not one of them, so it is handed to Notification::setSender() rather than
 * named in the config.
 *
 * @phpstan-type SentNotification array{
 *   title:     string,
 *   message:   string,
 *   url:       string,
 *   icon:      string,
 *   dataType:  string,
 *   dataID:    int,
 *   playerIDs: list<string>,
 *   badge:     int,
 * }
 */
class TestNotificationSender implements NotificationSender {

    /** @var list<SentNotification> */
    private static array $notifications = [];

    private static string $externalID = "the-external-id";


    /**
     * Sets the ID the sends answer with, an empty one being a refusal
     * @param string $externalID
     * @return void
     */
    public static function setExternalID(string $externalID): void {
        self::$externalID = $externalID;
    }

    /**
     * Sends the Notification to every device there is
     * @param string $title
     * @param string $message
     * @param string $url
     * @param string $icon
     * @param string $dataType
     * @param int    $dataID
     * @return string
     */
    #[\Override]
    public static function sendToAll(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
    ): string {
        return self::keep($title, $message, $url, $icon, $dataType, $dataID, [], 0);
    }

    /**
     * Sends the Notification to the given devices
     * @param string       $title
     * @param string       $message
     * @param string       $url
     * @param string       $icon
     * @param string       $dataType
     * @param int          $dataID
     * @param list<string> $playerIDs
     * @param int          $badge     Optional.
     * @return string
     */
    #[\Override]
    public static function sendToSome(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
        array $playerIDs,
        int $badge = 0,
    ): string {
        return self::keep($title, $message, $url, $icon, $dataType, $dataID, $playerIDs, $badge);
    }

    /**
     * Keeps the Notification and answers the way the Provider would
     * @param string       $title
     * @param string       $message
     * @param string       $url
     * @param string       $icon
     * @param string       $dataType
     * @param int          $dataID
     * @param list<string> $playerIDs
     * @param int          $badge
     * @return string
     */
    private static function keep(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
        array $playerIDs,
        int $badge,
    ): string {
        self::$notifications[] = [
            "title"     => $title,
            "message"   => $message,
            "url"       => $url,
            "icon"      => $icon,
            "dataType"  => $dataType,
            "dataID"    => $dataID,
            "playerIDs" => $playerIDs,
            "badge"     => $badge,
        ];

        // A refused push was handed over just the same, so it is kept
        return self::$externalID;
    }

    /**
     * Returns every Notification that was sent
     * @return list<SentNotification>
     */
    public static function getAll(): array {
        return self::$notifications;
    }

    /**
     * Returns the last Notification that was sent, or an empty one
     * @return SentNotification
     */
    public static function getLast(): array {
        $result = end(self::$notifications);
        if ($result === false) {
            return [
                "title"     => "",
                "message"   => "",
                "url"       => "",
                "icon"      => "",
                "dataType"  => "",
                "dataID"    => 0,
                "playerIDs" => [],
            ];
        }
        return $result;
    }

    /**
     * Returns the amount of Notifications that were sent
     * @return int
     */
    public static function getCount(): int {
        return count(self::$notifications);
    }

    /**
     * Forgets every Notification that was sent
     * @return void
     */
    public static function reset(): void {
        self::$notifications = [];
        self::$externalID    = "the-external-id";
    }
}
