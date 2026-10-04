<?php
namespace Tests\Analysis\Fixture\Email;

use Framework\Email\EmailMessage;
use Framework\System\Template;

class LayoutEmail extends EmailMessage {

    public static string $description = "With a template and no body";

    public static ?Template $template = Template::EmailCode;

    public static array $subject = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static function send(string $email): bool {
        return self::queue($email, "en", []);
    }
}
