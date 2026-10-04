<?php
namespace Tests\Analysis;

use Framework\Analysis\Notification\NotificationMessageRule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Notification rules
 * @extends RuleTestCase<Rule>
 */
class NotificationRulesTest extends RuleTestCase {
    use RuleHelpers;

    private const FixtureGroup = "Notification";


    /**
     * A rule, the files it is run over, and what it has to say about them
     * @param string                  $ruleClass
     * @param list<string>            $fixtures
     * @param list<array{string,int}> $errors
     * @param list<mixed>             $extra     Optional.
     * @return void
     */
    #[DataProvider("providerRules")]
    public function testTheRuleReportsWhatItIsFor(
        string $ruleClass,
        array $fixtures,
        array $errors,
        array $extra = [],
    ): void {
        $this->rule = $this->makeRule($ruleClass, extra: $extra);

        $this->analyse($this->fixtures($fixtures), $errors);
    }

    /**
     * @return array<string,array{string,list<string>,list<array{string,int}>,list<mixed>}>
     */
    public static function providerRules(): array {
        $languages = [ [ "es", "en" ] ];
        $missing   = "Tests\\Analysis\\Fixture\\Notification\\MissingNotification";
        $noSend    = "Tests\\Analysis\\Fixture\\Notification\\NoSendNotification";

        // The numbers are lines of the fixture, so a line added at the top of it moves them
        return [
            "the good ones" => [ NotificationMessageRule::class,
                [ "WelcomeNotification", "BaseNotification", "NotANotification" ], [], $languages,
            ],
            "the bad one"   => [ NotificationMessageRule::class, [ "MissingNotification" ], [
                [ "The \$title of the Notification $missing is missing the language: en.", 10 ],
                [ "The \$message of the Notification $missing has an unknown language: fr.", 14 ],
            ], $languages ],
            "one with no send" => [ NotificationMessageRule::class, [ "NoSendNotification" ], [
                [ "The Notification $noSend has no static send().", 6 ],
                [ "The \$message of the Notification $noSend must be an array by language.", 17 ],
            ], $languages ],
            // With no languages given the rule asks for its own
            "the default languages" => [ NotificationMessageRule::class, [ "MissingNotification" ], [
                [ "The \$title of the Notification $missing is missing the language: en.", 10 ],
                [ "The \$message of the Notification $missing has an unknown language: fr.", 14 ],
            ], [ [] ] ],
            // What a Notification takes from the one it extends counts as its own
            "the inherited one" => [ NotificationMessageRule::class,
                [ "BaseNotification", "ChildNotification" ], [], $languages,
            ],
        ];
    }
}
