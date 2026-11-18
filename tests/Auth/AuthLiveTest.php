<?php
namespace Tests\Auth;

use Framework\Auth\Auth;
use Framework\Auth\AuthToken;
use Framework\Auth\Credential;
use Framework\Auth\Schema\CredentialRequest;
use Framework\Auth\Schema\CredentialStatus;
use Framework\Date\TimeZone;
use Framework\Intl\NLS;
use Framework\System\Access;
use Framework\Utils\Dictionary;

use Tests\LiveTestCase;
use Tests\TestHelpers;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Auth, which holds who is signed in for the length of a request
 *
 * It is all static, so each test signs out first and leaves nothing behind.
 */
class AuthLiveTest extends LiveTestCase {
    use TestHelpers;

    private const AdminEmail = "auth-admin@framework.test";
    private const UserEmail  = "auth-user@framework.test";
    private const Password   = "a-password-of-the-tests";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        Auth::logout();

        // destroy() blanks a row rather than taking it away, so the table is
        // emptied outright between the tests that own it
        $this->query("DELETE FROM `credential`");
    }

    protected function tearDown(): void {
        Auth::logout();
        $this->setPrivateStaticProperty(Auth::class, "userValidator", null);
        $this->setConfig("AUTH_API_TOKEN", "");
        $this->setConfig("AUTH_FIELDS", []);

        // Signing in moves the timezone and the language of the Credential
        // onto the whole process, which the Date tests would then read
        $this->setPrivateStaticProperty(TimeZone::class, "stackZones", []);
        $this->setPrivateStaticProperty(TimeZone::class, "timeDiff", 0.0);
        $this->setPrivateStaticProperty(NLS::class, "language", "root");
    }

    /**
     * Sets the API token of the config, which is empty in this repository
     * @param string $apiToken
     * @return void
     */
    private function setApiToken(string $apiToken): void {
        $this->setConfig("AUTH_API_TOKEN", $apiToken);
    }

    /**
     * Creates a Credential and hands back its ID
     * @param string           $email
     * @param Access           $access Optional.
     * @param CredentialStatus $status Optional.
     * @return int
     */
    private function create(
        string $email,
        Access $access = Access::Admin,
        CredentialStatus $status = CredentialStatus::Active,
    ): int {
        return Credential::create(new CredentialRequest(
            firstName: "Ana",
            lastName:  "Suárez",
            email:     $email,
            password:  self::Password,
            access:    $access,
            status:    $status,
        ));
    }



    public function testNobodyIsSignedInToStartWith(): void {
        $this->assertFalse(Auth::isLoggedIn());
        $this->assertFalse(Auth::hasCredential());
        $this->assertSame(0, Auth::getID());
        $this->assertSame(Access::General, Auth::getAccessName());
    }

    public function testSigningInHoldsTheCredential(): void {
        $credentialID = $this->create(self::AdminEmail);
        Auth::login(Credential::getByID($credentialID, complete: true));

        $this->assertTrue(Auth::isLoggedIn());
        $this->assertTrue(Auth::hasCredential());
        $this->assertSame($credentialID, Auth::getID());
        $this->assertSame(Access::Admin, Auth::getAccessName());
        $this->assertSame($credentialID, Auth::getCredential()->credentialID);
    }

    public function testSigningInIsAnAdmin(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        $this->assertTrue(Auth::isAdmin());
    }

    public function testSigningOutLetsGoOfEverything(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));
        Auth::logout();

        $this->assertFalse(Auth::isLoggedIn());
        $this->assertSame(0, Auth::getID());
        $this->assertSame(0, Auth::getAdminID());
        $this->assertSame(Access::General, Auth::getAccessName());
    }

    public function testSigningInGivesARefreshToken(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        $this->assertNotSame("", Auth::getRefreshToken());
    }

    public function testTheLanguageComesFromTheCredential(): void {
        $credentialID = $this->create(self::AdminEmail);
        Credential::setLanguage($credentialID, "es");
        Auth::login(Credential::getByID($credentialID, complete: true));

        $this->assertSame("es", Auth::getLanguage());
    }

    public function testThePasswordIsCheckedForTheOneSignedIn(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        $this->assertTrue(Auth::isPasswordCorrect(self::Password));
        $this->assertFalse(Auth::isPasswordCorrect("not-the-password"));
    }



    /**
     * A Credential, and whether it is allowed to sign in at all
     * @param CredentialStatus $status
     * @param bool             $expected
     * @return void
     */
    #[DataProvider("providerCanLogin")]
    public function testOnlyAnActiveCredentialCanSignIn(
        CredentialStatus $status,
        bool $expected,
    ): void {
        $credentialID = $this->create(self::AdminEmail, status: $status);

        $this->assertSame(
            $expected,
            Auth::canLogin(Credential::getByID($credentialID, complete: true)),
        );
    }

    /**
     * @return array<string,array{CredentialStatus,bool}>
     */
    public static function providerCanLogin(): array {
        return [
            "an active one"   => [ CredentialStatus::Active, true ],
            "an inactive one" => [ CredentialStatus::Inactive, false ],
        ];
    }

    public function testOneThatIsNotThereCannotSignIn(): void {
        $this->assertFalse(Auth::canLogin(Credential::getByID(999999, complete: true)));
    }

    public function testADeletedCredentialCannotSignIn(): void {
        $credentialID = $this->create(self::AdminEmail);
        Credential::delete($credentialID);

        $this->assertFalse(Auth::canLogin(Credential::getByID($credentialID, complete: true)));
    }

    public function testTheCredentialToSignInIsFoundByItsEmail(): void {
        $credentialID = $this->create(self::AdminEmail);

        $this->assertSame($credentialID, Auth::getLoginCredential(self::AdminEmail)->credentialID);
    }

    public function testAnEmailThatIsNobodyFindsNothing(): void {
        $this->assertFalse(Auth::getLoginCredential("nobody@framework.test")->exists());
    }



    public function testAnAdminSignsInAsAUser(): void {
        $adminID = $this->create(self::AdminEmail, Access::Admin);
        $userID  = $this->create(self::UserEmail, Access::General);
        Auth::login(Credential::getByID($adminID, complete: true));

        $this->assertTrue(Auth::loginAs($userID));

        $this->assertSame($userID, Auth::getID());
        $this->assertSame($adminID, Auth::getAdminID());
        $this->assertTrue(Auth::isLoggedAsUser());
    }

    public function testSigningBackGivesTheAdminReturned(): void {
        $adminID = $this->create(self::AdminEmail, Access::Admin);
        $userID  = $this->create(self::UserEmail, Access::General);
        Auth::login(Credential::getByID($adminID, complete: true));
        Auth::loginAs($userID);

        $this->assertSame($userID, Auth::logoutAs());

        $this->assertSame($adminID, Auth::getID());
        $this->assertFalse(Auth::isLoggedAsUser());
    }

    public function testNobodyCanSignInAsSomeoneWithoutSigningInFirst(): void {
        $userID = $this->create(self::UserEmail, Access::General);

        $this->assertFalse(Auth::loginAs($userID));
    }

    public function testSigningBackWithNobodyToReturnToGivesNothing(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        $this->assertSame(0, Auth::logoutAs());
    }

    public function testACredentialCannotSignInAsOneAboveIt(): void {
        $userID  = $this->create(self::UserEmail, Access::General);
        $adminID = $this->create(self::AdminEmail, Access::Admin);
        Auth::login(Credential::getByID($userID, complete: true));

        $this->assertFalse(Auth::loginAs($adminID));
    }



    /**
     * The access asked of the one signed in, and whether it is granted
     * @param Access $current
     * @param Access $asked
     * @param bool   $expected
     * @return void
     */
    #[DataProvider("providerGrant")]
    public function testTheAccessIsGrantedByItsLevel(
        Access $current,
        Access $asked,
        bool $expected,
    ): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail, $current), complete: true));

        $this->assertSame($expected, Auth::grant($asked));
    }

    /**
     * @return array<string,array{Access,Access,bool}>
     */
    public static function providerGrant(): array {
        return [
            "the same one"    => [ Access::Admin, Access::Admin, true ],
            "one below"       => [ Access::Admin, Access::General, true ],
            "one above"       => [ Access::General, Access::Admin, false ],
            "the general one" => [ Access::General, Access::General, true ],
        ];
    }

    public function testOnlyTheGeneralAccessIsOpenToNobody(): void {
        $this->assertFalse(Auth::requiresLogin(Access::General));
        $this->assertTrue(Auth::requiresLogin(Access::Admin));
    }

    public function testARequestWithARefreshTokenIsAccepted(): void {
        $credentialID = $this->create(self::AdminEmail);
        $refreshToken = AuthToken::createRefreshToken($credentialID);

        $this->assertTrue(Auth::validateCredential("", $refreshToken, "es", 3));

        $this->assertTrue(Auth::isLoggedIn());
        $this->assertSame($credentialID, Auth::getID());
    }

    public function testARequestWithAnAccessTokenIsAccepted(): void {
        $credentialID = $this->create(self::AdminEmail);
        $accessToken  = AuthToken::createAccessToken([ "credentialID" => $credentialID ]);
        $refreshToken = AuthToken::createRefreshToken($credentialID);

        $this->assertTrue(Auth::validateCredential($accessToken, $refreshToken, "", 0));

        $this->assertSame($credentialID, Auth::getID());
    }

    public function testARequestCarryingTheApiTokenIsTakenAsTheApi(): void {
        $this->setApiToken("the-api-token");
        $accessToken = AuthToken::createAccessToken([ "apiToken" => "the-api-token" ]);

        $this->assertTrue(Auth::validateCredential($accessToken, "", "", 0));
        $this->assertTrue(Auth::hasAPI());
    }

    /**
     * A request the tokens of which name nobody
     * @param bool $withToken
     * @return void
     */
    #[DataProvider("providerNotValidated")]
    public function testARequestNamingNobodyIsRefused(bool $withToken): void {
        $accessToken = $withToken
            ? AuthToken::createAccessToken([ "credentialID" => 999999 ])
            : "";

        $this->assertFalse(Auth::validateCredential($accessToken, "", "", 0));
        $this->assertFalse(Auth::isLoggedIn());
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function providerNotValidated(): array {
        return [
            "no token at all"   => [ false ],
            "one naming nobody" => [ true ],
        ];
    }

    public function testARequestNamingAnAdminIsTakenAsActingAsTheUser(): void {
        $adminID     = $this->create(self::AdminEmail, Access::Admin);
        $userID      = $this->create(self::UserEmail, Access::General);
        $accessToken = AuthToken::createAccessToken([
            "credentialID" => $userID,
            "adminID"      => $adminID,
        ]);

        $this->assertTrue(Auth::validateCredential($accessToken, "", "", 0));

        $this->assertSame($userID, Auth::getID());
        $this->assertSame($adminID, Auth::getAdminID());
        $this->assertTrue(Auth::isLoggedAsUser());
    }

    public function testTheLanguageOfTheRequestIsKeptForTheAdminActingAsAUser(): void {
        $adminID     = $this->create(self::AdminEmail, Access::Admin);
        $userID      = $this->create(self::UserEmail, Access::General);
        $accessToken = AuthToken::createAccessToken([
            "credentialID" => $userID,
            "adminID"      => $adminID,
        ]);

        $this->assertTrue(Auth::validateCredential($accessToken, "", "pt", 0));

        // It is the Admin doing it whose language is written, not the user
        $this->assertSame("pt", Credential::getByID($adminID)->language);
        $this->assertSame("", Credential::getByID($userID)->language);
    }

    public function testSigningBackWhereTheAdminMayNotIsNothing(): void {
        // The pair arrives written into the token, so it is not the login that
        // has to allow it. A General cannot act as anyone, and asking to sign
        // back gives nothing rather than handing over their session
        $notAnAdminID = $this->create(self::AdminEmail, Access::General);
        $userID       = $this->create(self::UserEmail, Access::General);
        $accessToken  = AuthToken::createAccessToken([
            "credentialID" => $userID,
            "adminID"      => $notAnAdminID,
        ]);
        Auth::validateCredential($accessToken, "", "", 0);

        $this->assertSame(0, Auth::logoutAs());
    }

    public function testARequestOfADeletedCredentialIsRefused(): void {
        $credentialID = $this->create(self::AdminEmail);
        $refreshToken = AuthToken::createRefreshToken($credentialID);
        Credential::delete($credentialID);

        $this->assertFalse(Auth::validateCredential("", $refreshToken, "", 0));
    }

    public function testTheLanguageIsTheOneOfWhoeverIsBeingActedAs(): void {
        // getLanguage answers with the Credential that is held, so signed in
        // as a user it is theirs, even though the strings were set to the
        // language of the Admin doing it
        $adminID = $this->create(self::AdminEmail, Access::Admin);
        $userID  = $this->create(self::UserEmail, Access::General);
        Credential::setLanguage($adminID, "pt");
        Credential::setLanguage($userID, "es");

        Auth::login(Credential::getByID($adminID, complete: true));
        Auth::loginAs($userID);

        $this->assertSame("es", Auth::getLanguage());
    }

    public function testAnAdminSignsInAsAUserThroughTheirTwoEmails(): void {
        // The pair is written as one email, which is how the sign in form
        // asks for it without a screen of its own
        $adminID = $this->create(self::AdminEmail, Access::Admin);
        $userID  = $this->create(self::UserEmail, Access::General);

        $result = Auth::getLoginCredential(self::AdminEmail . "|" . self::UserEmail);

        $this->assertSame($userID, $result->credentialID);
        $this->assertSame($adminID, $result->adminID);
    }

    public function testAPairThatIsNotAllowedIsLeftAsTheUser(): void {
        $userID = $this->create(self::UserEmail, Access::General);
        $this->create(self::AdminEmail, Access::Admin);

        $result = Auth::getLoginCredential(self::UserEmail . "|" . self::AdminEmail);

        $this->assertSame(0, $result->adminID);
        $this->assertGreaterThan(0, $userID);
    }

    public function testTheApiTokenIsTheOneOfTheConfig(): void {
        $this->setApiToken("the-api-token");

        $this->assertSame("the-api-token", Auth::getApiToken());
        $this->assertTrue(Auth::validateAPI("the-api-token"));
        $this->assertTrue(Auth::hasAPI());
        $this->assertSame(Access::API, Auth::getAccessName());
    }

    public function testAnApiTokenThatIsNotTheOneIsRefused(): void {
        $this->setApiToken("the-api-token");

        $this->assertFalse(Auth::validateAPI("not-the-api-token"));
        $this->assertFalse(Auth::hasAPI());
    }

    public function testAnEmptyTokenNeverValidates(): void {
        // With no token configured, both sides of the comparison were the
        // empty string, and a request with no token at all was let in
        $this->setApiToken("");

        $this->assertFalse(Auth::validateAPI(""));
        $this->assertFalse(Auth::hasAPI());
    }

    public function testAnInternalRequestIsTakenAsTheApi(): void {
        Auth::validateInternal();

        $this->assertSame(Access::API, Auth::getAccessName());
    }

    public function testTheLoginIsDisabledByTheConfig(): void {
        // It answers whatever the config says, which the tests do not change
        $this->assertIsBool(Auth::isLoginDisabled());
    }

    public function testTheSpamProtectionIsAskedOfTheLogin(): void {
        $this->assertIsBool(Auth::spamProtection());
    }

    public function testTheCredentialIsReadAgain(): void {
        $credentialID = $this->create(self::AdminEmail);
        Auth::login(Credential::getByID($credentialID, complete: true));
        Credential::setLanguage($credentialID, "pt");

        $this->assertTrue(Auth::updateCredential());
        $this->assertSame("pt", Auth::getCredential()->language);
    }

    public function testThereIsNoCredentialToReadAgainForNobody(): void {
        $this->assertFalse(Auth::updateCredential());
    }

    /**
     * Returns the data of the Access Token that the Auth gives now
     * @return Dictionary
     */
    private function accessData(): Dictionary {
        /** @var Dictionary */
        return $this->callPrivateStaticMethod(AuthToken::class, "getAccessData", Auth::getAccessToken());
    }

    /**
     * Validates a Request of a new Admin that asks for the given User
     * @param int $userID
     * @return void
     */
    private function validateWithUser(int $userID): void {
        $credentialID = $this->create(self::AdminEmail);
        $refreshToken = AuthToken::createRefreshToken($credentialID);
        $this->assertTrue(Auth::validateCredential("", $refreshToken, "", 0, $userID));
    }

    public function testARequestUsesTheUserThatTheAppAllows(): void {
        Auth::setUserValidator(fn ($credential, int $userID) => $userID === 55);
        $this->validateWithUser(55);

        // Only the User changes, and the Token keeps the current one
        $this->assertSame(55, Auth::getUserID());
        $this->assertSame(Access::Admin, Auth::getAccessName());
        $this->assertSame(0, $this->accessData()->getInt("userID"));
    }

    public function testAUserThatTheAppRefusesIsNotUsed(): void {
        Auth::setUserValidator(fn ($credential, int $userID) => false);
        $this->validateWithUser(55);

        $this->assertSame(0, Auth::getUserID());
    }

    public function testAUserIsNotUsedWithoutAValidator(): void {
        $this->validateWithUser(55);

        $this->assertSame(0, Auth::getUserID());
    }

    public function testTheValidatorIsGivenTheCredentialAndTheUser(): void {
        $given = [];
        Auth::setUserValidator(function ($credential, int $userID) use (&$given): bool {
            $given = [ $credential->email, $userID ];
            return true;
        });
        $this->validateWithUser(55);

        $this->assertSame([ self::AdminEmail, 55 ], $given);
    }

    public function testChangingTheCurrentUserLetsGoOfTheUserOfTheRequest(): void {
        Auth::setUserValidator(fn ($credential, int $userID) => true);
        $this->validateWithUser(55);
        Auth::setCurrentUser(77, Access::General);

        $this->assertSame(77, Auth::getUserID());
    }

    public function testTheCurrentUserIsSet(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));
        Auth::setCurrentUser(77, Access::General);

        $this->assertSame(77, Auth::getUserID());
        $this->assertSame(Access::General, Auth::getAccessName());
    }

    public function testTheAccessTokenIsMadeForTheOneSignedIn(): void {
        $credentialID = $this->create(self::AdminEmail);
        Auth::login(Credential::getByID($credentialID, complete: true));

        $accessToken = Auth::getAccessToken();
        $this->assertNotSame("", $accessToken);
        $this->assertSame([ $credentialID, 0 ], AuthToken::getCredentials($accessToken, ""));
    }

    public function testThereIsNoAccessTokenForNobody(): void {
        $this->assertSame("", Auth::getAccessToken());
    }

    public function testTheAccessTokenOfTheApiSaysSo(): void {
        $this->setApiToken("the-api-token");
        Auth::validateAPI("the-api-token");

        $accessToken = Auth::getAccessToken();
        $this->assertNotSame("", $accessToken);
        $this->assertSame("the-api-token", AuthToken::getAPIToken($accessToken));
    }

    public function testTheAccessTokenCarriesTheFieldsOfTheConfig(): void {
        // The email is not one of the fields the token always carries
        $this->setConfig("AUTH_FIELDS", [ "email" ]);
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        /** @var Dictionary */
        $accessData = $this->callPrivateStaticMethod(
            AuthToken::class,
            "getAccessData",
            Auth::getAccessToken(),
        );
        $this->assertSame(self::AdminEmail, $accessData->getString("email"));
    }

    public function testTheApiIsGrantedOnlyTheApiAndTheGeneralAccess(): void {
        $this->setApiToken("the-api-token");
        Auth::validateAPI("the-api-token");

        $this->assertTrue(Auth::grant(Access::API));
        $this->assertTrue(Auth::grant(Access::General));
        $this->assertFalse(Auth::grant(Access::Admin));
    }

    public function testTheTempPathIsTheOneOfTheCredential(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        $this->assertNotSame("", Auth::getTempPath());
    }

    public function testNothingIsHeldForNobody(): void {
        $this->assertSame("", Auth::getRefreshToken());
        $this->assertSame("", Auth::getLanguage());
        $this->assertSame("", Auth::getTempPath());
        $this->assertFalse(Auth::getCredential()->exists());
    }

    public function testTheAccessOfTheUserBeingActedAsWins(): void {
        // An App sets the userAccess when the Credential reaches into one of
        // its own users, and that is the access of the request from then on
        $credential = Credential::getByID($this->create(self::AdminEmail), complete: true);
        $credential->userAccess = Access::General;

        Auth::setCredential($credential);

        $this->assertSame(Access::General, Auth::getAccessName());
    }

    public function testNothingIsRequiredOfSomeoneSignedIn(): void {
        Auth::login(Credential::getByID($this->create(self::AdminEmail), complete: true));

        $this->assertFalse(Auth::requiresLogin(Access::Admin));
    }
}
