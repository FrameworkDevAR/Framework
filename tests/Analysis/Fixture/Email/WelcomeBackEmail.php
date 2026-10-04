<?php
namespace Tests\Analysis\Fixture\Email;

class WelcomeBackEmail extends WelcomeEmail {

    public static string $description = "The body and the send come from the one it extends";

    public static array $subject = [
        "es" => "Hola de nuevo",
        "en" => "Hello again",
    ];
}
