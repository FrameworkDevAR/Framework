<?php
namespace Tests\Auth;

use Framework\Auth\Passkey;
use Framework\Auth\WebAuthn;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The passkeys of a credential, registered and used to login with a real key
 */
class PasskeyLiveTest extends LiveTestCase {

    private const CredentialID = 900041;
    private const OtherID      = 900042;
    private const RpID         = "app.example.com";
    private const Origin       = "https://app.example.com";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();
        $this->query("DELETE FROM credential_passkey");
        $this->query("DELETE FROM credential_challenge");
    }

    /**
     * Registers a Passkey of the fixture for the Credential, as the browser would
     * @param PasskeyFixture $fixture
     * @param int            $credentialID
     * @param string         $name
     * @return int
     */
    private function register(
        PasskeyFixture $fixture,
        int $credentialID = self::CredentialID,
        string $name = "Laptop",
    ): int {
        $options    = Passkey::getRegisterOptions($credentialID, "user@example.com", "User", self::RpID, "App");
        $clientData = PasskeyFixture::clientData("webauthn.create", (string)$options["challenge"], self::Origin);
        $authData   = $fixture->authData(self::RpID, withCredential: true);

        return Passkey::register(
            $credentialID,
            $name,
            self::RpID,
            [ self::Origin ],
            WebAuthn::encode($clientData),
            WebAuthn::encode($fixture->attestation($authData)),
        );
    }

    /**
     * Logins with the Passkey of the fixture, as the browser would
     * @param PasskeyFixture $fixture
     * @param int            $signCount
     * @return int
     */
    private function login(PasskeyFixture $fixture, int $signCount = 0): int {
        $options    = Passkey::getLoginOptions(self::RpID);
        $clientData = PasskeyFixture::clientData("webauthn.get", (string)$options["challenge"], self::Origin);
        $authData   = $fixture->authData(self::RpID, signCount: $signCount);

        return Passkey::getLoginCredentialID(
            self::RpID,
            [ self::Origin ],
            WebAuthn::encode($fixture->credentialKey),
            WebAuthn::encode($clientData),
            WebAuthn::encode($authData),
            WebAuthn::encode($fixture->sign($authData, $clientData)),
        );
    }



    #[DataProvider("providerGetRpID")]
    public function testGetRpID(string $origin, bool $withLocal, string $expected): void {
        $this->assertSame($expected, Passkey::getRpID($origin, "https://app.example.com/", $withLocal));
    }

    public static function providerGetRpID(): array {
        return [
            "the app"             => [ "https://app.example.com", false, "app.example.com" ],
            "another site"        => [ "https://evil.example.com", false, "" ],
            "the app over http"   => [ "http://app.example.com", false, "" ],
            "with a path"         => [ "https://app.example.com/login", false, "" ],
            "empty"               => [ "", true, "" ],
            "localhost"           => [ "http://localhost:4100", true, "localhost" ],
            "a local host"        => [ "https://app-alpha.local:4100", true, "app-alpha.local" ],
            "local not allowed"   => [ "http://localhost:4100", false, "" ],
            "another site, local" => [ "https://evil.example.com", true, "" ],
        ];
    }

    public function testTheRegisterOptionsAskForADiscoverableVerifiedPasskey(): void {
        $options = Passkey::getRegisterOptions(self::CredentialID, "user@example.com", "User", self::RpID, "App");

        $this->assertSame([ "id" => self::RpID, "name" => "App" ], $options["rp"]);
        $this->assertSame(Passkey::getUserHandle(self::CredentialID), $options["user"]["id"]);
        $this->assertSame(WebAuthn::encode((string)self::CredentialID), Passkey::getUserHandle(self::CredentialID));
        $this->assertSame("user@example.com", $options["user"]["name"]);
        $this->assertSame("none", $options["attestation"]);
        $this->assertSame("required", $options["authenticatorSelection"]["residentKey"]);
        $this->assertSame("required", $options["authenticatorSelection"]["userVerification"]);
        $this->assertSame([], $options["excludeCredentials"]);
    }

    public function testTheRegisterOptionsExcludeThePasskeysItHas(): void {
        $fixture = new PasskeyFixture();
        $this->register($fixture);

        $options = Passkey::getRegisterOptions(self::CredentialID, "user@example.com", "User", self::RpID, "App");
        $this->assertSame([
            [ "type" => "public-key", "id" => WebAuthn::encode($fixture->credentialKey) ],
        ], $options["excludeCredentials"]);
    }

    #[DataProvider("providerAlgorithms")]
    public function testAPasskeyIsRegisteredWithItsKey(int $algorithm): void {
        $fixture   = new PasskeyFixture($algorithm);
        $passkeyID = $this->register($fixture);

        $this->assertGreaterThan(0, $passkeyID);
        $passkeys = Passkey::getAllForCredential(self::CredentialID);
        $this->assertCount(1, $passkeys);
        $this->assertSame("Laptop", $passkeys[0]->name);
        $this->assertSame($algorithm, $passkeys[0]->algorithm);
        $this->assertSame($fixture->publicKeyPem, $passkeys[0]->publicKey);
        $this->assertSame($passkeyID, Passkey::getByCredentialKey(WebAuthn::encode($fixture->credentialKey))->id);
    }

    public static function providerAlgorithms(): array {
        return [
            "es256" => [ WebAuthn::ES256 ],
            "rs256" => [ WebAuthn::RS256 ],
        ];
    }

    #[DataProvider("providerAlgorithms")]
    public function testALoginReturnsTheCredentialOfThePasskey(int $algorithm): void {
        $fixture = new PasskeyFixture($algorithm);
        $this->register($fixture);

        $this->assertSame(self::CredentialID, $this->login($fixture));
        $this->assertNotNull(Passkey::getByCredentialKey(WebAuthn::encode($fixture->credentialKey))->lastUsedTime);
    }

    #[DataProvider("providerInvalidRegisters")]
    public function testAnInvalidRegisterIsRejected(string $change): void {
        $fixture    = new PasskeyFixture();
        $options    = Passkey::getRegisterOptions(self::CredentialID, "user@example.com", "User", self::RpID, "App");
        $challenge  = (string)$options["challenge"];
        $rpID       = self::RpID;
        $origin     = self::Origin;
        $type       = "webauthn.create";
        $flags      = 0x05;
        $credential = self::CredentialID;

        match ($change) {
            "other challenge"  => $challenge  = "made-up",
            "other origin"     => $origin     = "https://evil.example.com",
            "other type"       => $type       = "webauthn.get",
            "other site"       => $rpID       = "evil.example.com",
            "not verified"     => $flags      = 0x01,
            "other credential" => $credential = self::OtherID,
        };

        $clientData = PasskeyFixture::clientData($type, $challenge, $origin);
        $authData   = $fixture->authData($rpID, flags: $flags, withCredential: true);
        $passkeyID  = Passkey::register(
            $credential,
            "Laptop",
            self::RpID,
            [ self::Origin ],
            WebAuthn::encode($clientData),
            WebAuthn::encode($fixture->attestation($authData)),
        );

        $this->assertSame(0, $passkeyID);
        $this->assertSame([], Passkey::getAllForCredential($credential));
    }

    public static function providerInvalidRegisters(): array {
        return [
            "other challenge"  => [ "other challenge" ],
            "other origin"     => [ "other origin" ],
            "other type"       => [ "other type" ],
            "other site"       => [ "other site" ],
            "not verified"     => [ "not verified" ],
            "other credential" => [ "other credential" ],
        ];
    }

    #[DataProvider("providerConditional")]
    public function testAConditionalRegisterNeedsNoUser(bool $isConditional, int $expectedCount): void {
        $fixture    = new PasskeyFixture();
        $options    = Passkey::getRegisterOptions(self::CredentialID, "user@example.com", "User", self::RpID, "App");
        $clientData = PasskeyFixture::clientData("webauthn.create", (string)$options["challenge"], self::Origin);
        $authData   = $fixture->authData(self::RpID, flags: 0x00, withCredential: true);

        Passkey::register(
            self::CredentialID,
            "Laptop",
            self::RpID,
            [ self::Origin ],
            WebAuthn::encode($clientData),
            WebAuthn::encode($fixture->attestation($authData)),
            $isConditional,
        );
        $this->assertCount($expectedCount, Passkey::getAllForCredential(self::CredentialID));
    }

    public static function providerConditional(): array {
        return [
            "conditional" => [ true, 1 ],
            "asked"       => [ false, 0 ],
        ];
    }

    public function testARegisterWithoutTheKeyIsRejected(): void {
        $fixture    = new PasskeyFixture();
        $options    = Passkey::getRegisterOptions(self::CredentialID, "user@example.com", "User", self::RpID, "App");
        $clientData = PasskeyFixture::clientData("webauthn.create", (string)$options["challenge"], self::Origin);

        $passkeyID = Passkey::register(
            self::CredentialID,
            "Laptop",
            self::RpID,
            [ self::Origin ],
            WebAuthn::encode($clientData),
            WebAuthn::encode($fixture->attestation($fixture->authData(self::RpID))),
        );
        $this->assertSame(0, $passkeyID);
    }

    public function testTheSamePasskeyIsNotRegisteredTwice(): void {
        $fixture = new PasskeyFixture();

        $this->assertGreaterThan(0, $this->register($fixture));
        $this->assertSame(0, $this->register($fixture, self::OtherID));
    }

    #[DataProvider("providerInvalidLogins")]
    public function testAnInvalidLoginIsRejected(string $change): void {
        $fixture = new PasskeyFixture();
        $this->register($fixture);

        $options       = Passkey::getLoginOptions(self::RpID);
        $challenge     = (string)$options["challenge"];
        $origin        = self::Origin;
        $credentialKey = WebAuthn::encode($fixture->credentialKey);
        $signer        = $fixture;
        $flags         = 0x05;

        match ($change) {
            "other challenge" => $challenge     = "made-up",
            "other origin"    => $origin        = "https://evil.example.com",
            "unknown key"     => $credentialKey = WebAuthn::encode("unknown"),
            "other signer"    => $signer        = new PasskeyFixture(),
            "not verified"    => $flags         = 0x01,
        };

        $clientData = PasskeyFixture::clientData("webauthn.get", $challenge, $origin);
        $authData   = $fixture->authData(self::RpID, flags: $flags);
        $result     = Passkey::getLoginCredentialID(
            self::RpID,
            [ self::Origin ],
            $credentialKey,
            WebAuthn::encode($clientData),
            WebAuthn::encode($authData),
            WebAuthn::encode($signer->sign($authData, $clientData)),
        );
        $this->assertSame(0, $result);
    }

    public static function providerInvalidLogins(): array {
        return [
            "other challenge" => [ "other challenge" ],
            "other origin"    => [ "other origin" ],
            "unknown key"     => [ "unknown key" ],
            "other signer"    => [ "other signer" ],
            "not verified"    => [ "not verified" ],
        ];
    }

    public function testTheCounterHasToGoUpOnceItIsUsed(): void {
        $fixture = new PasskeyFixture();
        $this->register($fixture);

        $this->assertSame(self::CredentialID, $this->login($fixture, 5));
        $this->assertSame(0, $this->login($fixture, 5));
        $this->assertSame(0, $this->login($fixture, 0));
        $this->assertSame(self::CredentialID, $this->login($fixture, 6));
    }

    public function testASyncedPasskeyThatAlwaysSendsZeroCanLoginAgain(): void {
        $fixture = new PasskeyFixture();
        $this->register($fixture);

        $this->assertSame(self::CredentialID, $this->login($fixture));
        $this->assertSame(self::CredentialID, $this->login($fixture));
    }

    public function testAPasskeyIsRenamedOnlyByItsCredential(): void {
        $passkeyID = $this->register(new PasskeyFixture());

        Passkey::rename(self::OtherID, $passkeyID, "Stolen");
        $this->assertSame("Laptop", Passkey::getAllForCredential(self::CredentialID)[0]->name);

        $this->assertTrue(Passkey::rename(self::CredentialID, $passkeyID, "  Phone  "));
        $this->assertSame("Phone", Passkey::getAllForCredential(self::CredentialID)[0]->name);
    }

    public function testAPasskeyIsDeletedOnlyByItsCredential(): void {
        $fixture   = new PasskeyFixture();
        $passkeyID = $this->register($fixture);

        Passkey::delete(self::OtherID, $passkeyID);
        $this->assertCount(1, Passkey::getAllForCredential(self::CredentialID));

        $this->assertTrue(Passkey::delete(self::CredentialID, $passkeyID));
        $this->assertSame([], Passkey::getAllForCredential(self::CredentialID));
        $this->assertSame(0, $this->login($fixture));
    }

    public function testAllThePasskeysOfACredentialAreDeleted(): void {
        $this->register(new PasskeyFixture());
        $this->register(new PasskeyFixture(), name: "Phone");
        $this->register(new PasskeyFixture(), self::OtherID);

        $this->assertTrue(Passkey::deleteAllForCredential(self::CredentialID));
        $this->assertSame([], Passkey::getAllForCredential(self::CredentialID));
        $this->assertCount(1, Passkey::getAllForCredential(self::OtherID));
    }
}
