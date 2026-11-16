<?php
namespace Framework\MCP;

use Framework\MCP\Schema\MCPGrantSchema;
use Framework\MCP\Schema\MCPGrantEntity;
use Framework\MCP\Schema\MCPGrantQuery;
use Framework\Date\Date;
use Framework\Utils\Strings;

/**
 * The MCP Grants
 */
class MCPGrant extends MCPGrantSchema {

    public const Duration = 600;



    /**
     * Returns the Grant waiting for a Credential with the given Request ID
     * @param string $requestID
     * @return MCPGrantEntity
     */
    public static function getRequest(string $requestID): MCPGrantEntity {
        if ($requestID === "") {
            return new MCPGrantEntity();
        }
        $query = new MCPGrantQuery();
        $query->requestID->equal($requestID);
        $query->code->equal("");
        $query->expirationTime->greaterThan(Date::now());
        return self::getEntity($query);
    }

    /**
     * Returns the Grant that a Credential allowed with the given Code
     * @param string $code
     * @return MCPGrantEntity
     */
    public static function getCode(string $code): MCPGrantEntity {
        if ($code === "") {
            return new MCPGrantEntity();
        }
        $query = new MCPGrantQuery();
        $query->code->equal(MCPAuth::hash($code));
        $query->expirationTime->greaterThan(Date::now());
        return self::getEntity($query);
    }



    /**
     * Creates the Grant of a Request that waits for a Credential, and returns its ID
     * @param string $clientID
     * @param string $redirectUri
     * @param string $state
     * @param string $codeChallenge
     * @param string $scope
     * @return string
     */
    public static function create(
        string $clientID,
        string $redirectUri,
        string $state,
        string $codeChallenge,
        string $scope,
    ): string {
        $requestID = Strings::randomCode(32);
        self::createEntity(
            requestID:      $requestID,
            clientID:       $clientID,
            redirectUri:    $redirectUri,
            state:          $state,
            codeChallenge:  $codeChallenge,
            scope:          $scope,
            expirationTime: Date::now()->add(seconds: self::Duration),
        );
        return $requestID;
    }

    /**
     * Gives the Grant of the given Request to the Credential, and returns its Code
     * @param string $requestID
     * @param int    $credentialID
     * @return string
     */
    public static function allow(string $requestID, int $credentialID): string {
        $code  = Strings::randomCode(40);
        $query = new MCPGrantQuery();
        $query->requestID->equal($requestID);
        self::editEntity(
            $query,
            code:           MCPAuth::hash($code),
            credentialID:   $credentialID,
            expirationTime: Date::now()->add(seconds: self::Duration),
        );
        return $code;
    }

    /**
     * Removes the Grant with the given Request ID
     * @param string $requestID
     * @return bool
     */
    public static function remove(string $requestID): bool {
        $query = new MCPGrantQuery();
        $query->requestID->equal($requestID);
        return self::removeEntity($query);
    }

    /**
     * Removes the Grants that expired
     * @return bool
     */
    public static function removeOld(): bool {
        $query = new MCPGrantQuery();
        $query->expirationTime->lessThan(Date::now());
        return self::removeEntity($query);
    }
}
