<?php
namespace Framework\Notification;

/**
 * The Notification Sender
 *
 * A Provider that can push a notification. The build finds every one of them
 * and writes the NotificationProvider enum from their names, so a new provider
 * is a new class and nothing else: the class name is what
 * NOTIFICATION_PROVIDER takes, unless the class gives itself another with a
 * Name constant.
 *
 *     class Pushy implements NotificationSender {
 *         public const Name = "PushyMe";
 *     }
 *
 * Both sends answer with the ID the Provider gave the notification, or null
 * when it would not take it. The badge is the number the app icon shows, and
 * none means the Provider does what it does on its own. Everyone at once has
 * no number that is right for each of them, so only some devices take one.
 */
interface NotificationSender {

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
    public static function sendToAll(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
    ): string;

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
    public static function sendToSome(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
        array $playerIDs,
        int $badge = 0,
    ): string;
}
