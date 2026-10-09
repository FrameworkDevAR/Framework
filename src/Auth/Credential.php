<?php
namespace Framework\Auth;

use Framework\Analysis\Attr\NotTested;
use Framework\IO\Search;
use Framework\IO\Select;
use Framework\Database\Query\QueryBuilder;
use Framework\Auth\Passkey;
use Framework\Auth\Schema\CredentialSchema;
use Framework\Auth\Schema\CredentialRequest;
use Framework\Auth\Schema\CredentialEntity;
use Framework\Auth\Schema\CredentialColumn;
use Framework\Auth\Schema\CredentialStatus;
use Framework\Auth\Schema\CredentialQuery;
use Framework\Intl\NLS;
use Framework\System\Access;
use Framework\System\Path;
use Framework\Date\Date;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;
use Framework\Utils\Utils;

/**
 * The Auth Credential
 * @phpstan-import-type QueryValue from QueryBuilder
 */
class Credential extends CredentialSchema {

    /**
     * Returns the Credential with the given ID
     * @param int  $credentialID
     * @param bool $complete     Optional.
     * @return CredentialEntity
     */
    #[\Override]
    public static function getByID(
        int $credentialID,
        bool $complete = false,
    ): CredentialEntity {
        $query = new CredentialQuery();
        $query->credentialID->equal($credentialID);
        return self::getCredential($query, $complete);
    }

    /**
     * Returns the Credential with the given Email
     * @param string $email
     * @param bool   $complete Optional.
     * @return CredentialEntity
     */
    public static function getByEmail(
        string $email,
        bool $complete = true,
    ): CredentialEntity {
        $query = new CredentialQuery();
        $query->email->equal($email);
        return self::getCredential($query, $complete);
    }

    /**
     * Returns the Credential with the given Access Token
     * @param string $accessToken
     * @param bool   $complete    Optional.
     * @return CredentialEntity
     */
    public static function getByAccessToken(
        string $accessToken,
        bool $complete = true,
    ): CredentialEntity {
        $query = new CredentialQuery();
        $query->accessToken->equal($accessToken);
        $query->tokenExpiration->greaterThan(Date::now());
        return self::getCredential($query, $complete);
    }

    /**
     * Requests a single row from the database
     * @param CredentialQuery|null $query    Optional.
     * @param bool                 $complete Optional.
     * @return CredentialEntity
     */
    private static function getCredential(
        ?CredentialQuery $query = null,
        bool $complete = false,
    ): CredentialEntity {
        $list = self::getCredentials($query, null, $complete);
        if (isset($list[0])) {
            return $list[0];
        }
        return new CredentialEntity();
    }



    /**
     * Returns true if there is a Credential with the given ID and Access(s)
     * @param int                  $credentialID
     * @param CredentialQuery|null $query        Optional.
     * @return bool
     */
    #[\Override]
    public static function exists(
        int $credentialID,
        ?CredentialQuery $query = null,
    ): bool {
        if ($query === null) {
            $query = new CredentialQuery();
        }
        $query->credentialID->equal($credentialID);
        return self::entityExists($query);
    }

    /**
     * Returns true if there is a Credential with the given Email
     * @param string               $email
     * @param int                  $skipID Optional.
     * @param CredentialQuery|null $query  Optional.
     * @return bool
     */
    public static function emailExists(
        string $email,
        int $skipID = 0,
        ?CredentialQuery $query = null,
    ): bool {
        if ($query === null) {
            $query = new CredentialQuery();
        }
        $query->email->equal($email);
        $query->credentialID->notEqual($skipID);
        return self::entityExists($query);
    }



    /**
     * Returns all the Credentials
     * @param CredentialQuery        $query
     * @param CredentialRequest|null $sort  Optional.
     * @return list<CredentialEntity>
     */
    public static function getList(
        CredentialQuery $query,
        ?CredentialRequest $sort = null,
    ): array {
        return self::getCredentials($query, $sort, complete: false);
    }

    /**
     * Returns all the Credentials for the given Query
     * @param CredentialQuery|null   $query    Optional.
     * @param CredentialRequest|null $sort     Optional.
     * @param bool                   $complete Optional.
     * @return list<CredentialEntity>
     */
    private static function getCredentials(
        ?CredentialQuery $query = null,
        ?CredentialRequest $sort = null,
        bool $complete = false,
    ): array {
        $list   = self::getEntityList($query, $sort);
        $result = [];

        foreach ($list as $elem) {
            $elem->accessName = Access::getName($elem->access);

            if ($elem->avatar !== "") {
                $elem->avatarFile = $elem->avatar;
                $elem->avatar     = Path::getAvatarsUrl($elem->avatar);
            }
            if (!$complete) {
                $elem->password        = "";
                $elem->salt            = "";
                $elem->accessToken     = "";
                $elem->tokenExpiration = Date::empty();
            }
            $result[] = $elem;
        }
        return $result;
    }

    /**
     * Returns the total amount of Credentials
     * @param CredentialQuery $query
     * @return int
     */
    public static function getTotal(CredentialQuery $query): int {
        return self::getEntityTotal($query);
    }



    /**
     * Returns a select of Credentials for the given Query
     * @param CredentialQuery $query
     * @return list<Select>
     */
    public static function getSelect(CredentialQuery $query): array {
        $list   = self::getEntityList($query);
        $result = [];
        foreach ($list as $elem) {
            $result[] = new Select(
                $elem->credentialID,
                self::getName($elem),
            );
        }
        return $result;
    }

    /**
     * Returns the Credentials that contains the text and the given Accesses
     * @param string               $text
     * @param int                  $amount     Optional.
     * @param CredentialQuery|null $query      Optional.
     * @param bool                 $withEmail  Optional.
     * @param bool                 $splitValue Optional.
     * @return list<Search>
     */
    public static function search(
        string $text,
        int $amount = 10,
        ?CredentialQuery $query = null,
        bool $withEmail = true,
        bool $splitValue = true,
    ): array {
        if ($query === null) {
            $query = new CredentialQuery();
        }
        $query->search([
            CredentialColumn::FirstName,
            CredentialColumn::LastName,
            CredentialColumn::Email,
        ], $text, splitValue: $splitValue);
        $query->limit($amount);

        $list   = self::getEntityList($query);
        $result = [];
        foreach ($list as $elem) {
            $result[] = new Search(
                $elem->credentialID,
                self::getName($elem, $withEmail),
            );
        }
        return $result;
    }



    /**
     * Returns true if the given password is correct for the given Credential ID
     * @param CredentialEntity|int $credential
     * @param string               $password
     * @return bool
     */
    public static function isPasswordCorrect(
        CredentialEntity|int $credential,
        string $password,
    ): bool {
        if (!($credential instanceof CredentialEntity)) {
            $credential = self::getByID($credential, complete: true);
        }
        if ($credential->isEmpty()) {
            return false;
        }
        if ($credential->passExpiration->isPast()) {
            return false;
        }

        $hash = self::createHash($password, $credential->salt);
        return $hash["password"] === $credential->password;
    }

    /**
     * Returns true if the given Credential requires a password change
     * @param int    $credentialID Optional.
     * @param string $email        Optional.
     * @return bool
     */
    public static function reqPassChange(
        int $credentialID = 0,
        string $email = "",
    ): bool {
        $email = Strings::toString($email);
        if ($credentialID === 0 && $email === "") {
            return false;
        }

        $query = new CredentialQuery();
        $query->startOr();
        $query->credentialID->equalIf($credentialID);
        $query->email->equalIf($email);
        $query->endOr();
        return self::getEntityValue($query, CredentialColumn::ReqPassChange) === "1";
    }



    /**
     * Creates a new Credential
     * @param CredentialRequest $request
     * @param bool              $fromAdmin Optional.
     * @return int
     */
    public static function create(
        CredentialRequest $request,
        bool $fromAdmin = false,
    ): int {
        $credentialID = self::createEntity(
            $request,
            name:             Strings::merge($request->firstName, $request->lastName),
            lastLogin:        Date::now(),
            currentLogin:     Date::now(),
            reqPassChange:    $fromAdmin ? $request->reqPassChange : null,
            observations:     $fromAdmin ? $request->observations  : null,
            askNotifications: true,
        );
        self::setAccess($credentialID, $request->access);
        self::setPassword($credentialID, $request->password);
        self::setStatus($credentialID, $request->status);
        return $credentialID;
    }

    /**
     * Edits the given Credential
     * @param int               $credentialID
     * @param CredentialRequest $request
     * @param bool              $fromAdmin    Optional.
     * @return void
     */
    public static function edit(
        int $credentialID,
        CredentialRequest $request,
        bool $fromAdmin = false,
    ): void {
        self::editEntity(
            $credentialID,
            $request,
            name:          Strings::merge($request->firstName, $request->lastName),
            reqPassChange: $fromAdmin ? $request->reqPassChange : null,
            observations:  $fromAdmin ? $request->observations  : null,
        );
        self::setAccess($credentialID, $request->access);
        self::setPassword($credentialID, $request->password);
        self::setStatus($credentialID, $request->status);
    }

    /**
     * Deletes the given Credential
     * @param int $credentialID
     * @return bool
     */
    public static function delete(int $credentialID): bool {
        return self::deleteEntity($credentialID);
    }

    /**
     * Destroys the Credential
     * @param int $credentialID
     * @return void
     */
    public static function destroy(int $credentialID): void {
        self::editEntity(
            $credentialID,
            currentUser:      0,
            email:            "mail@mail.com",
            firstName:        NLS::getString("GENERAL_NAME"),
            lastName:         NLS::getString("GENERAL_LAST_NAME"),
            phone:            "",
            language:         "",
            avatar:           "",
            password:         "",
            salt:             "",
            reqPassChange:    false,
            passExpiration:   Date::empty(),
            accessToken:      "",
            tokenExpiration:  Date::empty(),
            observations:     "",
            sendEmails:       false,
            sendEmailNotis:   false,
            timezone:         0,
            currentLogin:     Date::empty(),
            lastLogin:        Date::empty(),
            askNotifications: false,
            status:           CredentialStatus::Inactive,
            isDeleted:        true,
        );

        // A Passkey would still prove the Credential that was destroyed
        Passkey::deleteAllForCredential($credentialID);
    }



    /**
     * Sets the current User for the given Credential
     * @param int $credentialID
     * @param int $userID
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setCurrentUser(int $credentialID, int $userID): void {
        self::editEntity($credentialID, currentUser: $userID);
    }

    /**
     * Sets the Credential Access
     * @param int    $credentialID
     * @param Access $access
     * @return void
     */
    public static function setAccess(int $credentialID, Access $access): void {
        if ($access !== Access::None) {
            self::editEntity($credentialID, access: $access);
        }
    }

    /**
     * Sets the Credential Email
     * @param int    $credentialID
     * @param string $email
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setEmail(int $credentialID, string $email): void {
        self::editEntity($credentialID, email: $email);
    }

    /**
     * Updates the Language for the given Credential
     * @param int    $credentialID
     * @param string $language
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setLanguage(int $credentialID, string $language): void {
        $language = Strings::substringBefore($language, "-");
        self::editEntity($credentialID, language: $language);
    }

    /**
     * Updates the Timezone for the given Credential
     * @param int $credentialID
     * @param int $timezone
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setTimezone(int $credentialID, int $timezone): void {
        self::editEntity($credentialID, timezone: $timezone);
    }

    /**
     * Sets the Credential Avatar
     * @param int    $credentialID
     * @param string $avatar
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setAvatar(int $credentialID, string $avatar): void {
        self::editEntity($credentialID, avatar: $avatar);
    }

    /**
     * Sets the Credential Appearance
     * @param int    $credentialID
     * @param string $appearance
     * @return void
     */
    #[NotTested("It needs a Database")]
    public static function setAppearance(int $credentialID, string $appearance): void {
        self::editEntity($credentialID, appearance: $appearance);
    }

    /**
     * Sets the Credential Status
     * @param int              $credentialID
     * @param CredentialStatus $status
     * @return void
     */
    public static function setStatus(int $credentialID, CredentialStatus $status): void {
        if ($status !== CredentialStatus::None) {
            self::editEntity($credentialID, status: $status);
        }
    }

    /**
     * Sets the Credential Password
     * @param int    $credentialID
     * @param string $password
     * @return array{password:string,salt:string}
     */
    public static function setPassword(int $credentialID, string $password): array {
        if ($password === "") {
            return [ "password" => "", "salt" => "" ];
        }

        $hash = self::createHash($password);
        self::editEntity(
            $credentialID,
            password:       $hash["password"],
            salt:           $hash["salt"],
            passExpiration: Date::empty(),
            reqPassChange:  false,
        );
        return $hash;
    }

    /**
     * Sets the required password change for the given Credential
     * @param int  $credentialID
     * @param bool $reqPassChange
     * @return bool
     */
    public static function setReqPassChange(int $credentialID, bool $reqPassChange): bool {
        return self::editEntity($credentialID, reqPassChange: $reqPassChange);
    }

    /**
     * Sets a temporary Password to the given Credential
     * @param int $credentialID
     * @param int $hours        Optional.
     * @return string
     */
    public static function setTempPass(int $credentialID, int $hours = 48): string {
        $password = Strings::randomCode(10, "lud");
        $hash     = self::createHash($password);
        self::editEntity(
            $credentialID,
            password:       $hash["password"],
            salt:           $hash["salt"],
            passExpiration: Date::now()->add(hours: $hours),
            reqPassChange:  true,
        );
        return $password;
    }

    /**
     * Sets an Access Token to the given Credential
     * @param int $credentialID
     * @param int $hours        Optional.
     * @return string
     */
    public static function setAccessToken(int $credentialID, int $hours = 4): string {
        $expiration = $hours > 0 ? Date::now()->add(hours: $hours) : Date::empty();
        $credential = self::getByID($credentialID, complete: true);

        if ($credential->tokenExpiration->isPast()) {
            self::editEntity($credentialID, tokenExpiration: $expiration);
            return $credential->accessToken;
        }

        $accessToken = Strings::randomCode(30, "lud");
        self::editEntity(
            $credentialID,
            accessToken:     $accessToken,
            tokenExpiration: $expiration,
        );
        return $accessToken;
    }

    /**
     * Removes an Access Token of the given Credential
     * @param int $credentialID
     * @return bool
     */
    public static function removeAccessToken(int $credentialID): bool {
        return self::editEntity(
            $credentialID,
            accessToken:     "",
            tokenExpiration: Date::empty(),
        );
    }

    /**
     * Stops asking the Credential for Notifications
     * @param int $credentialID
     * @return bool
     */
    public static function dontAskNotifications(int $credentialID): bool {
        return self::editEntity($credentialID, askNotifications: false);
    }

    /**
     * Updates the login time for the given Credential
     * @param int $credentialID
     * @return bool
     */
    public static function updateLoginTime(int $credentialID): bool {
        $currentLogin = self::getValue($credentialID, CredentialColumn::CurrentLogin);
        return self::editEntity(
            $credentialID,
            lastLogin:    Date::create($currentLogin),
            currentLogin: Date::now(),
        );
    }

    /**
     * Sets the Credential Progress
     * @param int $credentialID
     * @param int $value
     * @return bool
     */
    public static function setProgress(int $credentialID, int $value): bool {
        return self::editEntity($credentialID, progressValue: $value);
    }

    /**
     * Gets a Credential Value
     * @param int              $credentialID
     * @param CredentialColumn $column
     * @return int|string
     */
    public static function getValue(int $credentialID, CredentialColumn $column): int|string {
        $query = new CredentialQuery();
        $query->credentialID->equal($credentialID);
        return self::getEntityValue($query, $column);
    }

    /**
     * Sets a Credential Value
     * @param int              $credentialID
     * @param CredentialColumn $column
     * @param QueryValue       $value
     * @return bool
     */
    public static function setValue(
        int $credentialID,
        CredentialColumn $column,
        mixed $value,
    ): bool {
        return self::editEntityValue($credentialID, $column, $value);
    }



    /**
     * Creates a Hash and Salt (if required) for the given Password
     * @param string $password
     * @param string $salt     Optional.
     * @return array{password:string,salt:string}
     */
    public static function createHash(
        string $password,
        string $salt = "",
    ): array {
        $pass = Strings::toString($password);
        $salt = $salt !== "" ? $salt : Strings::random(50);
        $hash = Strings::base64Encode(hash_hmac("sha256", $pass, $salt, binary: true));
        return [ "password" => $hash, "salt" => $salt ];
    }

    /**
     * Returns a parsed Name for the given Credential
     * @param mixed  $data
     * @param bool   $withEmail Optional.
     * @param string $prefix    Optional.
     * @return string
     */
    public static function getName(
        mixed $data,
        bool $withEmail = false,
        string $prefix = "",
    ): string {
        $id        = Strings::toString(Arrays::getValue($data, "credentialID", prefix: $prefix));
        $firstName = Strings::toString(Arrays::getValue($data, "firstName", prefix: $prefix));
        $lastName  = Strings::toString(Arrays::getValue($data, "lastName", prefix: $prefix));
        $email     = Strings::toString(Arrays::getValue($data, "email", prefix: $prefix));
        $result    = "";

        if ($firstName !== "" || $lastName !== "") {
            $result = Strings::merge($firstName, $lastName);
            if ($withEmail && $email !== "") {
                $result .= " ($email)";
            }
        }
        if ($result === "" && $id !== "") {
            $result = "#$id";
        }
        return $result;
    }

    /**
     * Returns a parsed Phone for the given Credential
     * @param mixed  $data
     * @param string $prefix   Optional.
     * @param bool   $withPlus Optional.
     * @return string
     */
    public static function getPhone(
        mixed $data,
        string $prefix = "",
        bool $withPlus = false,
    ): string {
        $phone     = Strings::toString(Arrays::getValue($data, "phone", prefix: $prefix));
        $cellphone = Strings::toString(Arrays::getValue($data, "cellphone", prefix: $prefix));
        $iddRoot   = Strings::toString(Arrays::getValue($data, "iddRoot", prefix: $prefix));

        if ($cellphone !== "" && $iddRoot !== "") {
            return ($withPlus ? "+" : "") . $iddRoot . $cellphone;
        }
        if ($cellphone !== "") {
            return $cellphone;
        }
        return $phone;
    }

    /**
     * Returns a WhatsApp url
     * @param mixed  $data
     * @param string $prefix Optional.
     * @return string
     */
    public static function getWhatsAppUrl(mixed $data, string $prefix = ""): string {
        $whatsapp = self::getPhone($data, $prefix, withPlus: true);
        return Utils::getWhatsAppUrl($whatsapp);
    }
}
