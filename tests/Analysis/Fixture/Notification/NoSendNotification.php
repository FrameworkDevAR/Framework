<?php
namespace Tests\Analysis\Fixture\Notification;

use Framework\Notification\NotificationMessage;

class NoSendNotification extends NotificationMessage {

    private const Texts = [ "es" => "Hola", "en" => "Hello" ];

    public static string $description = "No send at all, and a message that is not written out";

    public static array $title = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static array $message = self::Texts;
}
