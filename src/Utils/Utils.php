<?php
// spell-checker: ignore  delle
namespace Framework\Utils;

use Framework\Utils\Strings;

/**
 * Several Utils functions
 */
class Utils {

    /**
     * Returns true if the given Password is valid
     * @param string $password
     * @param string $checkSets Optional.
     * @param int    $minLength Optional.
     * @return bool
     */
    public static function isValidPassword(
        string $password,
        string $checkSets = "ad",
        int $minLength = 6,
    ): bool {
        if (Strings::length($password) < $minLength) {
            return false;
        }
        if (Strings::contains($checkSets, "a") &&
            !Strings::match($password, "#[a-zA-Z]+#")
        ) {
            return false;
        }
        if (Strings::contains($checkSets, "l") &&
            !Strings::match($password, "#[a-z]+#")
        ) {
            return false;
        }
        if (Strings::contains($checkSets, "u") &&
            !Strings::match($password, "#[A-Z]+#")
        ) {
            return false;
        }
        if (Strings::contains($checkSets, "d") &&
            !Strings::match($password, "#[0-9]+#")
        ) {
            return false;
        }
        return true;
    }

    /**
     * Returns true if the given Color is valid
     * @param string $color
     * @return bool
     */
    public static function isValidColor(string $color): bool {
        return Strings::match($color, '/#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\b/');
    }



    /**
     * Returns true if the given Full Name is valid
     * @param string $fullName
     * @return bool
     */
    public static function isValidFullName(string $fullName): bool {
        $nameParts = Strings::split(trim($fullName), " ");
        return count($nameParts) > 1;
    }

    /**
     * Parses a Full Name into a First and Last Name
     * @param string $fullName
     * @param bool   $lastNameFirst Optional.
     * @param string $separator     Optional.
     * @return array{string,string}
     */
    public static function parseName(
        string $fullName,
        bool $lastNameFirst = false,
        string $separator = " ",
    ): array {
        $nameParts = Strings::split($fullName, $separator, trim: true);
        if (count($nameParts) > 1) {
            if ($lastNameFirst) {
                $lastName = array_shift($nameParts);
            } else {
                $lastName = array_pop($nameParts);
            }
            $firstName = Strings::join($nameParts, " ");
        } else {
            $firstName = $fullName;
            $lastName  = "";
        }
        return [ $firstName, $lastName ];
    }

    /**
     * Parses a Name into Title Case, as the documents print them in upper case.
     * The particles are kept in lower case, unless the Name starts with one,
     * and the Mc and the letter prefixes before an apostrophe are respected
     * @param string $name
     * @return string
     */
    public static function parseNameCase(string $name): string {
        $particles = [
            "a", "al", "bin", "da", "das", "de", "del", "della", "delle", "dei",
            "der", "des", "di", "do", "dos", "du", "e", "el", "ibn", "la", "las",
            "le", "les", "lo", "los", "san", "santa", "st", "te", "ten", "ter",
            "van", "vd", "von", "y", "zu",
        ];

        $result = Strings::toTitleCase(trim($name));
        if ($result === "") {
            return "";
        }

        $words = preg_split("/\s+/", $result);
        if ($words === false) {
            return $result;
        }

        foreach ($words as $index => $word) {
            // The particles of the compound surnames are not capitalized,
            // unless the Name starts with one, as in a surname on its own
            $lWord = Strings::toLowerCase($word);
            if ($index > 0 && Arrays::contains($particles, $lWord)) {
                $words[$index] = $lWord;
                continue;
            }

            // The Mc surnames capitalize the letter after the prefix
            if (mb_strlen($word) > 2 && mb_substr($word, 0, 2) === "Mc") {
                $words[$index] = "Mc" . Strings::toTitleCase(mb_substr($word, 2));
                continue;
            }

            // A short prefix before an apostrophe capitalizes what follows,
            // as in O'Connor or D'Angelo, but not in names like Dell'Orto
            $position = mb_strpos($word, "'");
            if ($position !== false && $position > 0 && $position <= 2) {
                $words[$index] = mb_substr($word, 0, $position + 1)
                    . Strings::toTitleCase(mb_substr($word, $position + 1));
            }
        }
        return implode(" ", $words);
    }



    /**
     * Returns true if the given Username is valid
     * @param string $username
     * @return bool
     */
    public static function isValidUsername(string $username): bool {
        return Strings::match($username, '/^[a-z0-9]([-a-z0-9]*[a-z0-9])?$/i');
    }

    /**
     * Generates a username from a domain
     * @param string $domain
     * @param string $email  Optional.
     * @return string
     */
    public static function generateUsername(string $domain, string $email = ""): string {
        $parts  = Strings::split($domain, ".");
        $result = $parts[0] ?? $domain;
        $result = Strings::replace($result, [ "-", "ñ" ], [ "", "n" ]);
        $result = Strings::substring($result, 0, 8);

        if ($email !== "" && is_numeric($result[0])) {
            $result = Strings::substring($email[0] . $result, 0, 8);
        }
        return $result;
    }



    /**
     * Returns true if the given Email is valid
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool {
        $result = filter_var($email, FILTER_VALIDATE_EMAIL);
        return $result !== false;
    }

    /**
     * Returns the given Email trimmed and in lowercase
     * @param string $email
     * @return string
     */
    public static function normalizeEmail(string $email): string {
        return Strings::toLowerCase(Strings::trim($email));
    }

    /**
     * Extracts the Domain from the given email
     * @param string $email
     * @return string
     */
    public static function getEmailDomain(string $email): string {
        if (!self::isValidEmail($email)) {
            return "";
        }

        $result = Strings::substringAfter($email, "@");
        $result = Strings::toLowerCase($result);
        return $result;
    }

    /**
     * Extracts the first Email from the given text
     * @param string $text
     * @return string
     */
    public static function extractEmail(string $text): string {
        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $text, $matches);
        $result = $matches[0][0] ?? "";
        return Strings::toString($result);
    }

    /**
     * Returns the given Email hiding most of the name and showing the domain
     * @param string $email
     * @return string
     */
    public static function hideEmail(string $email): string {
        if ($email === "" || !self::isValidEmail($email)) {
            return "";
        }

        $domain  = self::getEmailDomain($email);
        $name    = Strings::substringBefore($email, "@");
        $length  = Strings::length($name);
        $nameLen = $length > 3 ? 3 : 1;
        $hidden  = Strings::substring($name, 0, $nameLen);
        $stars   = Strings::repeat("*", $length - $nameLen);
        return "$hidden$stars@$domain";
    }



    /**
     * Returns true if the given Phone is valid
     * @param string $value
     * @return bool
     */
    public static function isValidPhone(string $value): bool {
        $phone = self::phoneToNumber($value);
        return is_numeric($phone);
    }

    /**
     * Removes spaces, dashes and parenthesis in the Phone
     * @param string $value
     * @return string
     */
    public static function phoneToNumber(string $value): string {
        return Strings::toNumber($value);
    }

    /**
     * Returns the given Phone hiding most of the numbers
     * @param string $phone
     * @return string
     */
    public static function hidePhone(string $phone): string {
        if ($phone === "" || !self::isValidPhone($phone)) {
            return "";
        }

        $length = Strings::length($phone);
        if ($length < 3) {
           return $length === 1 ? "*" : "*" . Strings::substring($phone, -1);
        }

        $middle     = "";
        $partSize   = (int)floor($length / 3);
        $middleSize = $length - ($partSize * 2);

        for ($i = 0; $i < $middleSize; $i += 1) {
            $middle .= "*";
        }

        $result  = Strings::substring($phone, 0, $partSize);
        $result .= $middle . Strings::substring($phone, -$partSize);
        return $result;
    }



    /**
     * Returns true if the given CUIT is valid
     * @param string $value
     * @return bool
     */
    public static function isValidCUIT(string $value): bool {
        $cuit = self::cuitToNumber($value);
        if (Strings::length($cuit) !== 11) {
            return false;
        }

        // The last number is the verifier
        $verify = (int)$cuit[10];
        $mult   = [ 5, 4, 3, 2, 7, 6, 5, 4, 3, 2 ];
        $total  = 0;

        // Multiply each number by the multiplier (except the last one)
        $count = count($mult);
        for ($i = 0; $i < $count; $i += 1) {
            $total += (int)$cuit[$i] * $mult[$i];
        }

        // Calculate the left over and value
        $mod = $total % 11;
        if ($mod === 0) {
            return $verify === 0;
        }
        if ($mod === 1) {
            return $verify === 9;
        }
        return $verify === 11 - $mod;
    }

    /**
     * Parses the given CUIT if it has 11 chars
     * @param string $value
     * @return string
     */
    public static function parseCUIT(string $value): string {
        if (Strings::length($value) === 11) {
            return Strings::replacePattern($value, "/^(\d{2})(\d{8})(\d{1})$/", "$1-$2-$3");
        }
        return $value;
    }

    /**
     * Removes spaces and dots in the CUIT
     * @param string $value
     * @return string
     */
    public static function cuitToNumber(string $value): string {
        return Strings::toNumber($value);
    }



    /**
     * Returns true if the given DNI is valid
     * @param string $value
     * @return bool
     */
    public static function isValidDNI(string $value): bool {
        $dni    = self::dniToNumber($value);
        $length = Strings::length($dni);
        if ($length < 6 || $length > 9) {
            return false;
        }
        return is_numeric($dni);
    }

    /**
     * Removes spaces and dots in the DNI
     * @param string $value
     * @return string
     */
    public static function dniToNumber(string $value): string {
        return Strings::toNumber($value);
    }



    /**
     * Returns an Avatar URL
     * @param string $url
     * @param string $email
     * @return string
     */
    public static function getAvatarUrl(string $url, string $email): string {
        if ($url !== "") {
            return $url;
        }
        $username = md5($email);
        return "https://gravatar.com/avatar/$username?default=mp";
    }

    /**
     * Returns a WhatsApp URL
     * @param string $whatsApp
     * @return string
     */
    public static function getWhatsAppUrl(string $whatsApp): string {
        return "https://wa.me/$whatsApp";
    }
}
