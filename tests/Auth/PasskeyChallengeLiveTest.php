<?php
namespace Tests\Auth;

use Framework\Auth\PasskeyChallenge;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The challenges a passkey answers once, for a credential or for a login
 */
class PasskeyChallengeLiveTest extends LiveTestCase {

    private const CredentialID = 900031;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();
        $this->query("DELETE FROM credential_challenge");
    }



    public function testAChallengeIsAUrlSafeCode(): void {
        $challenge = PasskeyChallenge::create(self::CredentialID);

        $this->assertMatchesRegularExpression("/^[A-Za-z0-9_-]{43}$/", $challenge);
        $this->assertNotSame($challenge, PasskeyChallenge::create(self::CredentialID));
    }

    #[DataProvider("providerConsume")]
    public function testConsume(int $createdFor, int $consumedFor, bool $expected): void {
        $challenge = PasskeyChallenge::create($createdFor);

        $this->assertSame($expected, PasskeyChallenge::consume($challenge, $consumedFor));
    }

    public static function providerConsume(): array {
        return [
            "for the credential" => [ self::CredentialID, self::CredentialID, true ],
            "for a login"        => [ 0, 0, true ],
            "another credential" => [ self::CredentialID, self::CredentialID + 1, false ],
            "a login for one"    => [ self::CredentialID, 0, false ],
        ];
    }

    public function testAChallengeIsAnsweredOnlyOnce(): void {
        $challenge = PasskeyChallenge::create();

        $this->assertTrue(PasskeyChallenge::consume($challenge));
        $this->assertFalse(PasskeyChallenge::consume($challenge));
    }

    public function testAWrongAnswerUsesTheChallengeToo(): void {
        $challenge = PasskeyChallenge::create(self::CredentialID);

        $this->assertFalse(PasskeyChallenge::consume($challenge, 0));
        $this->assertFalse(PasskeyChallenge::consume($challenge, self::CredentialID));
    }

    public function testAnUnknownOrEmptyChallengeIsNotValid(): void {
        $this->assertFalse(PasskeyChallenge::consume("never-made"));
        $this->assertFalse(PasskeyChallenge::consume(""));
    }

    public function testAnOldChallengeIsNotValidAndIsDeleted(): void {
        $old   = PasskeyChallenge::create();
        $fresh = PasskeyChallenge::create();
        $this->query("UPDATE credential_challenge SET time = time - 600 WHERE challenge = '$old'");

        $this->assertTrue(PasskeyChallenge::deleteOld());
        $this->assertFalse(PasskeyChallenge::consume($old));
        $this->assertTrue(PasskeyChallenge::consume($fresh));
    }

    public function testAnOldChallengeIsNotValidBeforeItIsDeleted(): void {
        $challenge = PasskeyChallenge::create();
        $this->query("UPDATE credential_challenge SET time = time - 600 WHERE challenge = '$challenge'");

        $this->assertFalse(PasskeyChallenge::consume($challenge));
    }
}
