<?php
namespace Tests\Analysis\Fixture\Notification;

use Framework\Notification\NotificationMessage;

class MissingNotification extends NotificationMessage {

    public static string $description = "A language missing and one unknown";

    public static array $title = [
        "es" => "Hola",
    ];

    public static array $message = [
        "es" => "Hola",
        "en" => "Hello",
        "fr" => "Bonjour",
    ];

    public static function send(): int {
        return 0;
    }
}
