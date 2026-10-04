<?php
namespace Tests\Analysis\Fixture\Email;

use Framework\Email\EmailMessage;

class MissingEmail extends EmailMessage {

    public static string $description = "A language missing and one unknown";

    public static array $subject = [
        "es" => "Hola",
    ];

    public static array $body = [
        "es" => "Hola",
        "en" => "Hello",
        "fr" => "Bonjour",
    ];

    public static function send(): bool {
        return true;
    }
}
