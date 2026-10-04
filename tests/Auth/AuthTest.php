<?php
namespace Tests\Auth;

use Framework\Auth\Auth;
use Framework\System\Access;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Auth of a request that carries a Token
 *
 * A token names an Access on its own, without a credential behind it. What the
 * token is worth is read from the environment, so the cases here write it there
 * and hand the value, or another one, to the validation.
 */
class AuthTest extends TestCase {
    use TestHelpers;

    protected function setUp(): void {
        $this->reset();
    }

    protected function tearDown(): void {
        $this->reset();
        $this->setConfig("AUTH_API_TOKEN", "");
    }

    /**
     * Puts the Auth back to a request that named nobody
     * @return void
     */
    private function reset(): void {
        $this->setPrivateStaticProperty(Auth::class, "accessName", Access::General);
        $this->setPrivateStaticProperty(Auth::class, "apiToken", "");
    }



    /**
     * The token a request comes with, against the one the environment holds
     * @param string $configured
     * @param string $given
     * @param bool   $isValid
     * @return void
     */
    #[DataProvider("providerValidateAPI")]
    public function testTheApiTokenIsValidated(
        string $configured,
        string $given,
        bool $isValid,
    ): void {
        $this->setConfig("AUTH_API_TOKEN", $configured);

        $this->assertSame($isValid, Auth::validateAPI($given));
        $this->assertSame($isValid, Auth::hasAPI());
        $this->assertSame($isValid ? Access::API : Access::General, Auth::getAccessName());
    }

    /**
     * @return array<string,array{string,string,bool}>
     */
    public static function providerValidateAPI(): array {
        return [
            "the one it holds" => [ "a-token", "a-token", true ],
            "another one"      => [ "a-token", "other-token", false ],
            // A request that brings none cannot pass as an app that has none
            "none at all"      => [ "", "", false ],
            "one against none" => [ "", "a-token", false ],
            "none against one" => [ "a-token", "", false ],
        ];
    }


    // An Access with no token of its own is never reached by one
    #[DataProvider("providerTokenFor")]
    public function testTheTokenOfAnAccessIsRead(Access $accessName): void {
        $this->assertSame("", Access::getTokenKey($accessName));
        $this->assertSame("", Auth::getTokenFor($accessName));
        $this->assertSame([], Auth::getTokensFor($accessName));
    }

    /**
     * @return array<string,array{Access}>
     */
    public static function providerTokenFor(): array {
        return [
            "general" => [ Access::General ],
            "admin"   => [ Access::Admin ],
            "api"     => [ Access::API ],
            "none"    => [ Access::None ],
        ];
    }

    public function testNoAccessIsReachedWithATokenHere(): void {
        // The roles of this repository are the default ones, none of which is
        // given a token, so an app is what puts something in this list
        $this->assertSame([], Access::getTokenAccesses());
    }
}
