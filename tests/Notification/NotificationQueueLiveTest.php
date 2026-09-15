<?php
namespace Tests\Notification;

use Framework\Auth\Device;
use Framework\Date\Date;
use Framework\Notification\Notification;
use Framework\Notification\NotificationQueue;
use Framework\Notification\NotificationResult;
use Framework\Notification\Schema\NotificationQueueRequest;
use Framework\System\NotificationProvider;

use Tests\Notification\Fixture\TestNotificationSender;
use Tests\LiveTestCase;
use Tests\TestHelpers;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Notification Queue, which holds the pushes and what became of them
 *
 * The Sender of the tests takes the push rather than delivering it, so the
 * queue is walked the way it is with a provider set up, and nothing is pushed.
 */
class NotificationQueueLiveTest extends LiveTestCase {
    use TestHelpers;

    private const CredentialID = 8001;
    private const OtherID      = 8002;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `notification_queue`");
        $this->query("DELETE FROM `credential_device`");
    }

    protected function tearDown(): void {
        $this->setConfig("NOTIFICATION_ACTIVE", false);
        $this->setConfig("NOTIFICATION_LIMIT", 0);
        $this->setConfig("NOTIFICATION_PROVIDER", "");
        Notification::setSender();
        TestNotificationSender::reset();
    }

    /**
     * Queues a Notification and hands back its ID
     * @param int    $credentialID Optional.
     * @param string $title        Optional.
     * @return int
     */
    private function add(
        int $credentialID = self::CredentialID,
        string $title = "A title",
    ): int {
        return NotificationQueue::add(
            credentialID: $credentialID,
            currentUser:  0,
            title:        $title,
            message:      "A message",
            url:          "/somewhere",
            dataType:     "order",
            dataID:       42,
        );
    }



    public function testANotificationIsQueued(): void {
        $notificationQueueID = $this->add();

        $this->assertGreaterThan(0, $notificationQueueID);

        $notification = NotificationQueue::getByID($notificationQueueID);
        $this->assertSame(self::CredentialID, $notification->credentialID);
        $this->assertSame("A title", $notification->title);
        $this->assertSame("A message", $notification->message);
        $this->assertSame("/somewhere", $notification->url);
        $this->assertSame("order", $notification->dataType);
        $this->assertSame(42, $notification->dataID);
        $this->assertSame(NotificationResult::NotProcessed, $notification->notificationResult);
    }

    public function testANotificationBelongsToItsCredential(): void {
        $notificationQueueID = $this->add();

        $this->assertTrue(NotificationQueue::existsForCredential(
            $notificationQueueID,
            self::CredentialID,
        ));
        $this->assertFalse(NotificationQueue::existsForCredential(
            $notificationQueueID,
            self::OtherID,
        ));
    }



    public function testTheUnsentAreTheOnesNotProcessed(): void {
        $notificationQueueID = $this->add();
        $this->add(self::OtherID);
        NotificationQueue::markAsRead($notificationQueueID);

        $this->assertCount(2, NotificationQueue::getAllUnsent());

        NotificationQueue::sendAll();
        $this->assertCount(0, NotificationQueue::getAllUnsent());
    }

    public function testTheConfigLimitCapsTheUnsent(): void {
        $this->add();
        $this->add(self::OtherID);

        $this->setConfig("NOTIFICATION_LIMIT", 1);
        $this->assertCount(1, NotificationQueue::getAllUnsent());

        // A limit of zero is the one of this repository, and means no limit
        $this->setConfig("NOTIFICATION_LIMIT", 0);
        $this->assertCount(2, NotificationQueue::getAllUnsent());
    }

    public function testTheUnsentOfACredentialAreOnlyTheirs(): void {
        $this->add();
        $this->add(self::OtherID);

        $list = NotificationQueue::getUnsentForCredential(self::CredentialID, 0, Date::now());
        $this->assertCount(1, $list);
        $this->assertSame(self::CredentialID, $list[0]->credentialID);
    }

    public function testOneAlreadySentIsNotUnsentForTheCredential(): void {
        $this->add();
        NotificationQueue::sendAll();

        $this->assertCount(
            0,
            NotificationQueue::getUnsentForCredential(self::CredentialID, 0, Date::now()),
        );
    }

    public function testTheOnesOfACredentialAreOnlyTheirs(): void {
        $this->add();
        $this->add(self::OtherID);

        $list = NotificationQueue::getAllForCredential(
            self::CredentialID,
            0,
            new NotificationQueueRequest(),
        );
        $this->assertCount(1, $list);
        $this->assertSame("A title", $list[0]->title);
    }

    public function testADiscardedOneIsNotOneOfTheirs(): void {
        $notificationQueueID = $this->add();
        NotificationQueue::discard($notificationQueueID);

        $this->assertCount(0, NotificationQueue::getAllForCredential(
            self::CredentialID,
            0,
            new NotificationQueueRequest(),
        ));
    }

    public function testTheUnreadAreCounted(): void {
        $notificationQueueID = $this->add();
        $this->add(self::CredentialID, "Another title");

        $this->assertSame(2, NotificationQueue::getUnreadAmount(self::CredentialID, 0));

        NotificationQueue::markAsRead($notificationQueueID);
        $this->assertSame(1, NotificationQueue::getUnreadAmount(self::CredentialID, 0));
    }

    public function testADiscardedOneIsNotCountedAsUnread(): void {
        $notificationQueueID = $this->add();
        NotificationQueue::discard($notificationQueueID);

        $this->assertSame(0, NotificationQueue::getUnreadAmount(self::CredentialID, 0));
    }



    /**
     * Whether the push is on, and what a send with no device answers
     * @param bool               $isActive
     * @param NotificationResult $expected
     * @return void
     */
    #[DataProvider("providerSendAll")]
    public function testASendMarksTheResult(bool $isActive, NotificationResult $expected): void {
        $this->setConfig("NOTIFICATION_ACTIVE", $isActive);
        $notificationQueueID = $this->add();

        NotificationQueue::sendAll();

        $notification = NotificationQueue::getByID($notificationQueueID);
        $this->assertSame($expected, $notification->notificationResult);
        $this->assertTrue($notification->sentTime->isNotEmpty());
    }

    /**
     * @return array<string,array{bool,NotificationResult}>
     */
    public static function providerSendAll(): array {
        return [
            // The push being off is looked at first, so it is the answer even
            // for a Credential that has no device to send to either
            "the push is off" => [ false, NotificationResult::InactiveSend ],
            "there is no one" => [ true, NotificationResult::NoDevices ],
        ];
    }

    public function testTheDevicesOfTheCredentialAreTheOnesSentTo(): void {
        // The send is not reached with the push off, but the devices it would
        // have used are written onto the row just the same
        $this->setConfig("NOTIFICATION_ACTIVE", false);
        Device::add(self::CredentialID, "a-player-id");
        $notificationQueueID = $this->add();

        NotificationQueue::sendAll();

        $notification = NotificationQueue::getByID($notificationQueueID);
        $this->assertSame([ "a-player-id" ], $notification->playerIDs->toStrings());
    }

    public function testOneThatGoesOutIsMarkedAsSent(): void {
        // The Sender of the tests takes the push rather than delivering it,
        // so the queue is walked the way it is with a provider set up
        $this->setConfig("NOTIFICATION_ACTIVE", true);
        Notification::setSender(TestNotificationSender::class);
        Device::add(self::CredentialID, "a-player-id");
        $notificationQueueID = $this->add();

        NotificationQueue::sendAll();

        $notification = NotificationQueue::getByID($notificationQueueID);
        $this->assertSame(NotificationResult::Sent, $notification->notificationResult);
        $this->assertSame("the-external-id", $notification->externalID);
        $this->assertSame("A title", TestNotificationSender::getLast()["title"]);
    }

    public function testOnlyTheDevicesOfTheProviderOfTheConfigArePushedTo(): void {
        $this->setConfig("NOTIFICATION_ACTIVE", true);
        $this->setConfig("NOTIFICATION_PROVIDER", "OneSignal");
        Notification::setSender(TestNotificationSender::class);
        Device::add(self::CredentialID, "a-player-id");
        Device::add(self::CredentialID, "a-token", NotificationProvider::Firebase);
        $notificationQueueID = $this->add();

        NotificationQueue::sendAll();

        $this->assertSame(1, TestNotificationSender::getCount());
        $this->assertSame([ "a-player-id" ], TestNotificationSender::getLast()["playerIDs"]);

        $notification = NotificationQueue::getByID($notificationQueueID);
        $this->assertSame([ "a-player-id" ], $notification->playerIDs->toStrings());
    }

    public function testTheBadgeIsTheUnreadOfTheCredential(): void {
        $this->setConfig("NOTIFICATION_ACTIVE", true);
        Notification::setSender(TestNotificationSender::class);
        Device::add(self::CredentialID, "a-player-id");
        Device::add(self::OtherID, "another-player");

        // Two unread and one read, which is pushed just the same but
        // not counted, and one of somebody else
        $this->add();
        $this->add();
        NotificationQueue::markAsRead($this->add());
        $this->add(self::OtherID);

        NotificationQueue::sendAll();

        $badges = [];
        foreach (TestNotificationSender::getAll() as $push) {
            $badges[] = $push["badge"];
        }
        sort($badges);
        $this->assertSame([ 1, 2, 2, 2 ], $badges);
    }

    public function testOneWithNoProviderSaysSo(): void {
        $this->setConfig("NOTIFICATION_ACTIVE", true);
        $this->setConfig("NOTIFICATION_PROVIDER", "");
        Device::add(self::CredentialID, "a-player-id");
        $notificationQueueID = $this->add();

        NotificationQueue::sendAll();

        $this->assertSame(
            NotificationResult::NoProvider,
            NotificationQueue::getByID($notificationQueueID)->notificationResult,
        );
    }

    public function testSendingAnEmptyQueueWritesNothing(): void {
        NotificationQueue::sendAll();

        $this->assertSame(0, NotificationQueue::getEntityTotal());
    }



    public function testMarkingAsReadLeavesItRead(): void {
        $notificationQueueID = $this->add();

        $this->assertTrue(NotificationQueue::markAsRead($notificationQueueID));

        $this->assertTrue(NotificationQueue::getByID($notificationQueueID)->isRead);
    }

    public function testDiscardingLeavesItDiscarded(): void {
        $notificationQueueID = $this->add();

        $this->assertTrue(NotificationQueue::discard($notificationQueueID));

        $this->assertTrue(NotificationQueue::getByID($notificationQueueID)->isDiscarded);
    }

    public function testOneThatIsNotThereIsNotMarked(): void {
        $this->assertFalse(NotificationQueue::markAsRead(999999));
        $this->assertFalse(NotificationQueue::discard(999999));
    }



    public function testANotificationIsDeleted(): void {
        $notificationQueueID = $this->add();

        $this->assertTrue(NotificationQueue::delete($notificationQueueID));

        $this->assertFalse(NotificationQueue::exists($notificationQueueID));
    }

    public function testOneThatIsNotThereIsNotDeleted(): void {
        $this->assertFalse(NotificationQueue::delete(999999));
    }

    /**
     * A notification queued some days back, and whether it lives through it
     * @param int  $days
     * @param bool $survives
     * @return void
     */
    #[DataProvider("providerDeleteOld")]
    public function testTheOldOnesAreDeleted(int $days, bool $survives): void {
        $notificationQueueID = $this->add();
        $this->query(
            "UPDATE `notification_queue` SET `createdTime` = UNIX_TIMESTAMP() - $days * 86400 " .
            "WHERE `NOTIFICATION_QUEUE_ID` = $notificationQueueID",
        );

        $this->assertSame(!$survives, NotificationQueue::deleteOld());

        $this->assertSame($survives, NotificationQueue::exists($notificationQueueID));
    }

    /**
     * @return array<string,array{int,bool}>
     */
    public static function providerDeleteOld(): array {
        return [
            "older than the 7 of the config" => [ 8, false ],
            "younger than it"                => [ 6, true ],
        ];
    }



    public function testTheListIsSearched(): void {
        $this->add();
        $this->add(self::OtherID, "Another title");

        $request = new NotificationQueueRequest(search: "Another");
        $this->assertSame(1, NotificationQueue::getTotal($request));
        $this->assertSame("Another title", NotificationQueue::getList($request)[0]->title);
    }

    public function testTheListIsFilteredByResult(): void {
        $this->add();
        NotificationQueue::sendAll();
        $this->add(self::OtherID);

        $request = new NotificationQueueRequest(
            results: [ NotificationResult::NotProcessed->name ],
        );
        $this->assertSame(1, NotificationQueue::getTotal($request));
        $this->assertSame(2, NotificationQueue::getTotal(new NotificationQueueRequest()));
    }
}
