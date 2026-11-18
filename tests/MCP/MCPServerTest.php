<?php
namespace Tests\MCP;

use Framework\Application;
use Framework\IO\Request;
use Framework\IO\Response;
use Framework\MCP\MCPServer;
use Framework\MCP\Attr\MCPTool;
use Framework\System\Access;
use Framework\System\Config;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The MCP Server
 */
class MCPServerTest extends TestCase {

    private string $method = "";



    #[\Override]
    protected function setUp(): void {
        $this->method = (string)($_SERVER["REQUEST_METHOD"] ?? "");
        $_SERVER["REQUEST_METHOD"] = "POST";
    }

    #[\Override]
    protected function tearDown(): void {
        $_SERVER["REQUEST_METHOD"] = $this->method;
    }

    /**
     * Returns the Response to a Message with the given method and params
     * @param string              $method
     * @param array<string,mixed> $params Optional.
     * @return array<string,mixed>
     */
    private static function send(string $method, array $params = []): array {
        $request = new Request([
            "jsonrpc" => "2.0",
            "id"      => 7,
            "method"  => $method,
            "params"  => $params,
        ]);
        return MCPServer::handle($request)->toArray();
    }


    #[DataProvider("providerEmptyAnswers")]
    public function testSomeMessagesHaveNoBody(string $httpMethod, array $message, int $status): void {
        $_SERVER["REQUEST_METHOD"] = $httpMethod;
        $response = MCPServer::handle(new Request($message));

        $this->assertSame("", $response->getText());
        $this->assertSame($status, $response->getStatusCode());
    }

    /**
     * @return array<string,array{string,array<string,mixed>,int}>
     */
    public static function providerEmptyAnswers(): array {
        $notification = [ "jsonrpc" => "2.0", "method" => "notifications/initialized" ];
        return [
            "a stream asked with a GET" => [ "GET", $notification, 405 ],
            "a notification"            => [ "POST", $notification, 202 ],
        ];
    }

    #[DataProvider("providerErrors")]
    public function testAnInvalidMessageIsAnError(array $message, mixed $id, int $code): void {
        $result = MCPServer::handle(new Request($message))->toArray();

        $this->assertSame("2.0", $result["jsonrpc"]);
        $this->assertSame($id, $result["id"]);
        $this->assertSame($code, $result["error"]["code"]);
    }

    /**
     * @return array<string,array{array<string,mixed>,mixed,int}>
     */
    public static function providerErrors(): array {
        $call = [ "jsonrpc" => "2.0", "id" => 3, "method" => "tools/call" ];
        return [
            "a body that is not JSON" => [ [], null, MCPServer::ParseError ],
            "no version"              => [ [ "id" => 1, "method" => "ping" ], 1, MCPServer::InvalidRequest ],
            "no method"               => [ [ "jsonrpc" => "2.0", "id" => 2 ], 2, MCPServer::InvalidRequest ],
            "an unknown method"       => [
                [ "jsonrpc" => "2.0", "id" => "a", "method" => "resources/list" ],
                "a",
                MCPServer::MethodNotFound,
            ],
            "an unknown tool"         => [
                [ ...$call, "params" => [ "name" => "missing" ] ],
                3,
                MCPServer::InvalidParams,
            ],
        ];
    }

    #[DataProvider("providerInitialize")]
    public function testTheVersionIsTheOneAskedForWhenKnown(string $asked, string $expected): void {
        $result = self::send("initialize", [ "protocolVersion" => $asked ])["result"];

        $this->assertSame($expected, $result["protocolVersion"]);
        $this->assertSame([ "tools" => [ "listChanged" => false ] ], $result["capabilities"]);
        $this->assertSame(Application::getName(), $result["serverInfo"]["name"]);
        $this->assertSame(Application::getVersion(), $result["serverInfo"]["version"]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerInitialize(): array {
        return [
            "the latest"      => [ "2025-11-25", "2025-11-25" ],
            "an older one"    => [ "2024-11-05", "2024-11-05" ],
            "an unknown one"  => [ "2030-01-01", MCPServer::Versions[0] ],
            "none"            => [ "", MCPServer::Versions[0] ],
        ];
    }

    public function testTheInfoIsSentWhenSet(): void {
        $result = self::send("initialize")["result"];
        $this->assertSame([ "name", "version" ], array_keys($result["serverInfo"]));
        $this->assertArrayNotHasKey("instructions", $result);

        MCPServer::setTitle("Title");
        MCPServer::setDescription("Description");
        MCPServer::setWebsiteUrl("https://example.com");
        MCPServer::setIcon("icon.png");
        MCPServer::setInstructions("Instructions");
        $result = self::send("initialize")["result"];
        MCPServer::setTitle("");
        MCPServer::setDescription("");
        MCPServer::setWebsiteUrl("");
        MCPServer::setIcon("");
        MCPServer::setInstructions("");

        $this->assertSame("Title", $result["serverInfo"]["title"]);
        $this->assertSame("Description", $result["serverInfo"]["description"]);
        $this->assertSame("https://example.com", $result["serverInfo"]["websiteUrl"]);
        $this->assertSame([[ "src" => Config::getAppUrl("icon.png") ]], $result["serverInfo"]["icons"]);
        $this->assertSame("Instructions", $result["instructions"]);
    }

    public function testThePingAnswersAnEmptyObject(): void {
        $result = self::send("ping");

        $this->assertSame(7, $result["id"]);
        $this->assertEquals((object)[], $result["result"]);
    }

    #[DataProvider("providerIsRequest")]
    public function testThePathIsOnlyTakenWithTools(string $path): void {
        // The Framework alone has no Tools, so it leaves every path to the Router
        $uri = $_SERVER["REQUEST_URI"] ?? null;
        $_SERVER["REQUEST_URI"] = $path;
        $this->assertFalse(MCPServer::isRequest());
        $_SERVER["REQUEST_URI"] = $uri;
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerIsRequest(): array {
        return [
            "the one of the MCP" => [ MCPServer::getPath() ],
            "another one"        => [ "/api/mcp" ],
        ];
    }

    #[DataProvider("providerRefusedRequests")]
    public function testTheRequestsThatCanNotGetIn(array $server, int $status): void {
        $server["REQUEST_URI"] = MCPServer::getPath();
        $previous = [];
        foreach ($server as $key => $value) {
            $previous[$key] = $_SERVER[$key] ?? null;
            $_SERVER[$key]  = $value;
        }

        $this->assertSame($status, MCPServer::execute()->getStatusCode());

        foreach ($previous as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    /**
     * @return array<string,array{array<string,string>,int}>
     */
    public static function providerRefusedRequests(): array {
        return [
            "another origin" => [[ "HTTP_HOST" => "acme.com", "HTTP_ORIGIN" => "https://evil.com" ], 403 ],
            "no token"       => [[ "HTTP_HOST" => "acme.com", "HTTP_AUTHORIZATION" => "" ], 401 ],
            "a wrong token"  => [[ "HTTP_HOST" => "acme.com", "HTTP_AUTHORIZATION" => "Bearer wrong" ], 401 ],
        ];
    }

    #[DataProvider("providerGetAnnotations")]
    public function testGetAnnotations(MCPTool $tool, string $methodName, array $expected): void {
        $expected = [ "title" => "Task: Do" ] + $expected;
        $this->assertSame($expected, MCPServer::getAnnotations($tool, "Task: Do", $methodName));
    }

    /**
     * @return array<string,array{MCPTool,string,array<string,bool>}>
     */
    public static function providerGetAnnotations(): array {
        $none = new MCPTool(Access::API, "A tool");
        return [
            "one that reads" => [ $none, "search", [
                "readOnlyHint"    => true,
                "destructiveHint" => false,
                "openWorldHint"   => false,
            ]],
            "one that deletes" => [ $none, "deleteImage", [
                "readOnlyHint"    => false,
                "destructiveHint" => true,
                "openWorldHint"   => false,
            ]],
            "one that changes" => [ $none, "create", [
                "readOnlyHint"    => false,
                "destructiveHint" => false,
                "openWorldHint"   => false,
            ]],
            "the overrides" => [ new MCPTool(Access::API, "A tool", readOnly: true, idempotent: true, openWorld: true), "download", [
                "readOnlyHint"    => true,
                "destructiveHint" => false,
                "openWorldHint"   => true,
                "idempotentHint"  => true,
            ]],
            "destructive when it changes" => [ new MCPTool(Access::API, "A tool", destructive: true), "upload", [
                "readOnlyHint"    => false,
                "destructiveHint" => true,
                "openWorldHint"   => false,
            ]],
        ];
    }

    public function testTheFrameworkAloneHasNoTools(): void {
        $this->assertSame([], MCPServer::getTools());
        $this->assertSame([ "tools" => [] ], self::send("tools/list")["result"]);
    }

    #[DataProvider("providerGetToolResult")]
    public function testTheResponseOfTheToolIsItsContent(
        Response $response,
        string $text,
        bool $isError,
        ?array $structured,
    ): void {
        $result = MCPServer::getToolResult($response);

        $this->assertSame([[ "type" => "text", "text" => $text ]], $result["content"]);
        $this->assertSame($isError, $result["isError"]);
        $this->assertSame($structured, $result["structuredContent"] ?? null);
    }

    /**
     * @return array<string,array{Response,string,bool,array<string,mixed>|null}>
     */
    public static function providerGetToolResult(): array {
        $data = [ "id" => 5, "name" => "Task" ];
        return [
            "the data"          => [
                Response::data($data),
                "{\n    \"id\": 5,\n    \"name\": \"Task\"\n}",
                false,
                $data,
            ],
            "a list of data"    => [ Response::data([ 1, 2 ]), "[\n    1,\n    2\n]", false, null ],
            "an error"          => [
                Response::error("the file is not valid"),
                "the file is not valid",
                true,
                null,
            ],
            "the field errors"  => [
                new Response([ "errors" => [ "name" => "empty", "url" => "wrong" ] ]),
                "name: empty\nurl: wrong",
                true,
                null,
            ],
            "a success"         => [
                Response::success("done", [ "id" => 1 ]),
                "done\n{\n    \"id\": 1\n}",
                false,
                [ "id" => 1 ],
            ],
            "an empty response" => [ Response::empty(), "", false, null ],
        ];
    }
}
