<?php
namespace Framework\Utils;

use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * The URL Utils
 */
class URL {

    /**
     * Returns true if is a valid URL
     * @param string $url
     * @return bool
     */
    public static function isValid(string $url): bool {
        return Strings::startsWith($url, "http://", "https://");
    }

    /**
     * Returns the URL created with the given parts
     * @param int|string ...$urlParts
     * @return string
     */
    public static function parseUrl(int|string ...$urlParts): string {
        $result   = Strings::join($urlParts, "/");
        $protocol = "";

        // Take out the protocol, so its slashes are not removed
        foreach ([ "http://", "https://" ] as $prefix) {
            if (Strings::startsWith($result, $prefix)) {
                $protocol = $prefix;
                $result   = Strings::substringAfter($result, $prefix);
                break;
            }
        }

        // Remove the double slashes and the last one
        while (Strings::contains($result, "//")) {
            $result = Strings::replace($result, "//", "/");
        }
        $result = Strings::stripEnd($result, "/");

        return "{$protocol}{$result}";
    }

    /**
     * Returns the host of the given URL
     * @param string $url
     * @return string
     */
    public static function getHost(string $url): string {
        if ($url === "") {
            return "";
        }

        $result = parse_url($url, PHP_URL_HOST);
        return Strings::toString($result);
    }

    /**
     * Returns the origin of the given URL, which is its scheme, host and port
     * @param string $url
     * @return string
     */
    public static function getOrigin(string $url): string {
        $scheme = Strings::toString(parse_url($url, PHP_URL_SCHEME));
        $host   = self::getHost($url);
        $port   = parse_url($url, PHP_URL_PORT);
        if ($scheme === "" || $host === "") {
            return "";
        }
        return "{$scheme}://{$host}" . (is_int($port) ? ":{$port}" : "");
    }



    /**
     * Returns the parts of the path of the given URL
     * @param string $url
     * @return list<string>
     */
    public static function getPathParts(string $url): array {
        $path = Strings::toString(parse_url($url, PHP_URL_PATH));
        if ($path === "") {
            return [];
        }

        $result = [];
        foreach (Strings::split($path, "/") as $part) {
            if ($part !== "") {
                $result[] = $part;
            }
        }
        return $result;
    }

    /**
     * Returns the part of the path of the given URL that follows the given one
     * @param string $url
     * @param string $part
     * @return string
     */
    public static function getPartAfter(string $url, string $part): string {
        $parts = self::getPathParts($url);
        foreach ($parts as $index => $value) {
            if (Strings::isEqual($value, $part) && isset($parts[$index + 1])) {
                return $parts[$index + 1];
            }
        }
        return "";
    }

    /**
     * Returns the first part of the path of the given URL
     * @param string $url
     * @return string
     */
    public static function getFirstPart(string $url): string {
        $parts = self::getPathParts($url);
        return $parts[0] ?? "";
    }

    /**
     * Returns the last part of the path of the given URL, skipping the given ones
     * @param string $url
     * @param string ...$skip
     * @return string
     */
    public static function getLastPart(string $url, string ...$skip): string {
        $parts = self::getPathParts($url);
        while (count($parts) > 0) {
            $last = $parts[count($parts) - 1];
            if (!Arrays::contains($skip, $last)) {
                return $last;
            }
            array_pop($parts);
        }
        return "";
    }

    /**
     * Returns the value of the given param in the query of the given URL
     * @param string $url
     * @param string $name
     * @return string
     */
    public static function getParam(string $url, string $name): string {
        $query = Strings::toString(parse_url($url, PHP_URL_QUERY));
        if ($query === "") {
            return "";
        }

        $params = [];
        parse_str($query, $params);
        if (!isset($params[$name]) || !is_scalar($params[$name])) {
            return "";
        }
        return Strings::toString($params[$name]);
    }



    /**
     * Returns true if the given Domain is valid
     * @param string $domain
     * @return bool
     */
    public static function isValidDomain(string $domain): bool {
        return Strings::match($domain, '/^([a-z]+\.)?([a-z0-9ñ]([-a-z0-9ñ]*[a-z0-9ñ])?)\.[a-z]{2,5}(\.[a-z]{2})?$/i');  // phpcs:ignore
    }

    /**
     * Returns the Domain of the given URL
     * @param string $url
     * @return string
     */
    public static function getDomain(string $url): string {
        $domain = Strings::toLowerCase($url);
        if (!Strings::startsWith($domain, "http://", "https://")) {
            $domain = "http://$domain";
        }

        $host = self::getHost($domain);
        if ($host !== "") {
            return Strings::replace($host, "www.", "");
        }
        return "";
    }

    /**
     * Returns the extension of the given domain (without the dot)
     * @param string $url
     * @return string
     */
    public static function getDomainExtension(string $url): string {
        $domain = self::getDomain($url);
        return Strings::substringAfter($domain, ".");
    }

    /**
     * Returns true if the given domain or www.domain is delegated
     * @param string $domain
     * @param string $serverIP Optional.
     * @return bool
     */
    public static function isDelegated(string $domain, string $serverIP = ""): bool {
        if (self::verifyDelegation($domain, $serverIP)) {
            return true;
        }
        return self::verifyDelegation("www.$domain", $serverIP);
    }

    /**
     * Returns true if the given domain is delegated
     * @param string $domain
     * @param string $serverIP Optional.
     * @return bool
     */
    public static function verifyDelegation(string $domain, string $serverIP = ""): bool {
        $host = gethostbyname($domain);
        if ($serverIP !== "") {
            return $host !== "" && $host === $serverIP;
        }
        return $host !== "" && $host !== $domain;
    }



    /**
     * Returns true if the given string is a valid slug
     * @param string $string
     * @return bool
     */
    public static function isValidSlug(string $string): bool {
        return Strings::match($string, '/^[a-z0-9\-]+$/');
    }

    /**
     * Returns a Slug from the given string
     * @param string $string
     * @return string
     */
    public static function toSlug(string $string): string {
        $result = Strings::toString($string);
        $result = Strings::sanitize($result, lowercase: true, anal: true);
        $result = Strings::replace($result, "---", "-");
        $result = Strings::replace($result, "--", "-");
        return $result;
    }



    /**
     * Encodes the given URL
     * @param string $url
     * @return string
     */
    public static function encode(string $url): string {
        return Strings::replaceCallback(
            string:   $url,
            pattern:  "/[\ \"<>`\\x{0080}-\\x{FFFF}]+/u",
            callback: function (array $match) {
                $value = Strings::toString($match[0] ?? "");
                return rawurlencode($value);
            },
        );
    }

    /**
     * Encodes the URL spaces
     * @param string $url
     * @return string
     */
    public static function encodeSpaces(string $url): string {
        return str_replace(" ", "%20", $url);
    }

    /**
     * Decodes the URL spaces
     * @param string $url
     * @return string
     */
    public static function decodeSpaces(string $url): string {
        return str_replace("%20", " ", $url);
    }



    /**
     * Adds Params to a URL
     * @param string                   $url
     * @param array<string,mixed>|null $params Optional.
     * @return string
     */
    public static function addParams(string $url, ?array $params = null): string {
        $parsedParams = self::parseParams($params);
        if ($parsedParams !== "") {
            $prefix = Strings::contains($url, "?") ? "&" : "?";
            $url   .= $prefix . $parsedParams;
        }
        return $url;
    }

    /**
     * Parses the URL Params
     * @param array<string,mixed>|null $params Optional.
     * @return string
     */
    public static function parseParams(?array $params = null): string {
        if ($params === null || count($params) === 0) {
            return "";
        }

        $content = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $content[] = "$key=" . urlencode(JSON::encode($value));
            } elseif (is_bool($value)) {
                $content[] = "$key=" . ($value ? "true" : "false");
            } elseif ($value !== null) {
                $content[] = "$key=" . urlencode(Strings::toString($value));
            }
        }

        return Strings::join($content, "&");
    }

    /**
     * Replaces the URLs in an HTML String
     * @param string $string
     * @param string $url
     * @return string
     */
    public static function replaceInHtml(string $string, string $url): string {
        return Strings::replaceCallback(
            string:   $string,
            pattern:  '/<(img|audio|video)([^>]*?)src=["\']((?!https?:\/\/|\/\/|data:)[^"\']+)["\']([^>]*?)>/i',  // phpcs:ignore,
            callback: function (array $matches) use ($url) {
                $tag          = Strings::toString($matches[1] ?? "");
                $beforeSrc    = Strings::toString($matches[2] ?? "");
                $relativePath = Strings::toString($matches[3] ?? "");
                $afterSrc     = Strings::toString($matches[4] ?? "");
                $absolutePath = "$url/{$relativePath}";
                return "<$tag{$beforeSrc}src=\"$absolutePath\"{$afterSrc}>";
            },
        );
    }
}
