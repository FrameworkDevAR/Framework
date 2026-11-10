<?php
namespace Tests\Email;

use Framework\Email\Email;
use Framework\Email\EmailResult;
use Framework\Email\Schema\EmailContentEntity;
use Framework\Email\Schema\EmailWhiteListRequest;
use Framework\Email\EmailWhiteList;
use Framework\IO\Request;
use Framework\System\EmailCode;
use Framework\Utils\Arrays;

use Tests\Email\Fixture\FakeHttps;
use Tests\Email\Fixture\TestEmailSender;
use Tests\LiveTestCase;
use Tests\TestHelpers;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Email sender, whole, without one leaving the machine
 *
 * The Sender of the tests keeps what it is handed rather than delivering it,
 * so a send runs the length of the class, template and all, and stops there
 * with the message to look at.
 */
class EmailLiveTest extends LiveTestCase {
    use TestHelpers;

    private const SendTo = "sender@framework.test";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `email_white_list`");
        TestEmailSender::reset();
    }

    protected function tearDown(): void {
        $this->setConfig("EMAIL_ACTIVE", false);
        $this->setConfig("EMAIL_USE_WHITE_LIST", false);
        $this->setConfig("EMAIL_RECAPTCHA_SECRET", "");
        $this->setConfig("EMAIL_PROVIDER", "");
        $this->setConfig("EMAIL_EMAIL", "");
        $this->setConfig("EMAIL_REPLY_TO", "");
        $this->setConfig("SMTP_HOST", "");
        Email::setSender();
        TestEmailSender::reset();
    }

    /**
     * Turns the email on, sending through the Sender of the tests
     * @return void
     */
    private function sendForReal(): void {
        $this->setConfig("EMAIL_ACTIVE", true);
        Email::setSender(TestEmailSender::class);
    }



    public function testNothingIsSentWhileOff(): void {
        $this->assertSame(
            EmailResult::InactiveSend,
            Email::send(self::SendTo, "The subject", "The message"),
        );
    }

    public function testAnAddressOffTheListIsFiltered(): void {
        $this->setConfig("EMAIL_ACTIVE", true);
        $this->setConfig("EMAIL_USE_WHITE_LIST", true);

        $this->assertSame(
            EmailResult::WhiteListFilter,
            Email::send(self::SendTo, "The subject", "The message"),
        );
    }

    public function testAnAddressOnTheListGetsPast(): void {
        // The list is asked about before the address is looked at, so the one
        // put on it here is not one, and the answer that comes back is what
        // the next check says rather than the filter
        $this->setConfig("EMAIL_ACTIVE", true);
        $this->setConfig("EMAIL_USE_WHITE_LIST", true);
        EmailWhiteList::add(new EmailWhiteListRequest(
            email:       "not an address",
            description: "The one entry of the tests",
        ));

        $this->assertSame(
            EmailResult::InvalidEmail,
            Email::send("not an address", "The subject", "The message"),
        );
    }

    /**
     * A send that is asked to go through whatever the white list says
     * @param bool        $sendAlways
     * @param bool        $sendTest
     * @param EmailResult $expected
     * @return void
     */
    #[DataProvider("providerSkipsTheWhiteList")]
    public function testTheListIsSteppedOverWhenAsked(
        bool $sendAlways,
        bool $sendTest,
        EmailResult $expected,
    ): void {
        $this->setConfig("EMAIL_ACTIVE", true);
        $this->setConfig("EMAIL_USE_WHITE_LIST", true);

        $this->assertSame($expected, Email::send(
            "not an address",
            "The subject",
            "The message",
            sendAlways: $sendAlways,
            sendTest:   $sendTest,
        ));
    }

    /**
     * @return array<string,array{bool,bool,EmailResult}>
     */
    public static function providerSkipsTheWhiteList(): array {
        return [
            "neither"      => [ false, false, EmailResult::WhiteListFilter ],
            "send always"  => [ true, false, EmailResult::InvalidEmail ],
            "send as test" => [ false, true, EmailResult::InvalidEmail ],
        ];
    }

    public function testATestSendGoesOutWhileOff(): void {
        // The test send is the one the console makes, and it answers for the
        // address rather than for the config being off
        $this->assertSame(
            EmailResult::InvalidEmail,
            Email::send("not an address", "The subject", "The message", sendTest: true),
        );
    }

    public function testAnAddressThatIsNotOneIsRefused(): void {
        $this->setConfig("EMAIL_ACTIVE", true);

        $this->assertSame(
            EmailResult::InvalidEmail,
            Email::send("not an address", "The subject", "The message"),
        );
    }



    public function testASendWithNoProviderSaysSo(): void {
        // Which is what this repository is set up with, since it sends nothing
        $this->setConfig("EMAIL_ACTIVE", true);

        $this->assertSame(
            EmailResult::NoProvider,
            Email::send(self::SendTo, "The subject", "The message"),
        );
    }

    public function testASendGoesThroughToTheProvider(): void {
        $this->sendForReal();
        $this->setConfig("EMAIL_EMAIL", "hi@framework.test");
        $this->setConfig("EMAIL_REPLY_TO", "reply@framework.test");

        $this->assertSame(
            EmailResult::Sent,
            Email::send(self::SendTo, "The subject", "The message"),
        );

        $email = TestEmailSender::getLast();
        $this->assertSame(self::SendTo, $email["toEmail"]);
        $this->assertSame("The subject", $email["subject"]);
        $this->assertSame("hi@framework.test", $email["fromEmail"]);
        $this->assertSame("reply@framework.test", $email["replyTo"]);
    }

    public function testTheReplyToOfTheSendTakesThePlaceOfTheConfig(): void {
        $this->sendForReal();
        $this->setConfig("EMAIL_REPLY_TO", "reply@framework.test");

        Email::send(self::SendTo, "The subject", "The message", replyTo: "billing@framework.test");
        $this->assertSame("billing@framework.test", TestEmailSender::getLast()["replyTo"]);
    }

    public function testTheMessageIsWrappedInTheTemplate(): void {
        $this->sendForReal();

        Email::send(self::SendTo, "The subject", "The message");

        // The template of this repository is an HTML table, and the message
        // is put inside it rather than being the whole of the body
        $body = TestEmailSender::getLast()["body"];
        $this->assertStringContainsString("The message", $body);
        $this->assertStringContainsString("<table", $body);
        $this->assertNotSame("The message", $body);
    }

    public function testTheMessageIsSentOnItsOwnWhenAsked(): void {
        $this->sendForReal();

        Email::send(self::SendTo, "The subject", "The message", withoutTemplate: true);

        $this->assertSame("The message", TestEmailSender::getLast()["body"]);
    }

    public function testASenderThatRefusesIsAProviderError(): void {
        $this->sendForReal();
        TestEmailSender::setSends(false);

        $this->assertSame(
            EmailResult::ProviderError,
            Email::send(self::SendTo, "The subject", "The message"),
        );
    }

    public function testAContentGoesToEveryAddress(): void {
        $this->sendForReal();
        $content = new EmailContentEntity(
            emailCode: EmailCode::Test,
            subject:   "The subject",
            message:   "The message",
        );

        $this->assertSame(EmailResult::Sent, Email::sendContent(
            $content,
            [ self::SendTo, "other@framework.test" ],
        ));

        $this->assertSame(2, TestEmailSender::getCount());
        $this->assertSame(
            [ self::SendTo, "other@framework.test" ],
            Arrays::createArray(TestEmailSender::getAll(), "toEmail"),
        );
    }

    public function testAContentSentToNobodyHasNoEmails(): void {
        $content = new EmailContentEntity(emailCode: EmailCode::Test);

        $this->assertSame(EmailResult::NoEmails, Email::sendContent($content, []));
    }



    public function testARequestWithoutACaptchaFails(): void {
        $this->setConfig("EMAIL_RECAPTCHA_SECRET", "the-secret");

        $this->assertFalse(Email::isCaptchaValid(new Request()));
    }

    public function testACaptchaWithoutASecretFails(): void {
        $request = new Request([ "g-recaptcha-response" => "the-answer" ]);

        $this->assertFalse(Email::isCaptchaValid($request));
    }

    /**
     * What Google answers, and what the check makes of it
     * @param string $body
     * @param bool   $withScore
     * @param bool   $expected
     * @return void
     */
    #[DataProvider("providerCaptcha")]
    public function testTheCaptchaIsWhatTheAnswerSays(
        string $body,
        bool $withScore,
        bool $expected,
    ): void {
        $this->setConfig("EMAIL_RECAPTCHA_SECRET", "the secret");
        $request = new Request([ "g-recaptcha-response" => "the answer" ]);

        $result = $this->withFakeHttps(
            $body,
            static fn() => Email::isCaptchaValid($request, $withScore),
        );

        $this->assertSame($expected, $result);

        // The secret and the answer are both written into the url, spaces and
        // all, which is what the encoding of each is for
        $this->assertStringContainsString("secret=the+secret", FakeHttps::$url);
        $this->assertStringContainsString("response=the+answer", FakeHttps::$url);
    }

    /**
     * @return array<string,array{string,bool,bool}>
     */
    public static function providerCaptcha(): array {
        return [
            "a success"              => [ '{"success":true}', false, true ],
            "a failure"              => [ '{"success":false}', false, false ],
            "nothing at all"         => [ '{}', false, false ],
            "an answer of nothing"   => [ '', false, false ],
            "a score above the half" => [ '{"success":true,"score":0.9}', true, true ],
            "a score below it"       => [ '{"success":true,"score":0.3}', true, false ],
            "the half itself"        => [ '{"success":true,"score":0.5}', true, false ],
            "a score not asked for"  => [ '{"success":true,"score":0.3}', false, true ],
            "no score to go by"      => [ '{"success":true}', true, true ],
        ];
    }

    /**
     * Runs the given callback with the https reads answered from the fixture
     * @param string   $body
     * @param callable $callback
     * @return bool
     */
    private function withFakeHttps(string $body, callable $callback): bool {
        FakeHttps::$body = $body;
        FakeHttps::$url  = "";

        stream_wrapper_unregister("https");
        stream_wrapper_register("https", FakeHttps::class);
        try {
            return (bool)$callback();
        } finally {
            stream_wrapper_restore("https");
        }
    }
}
