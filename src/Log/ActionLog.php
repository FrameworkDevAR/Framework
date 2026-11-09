<?php
namespace Framework\Log;

use Framework\Auth\Auth;
use Framework\Database\Query\Op;
use Framework\Log\SessionLog;
use Framework\Log\Type\Sec;
use Framework\Log\Type\Act;
use Framework\Log\Type\LogIntl;
use Framework\Log\Schema\LogActionSchema;
use Framework\Log\Schema\LogActionRequest;
use Framework\Log\Schema\LogActionEntity;
use Framework\Log\Schema\LogActionColumn;
use Framework\Log\Schema\LogActionQuery;
use Framework\System\Config;
use Framework\Date\Date;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Numbers;
use Framework\Utils\Strings;

/**
 * The Actions Log
 */
class ActionLog extends LogActionSchema {

    /**
     * Returns the List Query
     * @param LogActionRequest              $request
     * @param array<string,LogActionColumn> $mappings Optional.
     * @return LogActionQuery
     */
    private static function createQuery(
        LogActionRequest $request,
        array $mappings = [],
    ): LogActionQuery {
        $query = new LogActionQuery();

        foreach ($mappings as $key => $column) {
            $value = $request->getString($key);
            if (!Arrays::isEmpty($value)) {
                $query->where($column, Op::Equal, $value);
            }
        }

        $query->search([
            LogActionColumn::CredentialName,
            LogActionColumn::Module,
            LogActionColumn::Action,
        ], $request->search);

        $query->credentialID->equalIf($request->credentialID);
        $query->credentialID->in($request->credentialIDs);
        $query->createdTime->inPeriod($request);
        return $query;
    }

    /**
     * Post Process the Result
     * @param LogActionEntity $logAction
     * @return LogActionEntity
     */
    #[\Override]
    protected static function postProcess(LogActionEntity $logAction): LogActionEntity {
        $logAction->moduleName = LogIntl::getModuleName($logAction->module);
        $logAction->actionName = LogIntl::getActionName($logAction->module, $logAction->action);
        return $logAction;
    }



    /**
     * Returns all the Actions Log items
     * @param LogActionRequest              $request
     * @param array<string,LogActionColumn> $mappings Optional.
     * @return list<array<string,mixed>>
     */
    public static function getAll(LogActionRequest $request, array $mappings = []): array {
        $query = self::createQuery($request, $mappings);
        $query->sessionCreatedTime->orderByDesc();
        $query->sessionID->orderByDesc();
        $query->createdTime->orderByDesc();

        $list          = self::getEntityList($query, $request);
        $result        = [];
        $lastSessionID = 0;
        $lastIndex     = -1;

        foreach ($list as $elem) {
            if ($lastSessionID !== $elem->sessionID) {
                $result[] = [
                    "sessionID"      => $elem->sessionID,
                    "credentialID"   => $elem->credentialID,
                    "credentialName" => $elem->credentialName,
                    "ip"             => $elem->sessionIp,
                    "userAgent"      => $elem->sessionUserAgent,
                    "createdTime"    => $elem->sessionCreatedTime,
                    "actions"        => [],
                    "isLast"         => false,
                ];
                $lastSessionID = $elem->sessionID;
                $lastIndex    += 1;
            }
            $result[$lastIndex]["actions"][] = [
                "module"      => $elem->module,
                "moduleName"  => $elem->moduleName,
                "action"      => $elem->action,
                "actionName"  => $elem->actionName,
                "dataID"      => $elem->dataID !== "" ? JSON::decodeAsArray($elem->dataID) : "",
                "createdTime" => $elem->createdTime,
            ];
        }

        if ($lastIndex >= 0) {
            $result[$lastIndex]["isLast"] = true;
        }
        return array_values($result);
    }

    /**
     * Returns the total amount of Actions Log items
     * @param LogActionRequest              $request
     * @param array<string,LogActionColumn> $mappings Optional.
     * @return int
     */
    public static function getAmount(
        LogActionRequest $request,
        array $mappings = [],
    ): int {
        $query = self::createQuery($request, $mappings);
        return self::getEntityTotal($query);
    }



    /**
     * Returns the Credential ID for the current User
     * @param int $credentialID Optional.
     * @return int
     */
    private static function getCredentialID(int $credentialID = 0): int {
        if ($credentialID !== 0) {
            return $credentialID;
        }
        if (Auth::isLoggedAsUser()) {
            return Auth::getAdminID();
        }
        return Auth::getID();
    }

    /**
     * Starts a Log Session
     * @param bool $destroy Optional.
     * @return bool
     */
    public static function startSession(bool $destroy = false): bool {
        $credentialID = self::getCredentialID();
        $sessionID    = SessionLog::getID($credentialID);

        if ($destroy && $sessionID !== 0) {
            SessionLog::end($sessionID);
        }
        if ($destroy || $sessionID === 0) {
            SessionLog::start($credentialID);
            return true;
        }
        return false;
    }

    /**
     * Ends the Log Session
     * @return bool
     */
    public static function endSession(): bool {
        $credentialID = self::getCredentialID();
        $sessionID    = SessionLog::getID($credentialID);
        if ($sessionID === 0) {
            return false;
        }
        return SessionLog::end($sessionID);
    }

    /**
     * Logs the given Action
     * @param Sec|string $section
     * @param Act|string $action
     * @param mixed|int  $dataID       Optional.
     * @param int        $credentialID Optional.
     * @return bool
     */
    public static function add(
        Sec|string $section,
        Act|string $action,
        mixed $dataID = 0,
        int $credentialID = 0,
    ): bool {
        $credentialID = self::getCredentialID($credentialID);
        $sessionID    = SessionLog::getID($credentialID);
        if ($sessionID === 0) {
            return false;
        }

        $dataID = Arrays::toArray($dataID);
        foreach ($dataID as $index => $value) {
            $dataID[$index] = Numbers::isValid($value) ? Numbers::toInt($value) : $value;
        }

        self::createEntity(
            sessionID:    $sessionID,
            credentialID: $credentialID,
            currentUser:  Auth::getUserID(),
            module:       Strings::toString($section),
            action:       Strings::toString($action),
            dataID:       JSON::encode($dataID),
        );
        return true;
    }



    /**
     * Deletes the items older than some days
     * @return bool
     */
    public static function deleteOld(): bool {
        $days  = Config::getActionLogDeleteDays();
        $time  = Date::now()->subtract(days: $days);

        $query = new LogActionQuery();
        $query->createdTime->lessThan($time);
        self::removeEntity($query);
        return SessionLog::deleteOld();
    }
}
