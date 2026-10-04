<?php
namespace Tests\Email\Fixture;

use Framework\Email\EmailMessage;

/**
 * An Email Message for the tests, named so its code is the Test one of the enum
 */
class TestEmail extends EmailMessage {

    public static string $description = "An email of the tests";

    /** @var array<string,string> */
    public static array $subject = [
        "en" => "Hello {{name}}",
        "es" => "Hola {{name}}",
    ];

    /** @var array<string,string> */
    public static array $body = [
        "en" => "
            Hello <b>{{name}}</b>!

            Welcome to {{site}}.
        ",
        "es" => "Hola {{name}}",
    ];


    /**
     * Sends the Test Email
     * @param string $email
     * @param string $language
     * @param string $name
     * @return bool
     */
    public static function send(string $email, string $language, string $name): bool {
        return self::queue($email, $language, [
            "name" => $name,
        ]);
    }
}
