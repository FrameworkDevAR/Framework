<?php
namespace Framework\Auth;

use Framework\Auth\Schema\CredentialDeviceSchema;
use Framework\Auth\Schema\CredentialDeviceColumn;
use Framework\Auth\Schema\CredentialDeviceQuery;
use Framework\Log\DeviceLog;
use Framework\System\Config;
use Framework\System\NotificationProvider;
use Framework\Utils\Arrays;
use Framework\Utils\Server;

/**
 * The Credential Devices
 */
class Device extends CredentialDeviceSchema {

    /**
     * Checks if the Credential has at least one Device of the Provider
     * @param int                  $credentialID
     * @param NotificationProvider $provider     Optional.
     * @return bool
     */
    public static function has(
        int $credentialID,
        NotificationProvider $provider = NotificationProvider::None,
    ): bool {
        $devices = self::getAllForCredential($credentialID, $provider);
        return count($devices) > 0;
    }

    /**
     * Returns all the Devices of the Provider for the given Credential
     * @param list<int>|int        $credentialID
     * @param NotificationProvider $provider     Optional.
     * @return list<string>
     */
    public static function getAllForCredential(
        array|int $credentialID,
        NotificationProvider $provider = NotificationProvider::None,
    ): array {
        if (Arrays::isEmpty($credentialID)) {
            return [];
        }

        $query = new CredentialDeviceQuery();
        if (is_array($credentialID)) {
            $query->credentialID->in($credentialID);
        } else {
            $query->credentialID->equal($credentialID);
        }
        // With none in the config every device is read, as no push goes out anyway
        $query->provider->equal(self::getProvider($provider));

        $result = self::getEntityColumn($query, CredentialDeviceColumn::PlayerID);
        return Arrays::toStrings($result);
    }



    /**
     * Adds a Device, reached through the given Provider
     * @param int                  $credentialID
     * @param string               $playerID
     * @param NotificationProvider $provider     Optional.
     * @return bool
     */
    public static function add(
        int $credentialID,
        string $playerID,
        NotificationProvider $provider = NotificationProvider::None,
    ): bool {
        $result = self::replaceEntity(
            credentialID: $credentialID,
            userAgent:    Server::getUserAgent(),
            playerID:     $playerID,
            provider:     self::getProvider($provider),
        );
        DeviceLog::added($credentialID, $playerID);
        return $result;
    }

    /**
     * Removes a Device of the Provider
     * @param int                  $credentialID
     * @param string               $playerID
     * @param NotificationProvider $provider     Optional.
     * @return bool
     */
    public static function remove(
        int $credentialID,
        string $playerID,
        NotificationProvider $provider = NotificationProvider::None,
    ): bool {
        $query = new CredentialDeviceQuery();
        $query->credentialID->equal($credentialID);
        $query->playerID->equal($playerID);
        $query->provider->equal(self::getProvider($provider));

        $result = self::removeEntity($query);
        DeviceLog::removed($credentialID, $playerID);
        return $result;
    }

    /**
     * Removes a Device from every Credential that has it
     * @param string $playerID
     * @return bool
     */
    public static function removeByPlayer(string $playerID): bool {
        $query = new CredentialDeviceQuery();
        $query->playerID->equal($playerID);
        $list = self::getEntityList($query);

        $result = false;
        foreach ($list as $elem) {
            if (self::remove($elem->credentialID, $playerID, $elem->provider)) {
                $result = true;
            }
        }
        return $result;
    }



    /**
     * Returns the given Provider, or the one of the config when none is given
     * @param NotificationProvider $provider
     * @return NotificationProvider
     */
    private static function getProvider(NotificationProvider $provider): NotificationProvider {
        if ($provider === NotificationProvider::None) {
            return NotificationProvider::fromValue(Config::getNotificationProvider());
        }
        return $provider;
    }
}
