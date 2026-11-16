<?php
namespace Framework\MCP;

use Framework\Analysis\Attr\NotTested;
use Framework\Core\Configs;
use Framework\File\Storage;
use Framework\MCP\MCPResult;
use Framework\MCP\MCPServer;
use Framework\System\Config;
use Framework\Provider\Curl;
use Framework\Provider\Type\CurlMethod;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

/**
 * The MCP Client
 */
class MCPClient {

    /**
     * Calls the given Tool of the MCP of the given Environment, printing the error if it fails
     * @param string              $env
     * @param string              $tool
     * @param array<string,mixed> $arguments Optional.
     * @param string              $token     Optional.
     * @param string              $url       Optional.
     * @return Dictionary|null
     */
    #[NotTested("It needs a server")]
    public static function callEnvironment(
        string $env,
        string $tool,
        array $arguments = [],
        string $token = "",
        string $url = "",
    ): ?Dictionary {
        $url = $url !== "" ? Storage::addLastSlash($url) : self::getEnvironmentUrl($env);
        if ($url === "") {
            $key = "MCP_" . Strings::toUpperCase($env) . "_URL";
            print("The environment $env has no url, add the $key to the .env\n");
            return null;
        }

        $url    = $url . Strings::stripStart(MCPServer::Path, "/");
        $token  = $token !== "" ? $token : Configs::getString("mcpToken");
        $result = self::callTool($url, $token, $tool, $arguments);
        if ($result->isError) {
            print("Error: {$result->error}\n");
            return null;
        }
        return $result->data;
    }

    /**
     * Returns the Url of the given Environment, which is the MCP_<ENV>_URL of the .env
     * @param string $env
     * @return string
     */
    #[NotTested("It reads the Config of the App")]
    public static function getEnvironmentUrl(string $env): string {
        if ($env === "local") {
            return Config::getUrl();
        }
        return Configs::getString("mcp" . Strings::upperCaseFirst($env) . "Url");
    }

    /**
     * Calls the given Tool of the MCP Server at the given Url
     * @param string              $url
     * @param string              $token
     * @param string              $tool
     * @param array<string,mixed> $arguments Optional.
     * @return MCPResult
     */
    #[NotTested("It needs a server")]
    public static function callTool(
        string $url,
        string $token,
        string $tool,
        array $arguments = [],
    ): MCPResult {
        $response = Curl::execute(CurlMethod::POST, $url, [
            "jsonrpc" => "2.0",
            "id"      => 1,
            "method"  => "tools/call",
            "params"  => [ "name" => $tool, "arguments" => (object)$arguments ],
        ], [
            "Authorization" => "Bearer $token",
            "Accept"        => "application/json, text/event-stream",
        ], jsonBody: true);

        return self::parseResponse(new Dictionary($response));
    }

    /**
     * Returns the Result of a Tool from the given Response of the Server
     * @param Dictionary $response
     * @return MCPResult
     */
    public static function parseResponse(Dictionary $response): MCPResult {
        if ($response->isEmpty()) {
            return new MCPResult(error: "There was no response, or the token was not accepted");
        }

        // The errors of the protocol come apart from the ones of the Tool, which are its result,
        // and an error of the API, like a login that failed, is only its text
        $error = $response->getDict("error");
        if ($error->has("message")) {
            return new MCPResult(error: $error->getString("message"));
        }
        if ($response->getString("error") !== "") {
            return new MCPResult(error: $response->getString("error"));
        }
        $result = $response->getDict("result");
        if ($result->isEmpty()) {
            return new MCPResult(error: "The response is not of the protocol");
        }

        if ($result->getBool("isError")) {
            $texts = [];
            foreach ($result->getList("content") as $content) {
                $texts[] = (new Dictionary($content))->getString("text");
            }
            return new MCPResult(error: Strings::join($texts, "\n"));
        }
        return new MCPResult($result->getDict("structuredContent"));
    }
}
