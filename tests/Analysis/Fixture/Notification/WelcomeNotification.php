<?php
namespace Tests\Analysis\Fixture\Notification;

use Framework\Notification\NotificationMessage;

class WelcomeNotification extends NotificationMessage {

    public static string $description = "Welcome";

    public static array $title = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static array $message = [
        "es" => "Bienvenido",
        "en" => "Welcome",
    ];

    public static function send(int $credentialID): int {
        return self::queue($credentialID, 0, "en", []);
    }
}
