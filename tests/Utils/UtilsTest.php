<?php
// spell-checker: ignore  josé, MCDONALD, O'CONNOR, OCONNOR, Oconnor
namespace Tests\Utils;

use Framework\Utils\Utils;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class UtilsTest extends TestCase {

    #[DataProvider("providerIsValidPassword")]
    public function testIsValidPassword(string $password, string $requirements, int $minLength, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidPassword($password, $requirements, $minLength));
    }

    public static function providerIsValidPassword(): array {
        return [
            "def ld6 valid"          => [ "abc123", "ld", 6, true ],
            "def ld6 invalid short"  => [ "abc12", "ld", 6, false ],
            "def ld6 invalid digits" => [ "abcdef", "ld", 6, false ],
            "req l uppercase"        => [ "ABC123", "l", 6, false ],
            "req l valid"            => [ "abcde1", "l", 5, true ],
            "req l invalid"          => [ "ABC", "l", 1, false ],
            "req a valid"            => [ "abc", "a", 1, true ],
            "req a invalid"          => [ "123", "a", 1, false ],
            "req u valid"            => [ "ABC", "u", 1, true ],
            "req u invalid"          => [ "abc", "u", 1, false ],
            "req d valid"            => [ "123", "d", 1, true ],
            "req d invalid"          => [ "abc", "d", 1, false ],
        ];
    }


    #[DataProvider("providerIsValidColor")]
    public function testIsValidColor(string $color, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidColor($color));
    }

    public static function providerIsValidColor(): array {
        return [
            "valid short black" => [ "#000", true ],
            "valid short white" => [ "#fff", true ],
            "valid long"        => [ "#a1b2c3", true ],
            "valid long upper"  => [ "#A1B2C3", true ],
            "valid short upper" => [ "#FFF", true ],
            "invalid no hash"   => [ "fff", false ],
            "invalid bad hex"   => [ "#ggg", false ],
            "invalid long bad"  => [ "#gggFF", false ],
        ];
    }


    #[DataProvider("providerIsValidFullName")]
    public function testIsValidFullName(string $fullName, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidFullName($fullName));
    }

    public static function providerIsValidFullName(): array {
        return [
            "valid simple"        => [ "John Doe", true ],
            "valid with spaces"   => [ " Ana María ", true ],
            "valid multi part"    => [ "Ana María Dora", true ],
            "invalid empty"       => [ "", false ],
            "invalid single name" => [ "Madonna", false ],
            "invalid single word" => [ " Ana ", false ],
        ];
    }


    #[DataProvider("providerParseName")]
    public function testParseName(string $name, bool $lastNameFirst, string $separator, array $expected): void {
        $this->assertEquals($expected, Utils::parseName($name, $lastNameFirst, $separator));
    }

    public static function providerParseName(): array {
        return [
            "simple first last"     => [ "John Doe", false, " ", [ "John", "Doe" ] ],
            "last name first comma" => [ "Doe,John", true, ",", [ "John", "Doe" ] ],
            "multi part first name" => [ "Smith John Doe", false, " ", [ "Smith John", "Doe" ] ],
            "last name first space" => [ "Doe Smith John", true, " ", [ "Smith John", "Doe" ] ],
            "custom separator"      => [ "Last|First Middle", true, "|", [ "First Middle", "Last" ] ],
            "single name"           => [ "Single", false, " ", [ "Single", "" ] ],
        ];
    }


    #[DataProvider("providerParseNameCase")]
    public function testParseNameCase(string $name, string $expected): void {
        $this->assertSame($expected, Utils::parseNameCase($name));
    }

    public static function providerParseNameCase(): array {
        return [
            "upper to title"       => [ "JOHN SMITH", "John Smith" ],
            "already title"        => [ "John Smith", "John Smith" ],
            "empty"                => [ "", "" ],
            "only spaces"          => [ "   ", "" ],
            "trims and collapses"  => [ "  extra   spaces  ", "Extra Spaces" ],
            "keeps accents"        => [ "josé maría garcía", "José María García" ],

            // The particles of compound surnames are kept in lower case
            "particles lowercased" => [ "juan de la cruz", "Juan de la Cruz" ],
            "van der particles"    => [ "luis van der berg", "Luis van der Berg" ],
            // A leading particle is capitalized, since it is the surname itself
            "leading particle"     => [ "DE LA CRUZ", "De la Cruz" ],
            "leading particle los" => [ "LOS ANGELES", "Los Angeles" ],

            // The Mc surnames capitalize the letter after the prefix
            "mc surname"           => [ "PETER MCDONALD", "Peter McDonald" ],
            "mc leading"           => [ "OLD MCDONALD", "Old McDonald" ],
            "mc too short"         => [ "MC", "Mc" ],

            // A short prefix before an apostrophe capitalizes what follows
            "apostrophe o"         => [ "SEAN O'CONNOR", "Sean O'Connor" ],
            "apostrophe d"         => [ "GIOVANNI D'ANGELO", "Giovanni D'Angelo" ],
            // A long prefix before an apostrophe is left as title case
            "long apostrophe"      => [ "MARIA DELL'ORTO", "Maria Dell'orto" ],
            // Without an apostrophe there is no special handling
            "no apostrophe"        => [ "SEAN OCONNOR", "Sean Oconnor" ],
        ];
    }


    #[DataProvider("providerIsValidUsername")]
    public function testIsValidUsername(string $username, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidUsername($username));
    }

    public static function providerIsValidUsername(): array {
        return [
            "valid with dash number" => [ "user-name1", true ],
            "valid simple"           => [ "username", true ],
            "invalid with space"     => [ "user name", false ],
            "invalid with at"        => [ "user@name", false ],
            "invalid with dot"       => [ "user.name", false ],
            "invalid trailing dash"  => [ "user-name-", false ],
            "invalid leading dash"   => [ "-bad", false ],
            "invalid has space"      => [ "has space", false ],
        ];
    }


    #[DataProvider("providerGenerateUsername")]
    public function testGenerateUsername(string $domain, string $email, string $expected): void {
        $this->assertEquals($expected, Utils::generateUsername($domain, $email));
    }

    public static function providerGenerateUsername(): array {
        return [
            "simple domain"         => [ "example.com", "", "example" ],
            "with email prefix"     => [ "1domain.com", "e@mail.com", "e1domain" ],
            "unicode chars"         => [ "niño.com", "", "nino" ],
            "numeric first"         => [ "1domain.com", "", "1domain" ],
            "empty domain"          => [ "", "", "" ],
            "invalid domain"        => [ ".com", "", "" ],
            "dashes and unicode"    => [ "a-b-ñ.com", "", "abn" ],
            "numeric with email"    => [ "9domain.com", "z@x.com", "z9domain" ],
            "truncation with email" => [ "123456789.com", "a@b.com", "a1234567" ],
        ];
    }


    #[DataProvider("providerIsValidEmail")]
    public function testIsValidEmail(string $email, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidEmail($email));
    }

    public static function providerIsValidEmail(): array {
        return [
            "valid simple"       => [ "test@example.com", true ],
            "valid with tag"     => [ "user+tag@example.co.uk", true ],
            "valid with dot"     => [ "user.name@example.com", true ],
            "valid with under"   => [ "USER_123@sub.example-domain.com", true ],
            "invalid no at"      => [ "not-an-email", false ],
            "invalid no domain"  => [ "user@.com", false ],
            "invalid no local"   => [ "@example.com", false ],
            "invalid no tld"     => [ "user@com", false ],
            "invalid double dot" => [ "user@example..com", false ],
            "invalid empty"      => [ "", false ],
        ];
    }


    #[DataProvider("providerNormalizeEmail")]
    public function testNormalizeEmail(string $email, string $expected): void {
        $this->assertEquals($expected, Utils::normalizeEmail($email));
    }

    public static function providerNormalizeEmail(): array {
        return [
            "already normal"  => [ "user@example.com", "user@example.com" ],
            "uppercase"       => [ "User.Name@Example.COM", "user.name@example.com" ],
            "leading space"   => [ " user@example.com", "user@example.com" ],
            "trailing spaces" => [ "user@example.com  ", "user@example.com" ],
            "tabs and lines"  => [ "\tUser@Example.com\n", "user@example.com" ],
            "not an email"    => [ " Not An Email ", "not an email" ],
            "empty"           => [ "", "" ],
            "only spaces"     => [ "   ", "" ],
        ];
    }


    #[DataProvider("providerGetEmailDomain")]
    public function testGetEmailDomain(string $email, string $expected): void {
        $this->assertEquals($expected, Utils::getEmailDomain($email));
    }

    public static function providerGetEmailDomain(): array {
        return [
            "valid simple"         => [ "user@Example.COM", "example.com" ],
            "valid subdomain"      => [ "user@Sub.Example.Co.UK", "sub.example.co.uk" ],
            "valid with tag"       => [ "user+tag@EXAMPLE.COM", "example.com" ],
            "valid multi part"     => [ "User.Name+tag@Sub-Example.COM", "sub-example.com" ],
            "valid numeric domain" => [ "user@123domain.com", "123domain.com" ],
            "invalid with spaces"  => [ " user@Example.COM ", "" ],
            "invalid localhost"    => [ "user@localhost", "" ],
            "invalid not email"    => [ "not-an-email", "" ],
            "invalid empty"        => [ "", "" ],
        ];
    }


    #[DataProvider("providerExtractEmail")]
    public function testExtractEmail(string $input, string $expected): void {
        $this->assertEquals($expected, Utils::extractEmail($input));
    }

    public static function providerExtractEmail(): array {
        return [
            "simple email"          => [ "contact: foo@bar.com here", "foo@bar.com" ],
            "email in brackets"     => [ "Contact: <john.smith@sub.example.com> is listed", "john.smith@sub.example.com" ],
            "multiple emails"       => [ "Multiple: first@a.com second@b.com", "first@a.com" ],
            "email with underscore" => [ "user_1@domain.org is the contact", "user_1@domain.org" ],
            "plus in local part"    => [ "user+tag@example.com", "tag@example.com" ],
            "plus with subdomain"   => [ "name+foo@domain.co.uk and more", "foo@domain.co.uk" ],
            "no email"              => [ "no email here", "" ],
            "empty string"          => [ "", "" ],
        ];
    }


    #[DataProvider("providerHideEmail")]
    public function testHideEmail(string $email, string $expected): void {
        $this->assertEquals($expected, Utils::hideEmail($email));
    }

    public static function providerHideEmail(): array {
        return [
            "simple email"      => [ "john.doe@example.com", "joh*****@example.com" ],
            "invalid empty"     => [ "", "" ],
            "invalid not email" => [ "not-an-email", "" ],
            "invalid no domain" => [ "user@.com", "" ],
            "invalid no local"  => [ "@example.com", "" ],
            "invalid no tld"    => [ "invalid@", "" ],
            "short single char" => [ "a@b.com", "a@b.com" ],
            "short two chars"   => [ "ab@b.com", "a*@b.com" ],
            "short three chars" => [ "abc@b.com", "a**@b.com" ],
            "short four chars"  => [ "abcd@b.com", "abc*@b.com" ],
            "preserves case"    => [ "User@EXAMPLE.COM", "Use*@example.com" ],
        ];
    }


    #[DataProvider("providerIsValidPhone")]
    public function testIsValidPhone(string $phone, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidPhone($phone));
    }

    public static function providerIsValidPhone(): array {
        return [
            "valid plain"         => [ "1234567890", true ],
            "valid formatted"     => [ "(123) 456-7890", true ],
            "valid dashed"        => [ "123-456-7890", true ],
            "valid international" => [ "+1 (123) 456-7890", true ],
            "invalid empty"       => [ "", false ],
            "invalid letters"     => [ "abc", false ],
        ];
    }


    #[DataProvider("providerPhoneToNumber")]
    public function testPhoneToNumber(string $phone, string $expected): void {
        $this->assertEquals($expected, Utils::phoneToNumber($phone));
    }

    public static function providerPhoneToNumber(): array {
        return [
            "formatted parentheses" => [ "(123) 456-7890", "1234567890" ],
            "formatted dashed"      => [ "123-456-7890", "1234567890" ],
            "international"         => [ "+1 (123) 456-7890", "11234567890" ],
            "invalid empty"         => [ "", "" ],
            "invalid letters"       => [ "abc", "" ],
        ];
    }


    #[DataProvider("providerHidePhone")]
    public function testHidePhone(string $phone, string $expected): void {
        $this->assertEquals($expected, Utils::hidePhone($phone));
    }

    public static function providerHidePhone(): array {
        return [
            "plain digits"            => [ "1234567890", "123****890" ],
            "short two chars"         => [ "03", "*3" ],
            "empty string"            => [ "", "" ],
            "very short one char"     => [ "1", "*" ],
            "very short two chars"    => [ "12", "*2" ],
            "small three chars"       => [ "123", "1*3" ],
            "small four chars"        => [ "1234", "1**4" ],
            "small five chars"        => [ "12345", "1***5" ],
            "small seven chars"       => [ "1234567", "12***67" ],
            "international formatted" => [ "+1 (123) 456-7890", "+1 (1*******-7890" ],
            "invalid letters"         => [ "abc", "" ],
        ];
    }


    #[DataProvider("providerIsValidCUIT")]
    public function testIsValidCUIT(string $cuit, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidCUIT($cuit));
    }

    public static function providerIsValidCUIT(): array {
        return [
            "valid raw digits 1" => [ "20123456786", true ],
            "valid raw digits 2" => [ "20123456840", true ],
            "valid raw digits 3" => [ "23123456849", true ],
            "valid formatted 1"  => [ "20-12345678-6", true ],
            "valid formatted 2"  => [ "20-12345684-0", true ],
            "valid formatted 3"  => [ "23-12345684-9", true ],
            "invalid last digit" => [ "20123456780", false ],
            "invalid length"     => [ "1234567", false ],
        ];
    }


    #[DataProvider("providerParseCUIT")]
    public function testParseCUIT(string $cuit, string $expected): void {
        $this->assertEquals($expected, Utils::parseCUIT($cuit));
    }

    public static function providerParseCUIT(): array {
        return [
            "valid formatted 1"    => [ "20-12345678-6", "20-12345678-6" ],
            "valid raw digits 1"   => [ "20123456786", "20-12345678-6" ],
            "valid raw digits 2"   => [ "20123456840", "20-12345684-0" ],
            "valid raw digits 3"   => [ "23123456849", "23-12345684-9" ],
            "invalid non 11 input" => [ "20-123456789-6", "20-123456789-6" ],
            "invalid short"        => [ "123", "123" ],
            "invalid empty"        => [ "", "" ],
            "invalid with letter"  => [ "2012345678a", "2012345678a" ],
        ];
    }


    #[DataProvider("providerCuitToNumber")]
    public function testCuitToNumber(string $cuit, string $expected): void {
        $this->assertEquals($expected, Utils::cuitToNumber($cuit));
    }

    public static function providerCuitToNumber(): array {
        return [
            "valid formatted"   => [ "20-12345678-6", "20123456786" ],
            "valid with spaces" => [ "20 12345678 6", "20123456786" ],
            "invalid empty"     => [ "", "" ],
        ];
    }


    #[DataProvider("providerIsValidDNI")]
    public function testIsValidDNI(string $dni, bool $expected): void {
        $this->assertEquals($expected, Utils::isValidDNI($dni));
    }

    public static function providerIsValidDNI(): array {
        return [
            "valid plain digits"   => [ "12345678", true ],
            "valid formatted dots" => [ "12.345.678", true ],
            "valid with spaces"    => [ " 12.345.678 ", true ],
            "invalid too short"    => [ "123", false ],
            "invalid too long"     => [ "123456789012", false ],
            "invalid non digits"   => [ "abcdefg", false ],
            "invalid empty"        => [ "", false ],
        ];
    }


    #[DataProvider("providerDniToNumber")]
    public function testDniToNumber(string $dni, string $expected): void {
        $this->assertEquals($expected, Utils::dniToNumber($dni));
    }

    public static function providerDniToNumber(): array {
        return [
            "formatted dots"          => [ "12.345.678", "12345678" ],
            "formatted dots spaces"   => [ " 12.345.678 ", "12345678" ],
            "plain digits"            => [ "12345678", "12345678" ],
            "leading zeros formatted" => [ "00.123.456", "00123456" ],
            "invalid letters"         => [ "abc", "" ],
            "invalid empty"           => [ "", "" ],
        ];
    }


    #[DataProvider("providerGetAvatarUrl")]
    public function testGetAvatarUrl(string $custom, string $email, string $expected): void {
        $this->assertEquals($expected, Utils::getAvatarUrl($custom, $email));
    }

    public static function providerGetAvatarUrl(): array {
        return [
            "with email"        => [ "", "me@example.com", "https://gravatar.com/avatar/" . md5("me@example.com") . "?default=mp" ],
            "with custom"       => [ "custom", "me@example.com", "custom" ],
            "fallback no email" => [ "", "", "https://gravatar.com/avatar/" . md5("") . "?default=mp" ],
        ];
    }


    #[DataProvider("providerGetWhatsAppUrl")]
    public function testGetWhatsAppUrl(string $phone, string $expected): void {
        $this->assertEquals($expected, Utils::getWhatsAppUrl($phone));
    }

    public static function providerGetWhatsAppUrl(): array {
        return [
            "simple number"  => [ "12345", "https://wa.me/12345" ],
            "with plus sign" => [ "+5412345", "https://wa.me/+5412345" ],
        ];
    }
}
