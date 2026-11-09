<?php
namespace Tests\Log;

use Framework\Auth\Auth;
use Framework\Auth\Credential;
use Framework\Auth\Schema\CredentialRequest;
use Framework\Auth\Schema\CredentialStatus;
use Framework\Log\ActionLog;
use Framework\Log\SessionLog;
use Framework\Log\Schema\LogActionColumn;
use Framework\Log\Schema\LogActionRequest;
use Framework\Log\Type\Act;
use Framework\Log\Type\Sec;
use Framework\System\Access;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Actions Log, what was done and in which sign in it was done
 *
 * An action belongs to an open session, so most of these start one first.
 */
class ActionLogLiveTest extends LiveTestCase {

    private const CredentialID = 9201;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        Auth::logout();
        $this->query("DELETE FROM `log_action`");
        $this->query("DELETE FROM `log_session`");
        $this->query("DELETE FROM `credential`");
    }

    protected function tearDown(): void {
        Auth::logout();
    }

    /**
     * Creates a Credential and hands back its ID
     * @param string $email  Optional.
     * @param Access $access Optional.
     * @return int
     */
    private function create(
        string $email = "log@framework.test",
        Access $access = Access::Admin,
    ): int {
        return Credential::create(new CredentialRequest(
            firstName: "Ana",
            lastName:  "Suárez",
            email:     $email,
            password:  "a-password-of-the-tests",
            access:    $access,
            status:    CredentialStatus::Active,
        ));
    }



    public function testASessionIsStarted(): void {
        // Nobody signed in is credential 0, which is a session just the same
        $this->assertTrue(ActionLog::startSession());

        $this->assertSame(1, SessionLog::getEntityTotal());
    }

    public function testASecondStartFindsItOpen(): void {
        ActionLog::startSession();

        $this->assertFalse(ActionLog::startSession());

        $this->assertSame(1, SessionLog::getEntityTotal());
    }

    public function testADestroyOpensAnotherOne(): void {
        ActionLog::startSession();
        $first = SessionLog::getID(0);

        $this->assertTrue(ActionLog::startSession(destroy: true));

        $this->assertFalse(SessionLog::getByID($first)->isOpen);
        $this->assertSame(2, SessionLog::getEntityTotal());
        $this->assertNotSame($first, SessionLog::getID(0));
    }

    public function testTheSessionIsEnded(): void {
        ActionLog::startSession();

        $this->assertTrue(ActionLog::endSession());

        $this->assertSame(0, SessionLog::getID(0));
    }

    public function testThereIsNoSessionToEnd(): void {
        $this->assertFalse(ActionLog::endSession());
    }

    public function testTheSessionIsOfWhoSignedIn(): void {
        $credentialID = $this->create();
        Auth::login(Credential::getByID($credentialID, complete: true));
        // The sign in starts one of its own, so this is the session it left
        $sessionID = SessionLog::getID($credentialID);

        $this->assertGreaterThan(0, $sessionID);
        $this->assertSame($credentialID, SessionLog::getByID($sessionID)->credentialID);
    }

    public function testTheSessionIsOfTheAdmin(): void {
        // The log says who did it, and that is the Admin behind the user
        $adminID = $this->create("log-admin@framework.test", Access::Admin);
        $userID  = $this->create("log-user@framework.test", Access::General);
        Auth::login(Credential::getByID($adminID, complete: true));
        $this->assertTrue(Auth::loginAs($userID));
        $this->assertTrue(Auth::isLoggedAsUser());

        ActionLog::startSession(destroy: true);

        $this->assertGreaterThan(0, SessionLog::getID($adminID));
        $this->assertSame(0, SessionLog::getID($userID));
    }



    public function testAnActionIsLogged(): void {
        ActionLog::startSession();

        $this->assertTrue(ActionLog::add(Sec::Example, Act::Example, 42));

        $request = new LogActionRequest();
        $this->assertSame(1, ActionLog::getAmount($request));

        $list = ActionLog::getAll($request);
        $this->assertCount(1, $list);
        $this->assertSame("Example", $list[0]["actions"][0]["module"]);
        $this->assertSame("Example", $list[0]["actions"][0]["action"]);
        $this->assertSame([ 42 ], $list[0]["actions"][0]["dataID"]);
    }

    public function testTheActionsCarryTheNamesToShow(): void {
        // The list of an app shows the translated names, so they travel beside the
        // module and the action this groups by
        ActionLog::startSession();
        ActionLog::add(Sec::Example, Act::Example, 1);

        $action = ActionLog::getAll(new LogActionRequest())[0]["actions"][0];
        $this->assertSame(
            [ "module", "moduleName", "action", "actionName", "dataID", "createdTime" ],
            array_keys($action),
        );
    }

    public function testAnActionIsLoggedByName(): void {
        // The Sections and the Actions are enums an app builds, and the log
        // takes the name of one or the string itself
        ActionLog::startSession();

        $this->assertTrue(ActionLog::add("Orders", "Shipped", 7));

        $list = ActionLog::getAll(new LogActionRequest());
        $this->assertSame("Orders", $list[0]["actions"][0]["module"]);
        $this->assertSame("Shipped", $list[0]["actions"][0]["action"]);
    }

    public function testSeveralDataIDsAreKept(): void {
        ActionLog::startSession();

        ActionLog::add(Sec::Example, Act::Example, [ 1, 2, 3 ]);

        $list = ActionLog::getAll(new LogActionRequest());
        $this->assertSame([ 1, 2, 3 ], $list[0]["actions"][0]["dataID"]);
    }

    public function testAnActionTakesACredential(): void {
        // Which is how a job that runs for somebody logs it as theirs, rather
        // than as whoever happens to be signed in. They need a session of
        // their own for it to go anywhere
        $credentialID = $this->create();
        SessionLog::start($credentialID);
        ActionLog::startSession();

        $this->assertTrue(ActionLog::add(Sec::Example, Act::Example, 1, $credentialID));

        // It went into their session rather than the one of nobody
        $list = ActionLog::getAll(new LogActionRequest());
        $this->assertCount(1, $list);
        $this->assertSame($credentialID, $list[0]["credentialID"]);
        $this->assertSame(SessionLog::getID($credentialID), $list[0]["sessionID"]);
    }

    public function testAnActionNeedsASession(): void {
        $this->assertFalse(ActionLog::add(Sec::Example, Act::Example, 42));

        $this->assertSame(0, ActionLog::getEntityTotal());
    }

    public function testTheActionsAreGrouped(): void {
        ActionLog::startSession();
        ActionLog::add(Sec::Example, Act::Example, 1);
        ActionLog::add(Sec::Example, Act::Example, 2);

        ActionLog::startSession(destroy: true);
        ActionLog::add(Sec::Example, Act::Example, 3);

        $list = ActionLog::getAll(new LogActionRequest());
        $this->assertCount(2, $list);
        $this->assertCount(1, $list[0]["actions"]);
        $this->assertCount(2, $list[1]["actions"]);

        // The last group is told apart, since it is what the page stops at
        $this->assertFalse($list[0]["isLast"]);
        $this->assertTrue($list[1]["isLast"]);
    }

    public function testThereIsNothingLogged(): void {
        $result = ActionLog::getAll(new LogActionRequest());

        $this->assertSame([], $result);
        $this->assertSame(0, ActionLog::getAmount(new LogActionRequest()));
    }

    public function testTheActionsAreFilteredByWho(): void {
        $credentialID = $this->create();
        Auth::login(Credential::getByID($credentialID, complete: true));
        ActionLog::add(Sec::Example, Act::Example, 1);

        Auth::logout();
        ActionLog::startSession();
        ActionLog::add(Sec::Example, Act::Example, 2);

        $request = new LogActionRequest(credentialID: $credentialID);
        $this->assertSame(1, ActionLog::getAmount($request));
        $this->assertSame(2, ActionLog::getAmount(new LogActionRequest()));
    }

    /**
     * @param list<string> $whoIDs
     * @param string       $who
     * @param int          $expected
     */
    #[DataProvider("providerFilteredBySeveral")]
    public function testTheActionsAreFilteredBySeveral(
        array $whoIDs,
        string $who,
        int $expected,
    ): void {
        // An app lists what the users of one of its accounts did, so the log takes a list
        // of them, and the one of the filter on top of it narrows it down
        $ids = [
            "ana"  => $this->create("log-ana@framework.test"),
            "bea"  => $this->create("log-bea@framework.test"),
            "cris" => $this->create("log-cris@framework.test"),
        ];
        foreach ($ids as $credentialID) {
            SessionLog::start($credentialID);
            ActionLog::add(Sec::Example, Act::Example, 1, $credentialID);
        }

        $request = new LogActionRequest(
            credentialID:  $who !== "" ? $ids[$who] : 0,
            credentialIDs: array_map(fn (string $key) => $ids[$key], $whoIDs),
        );
        $this->assertSame($expected, ActionLog::getAmount($request));
        $this->assertCount($expected, ActionLog::getAll($request));
    }

    /**
     * @return array<string,array{list<string>,string,int}>
     */
    public static function providerFilteredBySeveral(): array {
        return [
            "no list"            => [ [], "", 3 ],
            "a list of one"      => [ [ "ana" ], "", 1 ],
            "a list of two"      => [ [ "ana", "bea" ], "", 2 ],
            "one of the list"    => [ [ "ana", "bea" ], "bea", 1 ],
            "one out of it"      => [ [ "ana", "bea" ], "cris", 0 ],
        ];
    }

    public function testTheActionsAreSearched(): void {
        ActionLog::startSession();
        ActionLog::add("Orders", "Shipped", 1);
        ActionLog::add("Products", "Created", 2);

        $this->assertSame(1, ActionLog::getAmount(new LogActionRequest(search: "Products")));
    }

    public function testTheActionsTakeAMapping(): void {
        // An app lists the log filtered by one of its own columns, which it
        // gives as a mapping from the field of the request
        ActionLog::startSession();
        ActionLog::add("Orders", "Shipped", 1);
        ActionLog::add("Products", "Created", 2);

        $mappings = [ "search" => LogActionColumn::Module ];
        $request  = new LogActionRequest(search: "Orders");

        $this->assertSame(1, ActionLog::getAmount($request, $mappings));
        $this->assertSame(2, ActionLog::getAmount(new LogActionRequest(), $mappings));
    }

    public function testTheOldOnesAreDeleted(): void {
        ActionLog::startSession();
        ActionLog::add(Sec::Example, Act::Example, 1);
        $this->query("UPDATE `log_action` SET `createdTime` = UNIX_TIMESTAMP() - 91 * 86400");
        $this->query("UPDATE `log_session` SET `createdTime` = UNIX_TIMESTAMP() - 91 * 86400");

        $this->assertTrue(ActionLog::deleteOld());

        $this->assertSame(0, ActionLog::getEntityTotal());
        $this->assertSame(0, SessionLog::getEntityTotal());
    }

    public function testThereIsNothingOldToDelete(): void {
        ActionLog::startSession();
        ActionLog::add(Sec::Example, Act::Example, 1);

        $this->assertFalse(ActionLog::deleteOld());

        $this->assertSame(1, ActionLog::getEntityTotal());
    }
}
