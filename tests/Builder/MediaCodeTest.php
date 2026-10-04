<?php
namespace Tests\Builder;

use Framework\Builder\Builder;
use Framework\Database\Builder\MediaCode;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\SchemaModel;
use Framework\Discovery\Package;
use Framework\File\Storage;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;

/**
 * The Media Code, which writes the class that puts a file into a column
 */
class MediaCodeTest extends TestCase {

    public static function setUpBeforeClass(): void {
        $method = new ReflectionMethod(Builder::class, "loadTemplates");
        $method->invoke(null);
    }

    /**
     * Builds a model with one file field of the given type
     * @param FieldType $type
     * @param bool      $jsonFiles Optional.
     * @return SchemaModel
     */
    private function modelWithFile(FieldType $type, bool $jsonFiles = false): SchemaModel {
        $fields = [
            Field::create(name: "crateID", type: FieldType::Number, isID: true),
            Field::create(name: "image", type: $type),
        ];

        // Set on the field rather than asked of create(), the same as the
        // attribute does when the build reads a model
        $fields[1]->isFile    = true;
        $fields[1]->jsonFiles = $jsonFiles;

        return new SchemaModel(
            name:       "Crate",
            namespace:  "Tests\\Builder\\Generated",
            mainFields: $fields,
        );
    }



    /**
     * A file field, and how the generated code is told to write it
     * @param FieldType $type
     * @param bool      $jsonFiles
     * @param string    $expected  The one of the three flags that is set
     * @return void
     */
    #[DataProvider("providerFileFields")]
    public function testTheFieldSaysHowItIsWritten(
        FieldType $type,
        bool $jsonFiles,
        string $expected,
    ): void {
        $fields = MediaCode::getFields([ $this->modelWithFile($type, jsonFiles: $jsonFiles) ]);

        $this->assertCount(1, $fields);
        foreach ([ "isSet", "isReplace", "isJSON" ] as $flag) {
            $this->assertSame($flag === $expected, $fields[0][$flag], "$flag on a $expected field");
        }
    }

    /**
     * A file named inside text is replaced where it is written; one in a json
     * list is rewritten through that list; anything else is the whole value
     * @return array<string,array{FieldType,bool,string}>
     */
    public static function providerFileFields(): array {
        return [
            "a string column"    => [ FieldType::String, false, "isSet" ],
            "a text column"      => [ FieldType::Text, false, "isReplace" ],
            "a long text column" => [ FieldType::LongText, false, "isReplace" ],
            "a json column"      => [ FieldType::JSON, false, "isReplace" ],
            "a json of files"    => [ FieldType::JSON, true, "isJSON" ],
            "text holding files" => [ FieldType::Text, true, "isJSON" ],
        ];
    }

    public function testTheFieldIsNamedWithItsModel(): void {
        $fields = MediaCode::getFields([ $this->modelWithFile(FieldType::String) ]);

        $this->assertSame("Crate", $fields[0]["name"]);
        $this->assertSame("crateQuery", $fields[0]["query"]);
        $this->assertSame("crate", $fields[0]["tableName"]);
        $this->assertSame("image", $fields[0]["fieldName"]);
    }

    public function testAFieldThatHoldsNoFileIsPassedOver(): void {
        $model = new SchemaModel(
            name:       "Crate",
            namespace:  "Tests\\Builder\\Generated",
            mainFields: [ Field::create(name: "name", type: FieldType::String) ],
        );

        $this->assertSame([], MediaCode::getFields([ $model ]));
    }

    public function testEveryModelIsWalked(): void {
        $fields = MediaCode::getFields([
            $this->modelWithFile(FieldType::String),
            $this->modelWithFile(FieldType::Text),
        ]);

        $this->assertCount(2, $fields);
        $this->assertTrue($fields[0]["isSet"]);
        $this->assertTrue($fields[1]["isReplace"]);
    }

    public function testNoModelsMeanNoFields(): void {
        $this->assertSame([], MediaCode::getFields([]));
    }





    /**
     * Renders the class for a model with one file field of the given type
     * @param FieldType $type
     * @param bool      $jsonFiles Optional.
     * @return string
     */
    private function codeFor(FieldType $type, bool $jsonFiles = false): string {
        $fields  = MediaCode::getFields([ $this->modelWithFile($type, jsonFiles: $jsonFiles) ]);
        $hasJSON = false;
        foreach ($fields as $field) {
            if ($field["isJSON"]) {
                $hasJSON = true;
            }
        }

        return Builder::render("MediaSchema", [
            "fields"     => $fields,
            "hasFields"  => count($fields) > 0,
            "hasReplace" => true,
            "hasJSON"    => $hasJSON,
            "total"      => count($fields),
        ]);
    }

    public function testAJsonOfFilesAsksForTheConditionWhole(): void {
        // The condition binds a value and has no column of its own, so it is
        // given as an Expression, which is what took the place of whereExp
        $code = $this->codeFor(FieldType::JSON, jsonFiles: true);

        $this->assertStringContainsString("use Framework\\Database\\Query\\Exp;", $code);
        $this->assertStringContainsString('->where(Exp::jsonValid("`image`"))', $code);
        $this->assertStringContainsString(
            '->where(Exp::jsonSearch("`image`", $old)->isNotNull())',
            $code,
        );
        $this->assertStringNotContainsString("whereExp", $code);
    }

    public function testTheFilesInsideADirectoryChangeWithIt(): void {
        // A Directory renamed, moved or deleted takes the Files inside it along, matching
        // only the start of the path and with its case, not any text that is like it
        $code = $this->codeFor(FieldType::String);

        $this->assertStringContainsString('Assign::replacePrefix($oldDir, $newDir)', $code);
        $this->assertSame(2, substr_count(
            $code,
            '->where(Exp::create("LEFT(`image`, ?)", $length), Op::Equal, $oldDir, caseSensitive: true)',
        ));
    }

    public function testAFieldThatIsNoJsonAsksForNoJsonExpression(): void {
        $code = $this->codeFor(FieldType::String);

        $this->assertStringNotContainsString("Exp::json", $code);
        $this->assertStringNotContainsString("Assign::json", $code);
    }
    public function testTheGeneratedCodeParses(): void {
        // It writes into the build directory, which is gitignored and holds
        // what ./framework build already put there
        ob_start();
        $written = MediaCode::generateCode();
        ob_get_clean();

        $this->assertSame(1, $written);

        $code = Storage::readFile(Package::getBuildPath(), "MediaSchema.php");
        $this->assertNotSame("", $code);
        $this->assertStringContainsString("class MediaSchema", $code);

        $error = null;
        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $error = $e->getMessage();
        }
        $this->assertNull($error, "the media schema does not parse: $error");
    }

    public function testThereIsNothingToDestroy(): void {
        // The class is written into the build directory, which the build empties
        $this->assertSame(1, MediaCode::destroyCode());
    }
}
