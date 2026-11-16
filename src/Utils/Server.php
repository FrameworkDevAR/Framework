<?php
// spell-checker: ignore MSIE
namespace Framework\Utils;

use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * The Server Utils
 */
class Server {

    /**
     * Returns true if the given key exists in the $_SERVER
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool {
        return isset($_SERVER[$key]);
    }

    /**
     * Returns the given key from the $_SERVER
     * @param string $key
     * @return string
     */
    public static function getString(string $key): string {
        if (isset($_SERVER[$key])) {
            return Strings::toString($_SERVER[$key]);
        }
        return "";
    }



    /**
     * Returns true if the request method is POST
     * @return bool
     */
    public static function isPostRequest(): bool {
        $method = self::getString("REQUEST_METHOD");
        return Strings::toUpperCase($method) === "POST";
    }

    /**
     * Returns the Authorization Token
     * @return string
     */
    public static function getAuthToken(): string {
        $headers = getallheaders();
        $auth    = Strings::toString($headers["Authorization"] ?? "");
        if ($auth === "") {
            $auth = self::getString("HTTP_AUTHORIZATION");
        }
        return trim(Strings::substringAfter($auth, "Bearer "));
    }

    /**
     * Returns the current Payload
     * @param bool $withRequest Optional.
     * @return Dictionary
     */
    public static function getPayload(bool $withRequest = true): Dictionary {
        $data    = new Dictionary($withRequest ? $_REQUEST : null);
        $payload = file_get_contents("php://input");
        if ($payload !== false && $payload !== "" && JSON::isValid($payload)) {
            $data = JSON::decodeAsDictionary($payload);
        }
        return $data;
    }



    /**
     * Returns true if running on Localhost
     * @param list<string> $whitelist Optional.
     * @return bool
     */
    public static function isLocalHost(array $whitelist = [ "127.0.0.1", "::1" ]): bool {
        return Arrays::contains($whitelist, self::getString("REMOTE_ADDR"));
    }

    /**
     * Returns true if the Host starts with the given prefix
     * @param string $prefix
     * @return bool
     */
    public static function hostStartsWith(string $prefix): bool {
        $host = self::getString("HTTP_HOST");
        return Strings::startsWith($host, $prefix);
    }



    /**
     * Returns the Origin Url
     * @param bool $useForwarded Optional.
     * @return string
     */
    public static function getUrl(bool $useForwarded = false): string {
        if (self::getString("HTTP_HOST") === "") {
            return "";
        }

        $ssl         = self::getString("HTTPS") === "on";
        $serverProto = self::getString("SERVER_PROTOCOL");
        $serverProto = Strings::toLowerCase($serverProto);
        $http        = Strings::substringBefore($serverProto, "/");
        $protocol    = $http . ($ssl ? "s" : "");

        $port        = self::getString("SERVER_PORT");
        $port        = (!$ssl && $port === "80") || ($ssl && $port === "443") ? "" : ":$port";

        $forwardHost = self::getString("HTTP_X_FORWARDED_HOST");
        $httpHost    = self::getString("HTTP_HOST");

        $host = "";
        if ($useForwarded && $forwardHost !== "") {
            $host = $forwardHost;
        } else {
            $host = $httpHost;
        }

        return "$protocol://$host";
    }

    /**
     * Returns the Full Url
     * @param bool $useForwarded Optional.
     * @return string
     */
    public static function getFullUrl(bool $useForwarded = false): string {
        return self::getUrl($useForwarded) . self::getString("REQUEST_URI");
    }

    /**
     * Returns the Path that was requested, without the query
     * @return string
     */
    public static function getPath(): string {
        return Strings::substringBefore(self::getString("REQUEST_URI"), "?");
    }

    /**
     * Returns the user IP
     * @return string
     */
    public static function getIP(): string {
        if (self::has("HTTP_X_FORWARDED_FOR")) {
            $ip = self::getString("HTTP_X_FORWARDED_FOR");
        } elseif (self::has("HTTP_CLIENT_IP")) {
            $ip = self::getString("HTTP_CLIENT_IP");
        } elseif (self::has("REMOTE_ADDR")) {
            $ip = self::getString("REMOTE_ADDR");
        } elseif (getenv("HTTP_X_FORWARDED_FOR") !== false) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } elseif (getenv("HTTP_CLIENT_IP") !== false) {
            $ip = getenv("HTTP_CLIENT_IP");
        } else {
            $ip = getenv("REMOTE_ADDR");
        }
        return Strings::toString($ip);
    }

    /**
     * Returns the User Agent
     * @return string
     */
    public static function getUserAgent(): string {
        return self::getString("HTTP_USER_AGENT");
    }

    /**
     * Returns the Platform from a User Agent
     * @param string $userAgent
     * @return string
     */
    public static function getPlatform(string $userAgent): string {
        $result = [];

        if (Strings::contains($userAgent, "Macintosh")) {
            $result[] = "MacOS";
        } elseif (Strings::contains($userAgent, "Windows")) {
            $result[] = "Windows";
        } elseif (Strings::contains($userAgent, "iPhone")) {
            $result[] = "iPhone";
        } elseif (Strings::contains($userAgent, "iPad")) {
            $result[] = "iPad";
        } elseif (Strings::contains($userAgent, "Android")) {
            $result[] = "Android";
        }

        if (Strings::contains($userAgent, "Firefox")) {
            $result[] = "FireFox";
        } elseif (Strings::contains($userAgent, "MSIE") ||
            Strings::contains($userAgent, "Trident")
        ) {
            $result[] = "IE";
        } elseif (Strings::contains($userAgent, "Chrome")) {
            $result[] = "Chrome";
        } elseif (Strings::contains($userAgent, "Safari")) {
            $result[] = "Safari";
        } elseif (Strings::contains($userAgent, "AIR")) {
            $result[] = "Air";
        } elseif (Strings::contains($userAgent, "Fluid")) {
            $result[] = "Fluid";
        }

        if (count($result) === 0) {
            $result[] = "Unknown";
        }
        return Strings::join($result, " ");
    }
}
