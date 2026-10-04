<?php
namespace Framework\Email;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Discovery\Attr\Priority;
use Framework\Builder\Builder;
use Framework\Email\EmailMessage;
use Framework\Email\EmailSender;
use Framework\Discovery\Package;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Email Builder
 * @phpstan-type EmailMessageData array{
 *   key:   string,
 *   class: string,
 * }
 * @phpstan-type EmailCodesResult array{
 *   codes:       list<string>,
 *   messages:    list<EmailMessageData>,
 *   hasMessages: bool,
 *   total:       int,
 * }
 * @phpstan-type EmailProviderData array{
 *   name:     string,
 *   constant: string,
 *   class:    string,
 * }
 * @phpstan-type EmailProvidersResult array{
 *   providers: list<EmailProviderData>,
 *   none:      string,
 *   total:     int,
 * }
 */
#[Priority(Priority::High)]
class EmailBuilder implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $result  = Builder::generateCode("EmailCode", self::collectEmails());
        $result += Builder::generateCode("EmailProvider", self::collectSenders());
        return $result;
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 2;
    }



    /**
     * Collects the Emails from the Email Messages
     * @return EmailCodesResult
     */
    public static function collectEmails(): array {
        $classes = Discovery::findClasses(
            parentClass:  EmailMessage::class,
            forAll:       !Package::isFramework(),
            forFramework: true,
        );
        return self::collectCodes(self::collectMessages($classes));
    }

    /**
     * Collects the Email Messages from the given Classes, by their Code
     * @param list<DiscoveryClass> $classes
     * @return array<string,string>
     */
    public static function collectMessages(array $classes): array {
        $result = [];
        foreach ($classes as $class) {
            $name = $class->getName();
            if (is_subclass_of($name, EmailMessage::class)) {
                $code = Strings::substringAfter($name, "\\");
                $code = Strings::stripEnd($code, "Email");
                $result[$code] = $name;
            }
        }
        return $result;
    }

    /**
     * Collects the Codes of the given Email Messages
     * @param array<string,string> $messages
     * @return EmailCodesResult
     */
    public static function collectCodes(array $messages): array {
        $codes = array_keys($messages);

        // If no codes are found, add a default one
        if (count($codes) === 0) {
            $codes[] = "Test";
        }

        // Pad the codes so the values of the array line up
        $maxLength = 0;
        foreach ($messages as $code => $class) {
            $maxLength = max($maxLength, Strings::length($code) + 2);
        }

        $list = [];
        foreach ($messages as $code => $class) {
            $list[] = [
                "key"   => Strings::padRight("\"$code\"", $maxLength),
                "class" => $class,
            ];
        }

        return [
            "codes"       => $codes,
            "messages"    => $list,
            "hasMessages" => count($list) > 0,
            "total"       => count($codes),
        ];
    }

    /**
     * Collects the Senders, which are the Providers an Email can go through
     * @return EmailProvidersResult
     */
    public static function collectSenders(): array {
        $classes   = Discovery::findClasses(
            interface:    EmailSender::class,
            forAll:       !Package::isFramework(),
            forFramework: true,
        );

        $providers = [];
        $maxLength = Strings::length("None");

        foreach ($classes as $class) {
            // The name is what EMAIL_PROVIDER takes, and it is the class
            // itself unless the class named itself something else
            $name        = $class->getConstant("Name");
            $name        = $name !== "" ? $name : Strings::substringAfter($class->getName(), "\\");
            $maxLength   = max($maxLength, Strings::length($name));
            $providers[] = [
                "name"     => $name,
                "constant" => $name,
                "class"    => $class->getName(),
            ];
        }

        $providers = Arrays::sortList($providers, function (array $a, array $b) {
            return $a["name"] <=> $b["name"];
        });

        // Pad the names so the arms of the match line up
        foreach ($providers as $index => $provider) {
            $providers[$index]["constant"] = Strings::padRight($provider["name"], $maxLength);
        }

        return [
            "providers" => $providers,
            "none"      => Strings::padRight("None", $maxLength),
            "total"     => count($providers),
        ];
    }
}
