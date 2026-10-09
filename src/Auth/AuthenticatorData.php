<?php
namespace Framework\Auth;

use Framework\Auth\WebAuthn;
use Framework\Utils\Numbers;

/**
 * The Authenticator Data of a Passkey
 */
class AuthenticatorData {

    // The flags of the Authenticator Data
    private const UserPresent   = 0x01;
    private const UserVerified  = 0x04;
    private const HasCredential = 0x40;

    public bool   $isEmpty       = true;
    public string $rpIdHash      = "";
    public int    $flags         = 0;
    public int    $signCount     = 0;
    public string $credentialKey = "";

    /** @var array<int|string,mixed> */
    public array  $publicKey     = [];



    /**
     * Creates a new AuthenticatorData instance, which is empty when the data is not valid
     * @param string $data
     */
    public function __construct(string $data) {
        if (strlen($data) < 37) {
            return;
        }

        $counter = unpack("N", substr($data, 33, 4));
        $flags   = ord($data[32]);

        // Only the registration has the credential, after the AAGUID and its length
        if (($flags & self::HasCredential) !== 0) {
            if (strlen($data) < 55) {
                return;
            }

            $length = unpack("n", substr($data, 53, 2));
            $length = is_array($length) ? Numbers::toInt($length[1] ?? 0) : 0;
            if ($length === 0 || strlen($data) < 55 + $length) {
                return;
            }

            $publicKey = WebAuthn::decodeCBOR(substr($data, 55 + $length));
            if (!is_array($publicKey) || count($publicKey) === 0) {
                return;
            }

            $this->credentialKey = substr($data, 55, $length);
            $this->publicKey     = $publicKey;
        }

        $this->isEmpty   = false;
        $this->rpIdHash  = substr($data, 0, 32);
        $this->flags     = $flags;
        $this->signCount = is_array($counter) ? Numbers::toInt($counter[1] ?? 0) : 0;
    }

    /**
     * Returns true if the data is for the Relying Party with a present and verified user
     * @param string $rpID
     * @param bool   $needsUser Optional.
     * @return bool
     */
    public function isValidFor(string $rpID, bool $needsUser = true): bool {
        if ($this->isEmpty) {
            return false;
        }
        if (!hash_equals(hash("sha256", $rpID, binary: true), $this->rpIdHash)) {
            return false;
        }

        // A Passkey the browser creates on its own after a login may have no user present
        if (!$needsUser) {
            return true;
        }

        $flags = self::UserPresent | self::UserVerified;
        return ($this->flags & $flags) === $flags;
    }

    /**
     * Returns true if the data has a new Credential with its Public Key
     * @return bool
     */
    public function hasCredential(): bool {
        return $this->credentialKey !== "" && count($this->publicKey) > 0;
    }
}
