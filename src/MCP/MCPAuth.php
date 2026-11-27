<?php
namespace Framework\MCP;

use Framework\Analysis\Attr\NotTested;
use Framework\Auth\Auth;
use Framework\Auth\Credential;
use Framework\IO\Request;
use Framework\IO\Response;
use Framework\MCP\MCPApp;
use Framework\MCP\MCPGrant;
use Framework\MCP\MCPServer;
use Framework\MCP\MCPToken;
use Framework\System\Access;
use Framework\System\Config;
use Framework\Utils\Arrays;
use Framework\Utils\Server;
use Framework\Utils\Strings;
use Framework\Utils\URL;

/**
 * The MCP Auth
 * https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization
 */
class MCPAuth {

    public const ResourcePath  = "/.well-known/oauth-protected-resource";
    public const ServerPath    = "/.well-known/oauth-authorization-server";
    public const RegisterPath  = "/register";
    public const AuthorizePath = "/authorize";
    public const TokenPath     = "/token";

    private static string $authorizeRoute = "";
    private static ?Access $access        = null;
    private static bool $writesAsUser     = false;



    /**
     * Sets the Route of the page where a Credential allows an App, with {requestID} in it
     * @param string $authorizeRoute
     * @return void
     */
    public static function setAuthorizeRoute(string $authorizeRoute): void {
        self::$authorizeRoute = $authorizeRoute;
    }

    /**
     * Sets the Access that a Credential needs to allow an App
     * @param Access $access
     * @return void
     */
    #[NotTested("It only stores the Access")]
    public static function setAccess(Access $access): void {
        self::$access = $access;
    }

    /**
     * Returns true if the App can be connected with the OAuth, which needs the page to allow it
     * @return bool
     */
    public static function isEnabled(): bool {
        return self::$authorizeRoute !== "";
    }

    /**
     * Returns true if the given Path is one of the OAuth
     * @param string $path
     * @return bool
     */
    public static function isPath(string $path): bool {
        if (!self::isEnabled()) {
            return false;
        }

        // The clients also ask for the documents with the path of the MCP at their end
        return Arrays::contains([
            self::ResourcePath,
            self::ResourcePath . MCPServer::getPath(),
            self::ServerPath,
            self::ServerPath . MCPServer::getPath(),
            self::getPathOf(self::RegisterPath),
            self::getPathOf(self::AuthorizePath),
            self::getPathOf(self::TokenPath),
        ], $path);
    }

    /**
     * Returns the value of the WWW-Authenticate header, that points to where the OAuth starts
     * @return string
     */
    public static function getAuthenticate(): string {
        if (!self::isEnabled()) {
            return "Bearer";
        }
        $url = self::getUrl(self::ResourcePath);
        return "Bearer resource_metadata=\"$url\"";
    }

    /**
     * Returns the hash a Code or a Token is stored with
     * @param string $value
     * @return string
     */
    public static function hash(string $value): string {
        return hash("sha256", $value);
    }



    /**
     * Answers the Request of the given Path of the OAuth
     * @param string $path
     * @return Response
     */
    #[NotTested("It needs a request to the server")]
    public static function execute(string $path): Response {
        MCPGrant::removeOld();
        MCPToken::removeOld();

        $request = new Request(withRequest: true);
        $request->addPayload();

        if (Strings::startsWith($path, self::ResourcePath)) {
            return self::getResource();
        }
        if (Strings::startsWith($path, self::ServerPath)) {
            return self::getServer();
        }
        if (!Server::isPostRequest() && $path !== self::getPathOf(self::AuthorizePath)) {
            return Response::text("")->setStatusCode(405);
        }

        return match ($path) {
            self::getPathOf(self::RegisterPath)  => self::register($request),
            self::getPathOf(self::AuthorizePath) => self::authorize($request),
            default             => self::token($request),
        };
    }

    /**
     * Returns the document of the MCP as a protected resource
     * https://datatracker.ietf.org/doc/html/rfc9728
     * @return Response
     */
    private static function getResource(): Response {
        return self::json([
            "resource"                 => self::getUrl(MCPServer::getPath()),
            "authorization_servers"    => [ self::getIssuer() ],
            "bearer_methods_supported" => [ "header" ],
        ]);
    }

    /**
     * Returns the document of the server that gives the Tokens
     * https://datatracker.ietf.org/doc/html/rfc8414
     * @return Response
     */
    private static function getServer(): Response {
        $authorizePath = self::getPathOf(self::AuthorizePath);
        $tokenPath     = self::getPathOf(self::TokenPath);
        $registerPath  = self::getPathOf(self::RegisterPath);

        return self::json([
            "issuer"                                => self::getIssuer(),
            "authorization_endpoint"                => self::getUrl($authorizePath),
            "token_endpoint"                        => self::getUrl($tokenPath),
            "registration_endpoint"                 => self::getUrl($registerPath),
            "response_types_supported"              => [ "code" ],
            "grant_types_supported"                 => [ "authorization_code", "refresh_token" ],
            "code_challenge_methods_supported"      => [ "S256" ],
            "token_endpoint_auth_methods_supported" => [ "none" ],
        ]);
    }

    /**
     * Registers an App, as the assistants are not known before they connect
     * https://datatracker.ietf.org/doc/html/rfc7591
     * @param Request $request
     * @return Response
     */
    private static function register(Request $request): Response {
        $name         = $request->getString("client_name");
        $redirectUris = $request->getStrings("redirect_uris");
        $writesAsUser = $request->getBool("writes_as_user");
        if (count($redirectUris) === 0) {
            return self::error("invalid_redirect_uri", 400);
        }

        // An App is sent back to a site of its own, or to the computer it runs in
        foreach ($redirectUris as $redirectUri) {
            $isLocal = Arrays::contains([ "localhost", "127.0.0.1" ], URL::getHost($redirectUri));
            if (!Strings::startsWith($redirectUri, "https://") && !$isLocal) {
                return self::error("invalid_redirect_uri", 400);
            }
        }

        $clientID = MCPApp::register($name, $redirectUris, $writesAsUser);
        return self::json([
            "client_id"                  => $clientID,
            "client_name"                => $name,
            "redirect_uris"              => $redirectUris,
            "writes_as_user"             => $writesAsUser,
            "grant_types"                => [ "authorization_code", "refresh_token" ],
            "response_types"             => [ "code" ],
            "token_endpoint_auth_method" => "none",
        ], 201);
    }

    /**
     * Stores the Request of an App to connect, and sends the browser to the page that allows it
     * @param Request $request
     * @return Response
     */
    private static function authorize(Request $request): Response {
        // Without a known App and one of its Urls there is nowhere safe to send an error
        $app         = MCPApp::get($request->getString("client_id"));
        $redirectUri = $request->getString("redirect_uri");
        if ($app->isEmpty() || !MCPApp::hasRedirect($app, $redirectUri)) {
            return Response::text("The app or its url are not valid")->setStatusCode(400);
        }

        $state = $request->getString("state");
        if ($request->getString("response_type") !== "code") {
            return self::redirect($redirectUri, $state, error: "unsupported_response_type");
        }

        // The PKCE is required, as the Apps have no secret
        $codeChallenge = $request->getString("code_challenge");
        if ($codeChallenge === "" || $request->getString("code_challenge_method") !== "S256") {
            return self::redirect($redirectUri, $state, error: "invalid_request");
        }

        $resource = Strings::stripEnd($request->getString("resource"), "/");
        if ($resource !== "" && $resource !== self::getUrl(MCPServer::getPath())) {
            return self::redirect($redirectUri, $state, error: "invalid_target");
        }

        $requestID = MCPGrant::create(
            clientID:      $app->clientID,
            redirectUri:   $redirectUri,
            state:         $state,
            codeChallenge: $codeChallenge,
            scope:         $request->getString("scope"),
        );
        // The Url is read here and not in the config of the App, as the Configs are read once
        // and a config file would read them before a command names its env file
        $route = Strings::replace(self::$authorizeRoute, "{requestID}", $requestID);
        $url   = Config::getAppUrl($route);
        return self::location($url);
    }

    /**
     * Gives the Tokens for a Code, or new ones for a Refresh Token
     * @param Request $request
     * @return Response
     */
    private static function token(Request $request): Response {
        $grantType = $request->getString("grant_type");
        $clientID  = $request->getString("client_id");

        if ($grantType === "authorization_code") {
            $grant = MCPGrant::getCode($request->getString("code"));
            if ($grant->isEmpty() ||
                $grant->clientID !== $clientID ||
                $grant->redirectUri !== $request->getString("redirect_uri") ||
                !self::isValidVerifier($request->getString("code_verifier"), $grant->codeChallenge)
            ) {
                return self::error("invalid_grant", 400);
            }

            // A Code is used once
            MCPGrant::remove($grant->requestID);
            return self::getTokens($grant->clientID, $grant->credentialID);
        }

        if ($grantType === "refresh_token") {
            $token = MCPToken::getRefresh($request->getString("refresh_token"));
            if ($token->isEmpty() || $token->clientID !== $clientID) {
                return self::error("invalid_grant", 400);
            }

            // Each Refresh Token is used once, so a stolen one stops working when used
            MCPToken::remove($token->accessToken);
            return self::getTokens($token->clientID, $token->credentialID);
        }

        return self::error("unsupported_grant_type", 400);
    }

    /**
     * Returns true if the given Verifier is the one of the Challenge of the PKCE
     * @param string $verifier
     * @param string $challenge
     * @return bool
     */
    public static function isValidVerifier(string $verifier, string $challenge): bool {
        if ($verifier === "" || $challenge === "") {
            return false;
        }
        $hash = Strings::base64Encode(hash("sha256", $verifier, binary: true));
        $hash = Strings::stripEnd(Strings::replace($hash, [ "+", "/" ], [ "-", "_" ]), "=");
        return hash_equals($challenge, $hash);
    }

    /**
     * Creates the Tokens of the given Credential and returns them
     * @param string $clientID
     * @param int    $credentialID
     * @return Response
     */
    private static function getTokens(string $clientID, int $credentialID): Response {
        [ $accessToken, $refreshToken ] = MCPToken::create($clientID, $credentialID);
        header("Cache-Control: no-store");
        return self::json([
            "access_token"  => $accessToken,
            "token_type"    => "Bearer",
            "expires_in"    => MCPToken::AccessDuration,
            "refresh_token" => $refreshToken,
        ]);
    }



    /**
     * Returns the name of the App of the given Request, the host it goes back to, and whether
     * it writes as the user, or null
     * @param string $requestID
     * @return array{name:string,host:string,writesAsUser:bool}|null
     */
    #[NotTested("It needs the database")]
    public static function getRequestApp(string $requestID): ?array {
        $grant = MCPGrant::getRequest($requestID);
        if ($grant->isEmpty()) {
            return null;
        }

        // The name is the one the App gave itself, so the host is what shows which App it is
        $app = MCPApp::get($grant->clientID);
        return [
            "name"         => $app->name,
            "host"         => URL::getHost($grant->redirectUri),
            "writesAsUser" => $app->writesAsUser,
        ];
    }

    /**
     * Allows or denies the given Request for the current Credential, and returns the Url to go back
     * @param string $requestID
     * @param bool   $isAllowed
     * @return string
     */
    #[NotTested("It needs the database and a login")]
    public static function answer(string $requestID, bool $isAllowed): string {
        $grant = MCPGrant::getRequest($requestID);
        if ($grant->isEmpty()) {
            return "";
        }

        if (!$isAllowed || !Auth::hasCredential() || !self::hasAccess()) {
            MCPGrant::remove($requestID);
            return self::getRedirect($grant->redirectUri, $grant->state, error: "access_denied");
        }

        $code = MCPGrant::allow($requestID, Auth::getID());
        return self::getRedirect($grant->redirectUri, $grant->state, code: $code);
    }

    /**
     * Logs in the Credential of the given Access Token
     * @param string $accessToken
     * @return bool
     */
    #[NotTested("It needs the database")]
    public static function login(string $accessToken): bool {
        if (!self::isEnabled()) {
            return false;
        }

        $token = MCPToken::getAccess($accessToken);
        if ($token->isEmpty()) {
            return false;
        }

        $credential = Credential::getByID($token->credentialID, complete: true);
        if ($credential->isEmpty() || $credential->isDeleted) {
            return false;
        }

        // A Credential that lost its Access since it allowed the App loses the App too
        Auth::setCredential($credential);
        self::$writesAsUser = MCPApp::get($token->clientID)->writesAsUser;
        return self::hasAccess();
    }

    /**
     * Returns true unless the App of the Token that logged in writes as the user, so that what
     * an assistant writes is shown as its own
     * @return bool
     */
    public static function isAssistant(): bool {
        return !self::$writesAsUser;
    }

    /**
     * Returns true if the Credential has the Access that is needed to allow an App
     * @return bool
     */
    private static function hasAccess(): bool {
        // The Accesses are of each App, so Admin might not be one, and the level of None would
        // grant everyone
        $access = self::$access ?? Access::fromValue("Admin");
        return $access !== Access::None && Auth::grant($access);
    }



    /**
     * Returns the given Path of the OAuth after the Path of the MCP
     * @param string $path
     * @return string
     */
    public static function getPathOf(string $path): string {
        return MCPServer::getPath() . $path;
    }

    /**
     * Returns the Url of the App with the given Path
     * @param string $path
     * @return string
     */
    private static function getUrl(string $path): string {
        return self::getIssuer() . $path;
    }

    /**
     * Returns the Url of the App, which issues the Tokens
     * @return string
     */
    private static function getIssuer(): string {
        return Strings::stripEnd(Config::getUrl(), "/");
    }

    /**
     * Returns the Url to send the browser back to the App with the given Code or Error
     * @param string $redirectUri
     * @param string $state
     * @param string $code        Optional.
     * @param string $error       Optional.
     * @return string
     */
    private static function getRedirect(
        string $redirectUri,
        string $state,
        string $code = "",
        string $error = "",
    ): string {
        $params = [];
        if ($code !== "") {
            $params["code"] = $code;
        }
        if ($error !== "") {
            $params["error"] = $error;
        }
        if ($state !== "") {
            $params["state"] = $state;
        }
        return URL::addParams($redirectUri, $params);
    }

    /**
     * Sends the browser back to the App with the given Error
     * @param string $redirectUri
     * @param string $state
     * @param string $error
     * @return Response
     */
    private static function redirect(string $redirectUri, string $state, string $error): Response {
        return self::location(self::getRedirect($redirectUri, $state, error: $error));
    }

    /**
     * Sends the browser to the given Url
     * @param string $url
     * @return Response
     */
    private static function location(string $url): Response {
        header("Location: $url");
        return Response::text("")->setStatusCode(302);
    }

    /**
     * Returns a Response with the given data as JSON
     * @param array<string,mixed> $data
     * @param int                 $statusCode Optional.
     * @return Response
     */
    private static function json(array $data, int $statusCode = 200): Response {
        $response = new Response($data, withTokens: false);
        return $response->setStatusCode($statusCode);
    }

    /**
     * Returns a Response with the given Error of the OAuth
     * @param string $error
     * @param int    $statusCode
     * @return Response
     */
    private static function error(string $error, int $statusCode): Response {
        return self::json([ "error" => $error ], $statusCode);
    }
}
