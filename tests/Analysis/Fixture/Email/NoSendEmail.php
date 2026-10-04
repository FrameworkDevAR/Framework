<?php
namespace Tests\Analysis\Fixture\Email;

use Framework\Email\EmailMessage;

class NoSendEmail extends EmailMessage {

    private const Texts = [ "es" => "Hola", "en" => "Hello" ];

    public static string $description = "No send at all, and a body that is not written out";

    public static array $subject = [
        "es" => "Hola",
        "en" => "Hello",
    ];

    public static array $body = self::Texts;
}
