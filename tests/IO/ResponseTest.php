<?php
namespace Tests\IO;

use Framework\IO\Errors;
use Framework\IO\Response;
use Framework\IO\Search;
use Framework\Utils\JSON;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use JsonSerializable;

class ResponseTest extends TestCase {

    #[DataProvider("providerAddTokens")]
    public function testAddTokens(bool $withTokens, string $accessToken, string $refreshToken, array $expectedData, array $missingKeys = []): void {
        $response = Response::empty($withTokens);
        $response->addTokens($accessToken, $refreshToken);

        $data = $response->toArray();
        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $data);
            $this->assertSame($value, $data[$key]);
        }
        foreach ($missingKeys as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
    }

    public static function providerAddTokens(): array {
        return [
            "access and refresh" => [
                true,
                "a-token",
                "r-token",
                [
                    "xAccessToken"  => "a-token",
                    "xRefreshToken" => "r-token",
                ],
            ],
            "access only"        => [
                true,
                "only-access",
                "",
                [
                    "xAccessToken" => "only-access",
                ],
                [ "xRefreshToken" ],
            ],
            "tokens disabled"    => [
                false,
                "x",
                "y",
                [],
                [ "xAccessToken", "xRefreshToken" ],
            ],
        ];
    }


    public function testTheDefaultIsASuccess(): void {
        $this->assertSame(200, Response::empty()->getStatusCode());
    }

    #[DataProvider("providerStatusCode")]
    public function testStatusCode(int $statusCode): void {
        $response = Response::error("SOME_ERROR");

        // The setter returns the Response, so it reads in the return of a route
        $this->assertSame($response, $response->setStatusCode($statusCode));
        $this->assertSame($statusCode, $response->getStatusCode());
    }

    public static function providerStatusCode(): array {
        return [
            "bad request"  => [ 400 ],
            "unauthorized" => [ 401 ],
            "not found"    => [ 404 ],
            "server error" => [ 500 ],
        ];
    }


    #[DataProvider("providerToArray")]
    public function testToArray(array $data, bool $withTokens): void {
        $response = new Response($data, $withTokens);
        $this->assertSame($data, $response->toArray());
    }

    public static function providerToArray(): array {
        return [
            "basic" => [ [ "a" => 1, "b" => "v" ], true ],
            "empty" => [ [], false ],
        ];
    }


    #[DataProvider("providerPrintData")]
    public function testPrintData(Response $response, string $expectedOutput): void {
        ob_start();
        $response->printData();
        $output = ob_get_clean();

        $this->assertSame($expectedOutput, $output);
    }

    public static function providerPrintData(): array {
        $payload = [ "k" => "v", "n" => 3 ];

        return [
            "payload"          => [
                Response::data($payload),
                JSON::encode($payload, asPretty: true),
            ],
            "empty_data"       => [
                Response::data([]),
                "[]",
            ],
            "missing_data_key" => [
                Response::result([ "x" => 1 ]),
                "",
            ],
        ];
    }


    #[DataProvider("providerEmpty")]
    public function testEmpty(bool $withTokens, ?array $tokens, array $expectedData): void {
        $response = Response::empty($withTokens);
        if ($tokens !== null) {
            $response->addTokens($tokens[0], $tokens[1]);
        }

        $this->assertSame($expectedData, $response->toArray());
    }

    public static function providerEmpty(): array {
        return [
            "default"         => [ true, null, [] ],
            "with tokens"     => [
                true,
                [ "a", "b" ],
                [
                    "xAccessToken"  => "a",
                    "xRefreshToken" => "b",
                ],
            ],
            "tokens disabled" => [ false, [ "a", "b" ], [] ],
        ];
    }


    #[DataProvider("providerExit")]
    public function testExit(int $exitCode): void {
        $response = Response::exit($exitCode);

        $this->assertSame([ "result" => $exitCode ], $response->toArray());

        $response->addTokens("a", "b");
        $this->assertSame([ "result" => $exitCode ], $response->toArray());
    }

    public static function providerExit(): array {
        return [
            "non zero" => [ 7 ],
            "zero"     => [ 0 ],
        ];
    }


    #[DataProvider("providerText")]
    public function testText(string $text): void {
        $response = Response::text($text);

        // The Text is printed in place of the Data, which takes no tokens
        $response->addTokens("a", "b");
        $this->assertSame($text, $response->getText());
        $this->assertSame([], $response->toArray());
    }

    public static function providerText(): array {
        return [
            "a word"  => [ "OK" ],
            "nothing" => [ "" ],
        ];
    }

    public function testOnlyATextResponseHasText(): void {
        $this->assertNull(Response::empty()->getText());
        $this->assertNull(Response::result([ "data" => "OK" ])->getText());
    }


    #[DataProvider("providerResult")]
    public function testResult(array $result): void {
        $this->assertSame($result, Response::result($result)->toArray());
    }

    public static function providerResult(): array {
        return [
            "filled" => [ [ "res" => 1 ] ],
            "empty"  => [ [] ],
        ];
    }


    #[DataProvider("providerData")]
    public function testData(JsonSerializable|array $payload, mixed $expectedData, ?string $expectedClass = null): void {
        $data = Response::data($payload)->toArray();

        $this->assertArrayHasKey("data", $data);
        $this->assertSame($expectedData, $data["data"]);
        if ($expectedClass !== null) {
            $this->assertInstanceOf($expectedClass, $data["data"]);
        }
    }

    public static function providerData(): array {
        $search = new Search(1, "T", null);

        return [
            "array payload"     => [
                [ "x" => 2 ],
                [ "x" => 2 ],
            ],
            "empty array"       => [
                [],
                [],
            ],
            "json serializable" => [
                $search,
                $search,
                Search::class,
            ],
        ];
    }


    #[DataProvider("providerSearch")]
    public function testSearch(array $searches, array $expectedData, ?string $expectedClass = null): void {
        $data = Response::search($searches)->toArray();

        $this->assertArrayHasKey("data", $data);
        $this->assertSame($expectedData, $data["data"]);
        if ($expectedClass !== null && $data["data"] !== []) {
            $this->assertInstanceOf($expectedClass, $data["data"][0]);
        }
    }

    public static function providerSearch(): array {
        $search = new Search(1, "T", null);

        return [
            "single" => [ [ $search ], [ $search ], Search::class ],
            "empty"  => [ [], [] ],
        ];
    }


    #[DataProvider("providerInvalid")]
    public function testInvalid(array $expectedData): void {
        $this->assertSame($expectedData, Response::invalid()->toArray());
    }

    public static function providerInvalid(): array {
        return [
            "invalid" => [ [ "data" => [ "error" => true ] ] ],
        ];
    }


    #[DataProvider("providerLogout")]
    public function testLogout(array $expectedData): void {
        $this->assertSame($expectedData, Response::logout()->toArray());
    }

    public static function providerLogout(): array {
        return [
            "logout" => [ [ "userLoggedOut" => true ] ],
        ];
    }


    #[DataProvider("providerSuccess")]
    public function testSuccess(string $message, JsonSerializable|array|null $data, string $param, array $expectedData): void {
        $this->assertSame($expectedData, Response::success($message, $data, $param)->toArray());
    }

    public static function providerSuccess(): array {
        return [
            "default param and null data" => [
                "ok",
                null,
                "",
                [
                    "success" => "ok",
                    "param"   => "",
                    "data"    => null,
                ],
            ],
            "with data and param"         => [
                "done",
                [ "x" => 1 ],
                "p",
                [
                    "success" => "done",
                    "param"   => "p",
                    "data"    => [ "x" => 1 ],
                ],
            ],
        ];
    }


    #[DataProvider("providerWarning")]
    public function testWarning(string $message, JsonSerializable|array|null $data, string $param, array $expectedData): void {
        $this->assertSame($expectedData, Response::warning($message, $data, $param)->toArray());
    }

    public static function providerWarning(): array {
        return [
            "default param and null data" => [
                "be careful",
                null,
                "",
                [
                    "warning" => "be careful",
                    "param"   => "",
                    "data"    => null,
                ],
            ],
            "with param"                  => [
                "warn",
                null,
                "pp",
                [
                    "warning" => "warn",
                    "param"   => "pp",
                    "data"    => null,
                ],
            ],
        ];
    }


    #[DataProvider("providerError")]
    public function testError(Errors|string $error, JsonSerializable|array|null $data, array|string $param, array $expectedData): void {
        $this->assertSame($expectedData, Response::error($error, $data, $param)->toArray());
    }

    public static function providerError(): array {
        $errorsWithGlobal = new Errors();
        $errorsWithGlobal->global("global-msg");

        $errorsWithoutGlobal = new Errors();
        $errorsWithoutGlobal->add("f", "m");

        return [
            "string error single param"    => [
                "oops",
                null,
                "p1",
                [
                    "error"  => "oops",
                    "param"  => "p1",
                    "params" => [ "p1" ],
                    "data"   => null,
                ],
            ],
            "string error multiple params" => [
                "error",
                null,
                [ "pA", "pB" ],
                [
                    "error"  => "error",
                    "param"  => "pA",
                    "params" => [ "pA", "pB" ],
                    "data"   => null,
                ],
            ],
            "errors with global"           => [
                $errorsWithGlobal,
                [ "dd" => 1 ],
                [ "pA", "pB" ],
                [
                    "error"  => "global-msg",
                    "param"  => "pA",
                    "params" => [ "pA", "pB" ],
                    "data"   => [ "dd" => 1 ],
                ],
            ],
            "errors without global"        => [
                $errorsWithoutGlobal,
                null,
                "",
                [
                    "errors" => [ "f" => "m" ],
                    "data"   => null,
                ],
            ],
        ];
    }
}
