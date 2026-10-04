<?php
namespace Tests\Email;

use Framework\Email\Email;
use Framework\Email\EmailQueue;
use Framework\Email\EmailResult;
use Tests\Email\Fixture\TestEmail;
use Tests\Email\Fixture\TestTemplateEmail;
use Tests\Email\Fixture\TestEmailSender;
use Framework\Email\Schema\EmailContentEntity;
use Framework\Email\Schema\EmailQueueRequest;
use Framework\System\EmailCode;

use Tests\LiveTestCase;
use Tests\TestHelpers;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Email Queue, which holds what is to be sent and what became of it
 *
 * Sending is left to answer InactiveSend, which is what the config of this
 * repository says, so a test reaches the whole of the queue without a single
 * email leaving the machine.
 */
class EmailQueueLiveTest extends LiveTestCase {
    use TestHelpers;

    private const SendTo = "queue@framework.test";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `email_queue`");
    }

    protected function tearDown(): void {
        $this->setConfig("EMAIL_LIMIT", 0);
        $this->setConfig("EMAIL_ACTIVE", false);
        Email::setSender();
        TestEmailSender::reset();
    }

    /**
     * Returns the Content the Queue is given, which is what an app looks up
     * @param string $subject Optional.
     * @param string $message Optional.
     * @return EmailContentEntity
     */
    private function content(
        string $subject = "The subject",
        string $message = "The message",
    ): EmailContentEntity {
        return new EmailContentEntity(
            emailCode: EmailCode::Test,
            subject:   $subject,
            message:   $message,
        );
    }



    public function testAnEmailIsQueued(): void {
        $this->assertTrue(EmailQueue::add($this->content(), self::SendTo));

        $emails = EmailQueue::getAllUnsent();
        $this->assertCount(1, $emails);
        $this->assertSame(EmailCode::Test, $emails[0]->emailCode);
        $this->assertSame("The subject", $emails[0]->subject);
        $this->assertSame("The message", $emails[0]->message);
        $this->assertSame([ self::SendTo ], $emails[0]->sendTo->toStrings());
    }

    public function testAMessageIsQueuedRendered(): void {
        $this->assertTrue(TestEmail::send(self::SendTo, "en", "John"));

        $emails = EmailQueue::getAllUnsent();
        $this->assertCount(1, $emails);
        $this->assertSame(EmailCode::Test, $emails[0]->emailCode);
        $this->assertSame("Hello John", $emails[0]->subject);
        $this->assertSame(TestEmail::getBody("en", [ "name" => "John" ]), $emails[0]->message);
        $this->assertSame([ self::SendTo ], $emails[0]->sendTo->toStrings());
    }

    public function testAnEmailThatCanNotWaitIsSentNow(): void {
        $this->assertTrue(TestTemplateEmail::send());

        $this->assertCount(0, EmailQueue::getAllUnsent());
    }

    public function testAnEmailIsAddedWithItsCode(): void {
        $this->assertTrue(EmailQueue::addEmail(EmailCode::Test, self::SendTo, "A subject", "A message", dataID: 3));

        $emails = EmailQueue::getAllUnsent();
        $this->assertSame("A subject", $emails[0]->subject);
        $this->assertSame("A message", $emails[0]->message);
        $this->assertSame(3, $emails[0]->dataID);
    }

    public function testAnEmailSentNowLeavesItSent(): void {
        $this->assertTrue(EmailQueue::addEmail(EmailCode::Test, self::SendTo, "A subject", "A message", sendNow: true));

        $this->assertCount(0, EmailQueue::getAllUnsent());
    }

    public function testTheTextGivenWinsOverTheContent(): void {
        EmailQueue::add($this->content(), self::SendTo, "Another message", "Another subject");

        $emails = EmailQueue::getAllUnsent();
        $this->assertSame("Another subject", $emails[0]->subject);
        $this->assertSame("Another message", $emails[0]->message);
    }

    public function testNobodyToSendToQueuesNothing(): void {
        $this->assertFalse(EmailQueue::add($this->content(), []));

        $this->assertCount(0, EmailQueue::getAllUnsent());
    }

    public function testSeveralAddressesAreOneEmail(): void {
        EmailQueue::add($this->content(), [ self::SendTo, "other@framework.test" ]);

        $emails = EmailQueue::getAllUnsent();
        $this->assertCount(1, $emails);
        $this->assertSame(
            [ self::SendTo, "other@framework.test" ],
            $emails[0]->sendTo->toStrings(),
        );
    }

    public function testSendingNowLeavesItSent(): void {
        $this->assertTrue(EmailQueue::add($this->content(), self::SendTo, sendNow: true));

        $this->assertCount(0, EmailQueue::getAllUnsent());
        $emails = EmailQueue::getList(new EmailQueueRequest());
        $this->assertSame(EmailResult::InactiveSend, $emails[0]->emailResult);
    }



    public function testASendMarksTheResult(): void {
        EmailQueue::add($this->content(), self::SendTo);
        $email = EmailQueue::getAllUnsent()[0];

        EmailQueue::send($email, sendAlways: false);

        $sent = EmailQueue::getByID($email->id);
        $this->assertSame(EmailResult::InactiveSend, $sent->emailResult);
        $this->assertTrue($sent->sentTime->isNotEmpty());
    }

    public function testAnEmailThatGoesOutIsMarkedAsSent(): void {
        // The Sender of the tests takes the email rather than delivering it,
        // so the queue is walked the way it is with a provider set up
        $this->setConfig("EMAIL_ACTIVE", true);
        Email::setSender(TestEmailSender::class);
        EmailQueue::add($this->content(), self::SendTo);
        $email = EmailQueue::getAllUnsent()[0];

        EmailQueue::send($email, sendAlways: true);

        $this->assertSame(
            EmailResult::Sent,
            EmailQueue::getByID($email->id)->emailResult,
        );
        $this->assertSame(self::SendTo, TestEmailSender::getLast()["toEmail"]);
    }

    public function testASendToNobodyHasNoEmails(): void {
        // The row is there with an empty list, which add() would have refused,
        // so the send has nothing to walk over and marks it as such
        EmailQueue::add($this->content(), self::SendTo);
        $email = EmailQueue::getAllUnsent()[0];
        $this->query(
            "UPDATE `email_queue` SET `sendTo` = '[]' WHERE `EMAIL_QUEUE_ID` = {$email->id}",
        );

        EmailQueue::send(EmailQueue::getByID($email->id), sendAlways: false);

        $this->assertSame(EmailResult::NoEmails, EmailQueue::getByID($email->id)->emailResult);
    }

    public function testSendingAllEmptiesTheQueue(): void {
        EmailQueue::add($this->content(), self::SendTo);
        EmailQueue::add($this->content(), "other@framework.test");

        EmailQueue::sendAll();

        $this->assertCount(0, EmailQueue::getAllUnsent());
    }

    public function testSendingAnEmptyQueueIsFine(): void {
        EmailQueue::sendAll();

        $this->assertSame(0, EmailQueue::getEntityTotal());
    }

    public function testTheConfigLimitCapsTheQueue(): void {
        $this->setConfig("EMAIL_LIMIT", 1);
        EmailQueue::add($this->content(), self::SendTo);
        EmailQueue::add($this->content(), "other@framework.test");

        $this->assertCount(1, EmailQueue::getAllUnsent());

        // A limit of zero is the one of this repository, and means no limit
        $this->setConfig("EMAIL_LIMIT", 0);
        $this->assertCount(2, EmailQueue::getAllUnsent());
    }



    public function testMarkingAsSentWritesTheTime(): void {
        EmailQueue::add($this->content(), self::SendTo);
        $emailQueueID = EmailQueue::getAllUnsent()[0]->id;

        EmailQueue::markAsSent($emailQueueID, EmailResult::Sent);

        $email = EmailQueue::getByID($emailQueueID);
        $this->assertSame(EmailResult::Sent, $email->emailResult);
        $this->assertTrue($email->sentTime->isNotEmpty());
    }

    public function testMarkingAsNotSentRequeuesIt(): void {
        EmailQueue::add($this->content(), self::SendTo);
        $emailQueueID = EmailQueue::getAllUnsent()[0]->id;
        EmailQueue::markAsSent($emailQueueID, EmailResult::Sent);
        $this->assertCount(0, EmailQueue::getAllUnsent());

        EmailQueue::markAsNotSent($emailQueueID);

        $email = EmailQueue::getByID($emailQueueID);
        $this->assertSame(EmailResult::NotProcessed, $email->emailResult);
        $this->assertTrue($email->sentTime->isEmpty());
        $this->assertCount(1, EmailQueue::getAllUnsent());
    }

    public function testSeveralAreRequeuedAtOnce(): void {
        EmailQueue::add($this->content(), self::SendTo, sendNow: true);
        EmailQueue::add($this->content(), "other@framework.test", sendNow: true);
        $emailQueueIDs = EmailQueue::getEmailQueueIDs();

        EmailQueue::markAsNotSent($emailQueueIDs);

        $this->assertCount(2, EmailQueue::getAllUnsent());
    }



    /**
     * An email queued some days back, and whether the delete reaches it
     * @param int  $days
     * @param bool $survives
     * @return void
     */
    #[DataProvider("providerDeleteOld")]
    public function testTheOldEmailsAreDeleted(int $days, bool $survives): void {
        EmailQueue::add($this->content(), self::SendTo);
        $emailQueueID = EmailQueue::getAllUnsent()[0]->id;
        $this->query(
            "UPDATE `email_queue` SET `createdTime` = UNIX_TIMESTAMP() - $days * 86400 " .
            "WHERE `EMAIL_QUEUE_ID` = $emailQueueID",
        );

        EmailQueue::deleteOld();

        $this->assertSame($survives, EmailQueue::exists($emailQueueID));
    }

    /**
     * @return array<string,array{int,bool}>
     */
    public static function providerDeleteOld(): array {
        return [
            "older than the 90 of the config" => [ 91, false ],
            "younger than it"                 => [ 89, true ],
        ];
    }

    public function testTheListIsSearched(): void {
        EmailQueue::add($this->content(), self::SendTo);
        EmailQueue::add($this->content(), "other@framework.test");

        $request = new EmailQueueRequest(search: "other@");
        $this->assertSame(1, EmailQueue::getTotal($request));
        $this->assertSame(
            [ "other@framework.test" ],
            EmailQueue::getList($request)[0]->sendTo->toStrings(),
        );
    }

    public function testTheListIsFilteredByResult(): void {
        EmailQueue::add($this->content(), self::SendTo, sendNow: true);
        EmailQueue::add($this->content(), "other@framework.test");

        $request = new EmailQueueRequest(results: [ EmailResult::NotProcessed->name ]);
        $this->assertSame(1, EmailQueue::getTotal($request));
    }

    public function testTheListIsFilteredByData(): void {
        EmailQueue::add($this->content(), self::SendTo, dataID: 42);
        EmailQueue::add($this->content(), "other@framework.test", dataID: 7);

        $this->assertSame(1, EmailQueue::getTotal(new EmailQueueRequest(dataID: 42)));
        $this->assertSame(2, EmailQueue::getTotal(new EmailQueueRequest()));
    }
}
