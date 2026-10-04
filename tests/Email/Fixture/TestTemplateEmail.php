<?php
namespace Tests\Email\Fixture;

use Framework\Email\EmailMessage;
use Framework\System\Template;

/**
 * An Email Message for the tests with a Template, which is one of the build as the
 * Framework has no template of its own for an email
 */
class TestTemplateEmail extends EmailMessage {

    public static string $description = "An email with a template";

    public static ?Template $template = Template::EmailCode;

    public static bool $sendNow = true;

    /** @var array<string,string> */
    public static array $subject = [
        "en" => "The codes",
        "es" => "Los códigos",
    ];



    /**
     * Sends the Test Template Email
     * @return bool
     */
    public static function send(): bool {
        return self::queue("test@framework.test", "en", [
            "namespace" => "Tests\\Email",
            "codes"     => [ "Welcome" ],
        ]);
    }
}
