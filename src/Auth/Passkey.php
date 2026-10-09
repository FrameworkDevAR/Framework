<?php
namespace Framework\Auth;

use Framework\Auth\AuthenticatorData;
use Framework\Auth\PasskeyChallenge;
use Framework\Auth\WebAuthn;
use Framework\Auth\Schema\CredentialPasskeySchema;
use Framework\Auth\Schema\CredentialPasskeyEntity;
use Framework\Auth\Schema\CredentialPasskeyQuery;
use Framework\Date\Date;
use Framework\Utils\Strings;
use Framework\Utils\URL;

/**
 * The Credential Passkeys
 */
class Passkey extends CredentialPasskeySchema {

    // The milliseconds the browser waits for the user
    private const Timeout = 300000;



    /**
     * Returns all the Passkeys of the given Credential
     * @param int $credentialID
     * @return list<CredentialPasskeyEntity>
     */
    public static function getAllForCredential(int $credentialID): array {
        $query = new CredentialPasskeyQuery();
        $query->credentialID->equal($credentialID);
        $query->createdTime->orderByDesc();
        return self::getEntityList($query);
    }

    /**
     * Returns the Passkey with the given Credential Key, as the browser sends it
     * @param string $credentialKey
     * @return CredentialPasskeyEntity
     */
    public static function getByCredentialKey(string $credentialKey): CredentialPasskeyEntity {
        $query = new CredentialPasskeyQuery();
        $query->credentialKey->equal($credentialKey);
        return self::getEntity($query);
    }



    /**
     * Returns the Relying Party ID of the Origin the browser sends, or "" when not valid
     * @param string $origin
     * @param string $appUrl
     * @param bool   $withLocal Optional.
     * @return string
     */
    public static function getRpID(
        string $origin,
        string $appUrl,
        bool $withLocal = false,
    ): string {
        // The Origin is only the scheme, host and port, so any other part makes it invalid
        if ($origin === "" || $origin !== URL::getOrigin($origin)) {
            return "";
        }

        // The browsers only allow the Passkeys over https or in the localhost, and the
        // development hosts are made secure in the browser, so locally those are accepted
        $host    = URL::getHost($origin);
        $isLocal = $host === "localhost" || Strings::endsWith($host, ".local");
        if ($origin === URL::getOrigin($appUrl) || ($withLocal && $isLocal)) {
            return $host;
        }
        return "";
    }

    /**
     * Returns the User Handle the browser saves with the Passkeys of the Credential
     * @param int $credentialID
     * @return string
     */
    public static function getUserHandle(int $credentialID): string {
        return WebAuthn::encode((string)$credentialID);
    }

    /**
     * Returns the options the browser needs to create a Passkey for the Credential
     * @param int    $credentialID
     * @param string $userName
     * @param string $displayName
     * @param string $rpID
     * @param string $rpName
     * @return array<string,mixed>
     */
    public static function getRegisterOptions(
        int $credentialID,
        string $userName,
        string $displayName,
        string $rpID,
        string $rpName,
    ): array {
        // The Passkeys the Credential has are excluded, so one device does not add it twice
        $excluded = [];
        foreach (self::getAllForCredential($credentialID) as $passkey) {
            $excluded[] = [ "type" => "public-key", "id" => $passkey->credentialKey ];
        }

        return [
            "challenge"              => PasskeyChallenge::create($credentialID),
            "rp"                     => [ "id" => $rpID, "name" => $rpName ],
            "user"                   => [
                "id"          => self::getUserHandle($credentialID),
                "name"        => $userName,
                "displayName" => $displayName,
            ],
            "pubKeyCredParams"       => [
                [ "type" => "public-key", "alg" => WebAuthn::ES256 ],
                [ "type" => "public-key", "alg" => WebAuthn::RS256 ],
            ],
            "timeout"                => self::Timeout,
            "attestation"            => "none",
            "excludeCredentials"     => $excluded,
            "authenticatorSelection" => [
                "residentKey"        => "required",
                "requireResidentKey" => true,
                "userVerification"   => "required",
            ],
        ];
    }

    /**
     * Saves the Passkey the browser created, returning its ID or 0 when it is not valid
     * @param int          $credentialID
     * @param string       $name
     * @param string       $rpID
     * @param list<string> $origins
     * @param string       $clientDataJSON    In Base64 URL.
     * @param string       $attestationObject In Base64 URL.
     * @param bool         $isConditional     Optional.
     * @return int
     */
    public static function register(
        int $credentialID,
        string $name,
        string $rpID,
        array $origins,
        string $clientDataJSON,
        string $attestationObject,
        bool $isConditional = false,
    ): int {
        $clientData = WebAuthn::decode($clientDataJSON);
        $challenge  = WebAuthn::getClientChallenge($clientData, "webauthn.create", $origins);
        if (!PasskeyChallenge::consume($challenge, $credentialID)) {
            return 0;
        }

        // The attestation is "none", so only the Authenticator Data inside it is read
        $attestation = WebAuthn::decodeCBOR(WebAuthn::decode($attestationObject));
        if (!is_array($attestation) || !is_string($attestation["authData"] ?? null)) {
            return 0;
        }

        // The browser creates a conditional Passkey after a login without asking the user,
        // which is safe as the Credential just logged in, and each login still verifies it
        $authData = new AuthenticatorData($attestation["authData"]);
        $isValid  = $authData->isValidFor($rpID, needsUser: !$isConditional);
        if (!$isValid || !$authData->hasCredential()) {
            return 0;
        }

        $algorithm = WebAuthn::getAlgorithm($authData->publicKey);
        $publicKey = WebAuthn::getPublicKeyPem($authData->publicKey);
        if ($algorithm === 0 || $publicKey === "") {
            return 0;
        }

        $credentialKey = WebAuthn::encode($authData->credentialKey);
        if (!self::getByCredentialKey($credentialKey)->isEmpty()) {
            return 0;
        }

        return self::createEntity(
            credentialID:  $credentialID,
            name:          Strings::substring(trim($name), 0, 255),
            credentialKey: $credentialKey,
            publicKey:     $publicKey,
            algorithm:     $algorithm,
            signCount:     $authData->signCount,
        );
    }

    /**
     * Returns the options the browser needs to login with a Passkey
     * @param string $rpID
     * @return array<string,mixed>
     */
    public static function getLoginOptions(string $rpID): array {
        // Without allowed credentials the browser offers every Passkey of the site
        return [
            "challenge"        => PasskeyChallenge::create(),
            "rpId"             => $rpID,
            "timeout"          => self::Timeout,
            "userVerification" => "required",
            "allowCredentials" => [],
        ];
    }

    /**
     * Returns the ID of the Credential that the Passkey proves, or 0 when it does not
     * @param string       $rpID
     * @param list<string> $origins
     * @param string       $credentialKey     In Base64 URL.
     * @param string       $clientDataJSON    In Base64 URL.
     * @param string       $authenticatorData In Base64 URL.
     * @param string       $signature         In Base64 URL.
     * @return int
     */
    public static function getLoginCredentialID(
        string $rpID,
        array $origins,
        string $credentialKey,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
    ): int {
        $clientData = WebAuthn::decode($clientDataJSON);
        $challenge  = WebAuthn::getClientChallenge($clientData, "webauthn.get", $origins);
        if (!PasskeyChallenge::consume($challenge)) {
            return 0;
        }

        $passkey = self::getByCredentialKey($credentialKey);
        if ($passkey->isEmpty()) {
            return 0;
        }

        $rawAuthData = WebAuthn::decode($authenticatorData);
        $authData    = new AuthenticatorData($rawAuthData);
        if (!$authData->isValidFor($rpID)) {
            return 0;
        }
        $rawSignature = WebAuthn::decode($signature);
        $publicKey    = $passkey->publicKey;
        if (!WebAuthn::isValidSignature($rawAuthData, $clientData, $rawSignature, $publicKey)) {
            return 0;
        }

        // A counter that does not go up means a cloned key, but the Passkeys that are
        // synced between devices always send 0, so those are not checked
        if (($authData->signCount > 0 || $passkey->signCount > 0) &&
            $authData->signCount <= $passkey->signCount
        ) {
            return 0;
        }

        self::editEntity(
            $passkey->id,
            signCount:    $authData->signCount,
            lastUsedTime: Date::now(),
        );
        return $passkey->credentialID;
    }



    /**
     * Renames the given Passkey of the Credential
     * @param int    $credentialID
     * @param int    $passkeyID
     * @param string $name
     * @return bool
     */
    public static function rename(int $credentialID, int $passkeyID, string $name): bool {
        $query = new CredentialPasskeyQuery();
        $query->passkeyID->equal($passkeyID);
        $query->credentialID->equal($credentialID);
        return self::editEntity($query, name: Strings::substring(trim($name), 0, 255));
    }

    /**
     * Deletes the given Passkey of the Credential
     * @param int $credentialID
     * @param int $passkeyID
     * @return bool
     */
    public static function delete(int $credentialID, int $passkeyID): bool {
        $query = new CredentialPasskeyQuery();
        $query->passkeyID->equal($passkeyID);
        $query->credentialID->equal($credentialID);
        return self::removeEntity($query);
    }

    /**
     * Deletes all the Passkeys of the given Credential
     * @param int $credentialID
     * @return bool
     */
    public static function deleteAllForCredential(int $credentialID): bool {
        $query = new CredentialPasskeyQuery();
        $query->credentialID->equal($credentialID);
        return self::removeEntity($query);
    }
}
