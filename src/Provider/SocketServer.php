<?php
namespace Framework\Provider;

use Framework\Analysis\Attr\NotTested;
use Framework\Date\Date;
use Framework\Log\SocketLog;
use Framework\Provider\Curl;
use Framework\Provider\Type\CurlMethod;
use Framework\System\Config;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;

use Firebase\JWT\JWT;

/**
 * The Socket Server
 */
class SocketServer {

    private const Algorithm = "HS256";

    /** @var array<string,bool> */
    private static array $topics = [];

    private static bool $isRegistered = false;

    private static string $sender = "";



    /**
     * Adds the given Topics to the ones sent at the end of the Request
     * @param string ...$topics
     * @return void
     */
    public static function add(string ...$topics): void {
        if (!Config::isSocketActive()) {
            return;
        }

        // The Topics are kept as keys, so a change made several times in one
        // Request is sent once
        foreach ($topics as $topic) {
            if ($topic !== "") {
                self::$topics[$topic] = true;
            }
        }

        // The send waits for the end of the Request, which is the same for the API,
        // the Jobs and the Hooks, and runs after the response was printed
        if (!self::$isRegistered && count(self::$topics) > 0) {
            register_shutdown_function([ self::class, "send" ]);
            self::$isRegistered = true;
        }
    }

    /**
     * Sets the Socket of the App that made the Request, which is given back with its Topics
     * @param string $sender
     * @return void
     */
    public static function setSender(string $sender): void {
        self::$sender = $sender;
    }

    /**
     * Returns the Topics that will be sent and forgets them
     * @return list<string>
     */
    public static function takeTopics(): array {
        $topics       = array_keys(self::$topics);
        self::$topics = [];
        return $topics;
    }

    /**
     * Sends the added Topics to the Socket Server
     * @return void
     */
    #[NotTested("It needs a Socket Server")]
    public static function send(): void {
        $topics = self::takeTopics();
        if (count($topics) === 0) {
            return;
        }

        // With PHP-FPM the response is closed first, so the answer never waits for
        // the Socket Server, which is a request to this same machine anyway
        if (function_exists("fastcgi_finish_request")) {
            fastcgi_finish_request();
        }

        // A failure is not retried, as the polling of the Apps brings the change. The
        // timeout is not of a second, as curl then fails to resolve the host now and then
        $result = new Dictionary(Curl::execute(
            CurlMethod::POST,
            Config::getSocketServer() . "publish",
            params:      [ "topics" => $topics, "sender" => self::$sender ],
            headers:     [ "Authorization" => "Bearer " . Config::getSocketSecret() ],
            jsonBody:    true,
            returnError: true,
            timeout:     2,
        ));

        // It is only counted, to see in the Dashboard what the Socket Server did not take
        if ($result->isEmpty() || $result->has("error")) {
            SocketLog::add($result->getString("error", "No answer"));
        }
    }

    /**
     * Returns the status of the Socket Server, or an empty list when it does not answer
     * @return array<string,mixed>
     */
    #[NotTested("It needs a Socket Server")]
    public static function getStatus(): array {
        $result = Curl::execute(
            CurlMethod::GET,
            Config::getSocketServer() . "status",
            headers:     [ "Authorization" => "Bearer " . Config::getSocketSecret() ],
            returnError: true,
            timeout:     2,
        );
        $status = Arrays::toStringMixedMap($result);
        if (!isset($status["status"])) {
            return [];
        }
        return $status;
    }

    /**
     * Returns a Token to connect to the Socket Server
     * @param string       $app
     * @param int          $id
     * @param list<string> $scopes
     * @param int          $clientID Optional.
     * @return string
     */
    public static function createToken(
        string $app,
        int $id,
        array $scopes,
        int $clientID = 0,
    ): string {
        $time = Date::now()->toTime();
        return JWT::encode([
            "app"      => $app,
            "id"       => $id,
            "clientID" => $clientID,
            "scopes"   => $scopes,
            "iat"      => $time,
            "exp"      => $time + Config::getSocketTokenHours() * 3600,
        ], Config::getSocketSecret(), self::Algorithm);
    }
}
