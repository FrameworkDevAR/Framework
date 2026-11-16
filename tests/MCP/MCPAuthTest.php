<?php
namespace Tests\MCP;

use Framework\MCP\MCPAuth;
use Framework\MCP\MCPServer;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The MCP Auth
 */
class MCPAuthTest extends TestCase {

    #[\Override]
    protected function tearDown(): void {
        MCPAuth::setAuthorizeUrl("");
    }


    #[DataProvider("providerIsPath")]
    public function testThePathsAreOnlyTakenWhenEnabled(string $path, bool $expected): void {
        $this->assertFalse(MCPAuth::isEnabled());
        $this->assertFalse(MCPAuth::isPath($path));

        // The App enables it by giving the page where a Credential allows an App
        MCPAuth::setAuthorizeUrl("https://acme.com/oauth/{requestID}");
        $this->assertTrue(MCPAuth::isEnabled());
        $this->assertSame($expected, MCPAuth::isPath($path));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerIsPath(): array {
        return [
            "the resource"            => [ MCPAuth::ResourcePath, true ],
            "the resource of the MCP" => [ MCPAuth::ResourcePath . MCPServer::Path, true ],
            "the server"              => [ MCPAuth::ServerPath, true ],
            "the register"            => [ MCPAuth::RegisterPath, true ],
            "the authorize"           => [ MCPAuth::AuthorizePath, true ],
            "the token"               => [ MCPAuth::TokenPath, true ],
            "the MCP"                 => [ MCPServer::Path, false ],
            "another one"             => [ "/mcp/other", false ],
        ];
    }

    public function testTheAuthenticatePointsToTheResource(): void {
        $this->assertSame("Bearer", MCPAuth::getAuthenticate());

        MCPAuth::setAuthorizeUrl("https://acme.com/oauth/{requestID}");
        $this->assertStringStartsWith("Bearer resource_metadata=\"", MCPAuth::getAuthenticate());
        $this->assertStringEndsWith(MCPAuth::ResourcePath . "\"", MCPAuth::getAuthenticate());
    }

    #[DataProvider("providerIsValidVerifier")]
    public function testIsValidVerifier(string $verifier, string $challenge, bool $expected): void {
        $this->assertSame($expected, MCPAuth::isValidVerifier($verifier, $challenge));
    }

    /**
     * @return array<string,array{string,string,bool}>
     */
    public static function providerIsValidVerifier(): array {
        $verifier  = "dBjftJeZ4CVP-mJ92K1PDCVHFmVIxvrPH96IC4Mc0Ju";
        $challenge = "uZ01tdJRPXGxSCtZ2HBW28FpSsXCG74mAcw7pVywf6M";
        return [
            "the one of the challenge" => [ $verifier, $challenge, true ],
            "another one"              => [ "another-verifier", $challenge, false ],
            "no verifier"              => [ "", $challenge, false ],
            "no challenge"             => [ $verifier, "", false ],
        ];
    }

    public function testTheHashIsTheSameEachTime(): void {
        $this->assertSame(MCPAuth::hash("a-token"), MCPAuth::hash("a-token"));
        $this->assertNotSame(MCPAuth::hash("a-token"), MCPAuth::hash("another-token"));
        $this->assertSame(64, strlen(MCPAuth::hash("a-token")));
    }
}
