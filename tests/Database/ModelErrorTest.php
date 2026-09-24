<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Discovery\Type\ComposerData;
use Framework\Database\SchemaFactory;
use Framework\Database\SchemaModel;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;

/**
 * What the parser does with a Model it cannot read
 *
 * Every Model of the repository is one it can, so the ones that are not are
 * kept under the fixtures and the Application is pointed at them. A Model
 * that cannot be read is named rather than thrown over, since the build has
 * the rest of them to write.
 */
class ModelErrorTest extends TestCase {
    use TestHelpers;

    private const SourceDir = "tests/Database/Broken";
    private const Namespace = "Tests\\Database\\Broken\\";

    /** @var list<SchemaModel> */
    private static array $schemaModels = [];

    private static string $output = "";


    protected function setUp(): void {
        if (self::$output !== "") {
            return;
        }

        $composerWas = $this->swapComposer(new ComposerData(
            namespace: self::Namespace,
            sourceDir: self::SourceDir,
        ));

        ob_start();
        try {
            self::$schemaModels = SchemaFactory::buildData();
        } finally {
            self::$output = (string)ob_get_clean();
            $this->swapComposer($composerWas);
        }
    }

    /**
     * Returns the names of the Models that were parsed
     * @return list<string>
     */
    private function names(): array {
        $result = [];
        foreach (self::$schemaModels as $schemaModel) {
            $result[] = $schemaModel->name;
        }
        return $result;
    }



    public function testTheOnesWithErrorAreNamed(): void {
        $this->assertStringContainsString("MODELS WITH ERROR:", self::$output);
        $this->assertStringContainsString(
            "BrokenModel: Model attribute could not be instantiated",
            self::$output,
        );
        $this->assertStringContainsString(
            "BrokenField: name attribute could not be instantiated",
            self::$output,
        );
    }

    public function testAnIndexOverNoFieldIsNamedAndLeftOut(): void {
        // The Model is kept, with the Index it could make. The other would
        // break the table, so it is said rather than written
        $this->assertStringContainsString("BadIndex: Index name_nothing names no field nothing", self::$output);
        $this->assertContains("BadIndex", $this->names());

        foreach (self::$schemaModels as $schemaModel) {
            if ($schemaModel->name === "BadIndex") {
                $this->assertCount(1, $schemaModel->indexes);
                $this->assertSame("name", $schemaModel->indexes[0]->name);
            }
        }
    }

    public function testAModelWithNoAttributeIsDropped(): void {
        $this->assertNotContains("BrokenModel", $this->names());
    }

    public function testAFieldWithNoAttributeIsDropped(): void {
        // The Model is still parsed, it just loses the field it could not read
        $this->assertContains("BrokenField", $this->names());
        $this->assertSame([ "brokenFieldID" ], $this->fieldsOf("BrokenField"));
    }

    public function testAnEnumOfPhpIsNotOne(): void {
        // It has to be one of the Framework, and the whole Model is dropped
        // rather than parsed without the field
        $this->assertNotContains("PlainEnum", $this->names());
    }

    public function testAnEnumThatIsNotJsonIsNotOne(): void {
        $this->assertNotContains("HalfEnum", $this->names());
    }

    public function testTheOddPropertiesAreWalkedPast(): void {
        // One has no type at all, one is a class the parser has no use for,
        // and the second Status is the one it already has
        $this->assertContains("OddProps", $this->names());

        $fields = $this->fieldsOf("OddProps");
        $this->assertContains("oddPropsID", $fields);
        $this->assertContains("status", $fields);
        $this->assertNotContains("otherStatus", $fields);
        $this->assertNotContains("anything", $fields);
    }

    /**
     * Returns the names of the fields of the Model of the given name
     * @param string $name
     * @return list<string>
     */
    private function fieldsOf(string $name): array {
        $result = [];
        foreach (self::$schemaModels as $schemaModel) {
            if ($schemaModel->name !== $name) {
                continue;
            }
            foreach ($schemaModel->fields as $field) {
                $result[] = $field->name;
            }
        }
        return $result;
    }
}
