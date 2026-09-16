<?php
namespace Framework\Database\Migration;

use Framework\Application;
use Framework\Console;
use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\DiscoveryConfig;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryMigration;
use Framework\Discovery\Attr\ConsoleCommand;
use Framework\Database\Database;
use Framework\Database\Migration\SchemaMigration;
use Framework\Database\Migration\BaseMigration;
use Framework\Database\Migration\DataMigration;
use Framework\Database\Migration\DeployMigration;
use Framework\Provider\Mustache;
use Framework\Core\Configs;
use Framework\Core\MigrationData;
use Framework\Date\Date;
use Framework\Date\Timer;
use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Database Migration
 */
class Migration {

    private const Template = "src/Database/Template/Migration.mu";

    private static string $migrationsPath = "config/migrations";
    private static string $lastApplied    = "";


    /** @var list<array{from:string,to:string}> */
    private static array $tableRenames  = [];

    /** @var list<array{table:string,from:string,to:string}> */
    private static array $columnRenames = [];


    /**
     * Sets the Directory where the Data Migrations are created
     * @param string $migrationsPath
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setPath(string $migrationsPath): void {
        if ($migrationsPath !== "") {
            self::$migrationsPath = $migrationsPath;
        }
    }

    /**
     * Sets the last Data Migration that was applied before this system, so that
     * it and the ones before it are stored as applied, without running them
     * @param string $lastApplied
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setLastApplied(string $lastApplied): void {
        self::$lastApplied = $lastApplied;
    }

    /**
     * Renames a Table
     * @param string $from
     * @param string $to
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function renameTable(string $from, string $to): void {
        self::$tableRenames[] = [
            "from" => $from,
            "to"   => $to,
        ];
    }

    /**
     * Renames a Column
     * @param string $table
     * @param string $from
     * @param string $to
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function renameColumn(string $table, string $from, string $to): void {
        self::$columnRenames[] = [
            "table" => $table,
            "from"  => $from,
            "to"    => $to,
        ];
    }



    /**
     * Creates a new Data Migration with the given Title
     * @param string $title Optional.
     * @return void
     */
    #[ConsoleCommand("migration")]
    #[NotTested("It needs a Database")]
    public static function createMigration(string $title = ""): void {
        DiscoveryConfig::load();

        $title = Strings::trim($title);
        if ($title === "") {
            $title = Strings::trim(Console::prompt("Title of the migration"));
        }
        if ($title === "") {
            print("The title of the migration is required\n");
            return;
        }

        // The name is the date, so the migrations of every branch can live together,
        // and they are grouped in a directory per year and month
        $date     = Date::now();
        $dirName  = Storage::parsePath($date->getYear(), $date->getMonthZero());
        $basePath = Application::getBasePath(self::$migrationsPath, $dirName);

        // Move to the next second while there is a migration with the same name
        $name     = $date->format("Y-m-d-His");
        $fileName = "$name.php";
        while (Storage::fileExists($basePath, $fileName)) {
            $date     = $date->add(seconds: 1);
            $dirName  = Storage::parsePath($date->getYear(), $date->getMonthZero());
            $basePath = Application::getBasePath(self::$migrationsPath, $dirName);
            $name     = $date->format("Y-m-d-His");
            $fileName = "$name.php";
        }

        // The class has the same date as the name, so it is unique too
        $template = Storage::readFile(Package::getBasePath(self::Template));
        $contents = Mustache::render($template, [
            "class" => "M" . $date->format("Ymd") . "T" . $date->format("His"),
            "title" => $title,
        ]);

        Storage::createDir($basePath);
        Storage::createFile($basePath, $fileName, $contents);

        $printPath = Storage::parsePath(self::$migrationsPath, $dirName, $fileName);
        print("Created the migration $printPath\n");

        // Open the new Migration, so it can be edited right away
        Console::openFile(Storage::parsePath($basePath, $fileName));
    }



    /**
     * Migrates the Data
     * @param string $envFile   Optional.
     * @param bool   $canDelete Optional.
     * @return void
     */
    #[ConsoleCommand("migrate")]
    public static function migrate(
        string $envFile = "",
        bool $canDelete = false,
    ): void {
        $timer = new Timer();
        print("Migrating data...\n");

        DiscoveryConfig::load();
        if ($envFile !== "") {
            print("Using ENV file: $envFile\n");
            Configs::setFileName($envFile);
        }


        // Migrate the Schema
        print("\nDATABASE MIGRATIONS\n");
        SchemaMigration::migrateData(
            self::$tableRenames,
            self::$columnRenames,
            $canDelete,
        );


        // Apply other Migrations from the Framework
        $frameClasses = Discovery::findClasses(
            interface:    DiscoveryMigration::class,
            forFramework: true,
        );
        if (count($frameClasses) > 0) {
            print("\nFRAMEWORK MIGRATIONS\n");
            foreach ($frameClasses as $class) {
                $instance = $class->newInstance();
                if ($instance instanceof DiscoveryMigration) {
                    $instance::migrateData();
                }
            }
        }


        // Apply the Migrations from the App
        $appMigrations = Discovery::findClasses(
            interface:    DiscoveryMigration::class,
            forFramework: false,
        );
        if (count($appMigrations) > 0) {
            print("\nAPP MIGRATIONS\n");
            foreach ($appMigrations as $class) {
                $instance = $class->newInstance();
                if ($instance instanceof DiscoveryMigration) {
                    $instance::migrateData();
                }
            }
        }


        // Execute the required Data Migrations
        print("\nDATA MIGRATIONS\n");
        self::applyDataMigrations();


        // Calculate and show the time taken
        $time = $timer->getElapsedText();
        print("\nMigrations completed in $time\n");
    }

    /**
     * Applies the Data Migrations that are pending
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function applyDataMigrations(): bool {
        $migrations = self::getMigrations(DataMigration::class);
        if (count($migrations) === 0) {
            print("- No data migrations found\n");
            return false;
        }

        // Store the Migrations that ran before this system, so they are not run again
        self::storeApplied($migrations);

        // Determine the Migrations that were not applied yet
        $applied = MigrationData::getAppliedNames();
        $pending = [];
        foreach ($migrations as $name => $className) {
            if (!Arrays::contains($applied, $name)) {
                $pending[$name] = $className;
            }
        }

        // Run the Migrations that are pending
        $amount = count($pending);
        if ($amount > 0) {
            print("Running $amount migrations\n");

            $db = Database::getInstance();
            foreach ($pending as $name => $className) {
                $title = $className::getTitle();

                print("- $name: $title\n");
                $className::migrate($db);
                MigrationData::add($name, $title);
            }
        } else {
            print("- No data migrations required\n");
        }
        return $amount > 0;
    }



    /**
     * Runs the Post Deploys of the Migrations, once the code is deployed
     * @param string $envFile Optional.
     * @return void
     */
    #[ConsoleCommand("postDeploy")]
    #[NotTested("It needs a Database")]
    public static function postDeploy(string $envFile = ""): void {
        $timer = new Timer();
        print("Running the post deploys...\n");

        DiscoveryConfig::load();
        if ($envFile !== "") {
            print("Using ENV file: $envFile\n");
            Configs::setFileName($envFile);
        }

        print("\n");
        self::applyPostMigrations();

        $time = $timer->getElapsedText();
        print("\nPost deploys completed in $time\n");
    }

    /**
     * Applies the Post Deploys of the Deploy Migrations that are pending
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function applyPostMigrations(): bool {
        $pending = self::getPendingDeploys();
        if (count($pending) === 0) {
            print("- No post deploys required\n");
            return false;
        }

        $amount = count($pending);
        print("Running $amount post deploys\n");

        $db = Database::getInstance();
        foreach ($pending as $name => $className) {
            $title = $className::getTitle();

            print("- $name: $title\n");
            $className::postDeploy($db);
            MigrationData::setDeployed($name, $title);
        }
        return true;
    }

    /**
     * Returns the Post Deploys that are pending, sorted by their Name
     * @return array<string,class-string<DeployMigration>>
     */
    private static function getPendingDeploys(): array {
        // A post deploy fills what the old code wrote after the migrate, so one that
        // migrates too waits until that ran, which is when it is written down, while
        // one that only deploys is due from the moment it is there
        $migrations  = self::getMigrations(DeployMigration::class);
        $applied     = MigrationData::getAppliedNames();
        $notDeployed = MigrationData::getNotDeployedNames();
        $result      = [];

        foreach ($migrations as $name => $className) {
            $isWaiting = Arrays::contains($notDeployed, $name);
            $isNew     = !Arrays::contains($applied, $name) &&
                !is_subclass_of($className, DataMigration::class);
            if ($isWaiting || $isNew) {
                $result[$name] = $className;
            }
        }
        return $result;
    }



    /**
     * Returns the Migrations of the given interface, indexed and sorted by their Name
     * @template T of BaseMigration
     * @param class-string<T> $interface
     * @return array<string,class-string<T>>
     */
    #[NotTested("It needs a Database")]
    public static function getMigrations(string $interface): array {
        $basePath  = Application::getBasePath(self::$migrationsPath);
        $filePaths = Storage::getFilesInDir($basePath, recursive: true);
        $result    = [];

        foreach ($filePaths as $filePath) {
            if (!Strings::endsWith($filePath, ".php")) {
                continue;
            }

            // The source is read before the file is included, so only the ones that
            // name the interface are loaded
            $content = Storage::readFile($filePath);
            if (!Strings::contains($content, $interface) ||
                !Strings::contains($content, " implements ")
            ) {
                continue;
            }

            $className = Strings::trim(Strings::substringBetween($content, "class", "implements"));
            include_once $filePath;
            if (!class_exists($className) || !is_subclass_of($className, $interface)) {
                continue;
            }

            // The file name is the Name used to sort the Migrations and to store them
            $name = Storage::getBaseName(Storage::getFileName($filePath));
            if (isset($result[$name])) {
                print("- There is more than one migration called $name\n");
                continue;
            }

            $result[$name] = $className;
        }

        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * Stores the Migrations up to the last applied one, so that an App that is
     * already running does not apply a second time the ones that already ran
     * @param array<string,class-string<DataMigration>> $migrations
     * @return void
     */
    private static function storeApplied(array $migrations): void {
        if (self::$lastApplied === "" || !MigrationData::isEmpty()) {
            return;
        }

        $index = 0;
        foreach ($migrations as $name => $className) {
            if (Strings::compare($name, self::$lastApplied) > 0) {
                break;
            }

            // What ran by hand before this system was deployed by hand too
            MigrationData::add($name, $className::getTitle(), isDeployed: true);
            $index += 1;
        }

        print("- Stored $index migrations that were already applied\n");
    }
}
