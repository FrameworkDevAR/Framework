<?php
namespace Tests\Notification\Fixture;

use Framework\Notification\NotificationMessage;

/**
 * A Notification Message for the tests
 */
class TestNotification extends NotificationMessage {

    public static string $description = "A notification of the tests";

    /** @var array<string,string> */
    public static array $title = [
        "en" => "Hello {{name}}",
        "es" => "Hola {{name}}",
    ];

    /** @var array<string,string> */
    public static array $message = [
        "en" => "
            You have a new message, {{name}}.
        ",
        "es" => "Tienes un mensaje nuevo, {{name}}.",
    ];


    /**
     * Sends the Test Notification
     * @param int    $credentialID
     * @param string $language
     * @param string $name
     * @return int
     */
    public static function send(int $credentialID, string $language, string $name): int {
        return self::queue($credentialID, 3, $language, [
            "name" => $name,
        ], "https://framework.test/inbox", "message", 7);
    }
}
