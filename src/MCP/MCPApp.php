<?php
namespace Framework\MCP;

use Framework\MCP\Schema\MCPAppSchema;
use Framework\MCP\Schema\MCPAppEntity;
use Framework\MCP\Schema\MCPAppQuery;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * The MCP Apps
 */
class MCPApp extends MCPAppSchema {

    /**
     * Returns the App with the given Client ID
     * @param string $clientID
     * @return MCPAppEntity
     */
    public static function get(string $clientID): MCPAppEntity {
        if ($clientID === "") {
            return new MCPAppEntity();
        }
        $query = new MCPAppQuery();
        $query->clientID->equal($clientID);
        return self::getEntity($query);
    }

    /**
     * Returns true if the given App can be sent back to the given Url
     * @param MCPAppEntity $app
     * @param string       $redirectUri
     * @return bool
     */
    public static function hasRedirect(MCPAppEntity $app, string $redirectUri): bool {
        $redirectUris = JSON::decodeAsStrings($app->redirectUris);
        return $redirectUri !== "" && Arrays::contains($redirectUris, $redirectUri);
    }

    /**
     * Registers an App with the given Name and Urls, and returns its Client ID
     * @param string       $name
     * @param list<string> $redirectUris
     * @param bool         $writesAsUser Optional.
     * @return string
     */
    public static function register(
        string $name,
        array $redirectUris,
        bool $writesAsUser = false,
    ): string {
        $clientID = Strings::randomCode(32);
        self::createEntity(
            clientID:     $clientID,
            name:         $name,
            redirectUris: JSON::encode($redirectUris),
            writesAsUser: $writesAsUser,
        );
        return $clientID;
    }
}
