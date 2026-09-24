<?php
namespace Tests\Framework;

use Framework\Framework;
use Framework\Auth\Auth;
use Framework\IO\Request;
use Framework\IO\Response;
use Framework\Log\ErrorLog;
use Framework\System\Access;

use Tests\LiveTestCase;
use Tests\TestHelpers;

/**
 * The Framework Service, the entry every request of an App goes through
 *
 * The request it reads is put in place rather than sent, and what it prints
 * is captured rather than served. The error handler it registers is taken
 * back out after each test, so the rest of the suite keeps the handling
 * PHPUnit gives it.
 *
 * The flow past the routing needs a route, and the Framework alone has none
 * to generate, so what happens inside one is out of reach from here.
 */
class FrameworkTest extends LiveTestCase {
    use TestHelpers;

    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();
        Framework::setResponse(null);
    }

    protected function tearDown(): void {
        // The init of the log registered a global handler, and the next test
        // may be one that expects the handling of PHPUnit instead
        if ((bool)$this->getPrivateStaticProperty(ErrorLog::class, "loaded")) {
            restore_error_handler();
            $this->setPrivateStaticProperty(ErrorLog::class, "loaded", false);
        }

        $this->setPrivateStaticProperty(Framework::class, "request", null);
        $this->setPrivateStaticProperty(Framework::class, "route", "");
        $this->setPrivateStaticProperty(Auth::class, "accessName", Access::General);
        $this->setPrivateStaticProperty(Auth::class, "apiToken", "");
        Framework::setResponse(null);
    }

    /**
     * Puts the given data in place as the request being served
     * @param array<string,mixed> $data
     * @return void
     */
    private function useRequest(array $data): void {
        $this->setPrivateStaticProperty(Framework::class, "request", new Request($data));
    }

    /**
     * Executes the Framework, keeping what it printed
     * @return array{bool,string}
     */
    private function execute(): array {
        ob_start();
        try {
            $result = Framework::execute();
        } finally {
            $output = (string)ob_get_clean();
        }
        return [ $result, $output ];
    }



    public function testARouteIsRequired(): void {
        $this->useRequest([ "token" => "something" ]);

        [ $result, $output ] = $this->execute();

        $this->assertFalse($result);
        $this->assertSame("", $output);
    }

    public function testTheRouteIsKept(): void {
        $this->assertSame("", Framework::getRoute());

        $this->useRequest([ "route" => "/job/messaging/flow", "token" => "secret" ]);
        $this->execute();
        $this->assertSame("/job/messaging/flow", Framework::getRoute());
    }

    public function testAnUnknownRouteIsAnswered(): void {
        // The Framework alone has no routes, so any route is this path
        $this->useRequest([ "route" => "nothing/here" ]);

        [ $result, $output ] = $this->execute();

        $this->assertTrue($result);
        $this->assertStringContainsString("GENERAL_ERROR_PATH", $output);
    }

    public function testTheSensitiveDataIsRemoved(): void {
        // The tokens and the routing data are read and taken out, so no
        // route handler can see them
        $this->useRequest([
            "route"         => "nothing/here",
            "token"         => "a-token",
            "xAccessToken"  => "an-access-token",
            "xRefreshToken" => "a-refresh-token",
            "xLangcode"     => "es",
            "xTimezone"     => -180,
            "name"          => "kept",
        ]);

        $this->execute();

        $request = Framework::getRequest();
        $this->assertFalse($request->hasValue("token"));
        $this->assertFalse($request->hasValue("xAccessToken"));
        $this->assertFalse($request->hasValue("xRefreshToken"));
        $this->assertTrue($request->hasValue("name"));
    }

    public function testTheAnswerIsJson(): void {
        $this->useRequest([ "route" => "nothing/here" ]);

        [ , $output ] = $this->execute();

        $this->assertNotNull(json_decode($output));
    }



    public function testAnApiTokenGrantsTheApi(): void {
        // A matching token makes the request one of the API, whose errors
        // are answered as a result rather than as a form error
        $this->setConfig("AUTH_API_TOKEN", "the-api-token");
        $this->useRequest([ "route" => "nothing/here", "token" => "the-api-token" ]);

        [ $result, $output ] = $this->execute();

        $this->assertTrue($result);
        $this->assertTrue(Auth::hasAPI());
        $this->assertStringContainsString("error", $output);
    }

    public function testBadTokensAreNotSignedIn(): void {
        $this->useRequest([
            "route"         => "nothing/here",
            "xAccessToken"  => "not-a-token",
            "xRefreshToken" => "not-one-either",
        ]);

        [ $result ] = $this->execute();

        $this->assertTrue($result);
        $this->assertFalse(Auth::isLoggedIn());
    }

    public function testAnInternalRequestHasApiAccess(): void {
        // It comes from the app itself, so there is no one to validate
        $payload = Framework::executeInternal();

        $this->assertSame(0, $payload->count());
        $this->assertTrue(Auth::hasAPI());
    }



    public function testTheOutputIsPrettyJson(): void {
        ob_start();
        try {
            Framework::output([ "one" => 1, "two" => "text" ]);
        } finally {
            $output = (string)ob_get_clean();
        }

        $this->assertSame([ "one" => 1, "two" => "text" ], json_decode($output, true));
        $this->assertStringContainsString("\n", $output);
    }

    public function testTheOutputSendsTheStatus(): void {
        $previous = http_response_code();
        ob_start();
        try {
            Framework::output([ "a" => 1 ]);
            $byDefault = http_response_code();
            Framework::output([ "a" => 1 ], 404);
            $whenGiven = http_response_code();
        } finally {
            ob_get_clean();
            http_response_code(is_int($previous) ? $previous : 200);
        }

        $this->assertSame(200, $byDefault);
        $this->assertSame(404, $whenGiven);
    }

    public function testTheRequestReadsTheGlobals(): void {
        $_REQUEST["name"] = "from the globals";
        try {
            $request = Framework::getRequest();
        } finally {
            $_REQUEST = [];
        }

        $this->assertSame("from the globals", $request->getString("name"));
    }

    public function testTheRequestIsBuiltOnce(): void {
        $this->useRequest([ "name" => "kept" ]);

        $this->assertSame(Framework::getRequest(), Framework::getRequest());
    }

    public function testAResponseIsStored(): void {
        $response = Response::result([ "done" => true ]);

        Framework::setResponse($response);
        $this->assertSame($response, Framework::getResponse());

        Framework::setResponse();
        $this->assertNull(Framework::getResponse());
    }
}
