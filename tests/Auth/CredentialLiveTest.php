<?php
namespace Tests\Auth;

use Framework\Auth\Credential;
use Framework\Auth\Passkey;
use Framework\Date\Date;
use Framework\Auth\Schema\CredentialColumn;
use Framework\Auth\Schema\CredentialQuery;
use Framework\Auth\Schema\CredentialRequest;
use Framework\Auth\Schema\CredentialStatus;
use Framework\System\Access;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Credentials, the people who can sign in
 *
 * The rows are made and taken away by each test, so nothing is left behind
 * for the next one to trip on.
 */
class CredentialLiveTest extends LiveTestCase {

    private const Email    = "credential@framework.test";
    private const Other    = "other-credential@framework.test";
    private const Password = "a-password-of-the-tests";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        // The table is the subject of these, and destroy() blanks a row
        // rather than taking it away, so it is emptied outright
        $this->query("DELETE FROM `credential`");
    }

    /**
     * Creates a Credential and hands back its ID
     * @param string $email     Optional.
     * @param string $firstName Optional.
     * @param string $lastName  Optional.
     * @return int
     */
    private function create(
        string $email = self::Email,
        string $firstName = "Ana",
        string $lastName = "Suárez",
    ): int {
        return Credential::create(new CredentialRequest(
            firstName: $firstName,
            lastName:  $lastName,
            email:     $email,
            password:  self::Password,
            access:    Access::Admin,
            status:    CredentialStatus::Active,
        ));
    }



    public function testTheCredentialIsCreated(): void {
        $credentialID = $this->create();

        $this->assertGreaterThan(0, $credentialID);
        $this->assertTrue(Credential::exists($credentialID));
        $this->assertTrue(Credential::emailExists(self::Email));
    }

    public function testTheNameIsMergedFromItsParts(): void {
        $entity = Credential::getByID($this->create(firstName: "Ana", lastName: "Suárez"));

        $this->assertSame("Ana", $entity->firstName);
        $this->assertSame("Suárez", $entity->lastName);
        $this->assertSame("Ana Suárez", $entity->name);
    }

    public function testTheCredentialIsFoundByItsEmail(): void {
        $credentialID = $this->create();

        $this->assertSame($credentialID, Credential::getByEmail(self::Email)->credentialID);
    }

    public function testOneThatWasNeverMadeIsNotThere(): void {
        $this->assertFalse(Credential::exists(999999));
        $this->assertFalse(Credential::emailExists("nobody@framework.test"));
        $this->assertFalse(Credential::getByID(999999)->exists());
    }

    public function testTheAccessAndTheStatusAreSet(): void {
        $entity = Credential::getByID($this->create());

        $this->assertSame(Access::Admin, $entity->access);
        $this->assertSame(CredentialStatus::Active, $entity->status);
    }



    public function testThePasswordIsCheckedAgainstItsHash(): void {
        $credentialID = $this->create();

        $this->assertTrue(Credential::isPasswordCorrect($credentialID, self::Password));
        $this->assertFalse(Credential::isPasswordCorrect($credentialID, "not-the-password"));
    }

    public function testTheHashOfTwoCredentialsDiffersForOnePassword(): void {
        // Each gets its own salt, so the same password is stored differently
        $first  = Credential::getByID($this->create(self::Email), complete: true);
        $second = Credential::getByID($this->create(self::Other), complete: true);

        $this->assertNotSame($first->salt, $second->salt);
        $this->assertNotSame($first->password, $second->password);
    }

    public function testThePasswordOfNobodyIsNeverCorrect(): void {
        $this->assertFalse(Credential::isPasswordCorrect(999999, self::Password));
    }

    public function testThePasswordIsChanged(): void {
        $credentialID = $this->create();
        Credential::setPassword($credentialID, "a-new-password");

        $this->assertFalse(Credential::isPasswordCorrect($credentialID, self::Password));
        $this->assertTrue(Credential::isPasswordCorrect($credentialID, "a-new-password"));
    }

    public function testAChangeOfPasswordIsAskedForAndDropped(): void {
        $credentialID = $this->create();

        Credential::setReqPassChange($credentialID, true);
        $this->assertTrue(Credential::reqPassChange($credentialID));

        Credential::setReqPassChange($credentialID, false);
        $this->assertFalse(Credential::reqPassChange($credentialID));
    }

    public function testATemporaryPasswordWorksAndAsksForAChange(): void {
        $credentialID = $this->create();
        $tempPass     = Credential::setTempPass($credentialID);

        $this->assertNotSame("", $tempPass);
        $this->assertTrue(Credential::isPasswordCorrect($credentialID, $tempPass));
        $this->assertTrue(Credential::reqPassChange($credentialID));
    }



    public function testTheCredentialIsEdited(): void {
        $credentialID = $this->create();

        Credential::edit($credentialID, new CredentialRequest(
            firstName: "Bruno",
            lastName:  "Lima",
            email:     self::Email,
            access:    Access::Admin,
            status:    CredentialStatus::Active,
        ));

        $entity = Credential::getByID($credentialID);
        $this->assertSame("Bruno Lima", $entity->name);
    }

    /**
     * One of the setters, and the column it writes
     * @param string $method
     * @param mixed  $value
     * @param string $column
     * @param mixed  $expected
     * @return void
     */
    #[DataProvider("providerSetters")]
    public function testTheSetterWritesItsColumn(
        string $method,
        mixed $value,
        string $column,
        mixed $expected,
    ): void {
        $credentialID = $this->create();

        /** @var callable */
        $callable = [ Credential::class, $method ];
        $callable($credentialID, $value);

        $this->assertSame($expected, Credential::getByID($credentialID)->$column);
    }

    /**
     * The avatar is a file, so the Entity hands back the url of it rather
     * than the name that was written
     * @return array<string,array{string,mixed,string,mixed}>
     */
    public static function providerSetters(): array {
        return [
            "the user"       => [ "setCurrentUser", 42, "currentUser", 42 ],
            "the email"      => [ "setEmail", "moved@framework.test", "email", "moved@framework.test" ],
            "the language"   => [ "setLanguage", "es", "language", "es" ],
            "the timezone"   => [ "setTimezone", 3, "timezone", 3 ],
            "the avatar"     => [ "setAvatar", "a-face.png", "avatar", "files/avatars/a-face.png" ],
            "the appearance" => [ "setAppearance", "dark", "appearance", "dark" ],
        ];
    }

    public function testTheAccessIsChanged(): void {
        $credentialID = $this->create();
        Credential::setAccess($credentialID, Access::API);

        $this->assertSame(Access::API, Credential::getByID($credentialID)->access);
    }

    public function testTheStatusIsChanged(): void {
        $credentialID = $this->create();
        Credential::setStatus($credentialID, CredentialStatus::Inactive);

        $this->assertSame(CredentialStatus::Inactive, Credential::getByID($credentialID)->status);
    }

    public function testAValueIsReadAndWrittenByItsColumn(): void {
        $credentialID = $this->create();
        Credential::setValue($credentialID, CredentialColumn::Language, "pt");

        $this->assertSame("pt", Credential::getValue($credentialID, CredentialColumn::Language));
    }

    public function testTheNotificationsAreNoLongerAskedFor(): void {
        $credentialID = $this->create();

        $this->assertTrue(Credential::dontAskNotifications($credentialID));
        $this->assertFalse(Credential::getByID($credentialID)->askNotifications);
    }

    public function testTheLoginTimeIsMovedOn(): void {
        // The one before becomes the last one. Asked of a Credential made in
        // this same second it would write the values already there, and a
        // statement that changes no rows is not a success
        $credentialID = $this->create();
        $past         = Date::now()->subtract(days: 2)->toTime();
        Credential::setValue($credentialID, CredentialColumn::CurrentLogin, $past);

        $this->assertTrue(Credential::updateLoginTime($credentialID));

        $entity = Credential::getByID($credentialID);
        $this->assertSame($past, $entity->lastLogin->toTime());
        $this->assertGreaterThan($past, $entity->currentLogin->toTime());
    }



    public function testTheAccessTokenIsMadeAndFound(): void {
        $credentialID = $this->create();
        $accessToken  = Credential::setAccessToken($credentialID);

        $this->assertNotSame("", $accessToken);
        $this->assertSame($credentialID, Credential::getByAccessToken($accessToken)->credentialID);
    }

    public function testTheAccessTokenIsTakenAway(): void {
        $credentialID = $this->create();
        $accessToken  = Credential::setAccessToken($credentialID);

        $this->assertTrue(Credential::removeAccessToken($credentialID));
        $this->assertFalse(Credential::getByAccessToken($accessToken)->exists());
    }

    public function testATokenThatWasNeverMadeFindsNobody(): void {
        $this->assertFalse(Credential::getByAccessToken("not-a-token")->exists());
    }



    public function testAPasswordThatHasExpiredIsNeverCorrect(): void {
        $credentialID = $this->create();
        $past         = Date::now()->subtract(days: 1)->toTime();
        $this->query(
            "UPDATE `credential` SET `passExpiration` = $past WHERE `CREDENTIAL_ID` = $credentialID",
        );

        $this->assertFalse(Credential::isPasswordCorrect($credentialID, self::Password));
    }

    public function testAChangeOfPasswordAskedOfNobodyIsNo(): void {
        $this->assertFalse(Credential::reqPassChange());
    }

    public function testAnAccessTokenStillGoodIsHandedBackAgain(): void {
        // The one that is there is kept and only its expiration is pushed on,
        // so the sessions holding it are not cut short
        $credentialID = $this->create();
        $first        = Credential::setAccessToken($credentialID);
        $past         = Date::now()->subtract(hours: 1)->toTime();
        $this->query(
            "UPDATE `credential` SET `tokenExpiration` = $past WHERE `CREDENTIAL_ID` = $credentialID",
        );

        $this->assertSame($first, Credential::setAccessToken($credentialID));
    }

    public function testTheCredentialIsBlankedWhenDestroyed(): void {
        // It keeps the row, so anything pointing at it still resolves, but
        // nothing of the person is left in it
        $credentialID = $this->create();
        Credential::destroy($credentialID);

        $entity = Credential::getByID($credentialID, complete: true);
        $this->assertNotSame(self::Email, $entity->email);
        $this->assertSame("", $entity->phone);
        $this->assertSame("", $entity->password);
        $this->assertSame("", $entity->salt);
        $this->assertSame(CredentialStatus::None, $entity->status);
    }

    public function testThePasskeysAreDeletedWhenDestroyed(): void {
        $credentialID = $this->create();
        $this->query(
            "INSERT INTO `credential_passkey` (`CREDENTIAL_ID`, `name`, `credentialKey`) " .
            "VALUES ($credentialID, 'Laptop', 'destroyed-key')",
        );

        Credential::destroy($credentialID);
        $this->assertSame([], Passkey::getAllForCredential($credentialID));
    }

    public function testTheCredentialIsMarkedAsDeleted(): void {
        $credentialID = $this->create();

        $this->assertTrue(Credential::delete($credentialID));
        $this->assertFalse(Credential::exists($credentialID));
    }

    public function testTheCredentialIsCounted(): void {
        $query  = new CredentialQuery();
        $before = Credential::getTotal($query);
        $this->create();

        $this->assertSame($before + 1, Credential::getTotal($query));
    }

    public function testTheCredentialsAreSearchedByTheirNameAndEmail(): void {
        $credentialID = $this->create();

        $result = Credential::search("Suárez");
        $found  = false;
        foreach ($result as $elem) {
            if ($elem->id === $credentialID) {
                $found = true;
            }
        }

        $this->assertTrue($found, "the Credential was not searched up");
    }

    public function testASearchForNobodyFindsNothing(): void {
        $this->create();

        $this->assertSame([], Credential::search("a-name-that-is-nobody"));
    }

    public function testASearchIsNarrowedByItsQuery(): void {
        $credentialID = $this->create();
        $query        = new CredentialQuery();
        $query->credentialID->equal(999999);

        $this->assertSame([], Credential::search("Suárez", query: $query));
        $this->assertNotSame([], Credential::search("Suárez"));
        $this->assertGreaterThan(0, $credentialID);
    }

    public function testTheCredentialsComeBackAsAList(): void {
        $credentialID = $this->create();
        $query        = new CredentialQuery();
        $query->credentialID->equal($credentialID);

        $result = Credential::getList($query);
        $this->assertCount(1, $result);
        $this->assertSame($credentialID, $result[0]->credentialID);
    }

    public function testTheProgressIsWritten(): void {
        $credentialID = $this->create();

        $this->assertTrue(Credential::setProgress($credentialID, 42));
        $this->assertSame(42, Credential::getByID($credentialID)->progressValue);
    }

    public function testAnEntityIsAskedForItsOwnPassword(): void {
        // It takes the Entity as well as the ID, which saves a read
        $entity = Credential::getByID($this->create(), complete: true);

        $this->assertTrue(Credential::isPasswordCorrect($entity, self::Password));
    }

    public function testAChangeOfPasswordIsAskedOfAnEmail(): void {
        $credentialID = $this->create();
        Credential::setReqPassChange($credentialID, true);

        $this->assertTrue(Credential::reqPassChange(0, self::Email));
        $this->assertFalse(Credential::reqPassChange(0, "nobody@framework.test"));
    }

    public function testTheAccessTokenIsGivenTheHoursAsked(): void {
        $credentialID = $this->create();

        $this->assertNotSame("", Credential::setAccessToken($credentialID, hours: 1));
    }

    public function testTheCredentialsComeBackAsTheOptionsOfASelect(): void {
        $credentialID = $this->create();

        $query = new CredentialQuery();
        $query->credentialID->equal($credentialID);

        $result = Credential::getSelect($query);
        $this->assertCount(1, $result);
        $this->assertSame($credentialID, $result[0]->key);
    }
}
