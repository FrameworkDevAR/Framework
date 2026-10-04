<?php
namespace Tests\Analysis;

use Framework\Analysis\Base\MustOverrideRule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Base rules
 * @extends RuleTestCase<Rule>
 */
class BaseRulesTest extends RuleTestCase {
    use RuleHelpers;

    private const FixtureGroup = "Base";


    /**
     * A rule, the files it is run over, and what it has to say about them
     * @param string                  $ruleClass
     * @param list<string>            $fixtures
     * @param list<array{string,int}> $errors
     * @return void
     */
    #[DataProvider("providerRules")]
    public function testTheRuleReportsWhatItIsFor(
        string $ruleClass,
        array $fixtures,
        array $errors,
    ): void {
        $this->rule = $this->makeRule($ruleClass);

        $this->analyse($this->fixtures($fixtures), $errors);
    }

    /**
     * @return array<string,array{string,list<string>,list<array{string,int}>}>
     */
    public static function providerRules(): array {
        $marked   = "which is marked with #[MustOverride].";
        $missing  = "Tests\\Analysis\\Fixture\\Base\\MustOverrideMissing";
        $deep     = "Tests\\Analysis\\Fixture\\Base\\MustOverrideMissingDeep";
        $property = "Tests\\Analysis\\Fixture\\Base\\MustOverridePropertyMissing";

        return [
            "one that does not override"  => [ MustOverrideRule::class,
                [ "MustOverrideBase", "MustOverrideMissing" ], [
                    [ "Class $missing does not override getName() of MustOverrideBase, $marked", 4 ],
                    [ "Class $deep does not override getName() of MustOverrideBase, $marked", 16 ],
                ],
            ],
            // The one that marked it is not asked to override itself, and neither
            // is the abstract one between them, which can not be built
            "one that overrides"          => [ MustOverrideRule::class,
                [ "MustOverrideBase", "MustOverrideDone" ], [],
            ],
            "the base and nothing else"   => [ MustOverrideRule::class,
                [ "MustOverrideBase" ], [],
            ],
            "a property"                  => [ MustOverrideRule::class,
                [ "MustOverridePropertyBase", "MustOverridePropertyDone", "MustOverridePropertyMissing" ], [
                    [ "Class $property does not override \$label of MustOverridePropertyBase, $marked", 4 ],
                ],
            ],
        ];
    }
}
