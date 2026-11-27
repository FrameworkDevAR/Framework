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
        MCPAuth::setAuthorizeRoute("");
    }


    #[DataProvider("providerIsPath")]
    public function testThePathsAreOnlyTakenWhenEnabled(string $path, bool $expected): void {
        $this->assertFalse(MCPAuth::isEnabled());
        $this->assertFalse(MCPAuth::isPath($path));

        // The App enables it by giving the page where a Credential allows an App
        MCPAuth::setAuthorizeRoute("oauth/{requestID}");
        $this->assertTrue(MCPAuth::isEnabled());
        $this->assertSame($expected, MCPAuth::isPath($path));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerIsPath(): array {
        return [
            "the resource"            => [ MCPAuth::ResourcePath, true ],
            "the resource of the MCP" => [ MCPAuth::ResourcePath . MCPServer::getPath(), true ],
            "the server"              => [ MCPAuth::ServerPath, true ],
            "the register"            => [ MCPAuth::getPathOf(MCPAuth::RegisterPath), true ],
            "the authorize"           => [ MCPAuth::getPathOf(MCPAuth::AuthorizePath), true ],
            "the token"               => [ MCPAuth::getPathOf(MCPAuth::TokenPath), true ],
            "the MCP"                 => [ MCPServer::getPath(), false ],
            "another one"             => [ "/mcp/other", false ],
        ];
    }

    public function testThePathsFollowTheOneOfTheMCP(): void {
        MCPAuth::setAuthorizeRoute("oauth/{requestID}");
        MCPServer::setPath("api/mcp");
        $isPath  = MCPAuth::isPath("/api/mcp/token");
        $isOld   = MCPAuth::isPath("/mcp/token");
        $mcpPath = MCPServer::getPath();
        MCPServer::setPath("/mcp");

        $this->assertSame("/api/mcp", $mcpPath);
        $this->assertTrue($isPath);
        $this->assertFalse($isOld);
    }

    public function testTheAuthenticatePointsToTheResource(): void {
        $this->assertSame("Bearer", MCPAuth::getAuthenticate());

        MCPAuth::setAuthorizeRoute("oauth/{requestID}");
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

    public function testWithoutTheTokenOfAnAppItIsAnAssistant(): void {
        // A call that did not log in with the Token of an App, like one with the Token of an
        // Access, is taken as an assistant's, as only an App can say that it writes as the user
        $this->assertTrue(MCPAuth::isAssistant());
    }
}
