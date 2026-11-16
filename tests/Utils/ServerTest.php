<?php
namespace Tests\Utils;

use Framework\Utils\Server;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ServerTest extends TestCase {

    protected function tearDown(): void {
        // clear any globals we modified
        $_SERVER = [];
        $_REQUEST = [];

        if (isset($GLOBALS["test_getallheaders"])) {
            unset($GLOBALS["test_getallheaders"]);
        }
        if (isset($GLOBALS["test_file_get_contents"])) {
            unset($GLOBALS["test_file_get_contents"]);
        }
    }


    #[DataProvider("providerHas")]
    public function testHas(string $key, bool $expected): void {
        $_SERVER["SOME_KEY"] = "value";
        $this->assertEquals($expected, Server::has($key));
    }

    public static function providerHas(): array {
        return [
            "key exists"     => [ "SOME_KEY", true ],
            "key not exists" => [ "NOPE", false ],
        ];
    }


    #[DataProvider("providerGetString")]
    public function testGetString(string $key, string $expected): void {
        $_SERVER["SOME_KEY"] = "value";
        $this->assertEquals($expected, Server::getString($key));
    }

    public static function providerGetString(): array {
        return [
            "key exists"     => [ "SOME_KEY", "value" ],
            "key not exists" => [ "NOPE", "" ],
        ];
    }


    #[DataProvider("providerGetPath")]
    public function testGetPath(string $uri, string $expected): void {
        $_SERVER["REQUEST_URI"] = $uri;
        $this->assertEquals($expected, Server::getPath());
    }

    public static function providerGetPath(): array {
        return [
            "a path"            => [ "/mcp", "/mcp" ],
            "a path with query" => [ "/api/task?id=5", "/api/task" ],
            "none"              => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsPostRequest")]
    public function testIsPostRequest(string $method, bool $expected): void {
        $_SERVER["REQUEST_METHOD"] = $method;
        $this->assertEquals($expected, Server::isPostRequest());
    }

    public static function providerIsPostRequest(): array {
        return [
            "no method"    => [ "", false ],
            "post request" => [ "POST", true ],
            "get request"  => [ "GET", false ],
        ];
    }


    #[DataProvider("providerGetAuthToken")]
    public function testGetAuthToken(array $serverVars, ?array $headers, string $expected): void {
        $_SERVER = $serverVars;
        if ($headers !== null) {
            global $test_getallheaders;
            $test_getallheaders = $headers;
        }
        $this->assertEquals($expected, Server::getAuthToken());
    }

    public static function providerGetAuthToken(): array {
        return [
            "empty"                => [ [], null, "" ],
            "http authorization"   => [ [ "HTTP_AUTHORIZATION" => "Bearer xyz789" ], null, "xyz789" ],
            "authorization header" => [ [ "HTTP_AUTHORIZATION" => "Bearer xyz789" ], [ "Authorization" => "Bearer abc123" ], "abc123" ],
        ];
    }


    #[DataProvider("providerGetPayload")]
    public function testGetPayload(array $request, ?string $input, bool $withRequest, array $expected): void {
        $_REQUEST = $request;
        if ($input !== null) {
            global $test_file_get_contents;
            $test_file_get_contents = $input;
        }
        $payload = Server::getPayload($withRequest);
        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $payload->getString($key));
        }
    }

    public static function providerGetPayload(): array {
        return [
            "request data"           => [ [ "a" => "1", "b" => "2" ], null, true, [ "a" => "1", "b" => "2" ] ],
            "json input"             => [ [], '{"x":"y","num":123}', true, [ "x" => "y", "num" => "123" ] ],
            // Without the request, the $_REQUEST data is ignored
            "without request"        => [ [ "a" => "1", "b" => "2" ], null, false, [ "a" => "", "b" => "" ] ],
            // The JSON payload still applies when the request is excluded
            "without request json"   => [ [ "a" => "1" ], '{"x":"y"}', false, [ "x" => "y", "a" => "" ] ],
        ];
    }


    #[DataProvider("providerIsLocalHost")]
    public function testIsLocalHost(?string $remoteAddr, ?array $allowedIps, bool $expected): void {
        if ($remoteAddr !== null) {
            $_SERVER["REMOTE_ADDR"] = $remoteAddr;
        }

        if ($allowedIps === null) {
            $this->assertEquals($expected, Server::isLocalHost());
        } else {
            $this->assertEquals($expected, Server::isLocalHost($allowedIps));
        }
    }

    public static function providerIsLocalHost(): array {
        return [
            "no remote addr"           => [ null, null, false ],
            "localhost default"        => [ "127.0.0.1", null, true ],
            "localhost in allowed"     => [ "127.0.0.1", [ "127.0.0.1" ], true ],
            "localhost not in allowed" => [ "127.0.0.1", [ "1.2.3.4" ], false ],
        ];
    }


    #[DataProvider("providerHostStartsWith")]
    public function testHostStartsWith(string $host, string $prefix, bool $expected): void {
        if ($host !== "") {
            $_SERVER["HTTP_HOST"] = $host;
        }
        $this->assertEquals($expected, Server::hostStartsWith($prefix));
    }

    public static function providerHostStartsWith(): array {
        return [
            "no host"       => [ "", "api.", false ],
            "host matches"  => [ "api.example.com", "api.", true ],
            "host no match" => [ "api.example.com", "www.", false ],
        ];
    }


    #[DataProvider("providerGetUrlAndFullUrl")]
    public function testGetUrlAndFullUrl(array $serverVars, bool $useForwarded, string $expectedUrl, string $expectedFullUrl): void {
        $_SERVER = $serverVars;
        $this->assertEquals($expectedUrl, Server::getUrl($useForwarded));
        $this->assertEquals($expectedFullUrl, Server::getFullUrl($useForwarded));
    }

    public static function providerGetUrlAndFullUrl(): array {
        return [
            "empty server" => [ [], false, "", "" ],
            "http request" => [
                [ "HTTP_HOST" => "example.com", "SERVER_PROTOCOL" => "HTTP/1.1", "HTTPS" => "off", "SERVER_PORT" => "80", "REQUEST_URI" => "/path?x=1" ],
                false,
                "http://example.com",
                "http://example.com/path?x=1"
            ],
            "forwarded host" => [
                [ "HTTP_HOST" => "example.com", "SERVER_PROTOCOL" => "HTTP/1.1", "HTTPS" => "off", "SERVER_PORT" => "80", "REQUEST_URI" => "/path?x=1", "HTTP_X_FORWARDED_HOST" => "forwarded.example" ],
                true,
                "http://forwarded.example",
                "http://forwarded.example/path?x=1"
            ],
        ];
    }


    #[DataProvider("providerGetIP")]
    public function testGetIP(array $serverVars, ?array $envVars, string $expected): void {
        $_SERVER = $serverVars;
        if ($envVars !== null) {
            foreach ($envVars as $key => $value) {
                putenv("$key=$value");
            }
        }
        $this->assertEquals($expected, Server::getIP());
        if ($envVars !== null) {
            foreach ($envVars as $key => $value) {
                putenv($key);
            }
        }
    }

    public static function providerGetIP(): array {
        return [
            "http x forwarded for"     => [ [ "HTTP_X_FORWARDED_FOR" => "10.0.0.1" ], null, "10.0.0.1" ],
            "http client ip"           => [ [ "HTTP_CLIENT_IP" => "192.0.2.4" ], null, "192.0.2.4" ],
            "remote addr"              => [ [ "REMOTE_ADDR" => "192.0.2.5" ], null, "192.0.2.5" ],
            "env http x forwarded for" => [ [], [ "HTTP_X_FORWARDED_FOR" => "10.1.1.2" ], "10.1.1.2" ],
            "env http client ip"       => [ [], [ "HTTP_CLIENT_IP" => "192.0.2.9" ], "192.0.2.9" ],
            "env remote addr"          => [ [], [ "REMOTE_ADDR" => "192.0.2.10" ], "192.0.2.10" ],
        ];
    }


    #[DataProvider("providerGetUserAgent")]
    public function testGetUserAgent(?string $userAgent, string $expected): void {
        if ($userAgent !== null) {
            $_SERVER["HTTP_USER_AGENT"] = $userAgent;
        }
        $this->assertEquals($expected, Server::getUserAgent());
    }

    public static function providerGetUserAgent(): array {
        return [
            "no user agent"   => [ null, "" ],
            "with user agent" => [ "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/90.0", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/90.0" ],
        ];
    }


    #[DataProvider("providerGetPlatform")]
    public function testGetPlatform(string $ua, string $expected): void {
        $this->assertEquals($expected, Server::getPlatform($ua));
    }

    public static function providerGetPlatform(): array {
        return [
            "empty ua"      => [ "", "Unknown" ],
            "macos firefox" => [ "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Firefox/88.0", "MacOS FireFox" ],
            "windows ie"    => [ "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Trident/7.0", "Windows IE" ],
            "iphone safari" => [ "Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Safari/604.1", "iPhone Safari" ],
            "ipad chrome"   => [ "Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15 Chrome/90.0", "iPad Chrome" ],
            "android fluid" => [ "Mozilla/5.0 (Android 10; Mobile; rv:88.0) Gecko/88.0 Fluid/88.0", "Android Fluid" ],
            "windows air"   => [ "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 AIR/33.0", "Windows Air" ],
            "unknown agent" => [ "SomeUnknownAgent/1.0", "Unknown" ],
        ];
    }
}
