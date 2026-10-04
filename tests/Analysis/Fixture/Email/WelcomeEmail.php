<?php
namespace Tests\Analysis\Fixture\Email;

use Framework\Email\EmailMessage;

class WelcomeEmail extends EmailMessage {

    public static string $description = "Welcome";

    public static array $subject = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static array $body = [
        "es" => "Bienvenido",
        "en" => "Welcome",
    ];

    public static function send(string $email): bool {
        return self::queue($email, "en", []);
    }
}
