<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Core\Configs;
use Framework\Core\MigrationData;
use Framework\Database\Migration\Migration;
use Framework\Database\Migration\DataMigration;
use Framework\Database\Migration\DeployMigration;
use Framework\File\Storage;

use Tests\Database\Fixture\RanMigrations;
use Tests\LiveTestCase;
use Tests\TestHelpers;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Data Migrations, the files of an App that run once and are written down
 *
 * They are found by reading the source of every file for one that implements
 * the interface, so the ones here are written out into a directory of their
 * own rather than declared, and each carries a class of its own name so that
 * including one does not clash with the last.
 *
 * A creation is only asked for with a title: without one it reads the answer
 * from the terminal, which a test has none of and would wait on.
 */
class DataMigrationLiveTest extends LiveTestCase {
    use TestHelpers;

    private const FixtureDir = "tests/Database/.tmp_migrations";

    private mixed $lastApplied    = null;
    private mixed $migrationsPath = null;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->lastApplied    = $this->getPrivateStaticProperty(Migration::class, "lastApplied");
        $this->migrationsPath = $this->getPrivateStaticProperty(Migration::class, "migrationsPath");
        $this->query("DELETE FROM `migrations`");
        RanMigrations::reset();
        Storage::createDir($this->basePath());
        Migration::setPath(self::FixtureDir);
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Migration::class, "lastApplied", $this->lastApplied);
        $this->setPrivateStaticProperty(Migration::class, "migrationsPath", $this->migrationsPath);
        Storage::deleteDir($this->basePath());
        RanMigrations::reset();
    }

    /**
     * Returns the directory the migrations of the tests are written into
     * @param string $dir Optional.
     * @return string
     */
    private function basePath(string $dir = ""): string {
        return Application::getBasePath(self::FixtureDir, $dir);
    }

    /**
     * Writes a Data Migration, named after the date the way a real one is
     * @param string $name  The file name, which is what it is stored under
     * @param string $title Optional.
     * @param string $dir   Optional.
     * @param string $class Optional. The class it declares, unique of itself
     * @param bool   $hasDeploy Optional. Whether it has a step for after the deploy
     * @param bool   $hasMigrate Optional. Whether it has the step of the migrate
     * @return void
     */
    private function writeMigration(
        string $name,
        string $title = "A migration",
        string $dir = "",
        string $class = "",
        bool $hasDeploy = false,
        bool $hasMigrate = true,
    ): void {
        if ($class === "") {
            $class = "M" . str_replace("-", "", Storage::getBaseName($name));
        }
        $path  = $this->basePath($dir);
        Storage::createDir($path);

        // Each step is an interface of its own, with its own use, and a migration
        // has one of them or both
        $uses       = [];
        $interfaces = [];
        $methods    = [];
        if ($hasMigrate) {
            $uses[]       = "use Framework\\Database\\Migration\\DataMigration;";
            $interfaces[] = "DataMigration";
            $methods[]    = <<<PHP
                public static function migrate(Database \$db): void {
                    RanMigrations::add("$name");
                }
            PHP;
        }
        if ($hasDeploy) {
            $uses[]       = "use Framework\\Database\\Migration\\DeployMigration;";
            $interfaces[] = "DeployMigration";
            $methods[]    = <<<PHP
                public static function postDeploy(Database \$db): void {
                    RanMigrations::addDeployed("$name");
                }
            PHP;
        }
        $useLines   = implode("\n", $uses);
        $implements = implode(", ", $interfaces);
        $body       = implode("\n\n", $methods);

        Storage::writeFile("$path/$name.php", <<<PHP
        <?php
        use Framework\\Database\\Database;
        $useLines
        use Tests\\Database\\Fixture\\RanMigrations;

        class $class implements $implements {
            public static function getTitle(): string {
                return "$title";
            }

        $body
        }
        PHP);
    }

    /**
     * Creates a Migration with the given title, keeping what it printed
     *
     * The command opens the file it wrote in the editor when it is run from
     * one, which is the whole point of it there and a nuisance here: the
     * tests are usually run from the terminal of VS Code, and every run
     * would leave a handful of migrations open. So the variable it reads to
     * know where it is running is taken away for as long as this takes
     * @param string $title
     * @return string
     */
    private function create(string $title): string {
        $termProgram = getenv("TERM_PROGRAM");
        putenv("TERM_PROGRAM");

        ob_start();
        try {
            Migration::createMigration($title);
        } finally {
            $output = ob_get_clean();
            if ($termProgram !== false) {
                putenv("TERM_PROGRAM=$termProgram");
            }
        }
        return (string)$output;
    }

    /**
     * Returns the path of the Migration file of the given name
     * @param string $name
     * @return string
     */
    private function fileOf(string $name): string {
        $parts = explode("-", $name);
        return $this->basePath("{$parts[0]}/{$parts[1]}") . "/$name.php";
    }

    /**
     * Finds the data migrations written for the test
     * @return array<string,string>
     */
    private function find(): array {
        return Migration::getMigrations(DataMigration::class);
    }

    /**
     * Finds the deploy migrations written for the test
     * @return array<string,string>
     */
    private function findDeploys(): array {
        return Migration::getMigrations(DeployMigration::class);
    }

    /**
     * Applies the migrations written for the test, keeping what it printed
     * @return string
     */
    private function apply(): string {
        ob_start();
        try {
            Migration::applyDataMigrations();
        } finally {
            $output = ob_get_clean();
        }
        return (string)$output;
    }

    /**
     * Applies the post deploys of the migrations written for the test, keeping what it printed
     * @return string
     */
    private function applyPostMigrations(): string {
        ob_start();
        try {
            Migration::applyPostMigrations();
        } finally {
            $output = ob_get_clean();
        }
        return (string)$output;
    }



    public function testAMigrationIsFound(): void {
        $this->writeMigration("2020-01-01-000000", "The first one");

        $this->assertSame(
            [ "2020-01-01-000000" => "M20200101000000" ],
            $this->find(),
        );
    }

    public function testTheyAreFoundInTheirFolders(): void {
        $this->writeMigration("2020-01-01-000001", dir: "2020/01");
        $this->writeMigration("2021-02-02-000002", dir: "2021/02");

        $this->assertCount(2, $this->find());
    }

    public function testTheyComeBackSortedByName(): void {
        $this->writeMigration("2021-01-01-000003");
        $this->writeMigration("2020-01-01-000004");
        $this->writeMigration("2020-06-01-000005");

        $this->assertSame([
            "2020-01-01-000004",
            "2020-06-01-000005",
            "2021-01-01-000003",
        ], array_keys($this->find()));
    }

    public function testAFileThatIsNotOneIsSkipped(): void {
        $this->writeMigration("2020-01-01-000006");
        Storage::writeFile($this->basePath() . "/notes.txt", "not a migration");
        Storage::writeFile($this->basePath() . "/other.php", "<?php class Other {}");

        $this->assertCount(1, $this->find());
    }

    public function testThereAreNoneToFind(): void {
        $this->assertSame([], $this->find());
    }



    public function testAMigrationIsApplied(): void {
        $this->writeMigration("2020-01-01-000010", "The first one");

        $output = $this->apply();

        $this->assertStringContainsString("Running 1 migrations", $output);
        $this->assertStringContainsString("2020-01-01-000010: The first one", $output);
        $this->assertSame([ "2020-01-01-000010" ], RanMigrations::getAll());
    }

    public function testAnAppliedOneIsWrittenDown(): void {
        $this->writeMigration("2020-01-01-000011", "The first one");
        $this->apply();

        $this->assertSame([ "2020-01-01-000011" ], MigrationData::getAppliedNames());
    }

    public function testTheyAreAppliedInOrder(): void {
        $this->writeMigration("2021-01-01-000012");
        $this->writeMigration("2020-01-01-000013");

        $this->apply();

        $this->assertSame([
            "2020-01-01-000013",
            "2021-01-01-000012",
        ], RanMigrations::getAll());
    }

    public function testOneAlreadyAppliedIsLeftAlone(): void {
        $this->writeMigration("2020-01-01-000014");
        $this->apply();
        RanMigrations::reset();

        $this->assertStringContainsString("No data migrations required", $this->apply());
        $this->assertSame([], RanMigrations::getAll());
    }

    public function testOnlyTheNewOneIsApplied(): void {
        $this->writeMigration("2020-01-01-000015");
        $this->apply();
        RanMigrations::reset();

        $this->writeMigration("2021-01-01-000016");
        $this->apply();

        $this->assertSame([ "2021-01-01-000016" ], RanMigrations::getAll());
        $this->assertCount(2, MigrationData::getAppliedNames());
    }

    public function testThereAreNoneToApply(): void {
        $this->assertStringContainsString("No data migrations found", $this->apply());
    }



    public function testAPostDeployIsRun(): void {
        $this->writeMigration("2020-01-01-000040", "The first one", hasDeploy: true);
        $this->apply();

        $output = $this->applyPostMigrations();

        $this->assertStringContainsString("Running 1 post deploys", $output);
        $this->assertStringContainsString("2020-01-01-000040: The first one", $output);
        $this->assertSame([ "2020-01-01-000040" ], RanMigrations::getDeployed());
        $this->assertSame([], MigrationData::getNotDeployedNames());
    }

    public function testAPostDeployIsOnlyRunOnce(): void {
        $this->writeMigration("2020-01-01-000041", hasDeploy: true);
        $this->apply();
        $this->applyPostMigrations();
        RanMigrations::reset();

        $this->assertStringContainsString("No post deploys required", $this->applyPostMigrations());
        $this->assertSame([], RanMigrations::getDeployed());
    }

    public function testThePostDeploysAreRunInOrder(): void {
        $this->writeMigration("2021-01-01-000042", hasDeploy: true);
        $this->writeMigration("2020-01-01-000043", hasDeploy: true);
        $this->apply();

        $this->applyPostMigrations();

        $this->assertSame([
            "2020-01-01-000043",
            "2021-01-01-000042",
        ], RanMigrations::getDeployed());
    }

    /**
     * A migration with nothing to run after the deploy, or not ready for it
     * @param bool $hasDeploy
     * @param bool $isApplied
     * @return void
     */
    #[DataProvider("providerNoPostDeploy")]
    public function testAPostDeployThatIsNotDueIsNotRun(bool $hasDeploy, bool $isApplied): void {
        $this->writeMigration("2020-01-01-000044", hasDeploy: $hasDeploy);
        if ($isApplied) {
            $this->apply();
        }

        $output = $this->applyPostMigrations();

        $this->assertStringContainsString("No post deploys required", $output);
        $this->assertSame([], RanMigrations::getDeployed());
    }

    /**
     * @return array<string,array{bool,bool}>
     */
    public static function providerNoPostDeploy(): array {
        return [
            // A migration that did not run yet has nothing to complete
            "one that has no step"       => [ false, true  ],
            "one that did not migrate"   => [ true,  false ],
        ];
    }

    /**
     * Each finder answers with the migrations of its own interface
     * @param string       $name
     * @param bool         $hasMigrate
     * @param bool         $hasDeploy
     * @param list<string> $expected
     * @param list<string> $expectedDeploys
     * @return void
     */
    #[DataProvider("providerFoundByStep")]
    public function testTheyAreFoundByTheirStep(
        string $name,
        bool $hasMigrate,
        bool $hasDeploy,
        array $expected,
        array $expectedDeploys,
    ): void {
        // Each case is a file of its own, as one already included is not read
        // again and keeps the shape it was first given
        $this->writeMigration($name, hasMigrate: $hasMigrate, hasDeploy: $hasDeploy);

        $this->assertSame($expected, array_keys($this->find()));
        $this->assertSame($expectedDeploys, array_keys($this->findDeploys()));
    }

    /**
     * @return array<string,array{string,bool,bool,list<string>,list<string>}>
     */
    public static function providerFoundByStep(): array {
        return [
            "only the migrate" => [
                "2020-01-01-000060", true, false, [ "2020-01-01-000060" ], [],
            ],
            "only the deploy" => [
                "2020-01-01-000061", false, true, [], [ "2020-01-01-000061" ],
            ],
            "both steps" => [
                "2020-01-01-000062", true, true, [ "2020-01-01-000062" ], [ "2020-01-01-000062" ],
            ],
        ];
    }

    public function testOneWithOnlyTheDeployStepWaitsForTheDeploy(): void {
        // The migrate has nothing of it to run or to write down, and the post
        // deploy is what stores it
        $this->writeMigration("2020-01-01-000054", hasDeploy: true, hasMigrate: false);

        $output = $this->apply();

        $this->assertStringContainsString("No data migrations found", $output);
        $this->assertSame([], RanMigrations::getAll());
        $this->assertSame([], MigrationData::getAppliedNames());

        $this->applyPostMigrations();
        $this->assertSame([ "2020-01-01-000054" ], RanMigrations::getDeployed());
        $this->assertSame([ "2020-01-01-000054" ], MigrationData::getAppliedNames());
        $this->assertSame([], MigrationData::getNotDeployedNames());
    }

    public function testTheOnesBeforeTheLastAreWrittenDown(): void {
        // An App that ran before this was written says which one it got to,
        // and the ones up to it are stored rather than run
        $this->writeMigration("2020-01-01-000020");
        $this->writeMigration("2020-06-01-000021");
        $this->writeMigration("2021-01-01-000022");
        $this->setPrivateStaticProperty(Migration::class, "lastApplied", "2020-06-01-000021");

        $output = $this->apply();

        $this->assertStringContainsString("Stored 2 migrations that were already applied", $output);
        $this->assertSame([ "2021-01-01-000022" ], RanMigrations::getAll());
        $this->assertCount(3, MigrationData::getAppliedNames());
    }

    public function testTheOnesBeforeTheLastAreDeployed(): void {
        // What ran by hand before this system was deployed by hand too, so it
        // is not waiting for a post deploy that would fill nothing
        $this->writeMigration("2020-01-01-000051", hasDeploy: true);
        $this->writeMigration("2021-01-01-000052", hasDeploy: true);
        $this->setPrivateStaticProperty(Migration::class, "lastApplied", "2020-01-01-000051");
        $this->apply();

        $this->applyPostMigrations();

        $this->assertSame([ "2021-01-01-000052" ], RanMigrations::getDeployed());
    }

    public function testTheLastAppliedIsOnlyReadOnce(): void {
        // It only means anything for a table with nothing in it, so a second
        // run does not store them all over again
        $this->writeMigration("2020-01-01-000023");
        $this->writeMigration("2021-01-01-000024");
        $this->setPrivateStaticProperty(Migration::class, "lastApplied", "2020-01-01-000023");
        $this->apply();

        $output = $this->apply();

        $this->assertStringNotContainsString("Stored", $output);
        $this->assertCount(2, MigrationData::getAppliedNames());
    }

    public function testWithNoLastAppliedTheyAllRun(): void {
        $this->writeMigration("2020-01-01-000025");
        $this->writeMigration("2021-01-01-000026");
        $this->setPrivateStaticProperty(Migration::class, "lastApplied", "");

        $this->apply();

        $this->assertCount(2, RanMigrations::getAll());
    }

    public function testThePathIsSet(): void {
        Migration::setPath("config/other");

        $this->assertSame(
            "config/other",
            $this->getPrivateStaticProperty(Migration::class, "migrationsPath"),
        );
    }

    public function testAnEmptyPathIsNotSet(): void {
        Migration::setPath("config/other");
        Migration::setPath("");

        $this->assertSame(
            "config/other",
            $this->getPrivateStaticProperty(Migration::class, "migrationsPath"),
        );
    }

    public function testTheLastAppliedIsSet(): void {
        Migration::setLastApplied("2020-01-01-000000");

        $this->assertSame(
            "2020-01-01-000000",
            $this->getPrivateStaticProperty(Migration::class, "lastApplied"),
        );
    }

    public function testTwoOfTheSameNameAreOne(): void {
        // The name is what they are stored under, so a second one of it would
        // be taken for the one that already ran. Each declares a class of its
        // own, since two of one name is a fatal before the check is reached
        $this->writeMigration("2020-01-01-000030", dir: "2020/01", class: "M20200101000030A");
        $this->writeMigration("2020-01-01-000030", dir: "2021/02", class: "M20200101000030B");

        ob_start();
        $result = $this->find();
        $output = (string)ob_get_clean();

        $this->assertCount(1, $result);
        $this->assertStringContainsString(
            "There is more than one migration called 2020-01-01-000030",
            $output,
        );
    }

    public function testThereAreNoneInTheRepository(): void {
        // Which is what the whole of it answers, since a Framework has none
        Migration::setPath((string)$this->migrationsPath);
        ob_start();
        $result = Migration::applyDataMigrations();
        $output = (string)ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString("No data migrations found", $output);
    }



    public function testAMigrationIsCreated(): void {
        Migration::setPath(self::FixtureDir);

        $output = $this->create("The new one");

        $this->assertStringContainsString("Created the migration", $output);
        $this->assertCount(1, $this->find());
    }

    public function testTheOneCreatedCarriesItsTitle(): void {
        $this->create("The new one");

        $name     = array_key_first($this->find());
        $contents = Storage::readFile($this->fileOf($name));
        $this->assertStringContainsString("The new one", $contents);
        $this->assertStringContainsString("implements DataMigration", $contents);
    }

    public function testTheOneCreatedIsNamedAfterTheDate(): void {
        // They live in a directory per year and month, so the ones of every
        // branch can be told apart and still sort together
        $this->create("The new one");

        $name = array_key_first($this->find());
        $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2}-\d{6}$/", $name);
        $this->assertTrue(Storage::fileExists($this->fileOf($name)));
    }

    public function testASecondOneTakesTheNextSecond(): void {
        $this->create("The first one");
        $this->create("The second one");

        $names = array_keys($this->find());
        $this->assertCount(2, $names);
        $this->assertNotSame($names[0], $names[1]);
    }


    public function testTheWholeMigrationIsRun(): void {
        // Which is the migrate of the command line: the tables, then the
        // Migrations of the Framework, then the ones written by hand
        ob_start();
        try {
            Migration::migrate();
        } finally {
            $output = (string)ob_get_clean();
        }

        $this->assertStringContainsString("DATABASE MIGRATIONS", $output);
        $this->assertStringContainsString("FRAMEWORK MIGRATIONS", $output);
        $this->assertStringContainsString("DATA MIGRATIONS", $output);
        $this->assertStringContainsString("Migrations completed in", $output);
    }

    public function testTheWholePostDeployIsRun(): void {
        // Which is the postDeploy of the command line, run once the code is there
        ob_start();
        try {
            Migration::postDeploy();
        } finally {
            $output = (string)ob_get_clean();
        }

        $this->assertStringContainsString("Running the post deploys", $output);
        $this->assertStringContainsString("Post deploys completed in", $output);
    }

    public function testTheEnvFileIsNamed(): void {
        // A deploy runs the migration against one environment at a time, and
        // the file it reads is given rather than found
        $fileName = $this->getPrivateStaticProperty(Configs::class, "fileName");

        ob_start();
        try {
            Migration::migrate("staging");
        } finally {
            $output = (string)ob_get_clean();
            $this->setPrivateStaticProperty(Configs::class, "fileName", $fileName);
        }

        $this->assertStringContainsString("Using ENV file: staging", $output);
    }
}
