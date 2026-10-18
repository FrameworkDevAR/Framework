<?php
namespace Framework\Log;

use Framework\Application;
use Framework\Database\Query\Assign;
use Framework\System\Config;
use Framework\Log\Schema\LogSocketSchema;
use Framework\Log\Schema\LogSocketQuery;
use Framework\Log\Schema\LogSocketRequest;
use Framework\Log\Schema\LogSocketColumn;
use Framework\Date\Date;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Socket Log
 */
class SocketLog extends LogSocketSchema {

    /**
     * Creates the List Query
     * @param LogSocketRequest $request
     * @return LogSocketQuery
     */
    #[\Override]
    protected static function createListQuery(LogSocketRequest $request): LogSocketQuery {
        $query = new LogSocketQuery();
        $query->search([
            LogSocketColumn::Error,
            LogSocketColumn::Environment,
        ], $request->search);

        if (!$request->fromDate->isEmpty()) {
            $query->minute->greaterOrEqual($request->fromDate->toTime());
        }
        if (!$request->toDate->isEmpty()) {
            $query->minute->lessOrEqual($request->toDate->toTime());
        }

        if ($request->isResolved === "yes") {
            $query->isResolved->isTrue();
        } elseif ($request->isResolved === "no") {
            $query->isResolved->isFalse();
        }
        return $query;
    }



    /**
     * Counts a publish the Socket Server did not take in the current minute
     * @param string $error
     * @return void
     */
    public static function add(string $error): void {
        $time   = Date::now()->toTime();
        $minute = $time - $time % 60;
        $error  = Strings::makeShort($error, 250);

        $query = new LogSocketQuery();
        $query->minute->equal($minute);
        $query->environment->equal(Application::getEnvironment());

        if (self::getEntityTotal($query) > 0) {
            self::editEntity($query, error: $error, amount: Assign::increase(1), isResolved: false);
        } else {
            self::createEntity(
                minute:      $minute,
                environment: Application::getEnvironment(),
                error:       $error,
                amount:      1,
                isResolved:  false,
            );
        }
    }

    /**
     * Returns the amount of failures of each minute since the given time
     * @param Date $from
     * @return array<int,int>
     */
    public static function getAmounts(Date $from): array {
        $query = new LogSocketQuery();
        $query->minute->greaterOrEqual($from->toTime());
        $query->minute->orderByAsc();

        $result = [];
        foreach (self::getEntityList($query) as $elem) {
            $result[$elem->minute] = ($result[$elem->minute] ?? 0) + $elem->amount;
        }
        return $result;
    }

    /**
     * Returns the last error since the given time
     * @param Date $from
     * @return string
     */
    public static function getLastError(Date $from): string {
        $query = new LogSocketQuery();
        $query->minute->greaterOrEqual($from->toTime());
        $query->minute->orderByDesc();
        $query->limit(1);

        $list = self::getEntityList($query);
        return isset($list[0]) ? $list[0]->error : "";
    }

    /**
     * Marks the given Socket Log(s) as Resolved
     * @param list<int>|int $logID
     * @return void
     */
    public static function markResolved(array|int $logID): void {
        $query = new LogSocketQuery();
        // The time of the row is the last failure, so resolving it does not change it
        $query->logID->in(Arrays::toInts($logID));
        self::editEntity($query, isResolved: true, skipTimestamps: true);
    }

    /**
     * Deletes the given Socket Log(s)
     * @param list<int>|int $logID
     * @return void
     */
    public static function delete(array|int $logID): void {
        $query = new LogSocketQuery();
        $query->logID->in(Arrays::toInts($logID));
        self::removeEntity($query);
    }

    /**
     * Deletes the items older than some days
     * @return void
     */
    public static function deleteOld(): void {
        $days = Config::getSocketLogDeleteDays();
        $time = Date::now()->subtract(days: $days);

        $query = new LogSocketQuery();
        $query->minute->lessThan($time->toTime());
        self::removeEntity($query);
    }
}
