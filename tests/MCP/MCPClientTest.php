<?php
namespace Tests\MCP;

use Framework\MCP\MCPClient;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The MCP Client
 */
class MCPClientTest extends TestCase {

    #[DataProvider("providerParseResponse")]
    public function testTheResponseGivesTheDataOrTheError(array $response, array $data, string $error): void {
        $result = MCPClient::parseResponse(new Dictionary($response));

        $this->assertSame($data, $result->data->toArray());
        $this->assertSame($error, $result->error);
        $this->assertSame($error !== "", $result->isError);
    }

    /**
     * @return array<string,array{array<string,mixed>,array<string,mixed>,string}>
     */
    public static function providerParseResponse(): array {
        $content = [ [ "type" => "text", "text" => "first" ], [ "type" => "text", "text" => "second" ] ];
        return [
            "no response"          => [ [], [], "There was no response, or the token was not accepted" ],
            "an error of the API"  => [ [ "error" => "GENERAL_ERROR_AUTH" ], [], "GENERAL_ERROR_AUTH" ],
            "another response"     => [ [ "data" => [ "id" => 1 ] ], [], "The response is not of the protocol" ],
            "an error of protocol" => [
                [ "jsonrpc" => "2.0", "id" => 1, "error" => [ "code" => -32602, "message" => "Unknown tool" ] ],
                [],
                "Unknown tool",
            ],
            "an error of the Tool" => [
                [ "jsonrpc" => "2.0", "id" => 1, "result" => [ "content" => $content, "isError" => true ] ],
                [],
                "first\nsecond",
            ],
            "the data"             => [
                [ "jsonrpc" => "2.0", "id" => 1, "result" => [
                    "content"           => $content,
                    "isError"           => false,
                    "structuredContent" => [ "id" => 5 ],
                ] ],
                [ "id" => 5 ],
                "",
            ],
        ];
    }
}
