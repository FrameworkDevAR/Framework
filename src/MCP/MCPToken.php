<?php
namespace Framework\MCP;

use Framework\MCP\Schema\MCPTokenSchema;
use Framework\MCP\Schema\MCPTokenEntity;
use Framework\MCP\Schema\MCPTokenQuery;
use Framework\Date\Date;
use Framework\Utils\Strings;

/**
 * The MCP Tokens
 */
class MCPToken extends MCPTokenSchema {

    public const AccessDuration  = 3600;
    public const RefreshDuration = 30 * 24 * 3600;



    /**
     * Returns the Token with the given Access Token, while it lasts
     * @param string $accessToken
     * @return MCPTokenEntity
     */
    public static function getAccess(string $accessToken): MCPTokenEntity {
        if ($accessToken === "") {
            return new MCPTokenEntity();
        }
        $query = new MCPTokenQuery();
        $query->accessToken->equal(MCPAuth::hash($accessToken));
        $query->expirationTime->greaterThan(Date::now());
        return self::getEntity($query);
    }

    /**
     * Returns the Token with the given Refresh Token, while it lasts
     * @param string $refreshToken
     * @return MCPTokenEntity
     */
    public static function getRefresh(string $refreshToken): MCPTokenEntity {
        if ($refreshToken === "") {
            return new MCPTokenEntity();
        }
        $query = new MCPTokenQuery();
        $query->refreshToken->equal(MCPAuth::hash($refreshToken));
        $query->refreshTime->greaterThan(Date::now());
        return self::getEntity($query);
    }



    /**
     * Creates the Tokens of a Credential for an App, and returns them as they are stored hashed
     * @param string $clientID
     * @param int    $credentialID
     * @return array{string,string}
     */
    public static function create(string $clientID, int $credentialID): array {
        $accessToken  = Strings::randomCode(48);
        $refreshToken = Strings::randomCode(48);
        self::createEntity(
            accessToken:    MCPAuth::hash($accessToken),
            refreshToken:   MCPAuth::hash($refreshToken),
            clientID:       $clientID,
            credentialID:   $credentialID,
            expirationTime: Date::now()->add(seconds: self::AccessDuration),
            refreshTime:    Date::now()->add(seconds: self::RefreshDuration),
        );
        return [ $accessToken, $refreshToken ];
    }

    /**
     * Removes the Token with the given hashed Access Token
     * @param string $accessToken
     * @return bool
     */
    public static function remove(string $accessToken): bool {
        $query = new MCPTokenQuery();
        $query->accessToken->equal($accessToken);
        return self::removeEntity($query);
    }

    /**
     * Removes all the Tokens of the given Credential
     * @param int $credentialID
     * @return bool
     */
    public static function removeAll(int $credentialID): bool {
        $query = new MCPTokenQuery();
        $query->credentialID->equal($credentialID);
        return self::removeEntity($query);
    }

    /**
     * Removes the Tokens that can not be refreshed anymore
     * @return bool
     */
    public static function removeOld(): bool {
        $query = new MCPTokenQuery();
        $query->refreshTime->lessThan(Date::now());
        return self::removeEntity($query);
    }
}
