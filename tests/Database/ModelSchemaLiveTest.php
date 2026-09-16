<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Discovery\Type\ComposerData;
use Framework\Builder\Builder;
use Framework\Database\Database;
use Framework\Database\SchemaBuilder;
use Framework\Database\SchemaFactory;
use Framework\Database\Migration\SchemaMigration;

use Tests\LiveTestCase;
use Tests\TestHelpers;
use Tests\Database\Fixture\TestParts;
use Tests\Database\Fixture\TestThings;

/**
 * The Schema over the Models kept for the tests
 *
 * A few paths of the Schema belong to Models the Framework does not ship: a
 * column the database keeps encrypted, a flag only one row may hold, the rows
 * of a second table pulled in with the first, and a delete on a Model that
 * cannot be deleted. So the Models are written under the fixtures and the
 * Application is pointed at them, which is what an App looks like from here.
 *
 * The classes are generated rather than committed, the same way the ones of
 * the repository are, and the tables are migrated from the Models and dropped
 * again when the class is done.
 */
class ModelSchemaLiveTest extends LiveTestCase {
    use TestHelpers;

    private const SourceDir = "tests/Database/Fixture";
    private const Namespace = "Tests\\Database\\Fixture\\";
    private const DbKey     = "the-key-of-the-tests";

    private static ?Database $db      = null;
    private static bool      $isBuilt = false;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->build();
        $this->setConfig("DB_KEY", self::DbKey);

        $this->query("DELETE FROM `test_part`");
        $this->query("DELETE FROM `test_thing`");
    }

    public static function tearDownAfterClass(): void {
        // The tables are only for this class, and a migration that can delete
        // would find them strays anywhere else
        self::$db?->execute("DROP TABLE IF EXISTS `test_part`");
        self::$db?->execute("DROP TABLE IF EXISTS `test_thing`");
        parent::tearDownAfterClass();
    }

    /**
     * Writes the classes of the Models of the tests and migrates their tables
     *
     * The Application is what the discovery of an App reads, so it is pointed
     * at the fixtures for as long as this takes and put back after: anything
     * else asking it where the source is would be answered the fixtures
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
            SchemaBuilder::generateSchemaCode(SchemaFactory::buildData(), "Fixture");
        } finally {
            ob_get_clean();
            $this->swapComposer($composerWas);
        }

        $this->migrate();
    }

    /**
     * Brings the tables up to the Models of the fixtures, keeping what the
     * migration printed
     * @return string
     */
    private function migrate(): string {
        $composerWas = $this->swapComposer(new ComposerData(
            namespace: self::Namespace,
            sourceDir: self::SourceDir,
        ));

        ob_start();
        try {
            SchemaMigration::migrateData([], [], canDelete: false);
        } finally {
            $output = (string)ob_get_clean();
            $this->swapComposer($composerWas);
        }
        return $output;
    }

    /**
     * Returns the secret of a Thing as the database holds it
     * @param int $testThingID
     * @return string
     */
    private function rawSecret(int $testThingID): string {
        $result = $this->db()->getData(
            "SELECT `secret` FROM `test_thing` WHERE `TEST_THING_ID` = ?",
            [ $testThingID ],
        );
        return $result->getDict(0)->getString("secret");
    }



    public function testTheTablesAreMigrated(): void {
        $this->assertTrue($this->db()->tableExists("test_thing"));
        $this->assertTrue($this->db()->tableExists("test_part"));
    }

    public function testTheColumnIsBinary(): void {
        // Which is what an encrypted field is stored as
        $this->assertStringStartsWith(
            "varbinary",
            $this->db()->getColumnType("test_thing", "secret"),
        );
    }



    public function testTheSecretIsNotInTheTable(): void {
        $testThingID = TestThings::add("The first", "the secret");

        $this->assertNotSame("the secret", $this->rawSecret($testThingID));
        $this->assertNotSame("", $this->rawSecret($testThingID));
    }

    public function testTheSecretIsReadBack(): void {
        $testThingID = TestThings::add("The first", "the secret");

        $entity = TestThings::getByID($testThingID, decrypted: true);

        $this->assertSame("the secret", $entity->secret);
    }

    public function testUndecryptedItIsNotRead(): void {
        $testThingID = TestThings::add("The first", "the secret");

        $this->assertNotSame("the secret", TestThings::getByID($testThingID)->secret);
    }

    public function testTheSecretIsWrittenOver(): void {
        $testThingID = TestThings::add("The first", "the secret");

        $this->assertTrue(TestThings::setSecret($testThingID, "another one"));
        $this->assertSame(
            "another one",
            TestThings::getByID($testThingID, decrypted: true)->secret,
        );
    }

    public function testAnotherKeyReadsNothing(): void {
        $testThingID = TestThings::add("The first", "the secret");
        $this->setConfig("DB_KEY", "a key that is not the one");

        $this->assertNotSame(
            "the secret",
            TestThings::getByID($testThingID, decrypted: true)->secret,
        );
    }



    public function testThePartsComeWithIt(): void {
        $testThingID = TestThings::add("The first");
        TestParts::add($testThingID, "A part");
        TestParts::add($testThingID, "Another part");

        $list = TestThings::getAll();

        $this->assertCount(1, $list);
        $this->assertCount(2, $list[0]->parts);
        $this->assertSame("A part", $list[0]->parts[0]->name);
    }

    public function testEachOneGetsItsOwn(): void {
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");
        TestParts::add($first, "A part");
        TestParts::add($second, "Another part");
        TestParts::add($second, "A third part");

        $list = TestThings::getAll();

        $this->assertCount(1, $list[0]->parts);
        $this->assertCount(2, $list[1]->parts);
    }

    public function testOneWithNoPartsHasNone(): void {
        TestThings::add("The first");

        $list = TestThings::getAll();

        $this->assertSame([], $list[0]->parts);
    }

    public function testThePartsAreKeyedByName(): void {
        // The same rows, given as a map rather than a list, which is what a
        // sub request with a field name hands back
        $testThingID = TestThings::add("The first");
        TestParts::add($testThingID, "A part");
        TestParts::add($testThingID, "Another part");

        $list = TestThings::getAll();

        $this->assertSame([ "A part", "Another part" ], array_keys($list[0]->partsByName));
    }

    public function testThePartsAreSkipped(): void {
        $testThingID = TestThings::add("The first");
        TestParts::add($testThingID, "A part");

        $list = TestThings::getAll(skipSubRequest: true);

        $this->assertSame([], $list[0]->parts);
    }

    public function testWithNoRowsNothingIsAsked(): void {
        $this->assertSame([], TestThings::getAll());
    }



    public function testTheFlagLeavesTheOther(): void {
        // Giving it to a row is the App's to do; the Schema only sees to it
        // that the row that had it does not keep it
        $first  = TestThings::add("The first", isDefault: 1);
        $second = TestThings::add("The second");

        $this->assertTrue(TestThings::setDefault($second, 0, 1));
        $this->assertSame(0, TestThings::getByID($first)->isDefault);
    }

    public function testTheFlagGoesToAnother(): void {
        // Taking it from the row that holds it hands it to the first row left
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second", isDefault: 1);

        $this->assertTrue(TestThings::setDefault($second, 1, 0));
        $this->assertSame(1, TestThings::getByID($first)->isDefault);
    }

    public function testTheFlagIsLeftAlone(): void {
        $first  = TestThings::add("The first", isDefault: 1);
        $second = TestThings::add("The second");

        $this->assertFalse(TestThings::setDefault($second, 1, 1));
        $this->assertSame(1, TestThings::getByID($first)->isDefault);
        $this->assertSame(0, TestThings::getByID($second)->isDefault);
    }



    public function testAThingIsDeleted(): void {
        // The row is only marked, so it is still there to be read: a false
        // withDeleted is what leaves the marked ones in
        $testThingID = TestThings::add("The first");

        $this->assertTrue(TestThings::drop($testThingID));
        $this->assertTrue(TestThings::getByID($testThingID, withDeleted: false)->isDeleted);
        $this->assertSame(0, TestThings::getByID($testThingID)->testThingID);
    }

    public function testAPartIsNotDeleted(): void {
        // Its Model has no canDelete, so there is no column to mark
        $testThingID = TestThings::add("The first");
        $testPartID  = TestParts::add($testThingID, "A part");

        $this->assertFalse(TestParts::drop($testPartID));
        $this->assertTrue(TestParts::exists($testPartID));
    }

    public function testAThingIsRemoved(): void {
        $testThingID = TestThings::add("The first");

        $this->assertTrue(TestThings::remove($testThingID));
        $this->assertFalse(TestThings::exists($testThingID));
    }

    public function testTheDeleteSkipsTheOrder(): void {
        // Which leaves the gap open, and is what a migration writing the
        // whole table over asks for
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");

        $this->assertTrue(TestThings::drop($first, skipOrder: true));
        $this->assertSame(2, TestThings::getByID($second)->position);
    }



    public function testEachOneTakesTheNext(): void {
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");

        $this->assertSame(1, TestThings::getByID($first)->position);
        $this->assertSame(2, TestThings::getByID($second)->position);
    }

    public function testADeleteClosesTheGap(): void {
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");
        $third  = TestThings::add("The third");

        $this->assertTrue(TestThings::drop($second));

        $this->assertSame(1, TestThings::getByID($first)->position);
        $this->assertSame(2, TestThings::getByID($third)->position);
    }

    public function testTheOrderIsLeftAlone(): void {
        // An edit that writes the position the row already had moves nothing,
        // so there is no row to write and the edit answers false
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");

        $this->assertFalse(TestThings::move($second, 2));

        $this->assertSame(1, TestThings::getByID($first)->position);
        $this->assertSame(2, TestThings::getByID($second)->position);
    }

    public function testAThingIsMoved(): void {
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");

        $this->assertTrue(TestThings::move($second, 1));

        $this->assertSame(2, TestThings::getByID($first)->position);
        $this->assertSame(1, TestThings::getByID($second)->position);
    }

    public function testNothingIsDeleted(): void {
        $this->assertFalse(TestThings::drop(9999));
    }

    public function testNothingIsRemoved(): void {
        $this->assertFalse(TestThings::remove(9999));
    }

    public function testAPageIsRead(): void {
        TestThings::add("The first");
        TestThings::add("The second");
        TestThings::add("The third");

        $list = TestThings::getPage(1, 2);

        $this->assertCount(1, $list);
        $this->assertSame("The third", $list[0]->name);
    }

    public function testAPageIsSorted(): void {
        TestThings::add("The first");
        TestThings::add("The second");
        TestThings::add("The third");

        $list = TestThings::getPage(0, 2, orderBy: "name", orderAsc: false);

        $this->assertCount(2, $list);
        $this->assertSame("The third", $list[0]->name);
        $this->assertSame("The second", $list[1]->name);
    }

    public function testARemoveClosesTheGap(): void {
        $first  = TestThings::add("The first");
        $second = TestThings::add("The second");
        $third  = TestThings::add("The third");

        $this->assertTrue(TestThings::remove($second));

        $this->assertSame(1, TestThings::getByID($first)->position);
        $this->assertSame(2, TestThings::getByID($third)->position);
    }


    public function testTheParentColumnIsIndexed(): void {
        // A parent is what the rows are looked up by, so the table it points
        // from carries a key on it
        $keys = $this->db()->getData("SHOW INDEX FROM `test_part`");

        $names = [];
        for ($index = 0; $index < $keys->count(); $index += 1) {
            $names[] = $keys->getDict($index)->getString("Column_name");
        }
        $this->assertContains("TEST_THING_ID", $names);
    }

    public function testTheIDColumnIsRenamed(): void {
        // A table whose auto increment is named something else is the one the
        // Model says, renamed rather than added beside the old one
        $this->query("DROP TABLE `test_part`");
        $this->query(
            "CREATE TABLE `test_part` (" .
            "`OLD_ID` int(10) unsigned NOT NULL AUTO_INCREMENT, " .
            "`TEST_THING_ID` int(10) unsigned NOT NULL DEFAULT 0, " .
            "`name` varchar(255) NOT NULL DEFAULT '', " .
            "PRIMARY KEY (`OLD_ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );

        $output = $this->migrate();

        $this->assertStringContainsString("Updated table test_part", $output);
        $this->assertTrue($this->db()->columnExists("test_part", "TEST_PART_ID"));
        $this->assertFalse($this->db()->columnExists("test_part", "OLD_ID"));
    }

    public function testTheIDColumnIsAdded(): void {
        // A table with no auto increment at all gets the ID as a new column,
        // and the key it had is dropped to make room for it
        $this->query("DROP TABLE `test_part`");
        $this->query(
            "CREATE TABLE `test_part` (" .
            "`TEST_THING_ID` int(10) unsigned NOT NULL DEFAULT 0, " .
            "`name` varchar(255) NOT NULL DEFAULT '', " .
            "PRIMARY KEY (`TEST_THING_ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );

        $this->migrate();

        $this->assertSame("TEST_PART_ID", $this->db()->getAutoIncrement("test_part"));
        $this->assertSame([ "TEST_PART_ID" ], $this->db()->getPrimaryKeys("test_part"));
    }

    public function testASettledTableIsLeftAlone(): void {
        $this->assertStringContainsString("No changes for test_thing", $this->migrate());
    }
}
