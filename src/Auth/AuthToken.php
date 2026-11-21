<?php
namespace Framework\Auth;

use Framework\Auth\RefreshToken;
use Framework\Auth\Schema\CredentialRefreshTokenEntity;
use Framework\System\Config;
use Framework\Date\Date;
use Framework\Utils\Dictionary;
use Framework\Utils\Server;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * The Auth Tokens
 */
class AuthToken {

    private const Algorithm = "HS256";
    private const KeyLength = 32;

    private static ?CredentialRefreshTokenEntity $tokenData = null;



    /**
     * Returns the duration of the Access Token
     * @return int
     */
    private static function getAccessTokenDuration(): int {
        return Config::getAuthHours() * 3600;
    }

    /**
     * Returns the duration of the Refresh Token in seconds
     * @return int
     */
    private static function getRefreshTokenDuration(): int {
        return Config::getAuthDays() * 24 * 3600;
    }



    /**
     * Returns the API Token for the given JWT
     * @param string $accessToken
     * @return string
     */
    public static function getAPIToken(string $accessToken): string {
        $accessData = self::getAccessData($accessToken);
        return $accessData->getString("apiToken");
    }


    /**
     * Returns true if the Refresh Token or the Access Token is valid
     * @param string $accessToken
     * @param string $refreshToken
     * @return bool
     */
    public static function isValid(string $accessToken, string $refreshToken): bool {
        if ($refreshToken !== "") {
            return !self::getOne($refreshToken)->isEmpty();
        }
        return self::isValidAccessToken($accessToken);
    }

    /**
     * Returns the Credentials for the given Refresh Token or JWT
     * @param string $accessToken
     * @param string $refreshToken
     * @return array{int,int}
     */
    public static function getCredentials(
        string $accessToken,
        string $refreshToken,
    ): array {
        $accessData = self::getAccessData($accessToken);
        if ($accessData->hasValue("credentialID")) {
            return [
                $accessData->getInt("credentialID"),
                $accessData->getInt("adminID"),
            ];
        }

        if ($refreshToken !== "") {
            $refresh = self::getOne($refreshToken);
            if ($refresh->credentialID !== 0) {
                return [ $refresh->credentialID, 0 ];
            }
        }

        return [ 0, 0 ];
    }



    /**
     * Returns true if the given Access Token is valid
     * @param string $accessToken
     * @return bool
     */
    public static function isValidAccessToken(string $accessToken): bool {
        $accessData = self::getAccessData($accessToken);
        return $accessData->isNotEmpty();
    }

    /**
     * Returns the data of the Access Token
     * @param string $accessToken
     * @return Dictionary
     */
    private static function getAccessData(string $accessToken): Dictionary {
        JWT::$leeway = 1000;
        $key = new Key(self::getKey(), self::Algorithm);
        try {
            $jwt = JWT::decode($accessToken, $key);
        } catch (Exception $e) {
            return new Dictionary();
        }
        return new Dictionary($jwt->data);
    }

    /**
     * Creates an Access Token
     * @param array<string,mixed> $data
     * @return string
     */
    public static function createAccessToken(array $data): string {
        JWT::$leeway = 1000;

        $time  = Date::now()->toTime();
        $token = [
            // Issued at: time when the token was generated
            "iat"  => $time,
            // Not before: 10 seconds
            "nbf"  => $time + 10,
            // Expire: In x hour
            "exp"  => $time + self::getAccessTokenDuration(),
            "data" => $data,
        ];
        return JWT::encode($token, self::getKey(), self::Algorithm);
    }

    /**
     * Returns the Key that signs the Access Tokens
     * @throws \UnexpectedValueException When the key is too short.
     * @return string
     */
    private static function getKey(): string {
        // An HS256 key with less than 32 bytes is refused by php-jwt, and on a decode it would
        // look like a token that is not valid, so it is said here instead
        $key = Config::getAuthKey();
        if (strlen($key) < self::KeyLength) {
            throw new \UnexpectedValueException("The AUTH_KEY must have at least 32 bytes");
        }
        return $key;
    }



    /**
     * Returns true if the given Refresh Token exists
     * @param string $refreshToken
     * @return CredentialRefreshTokenEntity
     */
    private static function getOne(string $refreshToken): CredentialRefreshTokenEntity {
        if (self::$tokenData !== null &&
            self::$tokenData->refreshToken === $refreshToken
        ) {
            return self::$tokenData;
        }

        self::$tokenData = RefreshToken::get($refreshToken);
        return self::$tokenData;
    }

    /**
     * Returns all the Refresh Tokens for the given Credential
     * @param int $credentialID
     * @return list<array<string,int|string>>
     */
    public static function getAllForCredential(int $credentialID): array {
        if ($credentialID === 0) {
            return [];
        }

        $list   = RefreshToken::getAllForCredential($credentialID);
        $result = [];

        foreach ($list as $elem) {
            $result[] = [
                "refreshToken" => $elem->refreshToken,
                "platform"     => Server::getPlatform($elem->userAgent),
                "time"         => $elem->createdTime->toTime(),
            ];
        }
        return $result;
    }



    /**
     * Creates a Refresh Token
     * @param int $credentialID
     * @return string
     */
    public static function createRefreshToken(int $credentialID): string {
        return RefreshToken::create($credentialID, self::getRefreshTokenDuration());
    }

    /**
     * Updates a Refresh Token
     * @param string $refreshToken
     * @return string
     */
    public static function updateRefreshToken(string $refreshToken): string {
        if ($refreshToken === "") {
            return "";
        }

        $currentToken = self::getOne($refreshToken);
        $lastHour     = Date::now()->subtract(hours: 1);
        if ($currentToken->isEmpty() || $currentToken->modifiedTime->isAfter($lastHour)) {
            return "";
        }

        return RefreshToken::update($refreshToken, self::getRefreshTokenDuration());
    }

    /**
     * Deletes a Refresh Token
     * @param string $refreshToken
     * @return bool
     */
    public static function deleteRefreshToken(string $refreshToken): bool {
        if ($refreshToken !== "") {
            return RefreshToken::remove($refreshToken);
        }
        return false;
    }

    /**
     * Deletes all the Refresh Tokens for the given Credential
     * @param int $credentialID
     * @return bool
     */
    public static function deleteAllForCredential(int $credentialID): bool {
        if ($credentialID !== 0) {
            return RefreshToken::removeAll($credentialID);
        }
        return false;
    }

    /**
     * Deletes the old Refresh Tokens
     * @return bool
     */
    public static function deleteOld(): bool {
        return RefreshToken::removeOld();
    }
}
