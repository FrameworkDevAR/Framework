<?php
namespace Tests\Auth;

use Framework\Auth\AuthenticatorData;
use Framework\Auth\WebAuthn;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AuthenticatorDataTest extends TestCase {

    public function testAnAssertionHasTheCounterAndNoCredential(): void {
        $fixture  = new PasskeyFixture();
        $authData = new AuthenticatorData($fixture->authData("app.example.com", signCount: 42));

        $this->assertFalse($authData->isEmpty);
        $this->assertSame(hash("sha256", "app.example.com", binary: true), $authData->rpIdHash);
        $this->assertSame(0x05, $authData->flags);
        $this->assertSame(42, $authData->signCount);
        $this->assertFalse($authData->hasCredential());
    }

    public function testARegistrationHasTheCredentialAndItsKey(): void {
        $fixture  = new PasskeyFixture();
        $authData = new AuthenticatorData($fixture->authData("app.example.com", withCredential: true));

        $this->assertTrue($authData->hasCredential());
        $this->assertSame($fixture->credentialKey, $authData->credentialKey);
        $this->assertSame($fixture->publicKeyPem, WebAuthn::getPublicKeyPem($authData->publicKey));
    }

    #[DataProvider("providerInvalidData")]
    public function testInvalidDataIsEmpty(string $data): void {
        $authData = new AuthenticatorData($data);

        $this->assertTrue($authData->isEmpty);
        $this->assertFalse($authData->hasCredential());
        $this->assertFalse($authData->isValidFor("app.example.com"));
    }

    public static function providerInvalidData(): array {
        $fixture = new PasskeyFixture();
        $full    = $fixture->authData("app.example.com", withCredential: true);
        return [
            "empty"            => [ "" ],
            "too short"        => [ str_repeat("a", 36) ],
            "no length"        => [ substr($full, 0, 54) ],
            "short credential" => [ substr($full, 0, 60) ],
            "no key"           => [ substr($full, 0, 55 + strlen($fixture->credentialKey)) ],
        ];
    }


    #[DataProvider("providerIsValidFor")]
    public function testIsValidFor(string $rpID, int $flags, bool $needsUser, bool $expected): void {
        $fixture  = new PasskeyFixture();
        $authData = new AuthenticatorData($fixture->authData($rpID, flags: $flags));

        $this->assertSame($expected, $authData->isValidFor("app.example.com", $needsUser));
    }

    public static function providerIsValidFor(): array {
        return [
            "present and verified" => [ "app.example.com", 0x05, true, true ],
            "with more flags"      => [ "app.example.com", 0x1D, true, true ],
            "other relying party"  => [ "evil.example.com", 0x05, true, false ],
            "not verified"         => [ "app.example.com", 0x01, true, false ],
            "not present"          => [ "app.example.com", 0x04, true, false ],
            "no user, not needed"  => [ "app.example.com", 0x00, false, true ],
            "other site, no user"  => [ "evil.example.com", 0x00, false, false ],
        ];
    }
}
