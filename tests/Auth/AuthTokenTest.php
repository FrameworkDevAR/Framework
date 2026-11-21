<?php
namespace Tests\Auth;

use Framework\Auth\AuthToken;
use Framework\Core\Configs;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Access Tokens, signed with the AUTH_KEY
 */
class AuthTokenTest extends TestCase {
    use TestHelpers;

    protected function tearDown(): void {
        Configs::load();
    }

    #[DataProvider("providerKeys")]
    public function testTheKeyNeeds32Bytes(string $key, bool $isValid): void {
        $this->setConfig("AUTH_KEY", $key);
        if (!$isValid) {
            $this->expectExceptionMessage("The AUTH_KEY must have at least 32 bytes");
        }

        $token = AuthToken::createAccessToken([ "credentialID" => 4 ]);
        $this->assertSame([ 4, 0 ], AuthToken::getCredentials($token, ""));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerKeys(): array {
        return [
            "a short one"    => [ "auth", false ],
            "one byte short" => [ str_repeat("k", 31), false ],
            "32 bytes"       => [ str_repeat("k", 32), true ],
        ];
    }
}
