<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Database\Builder\SchemaJSON;
use Framework\Database\SchemaFactory;
use Framework\Database\SchemaModel;
use Framework\File\Storage;
use Framework\Utils\JSON;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;

class SchemaJSONTest extends TestCase {
    use TestHelpers;

    private const SchemaFile = "tests/Database/.tmp_schema";


    protected function tearDown(): void {
        Storage::deleteFile(Application::getBasePath(), self::SchemaFile . ".json");
    }


    /**
     * Returns the Framework models, keyed by their table name
     * @return array<string,SchemaModel>
     */
    private function models(): array {
        $result = [];
        foreach (SchemaFactory::buildData(forFramework: true) as $schemaModel) {
            $result[$schemaModel->tableName] = $schemaModel;
        }
        return $result;
    }

    /**
     * Returns the names of the fields of the given table
     * @param array<string,mixed> $schema
     * @return list<string>
     */
    private function fieldNames(array $schema): array {
        $result = [];
        foreach ($schema["fields"] as $field) {
            $result[] = $field["name"];
        }
        return $result;
    }



    public function testTheEntryIsTheDescriptionAndTheWiring(): void {
        $schema = $this->models()["settings"]->toSchemaJSON();

        $this->assertEquals([ "description", "fields", "foreigns" ], array_keys($schema));
    }

    public function testTheFlagsAreNotWrittenOut(): void {
        $schema = $this->models()["credential"]->toSchemaJSON();

        foreach ([ "hasTimestamps", "hasStatus", "hasUsers", "canCreate", "canEdit", "canDelete" ] as $flag) {
            $this->assertArrayNotHasKey($flag, $schema);
        }
    }

    public function testTheDescriptionComesFromTheAttribute(): void {
        $schema = $this->models()["settings"]->toSchemaJSON();

        $this->assertEquals(
            "The application settings, one typed row per variable, grouped in sections.",
            $schema["description"],
        );
    }

    public function testEveryFrameworkModelIsDescribed(): void {
        foreach ($this->models() as $tableName => $schemaModel) {
            $schema = $schemaModel->toSchemaJSON();
            $this->assertNotEquals("", $schema["description"], "$tableName has no description");
        }
    }


    public function testTheColumnsOfTheModelAreListed(): void {
        $names = $this->fieldNames($this->models()["settings"]->toSchemaJSON());

        $this->assertContains("section", $names);
        $this->assertContains("variable", $names);
        $this->assertContains("value", $names);
        $this->assertContains("variableType", $names);
    }

    public function testTheColumnsTheFlagsAddAreListedToo(): void {
        // Settings is declared with hasTimestamps and canEdit, and nothing else
        $names = $this->fieldNames($this->models()["settings"]->toSchemaJSON());

        $this->assertContains("modifiedTime", $names);
        $this->assertNotContains("createdTime", $names);
        $this->assertNotContains("isDeleted", $names);
    }

    public function testTheStatusAndTheSoftDeleteAreListed(): void {
        $names = $this->fieldNames($this->models()["credential"]->toSchemaJSON());

        $this->assertContains("status", $names);
        $this->assertContains("isDeleted", $names);
        $this->assertContains("createdTime", $names);
        $this->assertContains("modifiedTime", $names);
    }

    public function testTheUserColumnsFollowTheFlag(): void {
        // Log Query is the only Framework model declared with hasUsers
        $withUsers    = $this->fieldNames($this->models()["log_query"]->toSchemaJSON());
        $withoutUsers = $this->fieldNames($this->models()["log_error"]->toSchemaJSON());

        $this->assertContains("createdUser", $withUsers);
        $this->assertContains("modifiedUser", $withUsers);
        $this->assertNotContains("createdUser", $withoutUsers);
    }


    public function testAFieldAlwaysCarriesItsNameAndType(): void {
        foreach ($this->models() as $tableName => $schemaModel) {
            foreach ($schemaModel->toSchemaJSON()["fields"] as $field) {
                $this->assertArrayHasKey("name", $field, "a field of $tableName has no name");
                $this->assertArrayHasKey("type", $field, "a field of $tableName has no type");
            }
        }
    }

    public function testAPrimaryColumnSaysSoAndAPlainOneStaysQuiet(): void {
        $fields = $this->models()["settings"]->toSchemaJSON()["fields"];

        // Section is part of the primary key, value is an ordinary column
        $this->assertEquals([ "name", "type", "isPrimary" ], array_keys($fields[0]));
        $this->assertEquals("section", $fields[0]["name"]);
        $this->assertTrue($fields[0]["isPrimary"]);

        $this->assertEquals([ "name", "type" ], array_keys($fields[2]));
        $this->assertEquals("value", $fields[2]["name"]);
    }

    public function testTheDefaultsAreLeftOut(): void {
        foreach ($this->models() as $tableName => $schemaModel) {
            foreach ($schemaModel->toSchemaJSON()["fields"] as $field) {
                $this->assertNotEquals(0, $field["length"] ?? 1, "$tableName writes a length of 0");
                $this->assertNotFalse($field["isPrimary"] ?? true, "$tableName writes isPrimary false");
                $this->assertNotFalse($field["isKey"] ?? true, "$tableName writes isKey false");
            }
        }
    }

    public function testAnIndexedColumnSaysSo(): void {
        $fields = $this->models()["log_action"]->toSchemaJSON()["fields"];

        $byName = [];
        foreach ($fields as $field) {
            $byName[$field["name"]] = $field;
        }
        $this->assertTrue($byName["CREDENTIAL_ID"]["isKey"]);
        $this->assertArrayNotHasKey("isKey", $byName["module"]);
        $this->assertArrayNotHasKey("isPrimary", $byName["module"]);
    }

    public function testAForeignIsGivenAsAnEdge(): void {
        // Log Action points at its session and at its credential
        $schema = $this->models()["log_action"]->toSchemaJSON();

        $this->assertNotEmpty($schema["foreigns"]);
        $this->assertEquals([ "fromField", "toTable", "toField" ], array_keys($schema["foreigns"][0]));

        $tables = [];
        foreach ($schema["foreigns"] as $foreign) {
            $tables[] = $foreign["toTable"];
        }
        $this->assertContains("log_session", $tables);
        $this->assertContains("credential", $tables);
    }

    public function testTheRelationsAndThePlainColumnsShareTheOneList(): void {
        // Whether the framework joins the two tables when it reads them is its own
        // business, so both kinds of edge are given the same way and in one place
        $schema = $this->models()["log_action"]->toSchemaJSON();

        $this->assertArrayNotHasKey("joins", $schema);
        foreach ($schema["foreigns"] as $foreign) {
            $this->assertEquals([ "fromField", "toTable", "toField" ], array_keys($foreign));
        }
    }


    public function testAModelWithNoColumnsIsLeftOut(): void {
        // The migration creates no table for one of these, and the builder
        // writes it no code, so there is no table for the schema to give
        $schemas = SchemaJSON::buildSchema([ new SchemaModel(name: "EmptyThing") ]);

        $this->assertArrayNotHasKey("empty_thing", $schemas);
    }

    public function testTheModelsThatHaveColumnsAreAllKept(): void {
        $schemas = SchemaJSON::buildSchema(SchemaFactory::buildData(forFramework: true));

        $this->assertSame(count($this->models()), count($schemas));
        $this->assertArrayHasKey("credential", $schemas);
    }


    public function testTheFileIsWrittenAndTakenBack(): void {
        // The repository writes its own from the build, so this one is
        // written under the tests and taken back at the end
        $this->setConfig("DB_SCHEMA_FILE", self::SchemaFile);
        $path = Application::getBasePath(self::SchemaFile . ".json");

        $this->assertSame(1, SchemaJSON::generateCode());
        $this->assertTrue(Storage::fileExists($path));

        $schemas = JSON::readFile($path);
        $this->assertArrayHasKey("credential", $schemas);
        $this->assertArrayHasKey("description", $schemas["credential"]);

        $this->assertSame(1, SchemaJSON::destroyCode());
        $this->assertFalse(Storage::fileExists($path));
    }

    public function testWithNoFileNothingIsWritten(): void {
        $this->setConfig("DB_SCHEMA_FILE", "");

        $this->assertSame(0, SchemaJSON::generateCode());
        $this->assertSame(0, SchemaJSON::destroyCode());
    }
}
