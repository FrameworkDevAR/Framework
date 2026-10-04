<?php
namespace Tests\Analysis;

use Framework\Analysis\Email\EmailMessageRule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Email rules
 * @extends RuleTestCase<Rule>
 */
class EmailRulesTest extends RuleTestCase {
    use RuleHelpers;

    private const FixtureGroup = "Email";


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
        $missing   = "Tests\\Analysis\\Fixture\\Email\\MissingEmail";
        $empty     = "Tests\\Analysis\\Fixture\\Email\\EmptyEmail";
        $noSend    = "Tests\\Analysis\\Fixture\\Email\\NoSendEmail";

        // The numbers are lines of the fixture, so a line added at the top of it moves them
        return [
            "the good ones" => [ EmailMessageRule::class,
                [ "WelcomeEmail", "LayoutEmail", "BaseEmail", "NotAnEmail" ], [], $languages,
            ],
            "the bad ones"  => [ EmailMessageRule::class, [ "MissingEmail", "EmptyEmail" ], [
                [ "The \$subject of the Email $missing is missing the language: en.", 10 ],
                [ "The \$body of the Email $missing has an unknown language: fr.", 14 ],
                [ "The Email $empty has no static send().", 6 ],
                [ "The Email $empty needs a \$body or a \$template.", 6 ],
            ], $languages ],
            "one with no send" => [ EmailMessageRule::class, [ "NoSendEmail" ], [
                [ "The Email $noSend has no static send().", 6 ],
                [ "The \$body of the Email $noSend must be an array by language.", 17 ],
            ], $languages ],
            // With no languages given the rule asks for its own
            "the default languages" => [ EmailMessageRule::class, [ "MissingEmail" ], [
                [ "The \$subject of the Email $missing is missing the language: en.", 10 ],
                [ "The \$body of the Email $missing has an unknown language: fr.", 14 ],
            ], [ [] ] ],
            // What an Email takes from the one it extends counts as its own
            "the inherited ones" => [ EmailMessageRule::class,
                [ "TemplateBaseEmail", "TemplateChildEmail", "WelcomeEmail", "WelcomeBackEmail" ], [], $languages,
            ],
        ];
    }
}
