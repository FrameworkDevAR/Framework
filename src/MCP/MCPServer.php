<?php
namespace Framework\MCP;

use Framework\Application;
use Framework\Auth\Auth;
use Framework\MCP\Attr\MCPTool;
use Framework\MCP\MCPAuth;
use Framework\MCP\MCPBuilder;
use Framework\MCP\MCPSignature;
use Framework\IO\Request;
use Framework\IO\Response;
use Framework\Intl\NLS;
use Framework\System\MCPTools;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\JSON;
use Framework\Utils\Server;
use Framework\Utils\Strings;
use Framework\Utils\URL;

use ReflectionMethod;
use Throwable;

/**
 * The MCP Server
 * https://modelcontextprotocol.io/specification/2025-06-18
 */
class MCPServer {

    public const Path     = "/mcp";
    public const Versions = [ "2025-06-18", "2025-03-26", "2024-11-05" ];

    public const ReadOnlyPrefixes    = [ "get", "list", "search" ];
    public const DestructivePrefixes = [ "delete", "remove" ];

    public const ParseError     = -32700;
    public const InvalidRequest = -32600;
    public const MethodNotFound = -32601;
    public const InvalidParams  = -32602;



    /**
     * Returns true if the Request is for the MCP or its OAuth, which have paths of their own
     * while there are Tools
     * @return bool
     */
    public static function isRequest(): bool {
        $path = Server::getPath();
        if ($path !== self::Path && !MCPAuth::isPath($path)) {
            return false;
        }
        return count(MCPTools::getHandlers()) > 0;
    }

    /**
     * Answers the Request of the MCP, after checking where it comes from and its login
     * @return Response
     */
    public static function execute(): Response {
        $path = Server::getPath();
        if ($path !== self::Path) {
            return MCPAuth::execute($path);
        }

        // A page open in a browser could call a server of the network, so only a client
        // without an Origin, like the assistants, or one of the same host gets in
        $origin = Server::getString("HTTP_ORIGIN");
        if ($origin !== "" && URL::getHost($origin) !== URL::getHost(Server::getUrl())) {
            return Response::text("")->setStatusCode(403);
        }

        // Only the Accesses with a Token of their own or a Credential that allowed an App
        // log in, never a session or another Token
        $token = Server::getAuthToken();
        if (!Auth::validateToken($token) && !MCPAuth::login($token)) {
            header("WWW-Authenticate: " . MCPAuth::getAuthenticate());
            return Response::text("")->setStatusCode(401);
        }

        // The Client sends the version it agreed on in every message after the initialize
        $version = Server::getString("HTTP_MCP_PROTOCOL_VERSION");
        if ($version !== "" && !Arrays::contains(self::Versions, $version)) {
            return Response::text("")->setStatusCode(400);
        }

        $payload = Server::getPayload(withRequest: false);
        return self::handle(new Request($payload->toStringMixedMap()));
    }

    /**
     * Answers the given Message of the protocol
     * @param Request $request
     * @return Response
     */
    public static function handle(Request $request): Response {
        // The server only answers each message, so it has no stream to open with a GET
        if (!Server::isPostRequest()) {
            return Response::text("")->setStatusCode(405);
        }

        // A body that is not JSON leaves the Request without the method, and a notification
        // has no id and expects no answer
        if (!$request->has("method") && !$request->has("id")) {
            return self::error(null, self::ParseError, "Parse error");
        }
        if (!$request->has("id")) {
            return Response::text("")->setStatusCode(202);
        }

        $id     = $request->get("id");
        $method = $request->getString("method");
        if ($request->getString("jsonrpc") !== "2.0" || $method === "") {
            return self::error($id, self::InvalidRequest, "Invalid Request");
        }

        $params = $request->getDict("params");
        return match ($method) {
            "initialize" => self::result($id, self::initialize($params)),
            "ping"       => self::result($id, []),
            "tools/list" => self::result($id, [ "tools" => self::getTools() ]),
            "tools/call" => self::callTool($id, $params),
            default      => self::error($id, self::MethodNotFound, "Method not found"),
        };
    }

    /**
     * Returns the result of the initialize, with the version that the Client asked for if known
     * @param Dictionary $params
     * @return array<string,mixed>
     */
    private static function initialize(Dictionary $params): array {
        $version = $params->getString("protocolVersion");
        if (!Arrays::contains(self::Versions, $version)) {
            $version = self::Versions[0];
        }

        return [
            "protocolVersion" => $version,
            "capabilities"    => [ "tools" => [ "listChanged" => false ] ],
            "serverInfo"      => [
                "name"    => Application::getName(),
                "version" => Application::getVersion(),
            ],
        ];
    }

    /**
     * Returns true if the current login can call the given Tool
     * @param string $name
     * @return bool
     */
    private static function canCall(string $name): bool {
        // The Token of an Access is the one of the owner of the App, so it calls every Tool, and
        // a Credential only calls the ones of its Access
        return Auth::hasToken() || Auth::grant(MCPTools::getAccess($name));
    }

    /**
     * Returns the Tools that the current Access can call
     * @return list<array<string,mixed>>
     */
    public static function getTools(): array {
        $result = [];
        foreach (MCPTools::getHandlers() as $name => [ $className, $methodName ]) {
            if (!self::canCall($name)) {
                continue;
            }

            $method = new ReflectionMethod($className, $methodName);
            $tool   = $method->getAttributes(MCPTool::class)[0] ?? null;
            if ($tool === null) {
                continue;
            }

            $attribute = $tool->newInstance();
            $title     = $attribute->title;
            if ($title === "") {
                $title = MCPBuilder::getToolTitle($className, $methodName);
            }

            $result[] = [
                "name"        => $name,
                "title"       => $title,
                "description" => MCPSignature::getDescription($method),
                "inputSchema" => MCPSignature::getInputSchema($method),
                "annotations" => self::getAnnotations($attribute, $title, $methodName),
            ];
        }
        return $result;
    }

    /**
     * Returns the Annotations of a Tool, which tell the Client what it asks before calling it
     * @param MCPTool $tool
     * @param string  $title
     * @param string  $methodName
     * @return array<string,string|bool>
     */
    public static function getAnnotations(MCPTool $tool, string $title, string $methodName): array {
        // The protocol takes a Tool that is not read only as destructive, and one that reaches
        // outside of the server as the default, so both are always sent
        $readOnly    = Strings::startsWith($methodName, ...self::ReadOnlyPrefixes);
        $readOnly    = $tool->readOnly ?? $readOnly;
        $destructive = Strings::startsWith($methodName, ...self::DestructivePrefixes);
        $destructive = $tool->destructive ?? $destructive;

        $result = [
            "title"           => $title,
            "readOnlyHint"    => $readOnly,
            "destructiveHint" => !$readOnly && $destructive,
            "openWorldHint"   => $tool->openWorld ?? false,
        ];
        if ($tool->idempotent !== null) {
            $result["idempotentHint"] = $tool->idempotent;
        }
        return $result;
    }

    /**
     * Calls the Tool that the given Params name with its arguments
     * @param mixed      $id
     * @param Dictionary $params
     * @return Response
     */
    private static function callTool(mixed $id, Dictionary $params): Response {
        $name = $params->getString("name");
        if (!isset(MCPTools::getHandlers()[$name]) || !self::canCall($name)) {
            return self::error($id, self::InvalidParams, "Unknown tool: $name");
        }

        // A failure of the Tool is told to the Client as its result, so it can try again, like
        // the inputs it needs and did not send
        $arguments = new Request($params->getDict("arguments")->toStringMixedMap());
        $missing   = self::getMissing($name, $arguments);
        if (count($missing) > 0) {
            $text = "The tool needs these inputs: " . Strings::join($missing, ", ");
            return self::result($id, self::getContent($text, isError: true));
        }

        // A value outside of an enum would be taken as its empty case, and the Tool would do
        // something else than what was asked without saying it
        $invalid = self::getInvalid($name, $arguments);
        if (count($invalid) > 0) {
            $text = Strings::join($invalid, "\n");
            return self::result($id, self::getContent($text, isError: true));
        }
        try {
            $response = MCPTools::call($name, $arguments);
        } catch (Throwable $e) {
            return self::result($id, self::getContent($e->getMessage(), isError: true));
        }
        return self::result($id, self::getToolResult($response));
    }

    /**
     * Returns the required inputs of the given Tool that the Arguments do not have
     * @param string  $name
     * @param Request $arguments
     * @return list<string>
     */
    private static function getMissing(string $name, Request $arguments): array {
        $handler = MCPTools::getHandlers()[$name] ?? null;
        if ($handler === null) {
            return [];
        }

        $result = [];
        $method = new ReflectionMethod($handler[0], $handler[1]);
        foreach (MCPSignature::getRequired($method) as $input) {
            if (!$arguments->has($input)) {
                $result[] = $input;
            }
        }
        return $result;
    }

    /**
     * Returns the errors of the inputs of the given Tool that are not one of the values of its enum
     * @param string  $name
     * @param Request $arguments
     * @return list<string>
     */
    private static function getInvalid(string $name, Request $arguments): array {
        $handler = MCPTools::getHandlers()[$name] ?? null;
        if ($handler === null) {
            return [];
        }

        $method     = new ReflectionMethod($handler[0], $handler[1]);
        $schema     = MCPSignature::getInputSchema($method);
        $properties = new Dictionary((array)($schema["properties"] ?? []));
        $result     = [];

        foreach ($properties->getKeys() as $input) {
            $property = $properties->getDict($input);
            $values   = $property->getStrings("enum");
            if (count($values) === 0) {
                $values = $property->getDict("items")->getStrings("enum");
            }
            if (count($values) === 0 || !$arguments->has($input)) {
                continue;
            }

            // The values are compared without the case, as the enums take them that way
            $allowed = [];
            foreach ($values as $value) {
                $allowed[] = Strings::toLowerCase($value);
            }
            $sent = $arguments->get($input);
            $sent    = is_array($sent) ? $sent : [ $sent ];
            foreach ($sent as $value) {
                if (!is_scalar($value) || (string)$value === "") {
                    continue;
                }
                if (!Arrays::contains($allowed, Strings::toLowerCase((string)$value))) {
                    $result[] = "The $input can only be: " . Strings::join($values, ", ");
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Returns the result of a Tool from the Response of its Method
     * @param Response $response
     * @return array<string,mixed>
     */
    public static function getToolResult(Response $response): array {
        $data = new Dictionary($response->toArray());

        $errors = $data->getDict("errors");
        if ($errors->isNotEmpty()) {
            $texts = [];
            foreach ($errors->getKeys() as $field) {
                $texts[] = "$field: " . NLS::getString($errors->getString($field));
            }
            return self::getContent(Strings::join($texts, "\n"), isError: true);
        }

        $error = $data->getString("error");
        if ($error !== "") {
            return self::getContent(NLS::getString($error), isError: true);
        }

        // An invalid Response has no error of its own, only the flag in its data
        if ($data->getDict("data")->getBool("error")) {
            $text = "The request is not valid, or it is not allowed";
            return self::getContent($text, isError: true);
        }

        $texts   = [];
        $success = $data->getString("success");
        if ($success !== "") {
            $texts[] = NLS::getString($success);
        }
        $value = $data->has("data") ? $data->get("data") : null;
        if ($value !== null) {
            $texts[] = JSON::encode($value, asPretty: true);
        }
        $result = self::getContent(Strings::join($texts, "\n"), isError: false);

        // The data also goes as JSON, for the clients that read it instead of the text, which
        // the protocol only allows when it is an object
        $value = JSON::decodeAsArray(JSON::encode($value));
        if (count($value) > 0 && !array_is_list($value)) {
            $result["structuredContent"] = $value;
        }
        return $result;
    }

    /**
     * Returns the content of a Tool result with the given Text
     * @param string $text
     * @param bool   $isError
     * @return array<string,mixed>
     */
    private static function getContent(string $text, bool $isError): array {
        return [
            "content" => [[ "type" => "text", "text" => $text ]],
            "isError" => $isError,
        ];
    }



    /**
     * Returns the Response of a Message with the given Result
     * @param mixed               $id
     * @param array<string,mixed> $result
     * @return Response
     */
    private static function result(mixed $id, array $result): Response {
        // An empty result is still an object in the protocol
        return new Response([
            "jsonrpc" => "2.0",
            "id"      => $id,
            "result"  => count($result) > 0 ? $result : (object)[],
        ], withTokens: false);
    }

    /**
     * Returns the Response of a Message that failed
     * @param mixed  $id
     * @param int    $code
     * @param string $message
     * @return Response
     */
    private static function error(mixed $id, int $code, string $message): Response {
        return new Response([
            "jsonrpc" => "2.0",
            "id"      => $id,
            "error"   => [ "code" => $code, "message" => $message ],
        ], withTokens: false);
    }
}
