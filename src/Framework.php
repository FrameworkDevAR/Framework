<?php
namespace Framework;

use Framework\IO\Request;
use Framework\IO\Response;
use Framework\Auth\Auth;
use Framework\Intl\NLS;
use Framework\Log\ErrorLog;
use Framework\System\Access;
use Framework\System\Router;
use Framework\Utils\Dictionary;
use Framework\Utils\JSON;
use Framework\Utils\Server;

use Exception;

/**
 * The Framework Service
 */
class Framework {

    private static ?Request  $request  = null;
    private static ?Response $response = null;
    private static string    $route    = "";



    /**
     * Executes the Framework
     * @return bool
     */
    public static function execute(): bool {
        ErrorLog::init();

        // Parse the Request
        $request      = self::getRequest();
        $route        = $request->getString("route");
        $token        = $request->getString("token");
        $accessToken  = $request->getString("xAccessToken");
        $refreshToken = $request->getString("xRefreshToken");
        $langcode     = $request->getString("xLangcode");
        $timezone     = $request->getInt("xTimezone");

        // Remove sensitive data
        $request->remove("route");
        $request->remove("token");
        $request->remove("xAccessToken");
        $request->remove("xRefreshToken");
        $request->remove("xLangcode");
        $request->remove("xTimezone");

        // The Route is required
        if ($route === "") {
            return false;
        }
        self::$route = $route;

        // Try getting the Token from the Header
        if ($token === "") {
            $token = Server::getAuthToken();
        }

        // Validate the API
        if ($token !== "") {
            Auth::validateAPI($token);

        // Validate the Credential
        } elseif ($accessToken !== "" || $refreshToken !== "") {
            Auth::validateCredential(
                $accessToken,
                $refreshToken,
                $langcode,
                $timezone,
            );
        }

        // The API can send the content as a JSON payload
        if (Auth::hasAPI()) {
            $request->addPayload();
        }

        // Perform the Request
        try {
            $response = self::request($route, $request);
            self::output($response->toArray(), $response->getStatusCode());
            return true;
        } catch (Exception $e) {
            http_response_code(400);
            print($e->getMessage());
            return false;
        }
    }

    /**
     * Returns the Route of the Request being executed
     * @return string
     */
    public static function getRoute(): string {
        return self::$route;
    }

    /**
     * Executes an Internal Request
     * @return Dictionary
     */
    public static function executeInternal(): Dictionary {
        ErrorLog::init();
        Auth::validateInternal();
        return Server::getPayload();
    }

    /**
     * Requests an API function
     * @param string  $route
     * @param Request $request
     * @return Response
     */
    private static function request(string $route, Request $request): Response {
        // The Route doesn't exist
        if (!Router::has($route)) {
            return self::errorResponse("GENERAL_ERROR_PATH");
        }

        // Grab the Access Name for the given Route
        $accessName = Router::getAccessName($route);
        $isAPI      = Access::isValidAPI($accessName);

        // The route requires login and the user is Logged Out
        if (Auth::requiresLogin($accessName)) {
            if ($isAPI) {
                return self::errorResponse("GENERAL_ERROR_AUTH", isAPI: true);
            }
            return Response::logout();
        }

        // The Provided Access Name is lower than the Required One
        if (!Auth::grant($accessName)) {
            return self::errorResponse("GENERAL_ERROR_PATH");
        }

        // Perform the Request
        $response = Router::call($route, $request);

        // Add the Tokens, the API doesn't use them
        if (!Auth::hasAPI()) {
            $response->addTokens(Auth::getAccessToken(), Auth::getRefreshToken());
        }
        return $response;
    }

    /**
     * Creates an Error Response using the translated text for the API
     * @param string $error
     * @param bool   $isAPI Optional.
     * @return Response
     */
    private static function errorResponse(string $error, bool $isAPI = false): Response {
        if ($isAPI || Auth::hasAPI()) {
            return Response::result([ "error" => NLS::getString($error) ]);
        }
        return Response::error($error);
    }

    /**
     * Outputs the given data as JSON
     * @param array<int|string,mixed> $data
     * @param int                     $statusCode Optional.
     * @return void
     */
    public static function output(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header("Content-Type: application/json;charset=utf-8");
        print(JSON::encode($data, asPretty: true));
    }



    /**
     * Returns the current Request
     * @return Request
     */
    public static function getRequest(): Request {
        if (self::$request === null) {
            self::$request = new Request(withRequest: true);
        }
        return self::$request;
    }

    /**
     * Stores a Response
     * @param Response|null $response Optional.
     * @return void
     */
    public static function setResponse(?Response $response = null): void {
        self::$response = $response;
    }

    /**
     * Returns the stored Response
     * @return Response|null
     */
    public static function getResponse(): ?Response {
        return self::$response;
    }
}
