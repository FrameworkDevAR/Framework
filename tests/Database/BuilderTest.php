<?php
namespace Tests\Database;

use Framework\Database\SchemaModel;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Core\Schema\SettingsSchema;
use Framework\Auth\Schema\CredentialSchema;
use Framework\Database\Query\ModificationBuilder;
use Framework\Database\Query\SelectionBuilder;
use Framework\Database\Query\Query;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionProperty;

class BuilderTest extends TestCase {

    /**
     * Returns one of the Framework's own models, taken from the generated
     * schema, which is the shape the runtime actually reads with
     * @param string $tableName
     * @return SchemaModel
     */
    private function model(string $tableName): SchemaModel {
        return match ($tableName) {
            "credential" => CredentialSchema::getModel(),
            default      => SettingsSchema::getModel(),
        };
    }



    public function testInsertCollectsTheFields(): void {
        $builder = ModificationBuilder::insert($this->model("settings"));
        $builder->setField("section", "general");
        $builder->setField("variable", "name");

        $this->assertEquals(
            [ "section" => "general", "variable" => "name" ],
            $builder->getFields()->toArray(),
        );
    }

    public function testReplaceCollectsTheFields(): void {
        $builder = ModificationBuilder::replace($this->model("settings"));
        $builder->setField("section", "general");

        $this->assertEquals([ "section" => "general" ], $builder->getFields()->toArray());
    }

    public function testUpdateTakesTheQueryItWillNarrowWith(): void {
        $query = Query::select("settings");
        $query->where("section", "=", "general");

        $builder = ModificationBuilder::update($this->model("settings"), $query);
        $builder->setField("value", "x");

        $this->assertEquals([ "value" => "x" ], $builder->getFields()->toArray());
    }

    public function testFieldsCanBeAddedInOneCall(): void {
        $builder = ModificationBuilder::insert($this->model("settings"));
        $builder->addFields([ "section" => "general", "variable" => "name" ]);

        $this->assertEquals(
            [ "section" => "general", "variable" => "name" ],
            $builder->getFields()->toArray(),
        );
    }

    public function testCreationAddsTheColumnsTheModelDeclares(): void {
        $builder = ModificationBuilder::insert($this->model("credential"));
        $builder->setField("email", "ada@example.com");
        $builder->addCreation(7);

        $fields = $builder->getFields()->toArray();
        $this->assertArrayHasKey("createdTime", $fields);
        $this->assertArrayHasKey("isDeleted", $fields);
    }

    public function testModificationStampsTheTimeUnlessItIsSkipped(): void {
        $stamped = ModificationBuilder::insert($this->model("credential"));
        $stamped->setField("email", "ada@example.com");
        $stamped->addModification(7);

        $skipped = ModificationBuilder::insert($this->model("credential"));
        $skipped->setField("email", "ada@example.com");
        $skipped->addModification(7, skipTimestamps: true);

        $this->assertArrayHasKey("modifiedTime", $stamped->getFields()->toArray());
        $this->assertArrayNotHasKey("modifiedTime", $skipped->getFields()->toArray());
    }


    /**
     * A model that stamps both who and when, so the audit columns exist
     * @return SchemaModel
     */
    private function auditedModel(): SchemaModel {
        return new SchemaModel(
            name:          "Product",
            hasUsers:      true,
            hasTimestamps: true,
            canCreate:     true,
            canEdit:       true,
            canDelete:     true,
            mainFields:    [
                Field::create(name: "productID", type: FieldType::Number, dbName: "PRODUCT_ID", isID: true),
                Field::create(name: "name", type: FieldType::String),
            ],
        );
    }

    public function testCreationStampsTheTimeAndTheUser(): void {
        $builder = ModificationBuilder::insert($this->auditedModel());
        $builder->setField("name", "Widget");
        $builder->addCreation(7);

        $fields = $builder->getFields()->toArray();
        $this->assertArrayHasKey("createdTime", $fields);
        $this->assertEquals(7, $fields["createdUser"]);
        $this->assertEquals(0, $fields["isDeleted"]);
    }

    public function testCreationWithoutACredentialLeavesTheUserOut(): void {
        $builder = ModificationBuilder::insert($this->auditedModel());
        $builder->setField("name", "Widget");
        $builder->addCreation();

        $fields = $builder->getFields()->toArray();
        $this->assertArrayHasKey("createdTime", $fields);
        $this->assertArrayNotHasKey("createdUser", $fields);
    }

    /**
     * A model whose rows keep the Environment that created them
     * @return SchemaModel
     */
    private function envModel(): SchemaModel {
        return new SchemaModel(
            name:       "Job",
            hasEnv:     true,
            canCreate:  true,
            canEdit:    true,
            mainFields: [
                Field::create(name: "jobID", type: FieldType::Number, dbName: "JOB_ID", isID: true),
                Field::create(name: "name", type: FieldType::String),
            ],
        );
    }

    public function testAnEnvModelHasTheColumn(): void {
        $this->assertContains("env", $this->envModel()->getFieldNames());
        $this->assertNotContains("env", $this->auditedModel()->getFieldNames());
    }

    #[DataProvider("providerEnvCreation")]
    public function testCreationWritesTheEnvironment(string $given, string $expected): void {
        $builder = ModificationBuilder::insert($this->envModel());
        $builder->setField("name", "Send");
        if ($given !== "") {
            $builder->setField("env", $given);
        }
        $builder->addCreation();

        $this->assertSame($expected, $builder->getFields()->toArray()["env"]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerEnvCreation(): array {
        return [
            "the current one"  => [ "", "local" ],
            "the one given"    => [ "dev", "dev" ],
        ];
    }

    public function testModificationLeavesTheEnvironment(): void {
        $builder = ModificationBuilder::insert($this->envModel());
        $builder->setField("name", "Send");
        $builder->addModification(9);

        $this->assertArrayNotHasKey("env", $builder->getFields()->toArray());
    }

    public function testModificationStampsTheTimeAndTheUser(): void {
        $builder = ModificationBuilder::insert($this->auditedModel());
        $builder->setField("name", "Widget");
        $builder->addModification(9);

        $fields = $builder->getFields()->toArray();
        $this->assertArrayHasKey("modifiedTime", $fields);
        $this->assertEquals(9, $fields["modifiedUser"]);
    }

    public function testSkippingTheTimestampsDropsBoth(): void {
        $builder = ModificationBuilder::insert($this->auditedModel());
        $builder->setField("name", "Widget");
        $builder->addModification(9, skipTimestamps: true);

        $this->assertEquals([ "name" => "Widget" ], $builder->getFields()->toArray());
    }

    public function testSelectionBuildsTheColumnListFromTheModel(): void {
        $query = Query::select("credential");
        $query->where("credentialID", "=", 1);

        $selection = SelectionBuilder::create($this->model("credential"), $query);
        $selection->addFields();

        $sql = $selection->toDebugSQL();
        $this->assertStringContainsString("SELECT", $sql);
        $this->assertStringContainsString("credential.CREDENTIAL_ID", $sql);
        $this->assertStringContainsString("credential.email", $sql);
    }

    public function testSelectionCarriesTheQueryBindings(): void {
        $query = Query::select("credential");
        $query->where("credentialID", "=", 1);

        $selection = SelectionBuilder::create($this->model("credential"), $query);
        $selection->addFields();

        $this->assertEquals([ 1 ], $selection->getBindings());
    }

    public function testSelectionTakesTheExtraParts(): void {
        $query = Query::select("credential");

        $selection = SelectionBuilder::create($this->model("credential"), $query);
        $selection->addFields();
        $selection->addExpressions();
        $selection->addSelects("COUNT(*) AS total");
        $selection->addJoins();
        $selection->addCounts();

        $this->assertStringContainsString("COUNT(*) AS total", $selection->toDebugSQL());
    }

    /**
     * Puts rows into the builder as though the request had returned them,
     * which is the only part of the selection that needs a connection
     * @param SelectionBuilder    $selection
     * @param list<array<string,mixed>> $rows
     * @return void
     */
    private function injectRequest(SelectionBuilder $selection, array $rows): void {
        $property = new ReflectionProperty(SelectionBuilder::class, "request");
        $property->setValue($selection, new Dictionary($rows));
    }

    public function testTheResultIsTheRequestThatWasMade(): void {
        $selection = SelectionBuilder::create($this->model("settings"), Query::select("settings"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "section" => "general", "variable" => "name", "value" => "App" ],
        ]);

        $this->assertCount(1, $selection->getResult()->toArray());
    }

    public function testResolveReturnsOneEntryPerRow(): void {
        $selection = SelectionBuilder::create($this->model("settings"), Query::select("settings"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "section" => "general", "variable" => "name", "value" => "App" ],
            [ "section" => "email",   "variable" => "from", "value" => "a@b.c" ],
        ]);

        $this->assertCount(2, $selection->resolve());
    }

    public function testResolveOnNoRowsGivesNothing(): void {
        $selection = SelectionBuilder::create($this->model("settings"), Query::select("settings"));
        $selection->addFields();
        $this->injectRequest($selection, []);

        $this->assertEquals([], $selection->resolve());
    }

    public function testResolveMapsTheRowOntoTheModelFields(): void {
        $selection = SelectionBuilder::create($this->model("settings"), Query::select("settings"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "section" => "general", "variable" => "name", "value" => "App", "variableType" => "string" ],
        ]);

        $resolved = $selection->resolve();

        // The row comes back keyed by the model's field names
        $this->assertEquals("general", $resolved[0]["section"]);
        $this->assertEquals("name", $resolved[0]["variable"]);
        $this->assertEquals("App", $resolved[0]["value"]);
    }

    public function testDecryptedFieldsAreRequestedSeparately(): void {
        $query = Query::select("credential");

        $plain = SelectionBuilder::create($this->model("credential"), $query);
        $plain->addFields();

        $decrypted = SelectionBuilder::create($this->model("credential"), $query);
        $decrypted->addFields(decrypted: true);

        $this->assertIsString($plain->toDebugSQL());
        $this->assertIsString($decrypted->toDebugSQL());
    }
}
