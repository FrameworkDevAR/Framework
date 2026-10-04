<?php
namespace Tests\Analysis;

use Framework\Analysis\Log\ActionAttributeRule;
use Framework\Analysis\Log\ActionSectionRule;
use Framework\Analysis\Log\SectionDuplicateRule;
use Framework\Analysis\Log\SectionNameCollector;

use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Log rules
 * @extends RuleTestCase<Rule>
 */
class LogRulesTest extends RuleTestCase {
    use RuleHelpers;

    private const FixtureGroup = "Log";


    /**
     * The duplicate rule reads what the collector gathered
     * @return list<Collector>
     */
    #[\Override]
    protected function getCollectors(): array {
        return [ new SectionNameCollector() ];
    }


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

        return [
            // Create gives both languages by name, so nothing is said about it.
            // Edit is missing one, and Remove gives them positionally, which
            // means neither language is seen at all.
            "the translations"           => [ ActionAttributeRule::class, [ "LogActions" ], [
                [ "The #[Action] attribute is missing the language: en.", 16 ],
                [ "Argument 2 in #[Action] must be a named parameter.", 22 ],
                [ "Argument 3 in #[Action] must be a named parameter.", 22 ],
                [ "The #[Action] attribute is missing the language: es.", 22 ],
                [ "The #[Action] attribute is missing the language: en.", 22 ],
            ], $languages ],

            // With no languages given the rule asks for its own
            "the default languages"      => [ ActionAttributeRule::class, [ "LogActions" ], [
                [ "The #[Action] attribute is missing the language: en.", 16 ],
                [ "Argument 2 in #[Action] must be a named parameter.", 22 ],
                [ "Argument 3 in #[Action] must be a named parameter.", 22 ],
                [ "The #[Action] attribute is missing the language: en.", 22 ],
                [ "The #[Action] attribute is missing the language: es.", 22 ],
            ], [ [] ] ],

            // The numbers are lines of the fixture, so a line added at the top
            // of it moves them
            "the name and the languages" => [ ActionAttributeRule::class, [ "BadActions" ], [
                [ "The #[Action] attribute requires a name as its first argument.", 12 ],
                [ "Argument 4 in #[Action] is not a valid language.", 18 ],
            ], $languages ],

            "a section with no action"   => [ ActionSectionRule::class, [ "SectionWithoutAction" ], [
                [
                    "Class Tests\\Analysis\\Fixture\\Log\\SectionWithoutAction has a #[Section] " .
                    "but no method with #[Action].",
                    6,
                ],
            ], [] ],

            "an action with no section"  => [ ActionSectionRule::class, [ "ActionWithoutSection" ], [
                [
                    "Class Tests\\Analysis\\Fixture\\Log\\ActionWithoutSection has a method with " .
                    "#[Action] but is missing a #[Section].",
                    8,
                ],
            ], [] ],

            "having both"                => [ ActionSectionRule::class, [ "LogActions" ], [], [] ],

            // The collision only shows across files, which is what the collector is for
            "one name in two classes"    => [
                SectionDuplicateRule::class,
                [ "DuplicateSectionA", "DuplicateSectionB" ],
                [
                    [
                        "Duplicate section 'Crates' found in " .
                        "Tests\\Analysis\\Fixture\\Log\\DuplicateSectionA and " .
                        "Tests\\Analysis\\Fixture\\Log\\DuplicateSectionB.",
                        8,
                    ],
                ],
                [],
            ],
        ];
    }
}
