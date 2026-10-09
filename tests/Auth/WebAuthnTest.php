<?php
namespace Tests\Auth;

use Framework\Auth\WebAuthn;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class WebAuthnTest extends TestCase {

    #[DataProvider("providerEncode")]
    public function testEncodeAndDecodeTheBase64Url(string $bytes, string $encoded): void {
        $this->assertSame($encoded, WebAuthn::encode($bytes));
        $this->assertSame($bytes, WebAuthn::decode($encoded));
    }

    public static function providerEncode(): array {
        return [
            "empty"         => [ "", "" ],
            "no padding"    => [ "abc", "YWJj" ],
            "one padding"   => [ "ab", "YWI" ],
            "two padding"   => [ "a", "YQ" ],
            "url chars"     => [ "\xfb\xff\xbf", "-_-_" ],
        ];
    }

    public function testDecodeAcceptsThePadding(): void {
        $this->assertSame("ab", WebAuthn::decode("YWI="));
        $this->assertSame("", WebAuthn::decode("not base64!"));
    }


    #[DataProvider("providerDecodeCBOR")]
    public function testDecodeCBOR(string $data, mixed $expected): void {
        $this->assertSame($expected, WebAuthn::decodeCBOR($data));
    }

    public static function providerDecodeCBOR(): array {
        return [
            "small int"     => [ PasskeyFixture::int(10), 10 ],
            "one byte int"  => [ PasskeyFixture::int(200), 200 ],
            "two byte int"  => [ PasskeyFixture::int(1000), 1000 ],
            "negative"      => [ PasskeyFixture::int(-7), -7 ],
            "big negative"  => [ PasskeyFixture::int(-257), -257 ],
            "bytes"         => [ PasskeyFixture::bytes("\x01\x02"), "\x01\x02" ],
            "text"          => [ PasskeyFixture::text("none"), "none" ],
            "list"          => [ PasskeyFixture::list([ PasskeyFixture::int(1), PasskeyFixture::text("a") ]), [ 1, "a" ] ],
            "map"           => [ PasskeyFixture::map([ 1 => PasskeyFixture::int(2), "fmt" => PasskeyFixture::text("none") ]), [ 1 => 2, "fmt" => "none" ] ],
            "true"          => [ "\xf5", true ],
            "false"         => [ "\xf4", false ],
            "null"          => [ "\xf6", null ],
            "empty"         => [ "", null ],
            "truncated"     => [ PasskeyFixture::head(2, 10) . "abc", null ],
            "float"         => [ "\xfa\x00\x00\x00\x00", null ],
            "indefinite"    => [ "\x5f", null ],
            "list as a key" => [ PasskeyFixture::head(5, 1) . PasskeyFixture::list([]) . PasskeyFixture::int(1), null ],
        ];
    }


    #[DataProvider("providerGetAlgorithm")]
    public function testGetAlgorithm(array $coseKey, int $expected): void {
        $this->assertSame($expected, WebAuthn::getAlgorithm($coseKey));
    }

    public static function providerGetAlgorithm(): array {
        return [
            "es256"   => [ [ 3 => -7 ], WebAuthn::ES256 ],
            "rs256"   => [ [ 3 => -257 ], WebAuthn::RS256 ],
            "eddsa"   => [ [ 3 => -8 ], 0 ],
            "missing" => [ [], 0 ],
        ];
    }


    #[DataProvider("providerAlgorithms")]
    public function testGetPublicKeyPemMatchesTheKey(int $algorithm): void {
        $fixture = new PasskeyFixture($algorithm);
        $coseKey = WebAuthn::decodeCBOR($fixture->coseKey);

        $this->assertIsArray($coseKey);
        $this->assertSame($fixture->publicKeyPem, WebAuthn::getPublicKeyPem($coseKey));
    }

    public static function providerAlgorithms(): array {
        return [
            "es256" => [ WebAuthn::ES256 ],
            "rs256" => [ WebAuthn::RS256 ],
        ];
    }

    #[DataProvider("providerInvalidKeys")]
    public function testGetPublicKeyPemRejectsOtherKeys(array $coseKey): void {
        $this->assertSame("", WebAuthn::getPublicKeyPem($coseKey));
    }

    public static function providerInvalidKeys(): array {
        return [
            "empty"         => [ [] ],
            "other curve"   => [ [ 1 => 2, -1 => 2, -2 => str_repeat("a", 32), -3 => str_repeat("b", 32) ] ],
            "short point"   => [ [ 1 => 2, -1 => 1, -2 => "a", -3 => "b" ] ],
            "rsa no exp"    => [ [ 1 => 3, -1 => str_repeat("a", 256) ] ],
            "okp"           => [ [ 1 => 1, -1 => 6, -2 => str_repeat("a", 32) ] ],
        ];
    }


    #[DataProvider("providerGetClientChallenge")]
    public function testGetClientChallenge(string $clientData, string $expected): void {
        $origins = [ "https://app.example.com" ];
        $this->assertSame($expected, WebAuthn::getClientChallenge($clientData, "webauthn.get", $origins));
    }

    public static function providerGetClientChallenge(): array {
        return [
            "valid"        => [ PasskeyFixture::clientData("webauthn.get", "abc", "https://app.example.com"), "abc" ],
            "other type"   => [ PasskeyFixture::clientData("webauthn.create", "abc", "https://app.example.com"), "" ],
            "other origin" => [ PasskeyFixture::clientData("webauthn.get", "abc", "https://evil.example.com"), "" ],
            "not json"     => [ "abc", "" ],
        ];
    }


    #[DataProvider("providerAlgorithms")]
    public function testIsValidSignatureAcceptsTheSignedData(int $algorithm): void {
        $fixture    = new PasskeyFixture($algorithm);
        $authData   = $fixture->authData("app.example.com");
        $clientData = PasskeyFixture::clientData("webauthn.get", "abc", "https://app.example.com");
        $signature  = $fixture->sign($authData, $clientData);

        $this->assertTrue(WebAuthn::isValidSignature($authData, $clientData, $signature, $fixture->publicKeyPem));
    }

    #[DataProvider("providerInvalidSignatures")]
    public function testIsValidSignatureRejectsOtherData(string $change): void {
        $fixture    = new PasskeyFixture();
        $authData   = $fixture->authData("app.example.com");
        $clientData = PasskeyFixture::clientData("webauthn.get", "abc", "https://app.example.com");
        $signature  = $fixture->sign($authData, $clientData);
        $publicKey  = $fixture->publicKeyPem;

        match ($change) {
            "auth data"   => $authData   = $fixture->authData("other.example.com"),
            "client data" => $clientData = PasskeyFixture::clientData("webauthn.get", "xyz", "https://app.example.com"),
            "other key"   => $publicKey  = (new PasskeyFixture())->publicKeyPem,
            "bad key"     => $publicKey  = "not a key",
            "signature"   => $signature  = "nope",
        };
        $this->assertFalse(WebAuthn::isValidSignature($authData, $clientData, $signature, $publicKey));
    }

    public static function providerInvalidSignatures(): array {
        return [
            "auth data"   => [ "auth data" ],
            "client data" => [ "client data" ],
            "other key"   => [ "other key" ],
            "bad key"     => [ "bad key" ],
            "signature"   => [ "signature" ],
        ];
    }
}
