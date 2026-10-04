<?php
namespace Tests\Analysis\Fixture\Notification;

use Framework\Notification\NotificationMessage;

abstract class BaseNotification extends NotificationMessage {

    public static function send(int $credentialID): int {
        return self::queue($credentialID, 0, "en", []);
    }
}
