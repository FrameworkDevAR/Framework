<?php
namespace Tests\Log;

use Framework\Log\SocketLog;
use Framework\Log\Schema\LogSocketRequest;
use Framework\Date\Date;

use Tests\LiveTestCase;
use Tests\TestHelpers;

/**
 * The Socket Log, one row per minute with the publishes the Socket Server did not take
 */
class SocketLogLiveTest extends LiveTestCase {
    use TestHelpers;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `log_socket`");
    }

    /**
     * Returns the start of the current minute
     * @return int
     */
    private function currentMinute(): int {
        $time = Date::now()->toTime();
        return $time - $time % 60;
    }



    public function testTheFailuresOfAMinuteAreCounted(): void {
        SocketLog::add("first");
        SocketLog::add("second");

        $amounts = SocketLog::getAmounts(Date::now()->subtract(minutes: 5));

        // A minute can turn between the two, so it is the total that is checked
        $this->assertSame(2, array_sum($amounts));
        $this->assertLessThanOrEqual(2, count($amounts));
    }

    public function testTheLastErrorIsKept(): void {
        SocketLog::add("first");
        SocketLog::add("second");

        $this->assertSame("second", SocketLog::getLastError(Date::now()->subtract(minutes: 5)));
    }

    public function testNothingIsAnEmptyList(): void {
        $this->assertSame([], SocketLog::getAmounts(Date::now()->subtract(minutes: 5)));
        $this->assertSame("", SocketLog::getLastError(Date::now()->subtract(minutes: 5)));
    }

    public function testTheListIsSearchedByTheError(): void {
        $old = $this->currentMinute() - 3600;
        $this->query("INSERT INTO `log_socket` (`minute`, `environment`, `error`, `amount`) VALUES ($old, 'local', 'Connection refused', 2)");
        SocketLog::add("Timed out");

        $request = new LogSocketRequest();
        $request->search = "refused";

        $list = SocketLog::getList($request);
        $this->assertCount(1, $list);
        $this->assertSame("Connection refused", $list[0]->error);
        $this->assertSame(2, SocketLog::getTotal(new LogSocketRequest()));
    }

    public function testTheGivenLogsAreDeleted(): void {
        SocketLog::add("first");
        $list = SocketLog::getList(new LogSocketRequest());

        SocketLog::delete($list[0]->logID);

        $this->assertSame(0, SocketLog::getTotal(new LogSocketRequest()));
    }

    public function testAResolvedMinuteOpensWhenItFailsAgain(): void {
        SocketLog::add("first");
        $logID = SocketLog::getList(new LogSocketRequest())[0]->logID;

        $this->query("UPDATE `log_socket` SET `modifiedTime` = 1000 WHERE `LOG_ID` = $logID");
        SocketLog::markResolved($logID);
        $this->assertTrue(SocketLog::getByID($logID)->isResolved);
        $this->assertSame(1000, SocketLog::getByID($logID)->modifiedTime?->toTime());

        // The minute can turn before the second failure, which then makes its own
        // row, so it is the newest one that has to be open
        SocketLog::add("second");
        $request = new LogSocketRequest();
        $request->isResolved = "no";
        $this->assertCount(1, SocketLog::getList($request));
    }

    public function testTheOldMinutesAreDeleted(): void {
        $old = $this->currentMinute() - 30 * 24 * 3600;
        $this->query("INSERT INTO `log_socket` (`minute`, `environment`, `error`, `amount`) VALUES ($old, 'local', 'old', 3)");
        SocketLog::add("new");

        SocketLog::deleteOld();

        $amounts = SocketLog::getAmounts(Date::now()->subtract(days: 60));
        $this->assertArrayNotHasKey($old, $amounts);
        $this->assertSame(1, array_sum($amounts));
    }
}
