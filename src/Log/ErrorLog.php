<?php
namespace Framework\Log;

use Framework\Framework;
use Framework\Application;
use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Package;
use Framework\Database\Query\Assign;
use Framework\Log\Schema\LogErrorSchema;
use Framework\Log\Schema\LogErrorRequest;
use Framework\Log\Schema\LogErrorColumn;
use Framework\Log\Schema\LogErrorQuery;
use Framework\System\Config;
use Framework\Date\Date;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Errors Log
 */
class ErrorLog extends LogErrorSchema {

    private static bool   $loaded    = false;
    private static string $framePath = "";
    private static string $basePath  = "";


    /**
     * Initializes the Log
     * @return bool
     */
    #[NotTested("It registers a global handler")]
    public static function init(): bool {
        if (self::$loaded) {
            return false;
        }

        self::$loaded    = true;
        self::$framePath = Package::getBasePath();
        self::$basePath  = Application::getIndexPath();

        register_shutdown_function("\\Framework\\Log\\ErrorLog::shutdown");
        set_error_handler("\\Framework\\Log\\ErrorLog::handler");
        return true;
    }



    /**
     * Creates the List Query
     * @param LogErrorRequest $request
     * @return LogErrorQuery
     */
    #[\Override]
    protected static function createListQuery(LogErrorRequest $request): LogErrorQuery {
        $query = new LogErrorQuery();
        $query->search([
            LogErrorColumn::Description,
            LogErrorColumn::File,
        ], $request->search);

        $query->createdTime->greaterThan($request->fromDate);
        $query->createdTime->lessThan($request->toDate);

        if ($request->isResolved === "yes") {
            $query->isResolved->isTrue();
        } elseif ($request->isResolved === "no") {
            $query->isResolved->isFalse();
        }
        return $query;
    }

    /**
     * Marks the given Error(s) as Resolved
     * @param list<int>|int $logID
     * @return bool
     */
    public static function markResolved(array|int $logID): bool {
        $query = new LogErrorQuery();
        $query->logID->in(Arrays::toInts($logID));
        return self::editEntity($query, isResolved: true);
    }

    /**
     * Deletes the given Error(s)
     * @param list<int>|int $logID
     * @return bool
     */
    public static function delete(array|int $logID): bool {
        $query = new LogErrorQuery();
        $query->logID->in(Arrays::toInts($logID));
        return self::removeEntity($query);
    }

    /**
     * Deletes the items older than some days
     * @return bool
     */
    public static function deleteOld(): bool {
        $days  = Config::getErrorLogDeleteDays();
        $time  = Date::now()->subtract(days: $days);

        $query = new LogErrorQuery();
        $query->createdTime->lessThan($time);
        return self::removeEntity($query);
    }



    /**
     * Handles the PHP Shutdown
     * @return bool
     */
    public static function shutdown(): bool {
        $error = error_get_last();
        if ($error === null) {
            return false;
        }
        return self::handler(
            $error["type"],
            $error["message"],
            $error["file"],
            $error["line"],
            route: Framework::getRoute(),
        );
    }

    /**
     * Handles the PHP Error
     * @param int    $errorCode
     * @param string $description
     * @param string $filePath    Optional.
     * @param int    $line        Optional.
     * @param string $route       Optional.
     * @return bool
     */
    public static function handler(
        int $errorCode,
        string $description,
        string $filePath = "",
        int $line = 0,
        string $route = "",
    ): bool {
        if (!self::hasPrimaryKey()) {
            return false;
        }

        [ $errorText, $errorLevel ] = self::parseErrorCode($errorCode);

        $filePath    = self::getFilePath($filePath);
        $description = self::getDescription($description);
        [ $description, $backtrace ] = self::getBacktrace($description, $route);

        $query = new LogErrorQuery();
        $query->errorCode->equal($errorCode);
        $query->file->equalIf($filePath);
        $query->line->equalIf($line);
        $query->description->equal($description);
        $query->backtrace->equal($backtrace);

        if (self::getEntityTotal($query) > 0) {
            $query->updatedTime->orderByDesc();
            $query->limit(1);

            self::editEntity(
                $query,
                amount:      Assign::increase(1),
                isResolved:  false,
                updatedTime: Date::now(),
            );
        } else {
            self::createEntity(
                errorCode:   $errorCode,
                errorText:   $errorText,
                errorLevel:  $errorLevel,
                environment: Application::getEnvironment(),
                file:        $filePath,
                line:        $line,
                description: $description,
                backtrace:   $backtrace,
                amount:      1,
                isResolved:  false,
                updatedTime: Date::now(),
            );
        }
        return true;
    }

    /**
     * Maps an Error Code into an Error word, and log location.
     * @param int $errorCode
     * @return array{string,int}
     */
    private static function parseErrorCode(int $errorCode): array {
        $errorText  = "";
        $errorLevel = 0;

        switch ($errorCode) {
        case E_PARSE:
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $errorText  = "Fatal Error";
            $errorLevel = LOG_ERR;
            break;
        case E_WARNING:
        case E_USER_WARNING:
        case E_COMPILE_WARNING:
        case E_RECOVERABLE_ERROR:
            $errorText  = "Warning";
            $errorLevel = LOG_WARNING;
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errorText  = "Notice";
            $errorLevel = LOG_NOTICE;
            break;
        case E_STRICT:
            $errorText  = "Strict";
            $errorLevel = LOG_NOTICE;
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $errorText  = "Deprecated";
            $errorLevel = LOG_NOTICE;
            break;
        default:
            break;
        }
        return [ $errorText, $errorLevel ];
    }

    /**
     * Returns the File Path
     * @param string $filePath
     * @return string
     */
    private static function getFilePath(string $filePath): string {
        if (Strings::contains($filePath, self::$framePath)) {
            return Strings::replace($filePath, self::$framePath . "/", "framework/");
        }
        return Strings::replace($filePath, self::$basePath . "/", "");
    }

    /**
     * Parses and returns the Description
     * @param string $description
     * @return string
     */
    private static function getDescription(string $description): string {
        $description = Strings::replace($description, [ "'", "`" ], "");
        $description = Strings::replace($description, self::$framePath . "/", "Framework/");
        $description = Strings::replace($description, self::$basePath . "/", "");
        return $description;
    }

    /**
     * Parses and returns the Backtrace
     * @param string $description
     * @param string $route       Optional.
     * @return array{string,string}
     */
    private static function getBacktrace(string $description, string $route = ""): array {
        if (Strings::contains($description, "Stack trace")) {
            $desParts    = Strings::split($description, "Stack trace:\n");
            $description = $desParts[0] ?? $description;
            $stacktrace  = $desParts[1] ?? "";

            $trace     = Strings::split($stacktrace, "\n");
            $trace     = Arrays::reverse($trace);
            $backtrace = "";
            $index     = 1;
            foreach ($trace as $item) {
                if (Strings::startsWith($item, "#") &&
                    !Strings::contains($item, "{main}")
                ) {
                    $line       = Strings::substringAfter($item, " ", useFirst: true);
                    $backtrace .= "#{$index}- $line\n";
                    $index     += 1;
                }
            }
            return [ $description, $backtrace ];
        }

        $trace     = debug_backtrace();
        $backtrace = "";
        $index     = 1;

        // An error that ended the request, like running out of memory, is logged once
        // the script stopped, so its backtrace is only the shutdown, and the Route of
        // the request that the shutdown gives is what tells where it came from
        if ($route !== "") {
            $backtrace = "Route: $route\n";
        }

        for ($i = count($trace) - 1; $i >= 2; $i -= 1) {
            $item     = $trace[$i] ?? [];
            $function = $item["function"] ?? "";
            $file     = "<unknown file>";
            if (isset($item["file"])) {
                $file = self::getFilePath($item["file"]);
            }

            $backtrace .= "#{$index}- ";
            $backtrace .= "$file ";
            $backtrace .= "(" . ($item["line"] ?? "<unknown line>") . ") ";
            $backtrace .= " -> {$function}()" . "\n";
            $index     += 1;
        }
        return [ $description, $backtrace ];
    }
}
