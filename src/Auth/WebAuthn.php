<?php
namespace Framework\Auth;

use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * The WebAuthn checks of the Passkeys
 */
class WebAuthn {

    // The COSE Algorithms that are accepted
    public const ES256 = -7;
    public const RS256 = -257;

    // The start of the DER of a P-256 public key, before its point
    private const P256Prefix = "3059301306072a8648ce3d020106082a8648ce3d030107034200";



    /**
     * Encodes the given bytes as a Base64 URL without padding
     * @param string $bytes
     * @return string
     */
    public static function encode(string $bytes): string {
        return rtrim(strtr(Strings::base64Encode($bytes), "+/", "-_"), "=");
    }

    /**
     * Decodes the given Base64 URL, with or without padding
     * @param string $value
     * @return string
     */
    public static function decode(string $value): string {
        $value  = strtr($value, "-_", "+/");
        $padded = str_pad($value, (int)(ceil(strlen($value) / 4) * 4), "=");
        return Strings::base64Decode($padded);
    }

    /**
     * Decodes the given CBOR data, returning null when it is not valid
     * @param string $data
     * @return mixed
     */
    public static function decodeCBOR(string $data): mixed {
        $offset = 0;
        try {
            return self::readCBOR($data, $offset);
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    /**
     * Returns the Algorithm of the given COSE Key, or 0 when it is not accepted
     * @param array<int|string,mixed> $coseKey
     * @return int
     */
    public static function getAlgorithm(array $coseKey): int {
        $algorithm = $coseKey[3] ?? 0;
        if ($algorithm === self::ES256 || $algorithm === self::RS256) {
            return $algorithm;
        }
        return 0;
    }

    /**
     * Returns the given COSE Key as a PEM, or an empty string when it is not accepted
     * @param array<int|string,mixed> $coseKey
     * @return string
     */
    public static function getPublicKeyPem(array $coseKey): string {
        $type = $coseKey[1] ?? 0;
        $der  = "";

        // An EC2 key on the P-256 curve
        if ($type === 2 && ($coseKey[-1] ?? 0) === 1) {
            $x = $coseKey[-2] ?? "";
            $y = $coseKey[-3] ?? "";
            if (is_string($x) && is_string($y) && strlen($x) === 32 && strlen($y) === 32) {
                $der = (string)hex2bin(self::P256Prefix) . "\x04" . $x . $y;
            }

        // An RSA key, with its modulus and its exponent
        } elseif ($type === 3) {
            $modulus  = $coseKey[-1] ?? "";
            $exponent = $coseKey[-2] ?? "";
            if (is_string($modulus) && is_string($exponent) &&
                $modulus !== "" && $exponent !== ""
            ) {
                $key = self::derSequence(self::derInteger($modulus) . self::derInteger($exponent));
                $der = self::derSequence(
                    self::derSequence((string)hex2bin("06092a864886f70d0101010500")) .
                    "\x03" . self::derLength(strlen($key) + 1) . "\x00" . $key,
                );
            }
        }

        if ($der === "") {
            return "";
        }
        $body = chunk_split(Strings::base64Encode($der), 64, "\n");
        return "-----BEGIN PUBLIC KEY-----\n{$body}-----END PUBLIC KEY-----\n";
    }

    /**
     * Returns the Challenge of the Client Data if it has the given Type and Origin
     * @param string       $clientDataJSON
     * @param string       $type
     * @param list<string> $origins
     * @return string
     */
    public static function getClientChallenge(
        string $clientDataJSON,
        string $type,
        array $origins,
    ): string {
        $clientData = JSON::decodeAsDictionary($clientDataJSON);
        if ($clientData->getString("type") !== $type) {
            return "";
        }
        if (!in_array($clientData->getString("origin"), $origins, strict: true)) {
            return "";
        }
        return $clientData->getString("challenge");
    }

    /**
     * Returns true if the Signature of the Authenticator and the Client Data is valid
     * @param string $authData
     * @param string $clientDataJSON
     * @param string $signature
     * @param string $publicKeyPem
     * @return bool
     */
    public static function isValidSignature(
        string $authData,
        string $clientDataJSON,
        string $signature,
        string $publicKeyPem,
    ): bool {
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            return false;
        }

        // Both algorithms sign with SHA-256, and the EC2 signature comes already as DER
        $data = $authData . hash("sha256", $clientDataJSON, binary: true);
        return openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }



    /**
     * Reads a CBOR item at the given offset, moving it past the item
     * @param string $data
     * @param int    $offset
     * @return mixed
     * @throws \UnexpectedValueException When the data is not valid.
     */
    private static function readCBOR(string $data, int &$offset): mixed {
        $initial    = ord(self::readBytes($data, $offset, 1));
        $majorType  = $initial >> 5;
        $additional = $initial & 0x1F;

        // The simple values and the floats, which the WebAuthn keys never use as values
        if ($majorType === 7) {
            return match ($additional) {
                20      => false,
                21      => true,
                22, 23  => null,
                default => throw new \UnexpectedValueException("Unsupported CBOR value"),
            };
        }

        $length = self::readLength($data, $offset, $additional);
        switch ($majorType) {
        case 0:
            return $length;
        case 1:
            return -1 - $length;
        case 2:
        case 3:
            return self::readBytes($data, $offset, $length);
        case 4:
            $list = [];
            for ($i = 0; $i < $length; $i += 1) {
                $list[] = self::readCBOR($data, $offset);
            }
            return $list;
        case 5:
            $map = [];
            for ($i = 0; $i < $length; $i += 1) {
                $key = self::readCBOR($data, $offset);
                if (!is_int($key) && !is_string($key)) {
                    throw new \UnexpectedValueException("Unsupported CBOR key");
                }
                $map[$key] = self::readCBOR($data, $offset);
            }
            return $map;
        default:
            throw new \UnexpectedValueException("Unsupported CBOR type");
        }
    }

    /**
     * Reads the length or the value that follows the initial byte of a CBOR item
     * @param string $data
     * @param int    $offset
     * @param int    $additional
     * @return int
     * @throws \UnexpectedValueException When the data is not valid.
     */
    private static function readLength(string $data, int &$offset, int $additional): int {
        if ($additional < 24) {
            return $additional;
        }

        $sizes = [ 24 => 1, 25 => 2, 26 => 4, 27 => 8 ];
        if (!isset($sizes[$additional])) {
            throw new \UnexpectedValueException("Unsupported CBOR length");
        }

        $result = 0;
        foreach (str_split(self::readBytes($data, $offset, $sizes[$additional])) as $byte) {
            $result = ($result << 8) | ord($byte);
        }
        if ($result < 0) {
            throw new \UnexpectedValueException("Unsupported CBOR length");
        }
        return $result;
    }

    /**
     * Reads the given amount of bytes at the offset, moving it past them
     * @param string $data
     * @param int    $offset
     * @param int    $length
     * @return string
     * @throws \UnexpectedValueException When the data is too short.
     */
    private static function readBytes(string $data, int &$offset, int $length): string {
        if ($length < 0 || $offset + $length > strlen($data)) {
            throw new \UnexpectedValueException("The CBOR data is too short");
        }
        $result  = substr($data, $offset, $length);
        $offset += $length;
        return $result;
    }

    /**
     * Encodes the given bytes as a DER Integer, which can not start with a high bit
     * @param string $bytes
     * @return string
     */
    private static function derInteger(string $bytes): string {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === "" || ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    /**
     * Encodes the given content as a DER Sequence
     * @param string $content
     * @return string
     */
    private static function derSequence(string $content): string {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    /**
     * Encodes the given length as DER
     * @param int $length
     * @return string
     */
    private static function derLength(int $length): string {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack("N", $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
