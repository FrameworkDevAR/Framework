<?php
namespace Tests;

use Framework\Database\Database;
use Framework\Database\Migration\SchemaMigration;

use PHPUnit\Framework\TestCase;

/**
 * The base of every test that needs a Database
 *
 * The connection is written here rather than read from a config, so nothing
 * has to be set to run these: the command line, the editor and its coverage
 * all work the same. A run without the database skips rather than fails.
 *
 * The database is created and dropped from at will, so it has to be one kept
 * for nothing else. Create it once with:
 *
 *     mysql -e "CREATE DATABASE framework_test"
 */
abstract class LiveTestCase extends TestCase {

    private const Database = "framework_test";
    private const Username = "root";
    private const Password = "";

    private static ?Database $db         = null;
    private static string    $skip       = "";
    private static bool      $isMigrated = false;


    /**
     * Skips the whole class when the database is not there
     *
     * The skip belongs here rather than in the setUp: PHPUnit runs the
     * tearDown of a test it skipped, and the one of a live test reaches for
     * the database to put back what it changed. Skipped from here the tests
     * are never set up at all, so nothing is torn down either.
     * @return void
     */
    public static function setUpBeforeClass(): void {
        if (self::$skip !== "") {
            self::markTestSkipped(self::$skip);
        }
        if (self::$db !== null) {
            return;
        }

        $db = new Database(
            host:         "127.0.0.1",
            database:     self::Database,
            username:     self::Username,
            password:     self::Password,
            charset:      "utf8mb4",
            triggerError: false,
        );
        if (!$db->isConnected()) {
            self::$skip = "Cannot reach " . self::Database;
            self::markTestSkipped(self::$skip);
        }

        self::$db = $db;
    }

    protected function setUp(): void {
        // The code under test asks the Database for its instance, so the one
        // it finds is the one built here
        Database::setInstance(self::$db);
    }

    public static function tearDownAfterClass(): void {
        if (self::$db !== null) {
            Database::setInstance(null);
        }
    }



    /**
     * Brings the schema up to the Models, once for the whole run, so a test
     * can count on the tables being there. Named apart from the migrate of
     * the MigrationTest, which runs it over and over on purpose
     * @return void
     */
    protected function migrateOnce(): void {
        if (self::$isMigrated) {
            return;
        }
        self::$isMigrated = true;

        ob_start();
        try {
            SchemaMigration::migrateData([], [], canDelete: true);
        } finally {
            ob_get_clean();
        }
    }

    /**
     * Returns the Database the tests run against
     * @return Database
     */
    protected function db(): Database {
        $this->assertNotNull(self::$db);
        return self::$db;
    }

    /**
     * Runs the given statement, for the steps that set a table up
     * @param string $expression
     * @return void
     */
    protected function query(string $expression): void {
        $this->db()->execute($expression);
    }
}
