<?php
namespace Tests\Auth;

use OpenSSLAsymmetricKey;

/**
 * Builds the data an Authenticator sends, signed with a real key, for the Passkey tests
 */
class PasskeyFixture {

    public OpenSSLAsymmetricKey $privateKey;
    public string $publicKeyPem;
    public string $coseKey;
    public string $credentialKey;

    /**
     * Creates a new key of the given algorithm, as an Authenticator would
     * @param int $algorithm
     */
    public function __construct(int $algorithm = -7) {
        if ($algorithm === -257) {
            $key = openssl_pkey_new([
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "private_key_bits" => 2048,
            ]);
        } else {
            $key = openssl_pkey_new([
                "private_key_type" => OPENSSL_KEYTYPE_EC,
                "curve_name"       => "prime256v1",
            ]);
        }
        if ($key === false) {
            throw new \RuntimeException("The key could not be created");
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new \RuntimeException("The key has no details");
        }

        $this->privateKey    = $key;
        $this->publicKeyPem  = $details["key"];
        $this->credentialKey = random_bytes(16);

        if ($algorithm === -257) {
            $this->coseKey = self::map([
                1  => self::int(3),
                3  => self::int(-257),
                -1 => self::bytes($details["rsa"]["n"]),
                -2 => self::bytes($details["rsa"]["e"]),
            ]);
        } else {
            $this->coseKey = self::map([
                1  => self::int(2),
                3  => self::int(-7),
                -1 => self::int(1),
                -2 => self::bytes(str_pad($details["ec"]["x"], 32, "\x00", STR_PAD_LEFT)),
                -3 => self::bytes(str_pad($details["ec"]["y"], 32, "\x00", STR_PAD_LEFT)),
            ]);
        }
    }

    /**
     * Returns the Authenticator Data, with the Credential when it is a registration
     * @param string $rpID
     * @param int    $flags
     * @param int    $signCount
     * @param bool   $withCredential
     * @return string
     */
    public function authData(
        string $rpID,
        int $flags = 0x05,
        int $signCount = 0,
        bool $withCredential = false,
    ): string {
        $result = hash("sha256", $rpID, binary: true);
        if (!$withCredential) {
            return $result . chr($flags) . pack("N", $signCount);
        }

        $result .= chr($flags | 0x40) . pack("N", $signCount);
        $result .= str_repeat("\x00", 16);
        $result .= pack("n", strlen($this->credentialKey)) . $this->credentialKey;
        return $result . $this->coseKey;
    }

    /**
     * Returns the Attestation Object of a registration with the "none" format
     * @param string $authData
     * @return string
     */
    public function attestation(string $authData): string {
        return self::map([
            "fmt"      => self::text("none"),
            "attStmt"  => self::map([]),
            "authData" => self::bytes($authData),
        ]);
    }

    /**
     * Signs the Authenticator Data and the Client Data with the private key
     * @param string $authData
     * @param string $clientDataJSON
     * @return string
     */
    public function sign(string $authData, string $clientDataJSON): string {
        $data = $authData . hash("sha256", $clientDataJSON, binary: true);
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return is_string($signature) ? $signature : "";
    }



    /**
     * Returns the Client Data JSON the browser builds
     * @param string $type
     * @param string $challenge
     * @param string $origin
     * @return string
     */
    public static function clientData(string $type, string $challenge, string $origin): string {
        return (string)json_encode([
            "type"      => $type,
            "challenge" => $challenge,
            "origin"    => $origin,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Encodes an integer as CBOR
     * @param int $value
     * @return string
     */
    public static function int(int $value): string {
        return $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value);
    }

    /**
     * Encodes a byte string as CBOR
     * @param string $value
     * @return string
     */
    public static function bytes(string $value): string {
        return self::head(2, strlen($value)) . $value;
    }

    /**
     * Encodes a text as CBOR
     * @param string $value
     * @return string
     */
    public static function text(string $value): string {
        return self::head(3, strlen($value)) . $value;
    }

    /**
     * Encodes a list of encoded values as CBOR
     * @param list<string> $values
     * @return string
     */
    public static function list(array $values): string {
        return self::head(4, count($values)) . implode("", $values);
    }

    /**
     * Encodes a map of encoded values as CBOR, with integer or text keys
     * @param array<int|string,string> $values
     * @return string
     */
    public static function map(array $values): string {
        $result = self::head(5, count($values));
        foreach ($values as $key => $value) {
            $result .= is_int($key) ? self::int($key) : self::text($key);
            $result .= $value;
        }
        return $result;
    }

    /**
     * Encodes the head of a CBOR item with its type and length
     * @param int $majorType
     * @param int $length
     * @return string
     */
    public static function head(int $majorType, int $length): string {
        $type = $majorType << 5;
        return match (true) {
            $length < 24      => chr($type | $length),
            $length < 0x100   => chr($type | 24) . chr($length),
            $length < 0x10000 => chr($type | 25) . pack("n", $length),
            default           => chr($type | 26) . pack("N", $length),
        };
    }
}
