<?php
namespace Tests\Analysis\Fixture\Email;

class TemplateChildEmail extends TemplateBaseEmail {

    public static string $description = "The template comes from its base";

    public static array $subject = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static function send(string $email): bool {
        return self::queue($email, "en", []);
    }
}
