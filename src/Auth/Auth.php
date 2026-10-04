<?php
namespace Framework\Auth;

use Framework\Analysis\Attr\NotTested;
use Framework\Auth\AuthToken;
use Framework\Auth\Credential;
use Framework\Auth\Reset;
use Framework\Auth\Spam;
use Framework\Auth\Schema\CredentialEntity;
use Framework\Auth\Schema\CredentialStatus;
use Framework\Core\Configs;
use Framework\Intl\NLS;
use Framework\File\Storage;
use Framework\File\FilePath;
use Framework\Log\ActionLog;
use Framework\System\Access;
use Framework\System\Config;
use Framework\Date\TimeZone;
use Framework\Utils\Strings;

/**
 * The Auth
 */
class Auth {

    private static string $refreshToken = "";
    private static bool   $sendRefresh  = false;

    private static Access $accessName   = Access::General;
    private static int    $credentialID = 0;
    private static int    $adminID      = 0;
    private static int    $userID       = 0;
    private static string $apiToken     = "";

    private static ?CredentialEntity $credential = null;
    private static ?CredentialEntity $admin      = null;



    /**
     * Returns true if the Auth Login is disabled
     * @return bool
     */
    public static function isLoginDisabled(): bool {
        return !Config::isAuthActive();
    }

    /**
     * Validates the Credential
     * @param string $accessToken
     * @param string $refreshToken
     * @param string $langcode
     * @param int    $timezone
     * @return bool
     */
    public static function validateCredential(
        string $accessToken,
        string $refreshToken,
        string $langcode,
        int $timezone,
    ): bool {
        Reset::deleteOld();
        AuthToken::deleteOld();

        // Validate the API Token
        $apiToken = AuthToken::getAPIToken($accessToken);
        if ($apiToken !== "") {
            return self::validateAPI($apiToken);
        }

        // Validate the Credential Tokens
        if (!AuthToken::isValid($accessToken, $refreshToken)) {
            return false;
        }

        // Retrieve the Token data
        [ $credentialID, $adminID ] = AuthToken::getCredentials($accessToken, $refreshToken);
        $credential = Credential::getByID($credentialID, complete: true);
        if ($credential->isEmpty() || $credential->isDeleted) {
            return false;
        }

        // Retrieve the Admin
        $admin = new CredentialEntity();
        if ($adminID !== 0) {
            $admin = Credential::getByID($adminID, complete: true);
        }

        // Update the Refresh Token
        self::$refreshToken = $refreshToken;
        AuthToken::updateRefreshToken($refreshToken);

        // Set the new Language and Timezone if required
        self::setLanguageTimezone($credential, $admin, $langcode, $timezone);

        // Set the Credential
        self::setCredential($credential, $admin, $credential->currentUser);

        // Start or reuse a log session
        ActionLog::startSession();
        return true;
    }

    /**
     * Sets the Language and Timezone if required
     * @param CredentialEntity $credential
     * @param CredentialEntity $admin
     * @param string           $langcode
     * @param int              $timezone
     * @return void
     */
    private static function setLanguageTimezone(
        CredentialEntity $credential,
        CredentialEntity $admin,
        string $langcode,
        int $timezone,
    ): void {
        $entity = $credential;
        if ($admin->exists()) {
            $entity = $admin;
        }

        if ($langcode !== "" && !$entity->hasValue("language")) {
            Credential::setLanguage($entity->id, $langcode);
            $entity->language = $langcode;
        }
        if ($timezone !== 0) {
            Credential::setTimezone($entity->id, $timezone);
            $entity->timezone = $timezone;
        }
    }

    /**
     * Returns the API Token
     * @return string
     */
    public static function getApiToken(): string {
        return Config::getAuthApiToken();
    }

    /**
     * Returns the Token that grants the given Access, which the environment holds
     * @param Access $accessName
     * @return string
     */
    public static function getTokenFor(Access $accessName): string {
        $tokenKey = Access::getTokenKey($accessName);
        if ($tokenKey === "") {
            return "";
        }
        return Configs::getString($tokenKey);
    }

    /**
     * Returns the Tokens that grant the given Access, as its key can hold several
     * separated by commas
     * @param Access $accessName
     * @return list<string>
     */
    public static function getTokensFor(Access $accessName): array {
        $tokenKey = Access::getTokenKey($accessName);
        if ($tokenKey === "") {
            return [];
        }

        $result = [];
        foreach (Configs::getList($tokenKey) as $accessToken) {
            $accessToken = Strings::trim($accessToken);
            if ($accessToken !== "") {
                $result[] = $accessToken;
            }
        }
        return $result;
    }

    /**
     * Validates and Sets the auth as API
     * @param string $token
     * @return bool
     */
    public static function validateAPI(string $token): bool {
        // Refused rather than compared, or an app with no token configured
        // would let in a request that also brings none
        if ($token === "") {
            return false;
        }

        // An Access of its own, reached with the token the environment gives it
        foreach (Access::getTokenAccesses() as $accessName) {
            foreach (self::getTokensFor($accessName) as $accessToken) {
                if (hash_equals($accessToken, $token)) {
                    self::$apiToken   = $token;
                    self::$accessName = $accessName;
                    return true;
                }
            }
        }

        $apiToken = self::getApiToken();
        if ($apiToken !== "" && hash_equals($apiToken, $token)) {
            self::$apiToken   = $token;
            self::$accessName = Access::API;
            return true;
        }
        return false;
    }

    /**
     * Validates and Sets the auth as API Internal
     * @return void
     */
    public static function validateInternal(): void {
        self::$accessName = Access::API;
    }



    /**
     * Checks the Spam Protection for the Login
     * @return bool
     */
    public static function spamProtection(): bool {
        return Spam::protect();
    }

    /**
     * Logins the given Credential
     * @param CredentialEntity $credential
     * @return void
     */
    public static function login(CredentialEntity $credential): void {
        $isNew = self::$credentialID !== $credential->id;
        self::setCredential($credential, null, $credential->currentUser);

        Credential::updateLoginTime($credential->id);
        ActionLog::startSession(destroy: true);

        $path = FilePath::getTempPath($credential->id, create: false);
        Storage::emptyDir($path);
        Reset::delete($credential->id);

        if ($isNew) {
            self::$refreshToken = AuthToken::createRefreshToken($credential->id);
            self::$sendRefresh  = true;
        }
    }

    /**
     * Logouts the Current Credential
     * @return void
     */
    public static function logout(): void {
        AuthToken::deleteRefreshToken(self::$refreshToken);
        ActionLog::endSession();

        self::$refreshToken = "";
        self::$sendRefresh  = false;
        self::$accessName   = Access::General;
        self::$apiToken     = "";
        self::$credential   = null;
        self::$credentialID = 0;
        self::$adminID      = 0;
        self::$userID       = 0;
    }

    /**
     * Returns true if the Credential can log in
     * @param CredentialEntity $credential
     * @return bool
     */
    public static function canLogin(CredentialEntity $credential): bool {
        return (
            $credential->exists() &&
            !$credential->isDeleted &&
            $credential->status === CredentialStatus::Active
        );
    }



    /**
     * Logins as the given Credential from an Admin account
     * @param int $credentialID
     * @return bool
     */
    public static function loginAs(int $credentialID): bool {
        if (self::$credential === null) {
            return false;
        }

        $admin = self::$credential;
        $user  = Credential::getByID($credentialID, complete: true);

        if (self::canLoginAs($admin, $user)) {
            self::setCredential($user, $admin, $user->currentUser);
            return true;
        }
        return false;
    }

    /**
     * Logouts as the current Credential and logins back as the Admin
     * @return int
     */
    public static function logoutAs(): int {
        if (self::$admin === null || self::$credential === null) {
            return 0;
        }

        if (self::canLoginAs(self::$admin, self::$credential)) {
            $credentialID = self::$credential->id;
            self::setCredential(self::$admin);
            return $credentialID;
        }
        return 0;
    }

    /**
     * Returns the Credential to Login from the given Email
     * @param string $email
     * @return CredentialEntity
     */
    public static function getLoginCredential(string $email): CredentialEntity {
        $email = Strings::toString($email);
        $parts = Strings::split($email, "|");
        $user  = null;

        if (isset($parts[0]) && isset($parts[1])) {
            $admin = Credential::getByEmail($parts[0], complete: true);
            $user  = Credential::getByEmail($parts[1], complete: true);

            if (self::canLoginAs($admin, $user)) {
                $user->password = $admin->password;
                $user->salt     = $admin->salt;
                $user->adminID  = $admin->id;
            }
        } else {
            $user = Credential::getByEmail($email, complete: true);
        }
        return $user;
    }

    /**
     * Returns true if the Admin can log in as the User
     * @param CredentialEntity $admin
     * @param CredentialEntity $user
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function canLoginAs(
        CredentialEntity $admin,
        CredentialEntity $user,
    ): bool {
        return (
            self::canLogin($admin) &&
            $user->exists() &&
            Access::getLevel($admin->access) >= Access::getLevel($user->access) &&
            Access::isValidAdmin($admin->access)
        );
    }



    /**
     * Sets the Credential
     * @param CredentialEntity      $credential
     * @param CredentialEntity|null $admin      Optional.
     * @param int                   $userID     Optional.
     * @return void
     */
    public static function setCredential(
        CredentialEntity $credential,
        ?CredentialEntity $admin = null,
        int $userID = 0,
    ): void {
        self::$credential   = $credential;
        self::$credentialID = $credential->id;
        self::$userID       = $userID;

        if ($credential->userAccess !== Access::None) {
            self::$accessName = $credential->userAccess;
        } else {
            self::$accessName = $credential->access;
        }

        $language = $credential->language;
        $timezone = $credential->timezone;

        if ($admin !== null && $admin->exists()) {
            self::$admin   = $admin;
            self::$adminID = $admin->id;
            $language = $admin->language;
            $timezone = $admin->timezone;
        } else {
            self::$admin   = null;
            self::$adminID = 0;
        }

        if ($language !== "") {
            NLS::setLanguage($language);
        }
        if ($timezone !== 0) {
            TimeZone::setTimeZone((float)$timezone);
        }
    }

    /**
     * Updates the Credential
     * @return bool
     */
    public static function updateCredential(): bool {
        if (self::$credentialID !== 0) {
            $credential = Credential::getByID(self::$credentialID, complete: true);
            if ($credential->exists()) {
                self::$credential = $credential;
                return true;
            }
        }
        return false;
    }

    /**
     * Sets the Current User
     * @param int           $userID
     * @param Access|string $accessName
     * @return void
     */
    public static function setCurrentUser(int $userID, Access|string $accessName): void {
        self::$userID     = $userID;
        self::$accessName = Access::fromValue($accessName);

        ActionLog::endSession();
        ActionLog::startSession(destroy: true);
    }

    /**
     * Creates and returns the Access token
     * @return string
     */
    public static function getAccessToken(): string {
        // Create an API Token
        if (self::hasAPI()) {
            return AuthToken::createAccessToken([
                "isAPI"    => true,
                "apiToken" => self::$apiToken,
            ]);
        }

        // Check if there is a Credential
        if (self::$credential === null) {
            return "";
        }

        // The general data
        $data = [
            "accessName"       => self::$accessName->toString(),
            "credentialID"     => self::$credentialID,
            "adminID"          => self::$adminID,
            "userID"           => self::$userID,
            "email"            => self::$credential->email,
            "name"             => self::$credential->name,
            "language"         => self::$credential->language,
            "avatar"           => self::$credential->avatar,
            "appearance"       => self::$credential->appearance,
            "reqPassChange"    => self::$credential->reqPassChange,
            "askNotifications" => self::$credential->askNotifications,
            "loggedAsUser"     => self::isLoggedAsUser(),
            "isAdmin"          => self::isAdmin(),
        ];

        // Add fields from the Config
        $fields = Config::getAuthFields();
        foreach ($fields as $field) {
            $data[$field] = self::$credential->get($field);
        }

        return AuthToken::createAccessToken($data);
    }

    /**
     * Returns the Refresh Token
     * @return string
     */
    public static function getRefreshToken(): string {
        if (!self::hasCredential() || !self::$sendRefresh) {
            return "";
        }
        return self::$refreshToken;
    }



    /**
     * Returns the Credential Entity
     * @return CredentialEntity
     */
    public static function getCredential(): CredentialEntity {
        if (self::$credential === null) {
            return new CredentialEntity();
        }
        return self::$credential;
    }

    /**
     * Returns the Credential ID
     * @return int
     */
    public static function getID(): int {
        return self::$credentialID;
    }

    /**
     * Returns the Admin ID
     * @return int
     */
    public static function getAdminID(): int {
        return self::$adminID;
    }

    /**
     * Returns the Credential Current User
     * @return int
     */
    public static function getUserID(): int {
        return self::$userID;
    }

    /**
     * Returns the Access Name
     * @return Access
     */
    public static function getAccessName(): Access {
        return self::$accessName;
    }

    /**
     * Returns the Language of the Credential
     * @return string
     */
    public static function getLanguage(): string {
        if (self::$credential === null) {
            return "";
        }
        return self::$credential->language;
    }

    /**
     * Returns the path used to store the temp files
     * @return string
     */
    public static function getTempPath(): string {
        if (self::$credentialID !== 0) {
            return FilePath::getTempPath(self::$credentialID);
        }
        return "";
    }



    /**
     * Returns true if the User is Logged in
     * @return bool
     */
    public static function isLoggedIn(): bool {
        return self::hasCredential() || self::hasAPI();
    }

    /**
     * Returns true or false if the admin is logged in as a user
     * @return bool
     */
    public static function isLoggedAsUser(): bool {
        return self::$adminID !== 0;
    }

    /**
     * Returns true if there is a Credential
     * @return bool
     */
    public static function hasCredential(): bool {
        return self::$credentialID !== 0;
    }

    /**
     * Returns true if there is an API
     * @return bool
     */
    public static function hasAPI(): bool {
        // A request that came in with a token is an API one whatever Access it
        // was given, so it reads its payload and is answered without tokens
        return self::$apiToken !== "" || self::$accessName === Access::API;
    }

    /**
     * Returns true or false if the current used is actually an admin
     * @return bool
     */
    public static function isAdmin(): bool {
        return self::$credential !== null &&
            Access::isValidAdmin(self::$credential->access);
    }



    /**
     * Returns true if the password is correct for the current auth
     * @param string $password
     * @return bool
     */
    public static function isPasswordCorrect(string $password): bool {
        return Credential::isPasswordCorrect(self::$credentialID, $password);
    }

    /**
     * Returns true if the current Auth requires login
     * @param Access $accessName
     * @return bool
     */
    public static function requiresLogin(Access $accessName): bool {
        return $accessName !== Access::General && !self::isLoggedIn();
    }

    /**
     * Returns true if the user has that Access
     * @param Access $accessName
     * @return bool
     */
    public static function grant(Access $accessName): bool {
        if (Access::isValidAPI(self::$accessName)) {
            return (
                Access::isValidAPI($accessName) ||
                Access::isValidGeneral($accessName)
            );
        }

        $currentLevel   = Access::getLevel(self::$accessName);
        $requestedLevel = Access::getLevel($accessName);
        return $currentLevel >= $requestedLevel;
    }
}
