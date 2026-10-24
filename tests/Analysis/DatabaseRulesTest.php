<?php
namespace Tests\Analysis;

use Framework\Analysis\Database\ModelAttributeRule;
use Framework\Analysis\Database\ModelEnumRule;
use Framework\Analysis\Database\ModelUniqueFieldRule;
use Framework\Analysis\Database\QueryArgumentRule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Database rules
 * @extends RuleTestCase<Rule>
 */
class DatabaseRulesTest extends RuleTestCase {
    use RuleHelpers;

    private const FixtureGroup = "Database";


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
        return [
            "a bare property"     => [ ModelAttributeRule::class, [ "BareFieldModel" ], [
                [ "Property 'undescribed' must have an attribute.", 13 ],
            ] ],

            // Validate and the Status type are not main attributes in themselves,
            // so those two properties are told twice: once for having none, and
            // once for the attribute they were supposed to be paired with
            "each attribute rule" => [ ModelAttributeRule::class, [ "AttributeModel" ], [
                [ "Property 'undescribed' must have an attribute.", 20 ],
                [ "Property 'combined' cannot combine main attributes.", 25 ],
                [ "Property 'validated' must have an attribute.", 29 ],
                [ "Property 'validated' is missing the #[Requested] attribute.", 29 ],
                [ "Property 'status' must have an attribute.", 32 ],
                [ "Property 'status' is missing the #[Field] attribute.", 32 ],
                [ "Property 'notAModel' must relate to a #[Model].", 36 ],
                [ "Property 'notAnArray' must have type array.", 40 ],
                [ "Property 'lostFile' has the file owner 'shopID', which is not a #[Field].", 44 ],
                [ "Property 'notAFile' has a file owner but keeps no files.", 48 ],
            ] ],
            "a well formed model" => [ ModelAttributeRule::class, [ "CrateModel" ], [] ],

            "two id fields"       => [ ModelUniqueFieldRule::class, [ "DuplicateIDModel" ], [
                [ "A model can only have 1 field marked as isID. Found: boxID, otherID", 13 ],
            ] ],

            // The id, the position and the status can each be claimed only once.
            // The second status is written as a union, so the type is read
            // through that as well as through a plain name.
            "every unique field"  => [ ModelUniqueFieldRule::class, [ "DuplicateFieldsModel" ], [
                [ "A model can only have 1 field marked as isID. Found: crateID, otherID", 15 ],
                [
                    "A model can only have 1 field marked as isPosition. " .
                    "Found: position, otherPosition",
                    21,
                ],
                [
                    "A model can only have 1 field using the Status type. " .
                    "Found: status, otherStatus",
                    27,
                ],
            ] ],
            "one of each"         => [ ModelUniqueFieldRule::class, [ "SingleIDModel" ], [] ],

            // Grade implements Enum and JsonSerializable, so it is left alone.
            // Weekday is a plain php enum, which is what the rule looks for.
            "an enum field"       => [ ModelEnumRule::class, [ "EnumFieldModel" ], [
                [ "Enum 'Weekday' must implement Enum and JsonSerializable.", 16 ],
            ] ],

            // The second method names every alias and uses an operator that exists
            // The generated queries take the same arguments as the plain ones, and
            // drop a condition just as quietly, so they are asked for the same things
            "the query arguments" => [ QueryArgumentRule::class, [ "QueryArguments" ], [
                [ "The 'as' parameter in Query::select must be a named argument.", 13 ],
                [ "The 'as' parameter in Query->join must be a named argument.", 14 ],
                [ "The 'on' parameter in Query->join must be a named argument.", 14 ],
                [ "The value 'IS SOMETHING' is not a valid Operator.", 15 ],
                [ "A Query->where without an operator must be given an Exp.", 22 ],
                [ "A Query->where without an operator must be given an Exp.", 31 ],
            ] ],
        ];
    }
}
