<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Builder\Builder;
use Framework\Database\SchemaBuilder;
use Framework\Database\SchemaFactory;
use Framework\Database\SchemaModel;
use Framework\File\Storage;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;

/**
 * The Schema Builder, which writes the classes of every Model
 *
 * It writes into the path each Model carries, so the Models of this
 * repository are taken and pointed at a directory of the tests, which is what
 * keeps the generated code of the repository out of it.
 */
class SchemaBuilderTest extends TestCase {
    use TestHelpers;

    private const FixtureDir = "tests/Database/.tmp_schema";


    protected function setUp(): void {
        // The code is rendered from templates the build reads once, so
        // without them every file would be written empty
        $this->callPrivateStaticMethod(Builder::class, "loadTemplates");
    }

    protected function tearDown(): void {
        Storage::deleteDir(Application::getBasePath(self::FixtureDir));
    }

    /**
     * Returns the Models of the repository, written into the fixture instead
     * @param string $name Optional. Only the Model of this name
     * @return list<SchemaModel>
     */
    private function models(string $name = ""): array {
        $result = [];
        foreach (SchemaFactory::buildData(forFramework: true) as $schemaModel) {
            if ($name !== "" && $schemaModel->name !== $name) {
                continue;
            }
            $schemaModel->path = Application::getBasePath(self::FixtureDir, $schemaModel->name);
            $result[] = $schemaModel;
        }
        return $result;
    }

    /**
     * Generates the given Models, keeping what it printed
     * @param list<SchemaModel> $schemaModels
     * @param string            $name         Optional.
     * @return string
     */
    private function generate(array $schemaModels, string $name = "Test"): string {
        ob_start();
        try {
            SchemaBuilder::generateSchemaCode($schemaModels, $name);
        } finally {
            $output = ob_get_clean();
        }
        return (string)$output;
    }

    /**
     * Returns the files written for the Model of the given name
     * @param string $name
     * @return list<string>
     */
    private function filesOf(string $name): array {
        $path = Application::getBasePath(self::FixtureDir, $name);
        if (!Storage::fileExists($path)) {
            return [];
        }

        $result = Storage::getFilesInDir($path);
        sort($result);
        return $result;
    }



    public function testTheClassesOfAModelAreWritten(): void {
        $this->generate($this->models("Credential"));

        $files = $this->filesOf("Credential");
        $this->assertContains("CredentialSchema.php", $files);
        $this->assertContains("CredentialEntity.php", $files);
        $this->assertContains("CredentialColumn.php", $files);
        $this->assertContains("CredentialQuery.php", $files);
        $this->assertContains("CredentialRequest.php", $files);
    }

    public function testTheStatusClassesAreWritten(): void {
        // Only a Model that has one gets the two of them
        $this->generate($this->models("Credential"));

        $files = $this->filesOf("Credential");
        $this->assertContains("CredentialStatus.php", $files);
        $this->assertContains("CredentialStatusWhere.php", $files);
    }

    public function testAModelWithNoStatusHasNone(): void {
        $this->generate($this->models("LogSession"));

        $files = $this->filesOf("LogSession");
        $this->assertNotContains("LogSessionStatus.php", $files);
        $this->assertContains("LogSessionSchema.php", $files);
    }

    public function testTheCountIsWhatWasWritten(): void {
        $models = $this->models("Credential");

        $this->generate($models);

        $this->assertCount(
            count($this->filesOf("Credential")),
            $this->filesOf("Credential"),
        );
    }

    public function testTheOutputNamesWhatItWrote(): void {
        $output = $this->generate($this->models("Credential"), "Framework");

        $this->assertStringContainsString("Framework Schema codes -> 1 models", $output);
    }

    public function testTheWrittenSchemaIsPhp(): void {
        $this->generate($this->models("Credential"));

        $path     = Application::getBasePath(self::FixtureDir, "Credential");
        $contents = Storage::readFile($path, "CredentialSchema.php");
        $this->assertStringStartsWith("<?php", $contents);
        $this->assertStringContainsString("class CredentialSchema", $contents);
    }

    public function testEveryModelIsWritten(): void {
        $models = $this->models();

        $output = $this->generate($models);

        $this->assertStringContainsString("-> " . count($models) . " models", $output);
        foreach ($models as $schemaModel) {
            $this->assertNotEmpty(
                $this->filesOf($schemaModel->name),
                "{$schemaModel->name} was not written",
            );
        }
    }

    public function testThereIsNothingToWrite(): void {
        $output = $this->generate([]);

        $this->assertStringContainsString("-> 0 models (0 files)", $output);
    }

    public function testTheCodeIsDestroyed(): void {
        // Only the Models of an App are emptied, since the ones of the
        // Framework are shipped with it
        $models = $this->models("Credential");
        foreach ($models as $schemaModel) {
            $schemaModel->fromFramework = false;
        }
        $this->generate($models);
        $this->assertNotEmpty($this->filesOf("Credential"));

        $deleted = SchemaBuilder::destroySchemaCode($models);

        $this->assertGreaterThan(0, $deleted);
        $this->assertSame([], $this->filesOf("Credential"));
    }

    public function testTheOnesOfTheFrameworkAreLeftAlone(): void {
        $models = $this->models("Credential");
        $this->generate($models);
        foreach ($models as $schemaModel) {
            $schemaModel->fromFramework = true;
        }

        $this->assertSame(0, SchemaBuilder::destroySchemaCode($models));
        $this->assertNotEmpty($this->filesOf("Credential"));
    }


    public function testTheWholeBuildIsRun(): void {
        // Which is what the build of the command line runs: the Models of the
        // Framework and then the ones of the App, into the paths they carry.
        // Here the two are the one repository, so both passes find the same
        // Models and write the same classes twice
        $output = $this->build();

        $this->assertStringContainsString("Framework Schema codes -> 19 models", $output);
        $this->assertStringContainsString("App Schema codes -> 19 models", $output);
    }

    public function testTheCodeOfTheBuildIsDestroyed(): void {
        // A destroy only takes away what an App wrote, and here the Models of
        // the repository answer for the App as well, so its classes go and
        // are written again before anything else asks for them
        $this->build();

        try {
            $deleted = SchemaBuilder::destroyCode();
        } finally {
            $this->build();
        }

        $this->assertGreaterThan(0, $deleted);
        $this->assertTrue(Storage::fileExists(
            Application::getSourcePath("Auth", "Schema", "CredentialSchema.php"),
        ));
    }

    /**
     * Runs the whole build, keeping what it printed
     * @return string
     */
    private function build(): string {
        ob_start();
        try {
            SchemaBuilder::generateCode();
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }
}
