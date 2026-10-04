<?php
namespace Tests\Analysis\Fixture\Email;

use Framework\Email\EmailMessage;

class EmptyEmail extends EmailMessage {

    public static string $description = "No body, no template and no send";

    public static array $subject = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public function send(): bool {
        return true;
    }
}
