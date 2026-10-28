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
use Framework\Database\Migration\MigrationOutput;
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

    private const Template  = "src/Database/Template/Migration.mu";
    private const DevName   = "dev";
    private const MediaName = "media";

    private static string $migrationsPath = "config/migrations";
    private static string $dataDir        = "";
    private static string $lastApplied    = "";

    /** @var list<string> */
    private static array $devEnvironments = [ "local" ];


    /** @var list<array{from:string,to:string}> */
    private static array $tableRenames  = [];

    /** @var list<array{table:string,from:string,to:string}> */
    private static array $columnRenames = [];

    /** @var list<array{table:string,from:string,to:string}> */
    private static array $indexRenames  = [];


    /**
     * Sets the Directory where the Data Migrations are created
     * @param string $migrationsPath
     * @return void
     */
    public static function setPath(string $migrationsPath): void {
        if ($migrationsPath !== "") {
            self::$migrationsPath = $migrationsPath;
        }
    }

    /**
     * Sets the Directory inside the one of the Migrations where the new Data Migrations go
     * @param string $dataDir
     * @return void
     */
    public static function setDataDir(string $dataDir): void {
        // Without one they go right in the Directory of the Migrations, next to the dev and
        // the media ones, which is how the Apps that adopted the Migrations first have them
        self::$dataDir = $dataDir;
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
     * Sets the Environments where the Dev Migrations run, which are never run anywhere else
     * @param string ...$environments
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setDevEnvironments(string ...$environments): void {
        self::$devEnvironments = array_values($environments);
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
     * Renames an Index
     * @param string $table
     * @param string $from
     * @param string $to
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function renameIndex(string $table, string $from, string $to): void {
        self::$indexRenames[] = [
            "table" => $table,
            "from"  => $from,
            "to"    => $to,
        ];
    }



    /**
     * Creates a new Data Migration with the given Title
     * @param string $title Optional.
     * @param bool   $dev   Optional.
     * @return void
     */
    #[ConsoleCommand("migration")]
    #[NotTested("It needs a Database")]
    public static function createMigration(string $title = "", bool $dev = false): void {
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
        // and they are grouped in a directory per year and month, while the Dev ones
        // go together in their own directory, so they are easy to find and delete
        $date     = Date::now();
        $dirName  = self::getNewDirName($date, $dev);
        $basePath = Application::getBasePath(self::$migrationsPath, $dirName);

        // Move to the next second while there is a migration with the same name
        $fileName = self::getNewFileName($date, $dev);
        while (Storage::fileExists($basePath, $fileName)) {
            $date     = $date->add(seconds: 1);
            $dirName  = self::getNewDirName($date, $dev);
            $basePath = Application::getBasePath(self::$migrationsPath, $dirName);
            $fileName = self::getNewFileName($date, $dev);
        }

        // The class has the same date as the name, so it is unique too
        $prefix   = $dev ? "D" : "M";
        $template = Storage::readFile(Package::getBasePath(self::Template));
        $contents = Mustache::render($template, [
            "class" => $prefix . $date->format("Ymd") . "T" . $date->format("His"),
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
     * Returns the Directory of a new Migration
     * @param Date $date
     * @param bool $dev
     * @return string
     */
    private static function getNewDirName(Date $date, bool $dev): string {
        if ($dev) {
            return self::DevName;
        }
        if (self::$dataDir !== "") {
            return Storage::parsePath(self::$dataDir, $date->getYear(), $date->getMonthZero());
        }
        return Storage::parsePath($date->getYear(), $date->getMonthZero());
    }

    /**
     * Returns the File Name of a new Migration
     * @param Date $date
     * @param bool $dev
     * @return string
     */
    private static function getNewFileName(Date $date, bool $dev): string {
        $prefix = $dev ? self::DevName . "-" : "";
        return $prefix . $date->format("Y-m-d-His") . ".php";
    }



    /**
     * Migrates the Data
     * @param string $envFile   Optional.
     * @param bool   $canDelete Optional.
     * @param bool   $progress  Optional.
     * @return void
     */
    #[ConsoleCommand("migrate")]
    public static function migrate(
        string $envFile = "",
        bool $canDelete = false,
        bool $progress = false,
    ): void {
        // A deploy only shows where the Migration is, as a long one looks stuck otherwise,
        // so the progress prints each table and migration, in a line of its own, before
        // working on it
        MigrationOutput::setProgress($progress);

        $timer = new Timer();
        MigrationOutput::detail("Migrating data...\n");

        DiscoveryConfig::load();
        if ($envFile !== "") {
            MigrationOutput::detail("Using ENV file: $envFile\n");
            Configs::setFileName($envFile);
        }


        // Migrate the Schema
        MigrationOutput::detail("\nDATABASE MIGRATIONS\n");
        SchemaMigration::migrateData(
            self::$tableRenames,
            self::$columnRenames,
            self::$indexRenames,
            $canDelete,
        );


        // Apply other Migrations from the Framework
        $frameClasses = Discovery::findClasses(
            interface:    DiscoveryMigration::class,
            forFramework: true,
        );
        if (count($frameClasses) > 0) {
            MigrationOutput::detail("\nFRAMEWORK MIGRATIONS\n");
            foreach ($frameClasses as $class) {
                $instance = $class->newInstance();
                if ($instance instanceof DiscoveryMigration) {
                    MigrationOutput::hideDetails(fn() => $instance::migrateData());
                }
            }
        }


        // Apply the Migrations from the App
        $appMigrations = Discovery::findClasses(
            interface:    DiscoveryMigration::class,
            forFramework: false,
        );
        if (count($appMigrations) > 0) {
            MigrationOutput::detail("\nAPP MIGRATIONS\n");
            foreach ($appMigrations as $class) {
                $instance = $class->newInstance();
                if ($instance instanceof DiscoveryMigration) {
                    MigrationOutput::hideDetails(fn() => $instance::migrateData());
                }
            }
        }


        // Execute the required Data Migrations
        MigrationOutput::detail("\nDATA MIGRATIONS\n");
        self::applyDataMigrations();


        // Calculate and show the time taken
        $time = $timer->getElapsedText();
        MigrationOutput::detail("\nMigrations completed in $time\n");
    }

    /**
     * Applies the Data Migrations that are pending
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function applyDataMigrations(): bool {
        $migrations = self::getMigrations(DataMigration::class);
        if (count($migrations) === 0) {
            MigrationOutput::detail("- No data migrations found\n");
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
            MigrationOutput::step("Running $amount migrations");

            $db = Database::getInstance();
            foreach ($pending as $name => $className) {
                $title = $className::getTitle();

                MigrationOutput::step("$name: $title");
                $className::migrate($db);
                MigrationData::add($name, $title);
            }
        } else {
            MigrationOutput::detail("- No data migrations required\n");
        }

        // The Dev Migrations are deleted once they ran everywhere, so the ones
        // still there are shown to keep them from piling up
        $devAmount = count(self::getDevNames());
        if ($devAmount > 0 && self::canRunDev()) {
            MigrationOutput::detail("- There are $devAmount dev migrations\n");
        } elseif ($devAmount > 0) {
            MigrationOutput::detail("- Skipped $devAmount dev migrations\n");
        }
        return $amount > 0;
    }



    /**
     * Runs the Post Deploys of the Migrations, once the code is deployed
     * @param string $envFile  Optional.
     * @param bool   $progress Optional.
     * @return void
     */
    #[ConsoleCommand("postDeploy")]
    #[NotTested("It needs a Database")]
    public static function postDeploy(string $envFile = "", bool $progress = false): void {
        MigrationOutput::setProgress($progress);

        $timer = new Timer();
        MigrationOutput::detail("Running the post deploys...\n");

        DiscoveryConfig::load();
        if ($envFile !== "") {
            MigrationOutput::detail("Using ENV file: $envFile\n");
            Configs::setFileName($envFile);
        }

        MigrationOutput::detail("\n");
        self::applyPostMigrations();

        $time = $timer->getElapsedText();
        MigrationOutput::detail("\nPost deploys completed in $time\n");
    }

    /**
     * Applies the Post Deploys of the Deploy Migrations that are pending
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function applyPostMigrations(): bool {
        $pending = self::getPendingDeploys();
        if (count($pending) === 0) {
            MigrationOutput::detail("- No post deploys required\n");
            return false;
        }

        $amount = count($pending);
        MigrationOutput::step("Running $amount post deploys");

        $db = Database::getInstance();
        foreach ($pending as $name => $className) {
            $title = $className::getTitle();

            MigrationOutput::step("$name: $title");
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
        $devPath   = Storage::addLastSlash(self::getDevPath());
        $canRunDev = self::canRunDev();
        $filePaths = Storage::getFilesInDir($basePath, recursive: true);
        $result    = [];

        foreach ($filePaths as $filePath) {
            if (!Strings::endsWith($filePath, ".php")) {
                continue;
            }
            if (!$canRunDev && Strings::startsWith($filePath, $devPath)) {
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

        MigrationOutput::detail("- Stored $index migrations that were already applied\n");
    }



    /**
     * Returns true if the Dev Migrations can run in the current Environment
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function canRunDev(): bool {
        return Arrays::contains(self::$devEnvironments, Configs::getEnvironment());
    }

    /**
     * Returns the Names of the Dev Migrations, sorted
     * @return list<string>
     */
    #[NotTested("It needs a Database")]
    public static function getDevNames(): array {
        $result = [];
        foreach (Storage::getFilesInDir(self::getDevPath()) as $fileName) {
            if (Strings::endsWith($fileName, ".php")) {
                $result[] = Storage::getFileName($fileName);
            }
        }
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * Returns the Names of the Dev Migrations applied here and in the given Environment
     * @param list<string> $envApplied
     * @param string       $envName
     * @return list<string>
     */
    #[NotTested("It needs a Database")]
    public static function getDevToDelete(array $envApplied, string $envName): array {
        // A Dev Migration is only deleted once it ran in both places, as the ones
        // of a branch that did not reach the Environment yet still have to run there
        $localApplied = MigrationData::getAppliedNames();
        $result       = [];

        foreach (self::getDevNames() as $name) {
            if (!Arrays::contains($localApplied, $name)) {
                print("- Keeping $name: not applied here\n");
            } elseif (!Arrays::contains($envApplied, $name)) {
                print("- Keeping $name: not applied in $envName\n");
            } else {
                print("- Applied everywhere: $name\n");
                $result[] = $name;
            }
        }
        return $result;
    }

    /**
     * Deletes the Dev Migration with the given Name
     * @param string $name
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function deleteDev(string $name): bool {
        if (!Arrays::contains(self::getDevNames(), $name)) {
            return false;
        }
        return Storage::deleteFile(self::getDevPath(), "$name.php");
    }

    /**
     * Returns the Path of the Directory with the Dev Migrations
     * @return string
     */
    private static function getDevPath(): string {
        return Application::getBasePath(self::$migrationsPath, self::DevName);
    }

    /**
     * Returns the Path of the Directory with the Files that the Data Migrations upload
     * @param string ...$pathParts
     * @return string
     */
    public static function getMediaPath(string ...$pathParts): string {
        return Application::getBasePath(self::$migrationsPath, self::MediaName, ...$pathParts);
    }
}
