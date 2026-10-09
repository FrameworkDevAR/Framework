<?php
namespace Framework\Auth;

use Framework\Auth\WebAuthn;
use Framework\Auth\Schema\CredentialChallengeSchema;
use Framework\Auth\Schema\CredentialChallengeQuery;
use Framework\Date\Date;

/**
 * The Passkey Challenges
 */
class PasskeyChallenge extends CredentialChallengeSchema {

    // The minutes the browser has to answer a Challenge
    private const MaxMinutes = 5;



    /**
     * Creates a Challenge for the given Credential, or for a login when it is 0
     * @param int $credentialID Optional.
     * @return string
     */
    public static function create(int $credentialID = 0): string {
        $challenge = WebAuthn::encode(random_bytes(32));
        self::replaceEntity(
            challenge:    $challenge,
            credentialID: $credentialID,
            time:         Date::now(),
        );
        return $challenge;
    }

    /**
     * Uses the given Challenge, returning true if it was made for the Credential in time
     * @param string $challenge
     * @param int    $credentialID Optional.
     * @return bool
     */
    public static function consume(string $challenge, int $credentialID = 0): bool {
        if ($challenge === "") {
            return false;
        }

        $query = new CredentialChallengeQuery();
        $query->challenge->equal($challenge);
        $query->credentialID->equal($credentialID);
        $query->time->greaterThan(Date::now()->subtract(minutes: self::MaxMinutes));
        $isValid = self::entityExists($query);

        // A Challenge is answered once, even when it was not valid for this answer
        $query = new CredentialChallengeQuery();
        $query->challenge->equal($challenge);
        self::removeEntity($query);
        return $isValid;
    }

    /**
     * Deletes the Challenges that were not answered in time
     * @return bool
     */
    public static function deleteOld(): bool {
        $query = new CredentialChallengeQuery();
        $query->time->lessThan(Date::now()->subtract(minutes: self::MaxMinutes));
        return self::removeEntity($query);
    }
}
