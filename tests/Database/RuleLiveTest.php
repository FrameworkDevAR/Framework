<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Discovery\Type\ComposerData;
use Framework\Builder\Builder;
use Framework\Database\Database;
use Framework\Database\SchemaBuilder;
use Framework\Database\SchemaFactory;
use Framework\Database\Migration\SchemaMigration;
use Framework\File\Storage;
use Framework\IO\Request;
use Framework\Utils\Strings;

use Tests\LiveTestCase;
use Tests\TestHelpers;
use Tests\Database\Rules\RuleTags;
use Tests\Database\Rules\Rules;
use Tests\Database\Rules\Schema\RuleRequest;
use Tests\Database\Rules\Schema\RuleSchema;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The validation a Schema is given, run rather than read
 *
 * The rules are written as one chain of if and elseif per field, so which one
 * answers for a value depends on the ones beside it. Reading the code back
 * says the rules are there; only running it says the right one answers. So
 * the classes are generated where they can be loaded, the tables are migrated
 * from the same Models, and a request is put through them.
 *
 * Every case starts from a request that validates clean and spoils one field,
 * which is what keeps the case about the rule it is named after.
 */
class RuleLiveTest extends LiveTestCase {
    use TestHelpers;

    private const SourceDir = "tests/Database/Rules";
    private const Namespace = "Tests\\Database\\Rules\\";

    private static ?Database $db      = null;
    private static bool      $isBuilt = false;
    private static int       $tagID   = 0;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();
        $this->build();

        $this->query("DELETE FROM `rule`");
    }

    public static function tearDownAfterClass(): void {
        self::$db?->execute("DROP TABLE IF EXISTS `rule`");
        self::$db?->execute("DROP TABLE IF EXISTS `rule_tag`");
        parent::tearDownAfterClass();
    }

    /**
     * Writes the classes of the Models of the rules and migrates their tables
     *
     * They are written where they can be loaded rather than into a directory
     * of their own, since here they are run and not only read
     * @return void
     */
    private function build(): void {
        self::$db = $this->db();
        if (self::$isBuilt) {
            return;
        }
        self::$isBuilt = true;

        // The code is rendered from templates the build reads once, so
        // without them every file would be written empty
        $this->callPrivateStaticMethod(Builder::class, "loadTemplates");

        $composerWas = $this->swapComposer(new ComposerData(
            namespace: self::Namespace,
            sourceDir: self::SourceDir,
        ));

        ob_start();
        try {
            SchemaBuilder::generateSchemaCode(SchemaFactory::buildData(), "Rules");
            SchemaMigration::migrateData([], [], canDelete: false);
        } finally {
            ob_get_clean();
            $this->swapComposer($composerWas);
        }

        $this->query("DELETE FROM `rule_tag`");
        self::$tagID = RuleTags::add("A tag");
    }

    /**
     * Returns a request that validates clean
     * @return array<string,mixed>
     */
    private function validData(): array {
        return [
            "tagIDs"     => "[" . self::$tagID . "]",
            "name"       => "A rule",
            "slug"       => "abc",
            "title"      => "A title",
            "kind"       => "Second",
            "flavor"     => "First",
            "amount"     => 5,
            "total"      => 3,
            "ruleTagID"  => self::$tagID,
            "otherTagID" => self::$tagID,
            "bigger"     => 30,
            "serial"     => 1,
            "ticket"     => 4,
            "email"      => "someone@framework.test",
            "otherEmail" => "other@framework.test",
            "website"    => "https://frameworkphp.com.ar",
            "price"      => "10.50",
            "fromDate"   => "01-01-2024",
            "fromHour"   => "10:00",
            "toDate"     => "02-01-2024",
            "color"      => "#e8384f",
            "status"     => "Active",
        ];
    }

    /**
     * Validates a request built from the valid data with the given overrides
     * @param array<string,mixed> $overrides Optional.
     * @return array<string,string>
     */
    private function validate(array $overrides = []): array {
        $data    = $overrides + $this->validData();
        $request = RuleRequest::fromRequest(new Request($data));
        return RuleSchema::validateRequest($request)->errors->get();
    }



    public function testEveryErrorHasACase(): void {
        // The generated code names every error it can write, so this reads
        // them back and demands a case that provokes each one: a rule added
        // to the Model fails here until it is put through its paces
        $code  = Storage::readFile(
            Application::getBasePath(self::SourceDir, "Schema"),
            "RuleSchema.php",
        );
        $start = Strings::substringAfter($code, "function validateRequest");
        $body  = Strings::substringBefore($start, "function ");

        preg_match_all('/"([A-Z][A-Z_]*_ERROR_?[A-Z_]*)"/', $body, $matches);
        $written = array_unique($matches[1]);
        sort($written);

        $covered = [];
        foreach (self::providerRules() as $case) {
            $covered[] = is_array($case[2]) ? $case[2][0] : $case[2];
        }
        // The ones the plain tests provoke, which no provider case carries
        $covered[] = "RULE_ERROR_EDIT";
        $covered[] = "RULE_ERROR_NOTE";
        $covered[] = "RULE_ERROR_PERCENT_EMPTY";
        $covered[] = "RULE_ERROR_PERCENT_INVALID";
        $covered[] = "RULE_ERROR_NAME_EXISTS";
        $covered[] = "RULE_ERROR_TICKET_EXISTS";
        $covered[] = "RULE_ERROR_OTHER_EMAIL_EXISTS";
        $covered[] = "RULE_ERROR_EXISTS";
        $covered[] = "RULE_ERROR_SERIAL_EXISTS";

        $this->assertSame(
            [],
            array_values(array_diff($written, $covered)),
            "Errors the code can write that no case provokes",
        );
    }

    public function testAWholeRequestPasses(): void {
        // Which is what every other case leans on: one field is spoiled and
        // the rest are these, so a rule that fires here would fire there too
        $this->assertSame([], $this->validate());
    }

    public function testTheOverriddenCanEditIsAsked(): void {
        // The generated canEdit answers true and is there to be overridden
        // by the class of the app, so a validation run through that class
        // has to ask the override
        $request = RuleRequest::fromRequest(new Request($this->validData()));

        Rules::$canEdit = false;
        try {
            $result = Rules::validateRequest($request);
        } finally {
            Rules::$canEdit = true;
        }

        $this->assertSame([ "form" => "RULE_ERROR_EDIT" ], $result->errors->get());
        $this->assertFalse($result->canValidate);
    }

    public function testAPassingRequestCanBeSaved(): void {
        $request = RuleRequest::fromRequest(new Request($this->validData()));

        $this->assertTrue(RuleSchema::validateRequest($request)->canValidate);
    }

    /**
     * A field spoiled one way, and the one error it is answered with
     * @param array<string,mixed>  $overrides
     * @param string               $field
     * @param string|array{string,int} $error
     * @return void
     */
    #[DataProvider("providerRules")]
    public function testARuleAnswersForItsField(array $overrides, string $field, string|array $error): void {
        $errors = $this->validate($overrides);

        $this->assertSame([ $field => $error ], $errors);
    }

    /**
     * Each case names the rule that has to answer, which for a field with
     * more than one is the first of them that the value fails
     * @return array<string,array{array<string,mixed>,string,string|array{string,int}}>
     */
    public static function providerRules(): array {
        return [
            // A string that is required and unique: the empty check first
            "a name that is missing"    => [ [ "name" => "" ], "name", "RULE_ERROR_NAME_EMPTY" ],

            // A string with only a length
            "a slug that is too long"   => [ [ "slug" => "abcdef" ], "slug", [ "RULE_ERROR_SLUG", 5 ] ],

            // Required and a length: the empty check comes first
            "a title that is missing"   => [ [ "title" => "" ], "title", "RULE_ERROR_TITLE_EMPTY" ],
            "a title that is too long"  => [ [ "title" => "a long title" ], "title", [ "RULE_ERROR_TITLE_LENGTH", 8 ] ],

            // A typeOf on its own is checked even when the value is empty
            "a kind of no such name"    => [ [ "kind" => "Third" ], "kind", "RULE_ERROR_KIND" ],
            "a kind that is missing"    => [ [ "kind" => "" ], "kind", "RULE_ERROR_KIND" ],

            // Required and a typeOf: the empty check first, then the type
            "a flavor that is missing"  => [ [ "flavor" => "" ], "flavor", "RULE_ERROR_FLAVOR_EMPTY" ],
            "a flavor of no such name"  => [ [ "flavor" => "Third" ], "flavor", "RULE_ERROR_FLAVOR_INVALID" ],

            // A range, which an empty value is outside of
            "an amount below its floor" => [ [ "amount" => 0 ], "amount", "RULE_ERROR_AMOUNT" ],
            "an amount above its roof"  => [ [ "amount" => 11 ], "amount", "RULE_ERROR_AMOUNT" ],

            // Required and numeric: zero is the empty of a number
            "a total that is missing"   => [ [ "total" => 0 ], "total", "RULE_ERROR_TOTAL_EMPTY" ],
            "a total below zero"        => [ [ "total" => -5 ], "total", "RULE_ERROR_TOTAL_INVALID" ],

            // A belongsTo on its own lets an empty value through
            "a tag that is not there"   => [ [ "ruleTagID" => 9999 ], "ruleTagID", "RULE_TAGS_ERROR_EXISTS" ],

            // Required and a belongsTo: the empty check first
            "another tag missing"       => [ [ "otherTagID" => 0 ], "otherTagID", "RULE_ERROR_OTHER_TAG" ],
            "another tag not there"     => [ [ "otherTagID" => 9999 ], "otherTagID", "RULE_TAGS_ERROR_EXISTS" ],

            // Numeric and greaterThan: the range first, then the other field
            "a bigger below zero"       => [ [ "bigger" => -1 ], "bigger", "RULE_ERROR_BIGGER_INVALID" ],
            "a bigger below the other"  => [ [ "bigger" => 3 ], "bigger", "RULE_ERROR_BIGGER_GREATER" ],
            "a bigger that is missing"  => [ [ "bigger" => 0 ], "bigger", "RULE_ERROR_BIGGER_GREATER" ],

            // Numeric and unique: the range first
            "a serial below its floor"  => [ [ "serial" => 0 ], "serial", "RULE_ERROR_SERIAL_INVALID" ],
            "a ticket below zero"       => [ [ "ticket" => -1 ], "ticket", "RULE_ERROR_TICKET_INVALID" ],

            // Required and an email
            "an email that is missing"  => [ [ "email" => "" ], "email", "GENERAL_ERROR_EMAIL_EMPTY" ],
            "an email of no shape"      => [ [ "email" => "not an email" ], "email", "GENERAL_ERROR_EMAIL_INVALID" ],

            // Required and a url
            "a site that is missing"    => [ [ "website" => "" ], "website", "GENERAL_ERROR_URL_EMPTY" ],
            "a site of no shape"        => [ [ "website" => "not a url" ], "website", "GENERAL_ERROR_URL_INVALID" ],

            // A price, which is a number of two decimals
            "a price that is missing"   => [ [ "price" => "" ], "price", "RULE_ERROR_PRICE_EMPTY" ],
            "a price below zero"        => [ [ "price" => "-1.00" ], "price", "RULE_ERROR_PRICE_INVALID" ],

            // A date that is required, with an hour of its own
            "a date that is missing"    => [ [ "fromDate" => "" ], "fromDate", "GENERAL_ERROR_FROM_DATE_EMPTY" ],
            "a date of no shape"        => [ [ "fromDate" => "not a date" ], "fromDate", "GENERAL_ERROR_FROM_DATE_INVALID" ],
            "an hour that is missing"   => [ [ "fromHour" => "" ], "fromDate", "GENERAL_ERROR_FROM_HOUR_EMPTY" ],

            // An hour that parses as a date but is not one of the clock
            "an hour of no sense"       => [ [ "fromHour" => "24:00" ], "fromDate", "GENERAL_ERROR_FROM_HOUR_INVALID" ],
            "a to date of no sense"     => [ [ "toDate" => "not a date" ], "toDate", "GENERAL_ERROR_TO_DATE_INVALID" ],

            // The second date is only checked against the first
            "a period that runs back"   => [ [ "toDate" => "01-12-2023" ], "toDate", "GENERAL_ERROR_DATE_PERIOD" ],

            // A color is a typeOf of the framework with a shared error key
            "a color of no such hex"    => [ [ "color" => "#123456" ], "color", "GENERAL_ERROR_COLOR" ],
            "a color that is missing"   => [ [ "color" => "" ], "color", "GENERAL_ERROR_COLOR" ],

            // A list of ids, each looked up in the Model it belongs to
            "a tag of the list is gone" => [ [ "tagIDs" => "[9999]" ], "tagIDs", "RULE_TAGS_ERROR_SOME_EXISTS" ],
        ];
    }

    public function testAValueThatIsNotAskedFor(): void {
        // A rule that is not required lets an empty value past rather than
        // running the check it carries against it
        $this->assertSame([], $this->validate([ "slug" => "" ]));
        $this->assertSame([], $this->validate([ "ruleTagID" => 0 ]));
    }



    public function testTheRuleOnlyAsksWhenItSaysTo(): void {
        // The note is required only when the kind is the one named, so the
        // same empty value passes or fails depending on another field
        $this->assertSame([], $this->validate([ "kind" => "Second", "note" => "" ]));

        $this->assertSame(
            [ "note" => "RULE_ERROR_NOTE" ],
            $this->validate([ "kind" => "First", "note" => "", "percent" => 50 ]),
        );
    }

    public function testARequiredFloatFires(): void {
        // The empty of a float is 0.0, and a strict check against the int
        // zero never matched: the required rule on a float silently passed.
        // The key carries a suffix, since the range writes the same one
        $this->assertSame(
            [ "percent" => "RULE_ERROR_PERCENT_EMPTY" ],
            $this->validate([ "kind" => "First", "note" => "A note", "percent" => 0 ]),
        );
    }

    public function testTheFloatRangeStillRuns(): void {
        $this->assertSame(
            [ "percent" => "RULE_ERROR_PERCENT_INVALID" ],
            $this->validate([ "kind" => "First", "note" => "A note", "percent" => 200 ]),
        );
    }

    public function testTheConditionIsNotTheRule(): void {
        $this->assertSame([], $this->validate([
            "kind"    => "First",
            "note"    => "A note",
            "percent" => 50,
        ]));
    }



    public function testEditingAMissingRowIsOneError(): void {
        // An edit names its row, and one that is gone is answered with the
        // one form error rather than a page of field errors
        $errors = $this->validate([ "ruleID" => 9999 ]);

        $this->assertSame([ "form" => "RULE_ERROR_EXISTS" ], $errors);
    }

    public function testASerialIsTakenOnce(): void {
        Rules::add("Another rule", 1, "one@framework.test");

        $this->assertSame(
            [ "serial" => "RULE_ERROR_SERIAL_EXISTS" ],
            $this->validate([ "serial" => 1 ]),
        );
    }

    public function testAUniqueValueIsTakenOnce(): void {
        $this->save();

        $this->assertSame(
            [ "name" => "RULE_ERROR_NAME_EXISTS" ],
            $this->validate([ "serial" => 2, "otherEmail" => "third@framework.test" ]),
        );
    }

    public function testAnEmptyValueIsNotUnique(): void {
        // A field that is not required is one rows are allowed to leave
        // empty, so the unique check is only made of a value there is
        Rules::add("Another rule", 2, "");

        $this->assertSame([], $this->validate([ "serial" => 3, "otherEmail" => "" ]));
    }

    public function testAnEmptyNumberIsNotUniqueEither(): void {
        Rules::add("Another rule", 2, "one@framework.test");

        $this->assertSame([], $this->validate([
            "serial"     => 3,
            "otherEmail" => "two@framework.test",
            "ticket"     => 0,
        ]));
    }

    public function testANumberIsTakenOnce(): void {
        Rules::add("Another rule", 2, "one@framework.test", 4);

        $this->assertSame(
            [ "ticket" => "RULE_ERROR_TICKET_EXISTS" ],
            $this->validate([ "serial" => 3, "otherEmail" => "two@framework.test" ]),
        );
    }

    public function testARowDoesNotClashWithItself(): void {
        // The row being edited is the one the unique check skips, and the id
        // it skips is the one of the request. A list of ids validated before
        // it used to leave the last of them there instead
        $ruleID = $this->save();

        $errors = $this->validate([
            "ruleID" => $ruleID,
            "tagIDs" => "[" . self::$tagID . "]",
        ]);

        $this->assertSame([], $errors);
    }

    /**
     * Writes the row of the valid data, and hands back its id
     * @return int
     */
    private function save(): int {
        $data = $this->validData();
        return Rules::add(
            (string)$data["name"],
            (int)$data["serial"],
            (string)$data["otherEmail"],
        );
    }
}
