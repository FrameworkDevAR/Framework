<?php
namespace Tests\Database;

use Framework\Core\MigrationData;
use Framework\Database\Query\Query;
use Framework\Email\EmailWhiteList;
use Framework\Email\Schema\EmailWhiteListQuery;
use Framework\Email\Schema\EmailWhiteListRequest;

use Tests\LiveTestCase;

use ReflectionMethod;

/**
 * The Schema, which every generated one extends, through a Model that writes
 *
 * The White List is the smallest Model that can be created and edited and
 * holds a unique field, so the whole write path of the Schema is reachable
 * through it: the insert, the update, the delete and the check that refuses
 * a second row with a value another one already has.
 */
class SchemaWriteLiveTest extends LiveTestCase {

    private const Email     = "live@framework.test";
    private const Other     = "other@framework.test";
    private const Migration = "live-duplicate";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        // These rows are the subject, so each test starts without them
        foreach ([ self::Email, self::Other ] as $email) {
            $emailID = EmailWhiteList::getEmailID($this->queryFor($email));
            if ($emailID > 0) {
                EmailWhiteList::remove($emailID);
            }
        }
    }

    /**
     * Returns a Query for the row of the given email
     * @param string $email
     * @return EmailWhiteListQuery
     */
    private function queryFor(string $email): EmailWhiteListQuery {
        $query = new EmailWhiteListQuery();
        $query->email->equal($email);
        return $query;
    }

    /**
     * Adds a row and hands back its ID
     * @param string $email
     * @param string $description Optional.
     * @return int
     */
    private function add(string $email, string $description = "a row of the tests"): int {
        return EmailWhiteList::add(new EmailWhiteListRequest(email: $email, description: $description));
    }



    public function testARowIsCreatedWithAnID(): void {
        $emailID = $this->add(self::Email);

        $this->assertGreaterThan(0, $emailID);
        $this->assertTrue(EmailWhiteList::exists($emailID));
    }

    public function testTheRowComesBackAsItsEntity(): void {
        $emailID = $this->add(self::Email, "the description");
        $entity  = EmailWhiteList::getByID($emailID);

        $this->assertTrue($entity->exists());
        $this->assertSame($emailID, $entity->emailID);
        $this->assertSame(self::Email, $entity->email);
        $this->assertSame("the description", $entity->description);
    }

    public function testTheTimestampsAreStamped(): void {
        // The Model has hasTimestamps, so the Schema fills these in, and the
        // Entity hands them back as Dates rather than as the numbers stored
        $entity = EmailWhiteList::getByID($this->add(self::Email));

        $this->assertTrue($entity->createdTime->isNotEmpty());
        $this->assertTrue($entity->modifiedTime->isNotEmpty());
    }

    public function testADuplicateCanBeIgnored(): void {
        // The White List checks its unique email in the code, so the
        // Migrations, whose name is the primary key, are the ones that
        // refuse a second row in the database. The create is protected, as
        // each Schema decides what it offers, so it is reached from here
        $create = new ReflectionMethod(MigrationData::class, "createEntity");
        Query::delete("migrations")->where("name", "=", self::Migration)->execute();

        $create->invokeArgs(null, [ "name" => self::Migration, "title" => "first" ]);
        $create->invokeArgs(null, [
            "name"            => self::Migration,
            "title"           => "second",
            "ignoreDuplicate" => true,
        ]);
        $migrations = Query::select("migrations")->where("name", "=", self::Migration)->getAll();
        Query::delete("migrations")->where("name", "=", self::Migration)->execute();

        $this->assertSame(1, $migrations->count());
        $this->assertSame("first", $migrations->getFirst()->getString("title"));
    }

    public function testARowThatIsNotThereComesBackEmpty(): void {
        $entity = EmailWhiteList::getByID(999999);

        $this->assertFalse($entity->exists());
        $this->assertTrue($entity->isEmpty());
    }

    public function testTheRowIsFoundByItsValue(): void {
        $emailID = $this->add(self::Email);

        $this->assertTrue(EmailWhiteList::emailExists(self::Email));
        $this->assertSame($emailID, EmailWhiteList::getByEmail(self::Email)->emailID);
    }



    public function testTheRowIsEdited(): void {
        $emailID = $this->add(self::Email, "before");

        $this->assertTrue(EmailWhiteList::edit(
            $emailID,
            new EmailWhiteListRequest(email: self::Email, description: "after"),
        ));
        $this->assertSame("after", EmailWhiteList::getByID($emailID)->description);
    }

    public function testEditingLeavesTheOtherRowsAlone(): void {
        $emailID = $this->add(self::Email, "mine");
        $otherID = $this->add(self::Other, "theirs");

        EmailWhiteList::edit($emailID, new EmailWhiteListRequest(email: self::Email, description: "changed"));

        $this->assertSame("theirs", EmailWhiteList::getByID($otherID)->description);
    }

    public function testAnEditThatChangesNothingSaysSo(): void {
        // A statement that can change rows is a success when it did, so the
        // second of these answers false even though the row is as asked
        $emailID = $this->add(self::Email, "the same");
        $request = new EmailWhiteListRequest(email: self::Email, description: "changed");

        $this->assertTrue(EmailWhiteList::edit($emailID, $request));
        $this->assertFalse(EmailWhiteList::edit($emailID, $request));
        $this->assertSame("changed", EmailWhiteList::getByID($emailID)->description);
    }

    public function testRemovingARowThatIsNotThereSaysSo(): void {
        $this->assertFalse(EmailWhiteList::remove(999999));
    }

    public function testTheRowIsRemoved(): void {
        $emailID = $this->add(self::Email);

        $this->assertTrue(EmailWhiteList::remove($emailID));
        $this->assertFalse(EmailWhiteList::exists($emailID));
        $this->assertFalse(EmailWhiteList::getByID($emailID)->exists());
    }



    public function testTheRowsAreCounted(): void {
        // getList and getTotal take the Request of the Model, which is what a
        // route hands them, rather than a Query
        $request = new EmailWhiteListRequest();
        $before  = EmailWhiteList::getTotal($request);
        $this->add(self::Email);
        $this->add(self::Other);

        $this->assertSame($before + 2, EmailWhiteList::getTotal($request));
    }

    public function testTheRowsComeBackAsAList(): void {
        $this->add(self::Email);
        $this->add(self::Other);

        $emails = [];
        foreach (EmailWhiteList::getList(new EmailWhiteListRequest()) as $entity) {
            $emails[] = $entity->email;
        }

        $this->assertContains(self::Email, $emails);
        $this->assertContains(self::Other, $emails);
    }

    public function testTheIdsComeBackOnTheirOwn(): void {
        $emailID = $this->add(self::Email);

        $this->assertContains($emailID, EmailWhiteList::getEmailIDs());
    }

    public function testAQueryNarrowsTheCount(): void {
        $this->add(self::Email);
        $this->add(self::Other);

        $query = $this->queryFor(self::Email);
        $this->assertSame(1, EmailWhiteList::getEntityTotal($query));
    }

    public function testAnEntityIsAskedForByItsQuery(): void {
        $emailID = $this->add(self::Email);
        $query   = $this->queryFor(self::Email);

        $this->assertTrue(EmailWhiteList::entityExists($query));
        $this->assertSame($emailID, EmailWhiteList::getEmailID($query));
    }

    public function testAQueryThatMatchesNothingFindsNothing(): void {
        $query = $this->queryFor("nobody@framework.test");

        $this->assertFalse(EmailWhiteList::entityExists($query));
        $this->assertSame(0, EmailWhiteList::getEmailID($query));
        $this->assertSame(0, EmailWhiteList::getEntityTotal($query));
    }
}
