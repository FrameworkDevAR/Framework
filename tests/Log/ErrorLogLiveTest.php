<?php
namespace Tests\Log;

use Framework\Framework;
use Framework\Application;
use Framework\Discovery\Package;
use Framework\Log\ErrorLog;
use Framework\Log\Schema\LogErrorEntity;
use Framework\Log\Schema\LogErrorRequest;

use Tests\LiveTestCase;
use Tests\TestHelpers;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Errors Log, one row per error with the times it has happened
 *
 * The init is left alone on purpose: it registers a shutdown function that
 * cannot be taken back and an error handler that would swallow the errors of
 * every test after it. What it registers, the handler, is called here itself,
 * with the two paths it works out put in place the way the init would.
 */
class ErrorLogLiveTest extends LiveTestCase {
    use TestHelpers;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `log_error`");

        $this->setPrivateStaticProperty(ErrorLog::class, "framePath", Package::getBasePath());
        $this->setPrivateStaticProperty(ErrorLog::class, "basePath", Application::getIndexPath());
        $this->setPrivateStaticProperty(Framework::class, "route", "");
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Framework::class, "route", "");
        parent::tearDown();
    }

    /**
     * Returns the one Error that was logged
     * @return LogErrorEntity
     */
    private function onlyOne(): LogErrorEntity {
        $list = ErrorLog::getList(new LogErrorRequest());
        $this->assertCount(1, $list);
        return $list[0];
    }



    public function testAnErrorIsLogged(): void {
        $this->assertTrue(
            ErrorLog::handler(E_USER_WARNING, "Something went wrong", "/tmp/a.php", 12),
        );

        $log = $this->onlyOne();
        $this->assertSame(E_USER_WARNING, $log->errorCode);
        $this->assertSame("Warning", $log->errorText);
        $this->assertSame(12, $log->line);
        $this->assertSame(1, $log->amount);
        $this->assertFalse($log->isResolved);
        $this->assertStringContainsString("Something went wrong", $log->description);
    }

    /**
     * A PHP error code, and the word the log files it under
     * @param int    $errorCode
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerErrorText")]
    public function testTheCodeIsGivenItsWord(int $errorCode, string $expected): void {
        ErrorLog::handler($errorCode, "A description");

        $this->assertSame($expected, $this->onlyOne()->errorText);
    }

    /**
     * @return array<string,array{int,string}>
     */
    public static function providerErrorText(): array {
        return [
            "a fatal one"     => [ E_USER_ERROR, "Fatal Error" ],
            "a warning"       => [ E_USER_WARNING, "Warning" ],
            "a notice"        => [ E_USER_NOTICE, "Notice" ],
            "a deprecation"   => [ E_USER_DEPRECATED, "Deprecated" ],
            "a parse error"   => [ E_PARSE, "Fatal Error" ],
            "a strict one"    => [ E_STRICT, "Strict" ],
            "one of no kind"  => [ 0, "" ],
        ];
    }

    public function testTheSameErrorIsCounted(): void {
        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 12);
        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 12);
        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 12);

        $this->assertSame(3, $this->onlyOne()->amount);
    }

    public function testAnotherLineIsAnotherRow(): void {
        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 12);
        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 34);

        $this->assertSame(2, ErrorLog::getEntityTotal());
    }

    public function testOneHappeningAgainIsNotResolved(): void {
        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 12);
        $logID = $this->onlyOne()->logID;
        ErrorLog::markResolved($logID);
        $this->assertTrue(ErrorLog::getByID($logID)->isResolved);

        ErrorLog::handler(E_USER_WARNING, "The same one", "/tmp/a.php", 12);

        $this->assertFalse(ErrorLog::getByID($logID)->isResolved);
    }

    public function testTheQuotesAreTakenOut(): void {
        // They are what a message wraps a class or a file in, and they only
        // make the same error look like two
        ErrorLog::handler(E_USER_WARNING, "Call to `Thing::method()` on 'null'");

        $this->assertSame("Call to Thing::method() on null", $this->onlyOne()->description);
    }

    public function testAStackTraceIsSplitOff(): void {
        $description = "It broke\nStack trace:\n#0 /tmp/a.php(12): one()\n#1 {main}";

        ErrorLog::handler(E_USER_ERROR, $description);

        $log = $this->onlyOne();
        $this->assertSame("It broke\n", $log->description);

        // The trace is turned around, so it reads from where it started, and
        // the {main} frame is left out
        $this->assertSame("#1- /tmp/a.php(12): one()\n", $log->backtrace);
    }

    public function testWithNoTraceTheCallIsUsed(): void {
        ErrorLog::handler(E_USER_WARNING, "It broke");

        // The frames are numbered from the outermost in, and the innermost
        // two are left off, so the last one named is what called the handler
        $backtrace = $this->onlyOne()->backtrace;
        $this->assertStringStartsWith("#1- ", $backtrace);
        $this->assertStringContainsString(
            "-> testWithNoTraceTheCallIsUsed()",
            $backtrace,
        );
    }

    public function testTheFrameworkPathIsShort(): void {
        // The rows are read in a browser, where the whole path of the machine
        // that ran it is noise
        ErrorLog::handler(E_USER_WARNING, "It broke", __FILE__, 1);

        $this->assertSame("framework/tests/Log/ErrorLogLiveTest.php", $this->onlyOne()->file);
    }

    public function testAnOutsidePathIsLeftAlone(): void {
        ErrorLog::handler(E_USER_WARNING, "It broke", "/tmp/a.php", 1);

        $this->assertSame("/tmp/a.php", $this->onlyOne()->file);
    }



    public function testAnErrorIsMarkedAsResolved(): void {
        ErrorLog::handler(E_USER_WARNING, "It broke");
        $logID = $this->onlyOne()->logID;

        $this->assertTrue(ErrorLog::markResolved($logID));

        $this->assertTrue(ErrorLog::getByID($logID)->isResolved);
    }

    public function testAnErrorIsDeleted(): void {
        ErrorLog::handler(E_USER_WARNING, "It broke");
        $logID = $this->onlyOne()->logID;

        $this->assertTrue(ErrorLog::delete($logID));

        $this->assertFalse(ErrorLog::exists($logID));
    }

    public function testSeveralAreDeletedAtOnce(): void {
        ErrorLog::handler(E_USER_WARNING, "One", "/tmp/a.php", 1);
        ErrorLog::handler(E_USER_WARNING, "Another", "/tmp/a.php", 2);

        $this->assertTrue(ErrorLog::delete(ErrorLog::getLogIDs()));

        $this->assertSame(0, ErrorLog::getEntityTotal());
    }

    public function testTheListIsFilteredByResolved(): void {
        ErrorLog::handler(E_USER_WARNING, "One", "/tmp/a.php", 1);
        ErrorLog::markResolved($this->onlyOne()->logID);
        ErrorLog::handler(E_USER_WARNING, "Another", "/tmp/a.php", 2);

        $this->assertSame(1, ErrorLog::getTotal(new LogErrorRequest(isResolved: "yes")));
        $this->assertSame(1, ErrorLog::getTotal(new LogErrorRequest(isResolved: "no")));
        $this->assertSame(2, ErrorLog::getTotal(new LogErrorRequest()));
    }

    public function testTheListIsSearched(): void {
        ErrorLog::handler(E_USER_WARNING, "The first one", "/tmp/a.php", 1);
        ErrorLog::handler(E_USER_WARNING, "The second one", "/tmp/a.php", 2);

        $request = new LogErrorRequest(search: "second");
        $this->assertSame(1, ErrorLog::getTotal($request));
    }

    public function testTheOldOnesAreDeleted(): void {
        ErrorLog::handler(E_USER_WARNING, "One", "/tmp/a.php", 1);
        $logID = $this->onlyOne()->logID;
        $this->query(
            "UPDATE `log_error` SET `createdTime` = UNIX_TIMESTAMP() - 91 * 86400 " .
            "WHERE `LOG_ID` = $logID",
        );
        ErrorLog::handler(E_USER_WARNING, "Another", "/tmp/a.php", 2);

        $this->assertTrue(ErrorLog::deleteOld());

        $this->assertFalse(ErrorLog::exists($logID));
        $this->assertSame(1, ErrorLog::getEntityTotal());
    }

    public function testThereIsNothingOldToDelete(): void {
        ErrorLog::handler(E_USER_WARNING, "One", "/tmp/a.php", 1);

        $this->assertFalse(ErrorLog::deleteOld());
    }



    public function testTheShutdownHasNothingToLog(): void {
        error_clear_last();

        $this->assertFalse(ErrorLog::shutdown());
        $this->assertSame(0, ErrorLog::getEntityTotal());
    }

    public function testTheShutdownLogsTheLast(): void {
        // Which is what the handler is given for an error that ended the
        // request, since there is nothing left to call it
        $this->runWithSuppressedWarnings(static function (): void {
            trigger_error("The last one", E_USER_WARNING);
        }, suppress: true);

        $this->assertTrue(ErrorLog::shutdown());

        $this->assertStringContainsString("The last one", $this->onlyOne()->description);
    }

    public function testTheShutdownLogsTheRoute(): void {
        // An error that ended the request has no backtrace to tell where it came
        // from, so the Route of the request goes first in it
        $this->setPrivateStaticProperty(Framework::class, "route", "/job/messaging/flow");
        $this->runWithSuppressedWarnings(static function (): void {
            trigger_error("Out of memory", E_USER_WARNING);
        }, suppress: true);

        $this->assertTrue(ErrorLog::shutdown());

        $this->assertStringStartsWith("Route: /job/messaging/flow\n", $this->onlyOne()->backtrace);
    }

    public function testAnErrorKeepsItsBacktraceWithARoute(): void {
        $this->setPrivateStaticProperty(Framework::class, "route", "/job/messaging/flow");
        ErrorLog::handler(E_USER_WARNING, "It broke");

        $this->assertStringNotContainsString("Route:", $this->onlyOne()->backtrace);
    }
}
