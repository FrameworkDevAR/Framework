<?php
namespace Tests\Utils;

use Framework\Date\Date;
use Framework\Enum\Enum;
use Framework\Enum\IsEnum;
use Framework\Utils\URL;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

enum TestUrlEnum implements Enum {
    use IsEnum;

    case None;
    case Value;
}

class URLTest extends TestCase {

    #[DataProvider("providerIsValidUrl")]
    public function testIsValid(string $url, bool $expected): void {
        $this->assertEquals($expected, URL::isValid($url));
    }

    public static function providerIsValidUrl(): array {
        return [
            "http url"         => [ "http://example.com", true ],
            "https url"        => [ "https://example.com", true ],
            "https path query" => [ "https://sub.example.co.uk/path?query=1#frag", true ],
            "https port"       => [ "https://example.com:8080", true ],
            "ip address"       => [ "https://127.0.0.1", true ],
            "ftp protocol"     => [ "ftp://example.com", false ],
            "missing protocol" => [ "www.example.com", false ],
            "empty string"     => [ "", false ],
            "invalid text"     => [ "not a url", false ],
        ];
    }


    #[DataProvider("providerParseUrl")]
    public function testParseUrl(array $pathParts, string $expected): void {
        $this->assertSame($expected, URL::parseUrl(...$pathParts));
    }

    public static function providerParseUrl(): array {
        return [
            "http"  => [ [ "http://example.com//files/", "demo.txt" ], "http://example.com/files/demo.txt" ],
            "https" => [ [ "https://example.com//files/", "demo.txt" ], "https://example.com/files/demo.txt" ],
            "path"  => [ [ "/tmp//demo/", "demo.txt" ], "/tmp/demo/demo.txt" ],
            "empty" => [ [], "" ],
        ];
    }


    #[DataProvider("providerGetHost")]
    public function testGetHost(string $url, string $expected): void {
        $this->assertEquals($expected, URL::getHost($url));
    }

    public static function providerGetHost(): array {
        return [
            "http url"            => [ "http://example.com/path", "example.com" ],
            "https url subdomain" => [ "https://www.example.co.uk/some/page", "www.example.co.uk" ],
            "empty string"        => [ "", "" ],
            "invalid url"         => [ "not a url", "" ],
            "missing protocol 1"  => [ "example.com/path", "" ],
            "missing protocol 2"  => [ "www.example.com/path", "" ],
        ];
    }


    #[DataProvider("providerGetOrigin")]
    public function testGetOrigin(string $url, string $expected): void {
        $this->assertSame($expected, URL::getOrigin($url));
    }

    public static function providerGetOrigin(): array {
        return [
            "with a path"   => [ "https://app.example.com/login?x=1", "https://app.example.com" ],
            "with a port"   => [ "http://localhost:4100/", "http://localhost:4100" ],
            "only the host" => [ "https://example.com", "https://example.com" ],
            "no scheme"     => [ "example.com/path", "" ],
            "empty"         => [ "", "" ],
        ];
    }


    #[DataProvider("providerGetPathParts")]
    public function testGetPathParts(string $url, array $expected): void {
        $this->assertSame($expected, URL::getPathParts($url));
    }

    public static function providerGetPathParts(): array {
        return [
            "the parts of the path"    => [ "https://example.com/a/b/c", [ "a", "b", "c" ] ],
            "the empty ones are gone"  => [ "https://example.com/a//b/", [ "a", "b" ] ],
            "the query is not a part"  => [ "https://example.com/a?x=1#f", [ "a" ] ],
            "a path without a host"    => [ "/a/b", [ "a", "b" ] ],
            "a host without a path"    => [ "https://example.com", [] ],
            "a path of only the slash" => [ "https://example.com/", [] ],
            "an empty string"          => [ "", [] ],
        ];
    }


    // The part is matched the way Strings does it, which ignores the case
    #[DataProvider("providerGetPartAfter")]
    public function testGetPartAfter(string $url, string $part, string $expected): void {
        $this->assertSame($expected, URL::getPartAfter($url, $part));
    }

    public static function providerGetPartAfter(): array {
        return [
            "the one that follows"    => [ "https://example.com/users/42/edit", "users", "42" ],
            "the first one that does" => [ "https://example.com/a/b/a/c", "a", "b" ],
            "another case"            => [ "https://example.com/Users/42", "users", "42" ],
            "nothing follows it"      => [ "https://example.com/users/42", "42", "" ],
            "the part is not there"   => [ "https://example.com/users/42", "missing", "" ],
            "there are no parts"      => [ "https://example.com", "a", "" ],
        ];
    }


    #[DataProvider("providerGetFirstPart")]
    public function testGetFirstPart(string $url, string $expected): void {
        $this->assertSame($expected, URL::getFirstPart($url));
    }

    public static function providerGetFirstPart(): array {
        return [
            "the first part"     => [ "https://example.com/a/b", "a" ],
            "there are no parts" => [ "https://example.com", "" ],
            "only a query"       => [ "https://example.com/?x=1", "" ],
        ];
    }


    // Every skipped part is dropped in turn, so the answer is the last one left
    #[DataProvider("providerGetLastPart")]
    public function testGetLastPart(array $args, string $expected): void {
        $this->assertSame($expected, URL::getLastPart(...$args));
    }

    public static function providerGetLastPart(): array {
        return [
            "the last part"        => [ [ "https://example.com/a/b/c" ], "c" ],
            "the last one kept"    => [ [ "https://example.com/a/b/edit", "edit" ], "b" ],
            "two of them skipped"  => [ [ "https://example.com/a/edit/new", "edit", "new" ], "a" ],
            "another case"         => [ [ "https://example.com/a/EDIT", "edit" ], "a" ],
            "every part is queued" => [ [ "https://example.com/edit", "edit" ], "" ],
            "there are no parts"   => [ [ "https://example.com" ], "" ],
        ];
    }


    #[DataProvider("providerGetParam")]
    public function testGetParam(string $url, string $name, string $expected): void {
        $this->assertSame($expected, URL::getParam($url, $name));
    }

    public static function providerGetParam(): array {
        return [
            "the value of the param" => [ "https://example.com/a?x=1&y=2", "x", "1" ],
            "the value is decoded"   => [ "https://example.com/a?x=a%20b", "x", "a b" ],
            "the fragment is not it" => [ "https://example.com/a?x=1#frag", "x", "1" ],
            "another param"          => [ "https://example.com/a?x=1", "z", "" ],
            "there is no query"      => [ "https://example.com/a", "x", "" ],
            "the value is empty"     => [ "https://example.com/a?x=", "x", "" ],
            "the value is a list"    => [ "https://example.com/a?x[]=1&x[]=2", "x", "" ],
            "an empty string"        => [ "", "x", "" ],
        ];
    }


    #[DataProvider("providerIsValidDomain")]
    public function testIsValidDomain(string $domain, bool $expected): void {
        $this->assertEquals($expected, URL::isValidDomain($domain));
    }

    public static function providerIsValidDomain(): array {
        return [
            "valid domain"            => [ "example.com", true ],
            "valid subdomain"         => [ "sub.example.co.uk", true ],
            "valid hyphenated"        => [ "ex-ample.com", true ],
            "invalid spaces"          => [ "not valid", false ],
            "invalid double dot"      => [ "example..com", false ],
            "invalid leading hyphen"  => [ "-example.com", false ],
            "invalid trailing hyphen" => [ "example.com-", false ],
            "invalid empty"           => [ "", false ],
        ];
    }


    #[DataProvider("providerGetDomain")]
    public function testGetDomain(string $input, string $expected): void {
        $this->assertEquals($expected, URL::getDomain($input));
    }

    public static function providerGetDomain(): array {
        return [
            "simple domain"           => [ "Example.Com", "example.com" ],
            "www prefix"              => [ "www.example.com", "example.com" ],
            "uppercase"               => [ "WWW.EXAMPLE.ORG", "example.org" ],
            "http www"                => [ "http://www.Example.Com", "example.com" ],
            "https www"               => [ "https://WWW.EXAMPLE.COM", "example.com" ],
            "https with path query"   => [ "https://WWW.EXAMPLE.COM/path?query=string", "example.com" ],
            "subdomain"               => [ "Sub.Example.Co.UK", "sub.example.co.uk" ],
            "subdomain with www"      => [ "www.Sub.Example.Co.UK", "sub.example.co.uk" ],
            "subdomain http with www" => [ "http://www.Sub.Example.Co.UK", "sub.example.co.uk" ],
            "invalid input"           => [ "not a domain", "not a domain" ],
            "empty string"            => [ "", "" ],
        ];
    }


    #[DataProvider("providerGetDomainExtension")]
    public function testGetDomainExtension(string $input, string $expected): void {
        $this->assertEquals($expected, URL::getDomainExtension($input));
    }

    public static function providerGetDomainExtension(): array {
        return [
            "simple domain"         => [ "example.com", "com" ],
            "subdomain co uk"       => [ "sub.example.co.uk", "uk" ],
            "http subdomain co uk"  => [ "http://sub.example.co.uk", "uk" ],
            "https subdomain co uk" => [ "https://sub.example.co.uk", "uk" ],
            "https with path query" => [ "https://sub.example.co.uk/path?query=string", "uk" ],
            "localhost"             => [ "localhost", "localhost" ],
            "empty string"          => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsDelegated")]
    public function testIsDelegated(string $host, string $serverIp, bool $expected): void {
        $this->assertEquals($expected, URL::isDelegated($host, $serverIp));
    }

    public static function providerIsDelegated(): array {
        $hostIp = gethostbyname("localhost");
        return [
            "localhost no ip"      => [ "localhost", "", true ],
            "localhost correct ip" => [ "localhost", $hostIp, true ],
            "localhost wrong ip"   => [ "localhost", "1.2.3.4", false ],
            "invalid domain"       => [ "no-such-host-example.invalid", "", false ],
        ];
    }


    #[DataProvider("providerVerifyDelegation")]
    public function testVerifyDelegation(string $host, string $serverIp, bool $expected): void {
        $this->assertEquals($expected, URL::verifyDelegation($host, $serverIp));
    }

    public static function providerVerifyDelegation(): array {
        $hostIp = gethostbyname("localhost");
        return [
            "localhost no ip"      => [ "localhost", "", true ],
            "localhost correct ip" => [ "localhost", $hostIp, true ],
            "localhost wrong ip"   => [ "localhost", "1.2.3.4", false ],
            "invalid domain"       => [ "no-such-host-example.invalid", "", false ],
            "empty string"         => [ "", "", false ],
        ];
    }


    #[DataProvider("providerIsValidSlug")]
    public function testIsValidSlug(string $slug, bool $expected): void {
        $this->assertEquals($expected, URL::isValidSlug($slug));
    }

    public static function providerIsValidSlug(): array {
        return [
            "basic slug"             => [ "a-slug-1", true ],
            "single char"            => [ "a", true ],
            "empty string"           => [ "", false ],
            "invalid underscore"     => [ "a_slug", false ],
            "invalid spaces special" => [ "Invalid Slug!", false ],
        ];
    }


    #[DataProvider("providerToSlug")]
    public function testToSlug(string $input, string $expected): void {
        $this->assertEquals($expected, URL::toSlug($input));
    }

    public static function providerToSlug(): array {
        return [
            "lowercase hyphen"   => [ "A-Slug", "a-slug" ],
            "spaces and special" => [ "A Slug!!", "a-slug" ],
            "multiple spaces"    => [ "A  Slug !!", "a-slug" ],
            "simple word"        => [ "Simple", "simple" ],
            "empty string"       => [ "", "" ],
        ];
    }


    #[DataProvider("providerEncode")]
    public function testEncode(string $input, string $expectedFragment): void {
        $encoded = URL::encode($input);
        $this->assertStringContainsString($expectedFragment, $encoded);
    }

    public static function providerEncode(): array {
        return [
            "spaces encoded"           => [ "a b", "%20" ],
            "complex no raw spaces"    => [ "a b/c?d=e&f=g", "%20" ],
            "non ascii utf8"           => [ "mañana", "%C3%B1" ],
            "already encoded retained" => [ "a%20b", "%20" ],
        ];
    }


    #[DataProvider("providerEncodeSpaces")]
    public function testEncodeSpaces(string $input, string $expected): void {
        $this->assertEquals($expected, URL::encodeSpaces($input));
    }

    public static function providerEncodeSpaces(): array {
        return [
            "spaces single"   => [ "a b", "a%20b" ],
            "spaces multiple" => [ "a  b", "a%20%20b" ],
            "no spaces"       => [ "a", "a" ],
            "forward slash"   => [ "a/b", "a/b" ],
            "plus sign"       => [ "a+b", "a+b" ],
        ];
    }


    #[DataProvider("providerDecodeSpaces")]
    public function testDecodeSpaces(string $input, string $expected): void {
        $this->assertEquals($expected, URL::decodeSpaces($input));
    }

    public static function providerDecodeSpaces(): array {
        return [
            "encoded spaces single"   => [ "a%20b", "a b" ],
            "encoded spaces multiple" => [ "a%20%20b", "a  b" ],
            "no encoded spaces"       => [ "a", "a" ],
            "forward slash"           => [ "a/b", "a/b" ],
            "plus sign"               => [ "a+b", "a+b" ],
        ];
    }


    #[DataProvider("providerAddParams")]
    public function testAddParams(string $path, array|null $params, string $expected): void {
        $result = URL::addParams($path, $params);
        $this->assertEquals($expected, $result);
    }

    public static function providerAddParams(): array {
        return [
            "basic"    => [ "/path", [ "a" => 1, "b" => 2 ], "/path?a=1&b=2" ],
            "existing" => [ "/path?existing=1", [ "a" => 2 ], "/path?existing=1&a=2" ],
            "spaced"   => [ "/path", [ "q" => "a b" ], "/path?q=a+b" ],
            "boolean"  => [ "/path", [ "flag" => true ], "/path?flag=true" ],
            "numeric"  => [ "/path", [ "num" => 0 ], "/path?num=0" ],
            "enum"     => [ "/path", [ "e" => TestUrlEnum::Value ], "/path?e=Value" ],
            "null val" => [ "/path", [ "n" => null ], "/path" ],
            "null"     => [ "/path", null, "/path" ],
            "empty"    => [ "/path", [], "/path" ],
        ];
    }


    #[DataProvider("providerParseParams")]
    public function testParseParams(array|null $params, string $expected): void {
        $result = URL::parseParams($params);
        $this->assertEquals($expected, $result);
    }

    public static function providerParseParams(): array {
        return [
            "string"   => [ [ "a" => "1", "b" => "2" ], "a=1&b=2" ],
            "numeric"  => [ [ "n" => 123 ], "n=123" ],
            "boolean"  => [ [ "t" => true ], "t=true" ],
            "array"    => [ [ "a" => [ "k" => "v" ]], "a=%7B%22k%22%3A%22v%22%7D" ],
            "spaced"   => [ [ "q" => "a b" ], "q=a+b" ],
            "enum"     => [ [ "e" => TestUrlEnum::Value ], "e=Value" ],
            "date"     => [ [ "d" => Date::create("2024-01-01") ], "d=2024-01-01+00%3A00%3A00" ],
            "null val" => [ [ "n" => null, "m" => "12" ], "m=12" ],
            "null"     => [ null, "" ],
            "empty"    => [ [], "" ],
        ];
    }


    #[DataProvider("providerReplaceInHtml")]
    public function testReplaceInHtml(string $html, string $baseUrl, string $expectedFragment): void {
        $out = URL::replaceInHtml($html, $baseUrl);
        $this->assertStringContainsString($expectedFragment, $out);
    }

    public static function providerReplaceInHtml(): array {
        return [
            "image tag"          => [
                "<img src=\"img/pic.jpg\">",
                "http://cdn",
                "http://cdn/",
            ],
            "audio tag"          => [
                "<audio src=\"audio/song.mp3\"></audio>",
                "http://cdn",
                "http://cdn/audio/song.mp3",
            ],
            "video tag"          => [
                "<video src=\"video/clip.mp4\"></video>",
                "http://cdn",
                "http://cdn/video/clip.mp4",
            ],
            "absolute http url"  => [
                "<img src=\"http://example.com/img.jpg\">",
                "http://cdn",
                "http://example.com/img.jpg",
            ],
            "absolute https url" => [
                "<video src=\"https://videos.example.org/clip.mp4\"></video>",
                "http://cdn",
                "https://videos.example.org/clip.mp4",
            ],
            "data uri"           => [
                "<img src=\"data:image/png;base64,iVBORw0KGgo=\">",
                "http://cdn",
                "data:image/png;base64,iVBORw0KGgo=",
            ],
        ];
    }
}
