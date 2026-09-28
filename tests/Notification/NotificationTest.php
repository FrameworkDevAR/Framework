<?php
namespace Tests\Notification;

use Framework\Notification\Notification;
use Framework\Notification\NotificationOutput;
use Framework\Notification\NotificationResult;

use Tests\Notification\Fixture\TestNotificationSender;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Notification sender, whole, without one being pushed
 *
 * The Sender of the tests keeps what it is handed rather than pushing it, so a
 * send runs the length of the class and stops there with the push to look at.
 */
class NotificationTest extends TestCase {
    use TestHelpers;

    private const PlayerID = "a-player-id";
    private const Url      = "https://framework.test/";


    protected function setUp(): void {
        TestNotificationSender::reset();
    }

    protected function tearDown(): void {
        $this->setConfig("NOTIFICATION_ACTIVE", false);
        $this->setConfig("NOTIFICATION_ICON", "");
        $this->setConfig("NOTIFICATION_PROVIDER", "");
        $this->setConfig("URL", "");
        Notification::setSender();
        TestNotificationSender::reset();
    }

    /**
     * Turns the push on, sending through the Sender of the tests
     * @return void
     */
    private function sendForReal(): void {
        $this->setConfig("NOTIFICATION_ACTIVE", true);
        $this->setConfig("URL", self::Url);
        Notification::setSender(TestNotificationSender::class);
    }

    /**
     * Sends to the one device of the tests
     * @param string $url   Optional.
     * @param int    $badge Optional.
     * @return NotificationOutput
     */
    private function send(string $url = "orders/7", int $badge = 0): NotificationOutput {
        return Notification::sendToSome(
            "A title",
            "A message",
            $url,
            "order",
            7,
            [ self::PlayerID ],
            $badge,
        );
    }

    /**
     * Asserts that the Output holds the given values
     * @param NotificationResult $result
     * @param string             $externalID
     * @param string             $error
     * @param NotificationOutput $output
     * @return void
     */
    private function assertOutput(
        NotificationResult $result,
        string $externalID,
        string $error,
        NotificationOutput $output,
    ): void {
        $this->assertSame($result, $output->result);
        $this->assertSame($externalID, $output->externalID);
        $this->assertSame($error, $output->error);
    }



    public function testNothingIsSentWhileThePushIsOff(): void {
        Notification::setSender(TestNotificationSender::class);

        $this->assertOutput(NotificationResult::InactiveSend, "", "", $this->send());
        $this->assertSame(0, TestNotificationSender::getCount());
    }

    public function testThereIsNothingToSendToNobody(): void {
        $this->sendForReal();

        $this->assertOutput(NotificationResult::NoDevices, "", "", Notification::sendToSome(
            "A title",
            "A message",
            "orders/7",
            "order",
            7,
            [],
        ));
        $this->assertSame(0, TestNotificationSender::getCount());
    }

    public function testThereIsNothingToSendThroughWithoutAProvider(): void {
        // Which is what this repository is set up with, since it pushes nothing
        $this->setConfig("NOTIFICATION_ACTIVE", true);
        $this->setConfig("NOTIFICATION_PROVIDER", "");

        $this->assertOutput(NotificationResult::NoProvider, "", "", $this->send());
    }

    public function testASendGoesThroughToTheProvider(): void {
        $this->sendForReal();

        $this->assertOutput(NotificationResult::Sent, "the-external-id", "", $this->send());

        $push = TestNotificationSender::getLast();
        $this->assertSame("A title", $push["title"]);
        $this->assertSame("A message", $push["message"]);
        $this->assertSame("order", $push["dataType"]);
        $this->assertSame(7, $push["dataID"]);
        $this->assertSame([ self::PlayerID ], $push["playerIDs"]);
        $this->assertSame(0, $push["badge"]);
    }

    public function testTheBadgeIsHandedToTheProvider(): void {
        $this->sendForReal();

        $this->send(badge: 3);

        $this->assertSame(3, TestNotificationSender::getLast()["badge"]);
    }

    /**
     * A send to everyone, which answers for the config the same way
     * @param bool               $isActive
     * @param NotificationResult $expected
     * @return void
     */
    #[DataProvider("providerSendToAll")]
    public function testASendToEveryoneAnswersForTheConfig(
        bool $isActive,
        NotificationResult $expected,
    ): void {
        $this->setConfig("NOTIFICATION_ACTIVE", $isActive);

        $this->assertOutput($expected, "", "", Notification::sendToAll(
            "A title",
            "A message",
            "changelog",
            "release",
            42,
        ));
        $this->assertSame(0, TestNotificationSender::getCount());
    }

    /**
     * @return array<string,array{bool,NotificationResult}>
     */
    public static function providerSendToAll(): array {
        return [
            "the push is off"      => [ false, NotificationResult::InactiveSend ],
            "there is no provider" => [ true, NotificationResult::NoProvider ],
        ];
    }

    public function testASendToEveryoneCarriesNoDevices(): void {
        $this->sendForReal();

        $this->assertOutput(
            NotificationResult::Sent,
            "the-external-id",
            "",
            Notification::sendToAll("A title", "A message", "changelog", "release", 42),
        );

        $this->assertSame([], TestNotificationSender::getLast()["playerIDs"]);
    }

    public function testAProviderThatRefusesIsAProviderError(): void {
        // A Provider that would not take the push gives no ID for it
        $this->sendForReal();
        TestNotificationSender::setExternalID("");
        TestNotificationSender::setError("Not subscribed");

        $this->assertOutput(NotificationResult::ProviderError, "", "Not subscribed", $this->send());

        // It was handed over just the same
        $this->assertSame(1, TestNotificationSender::getCount());
    }

    public function testASentPushKeepsTheErrorsOfSomeDevices(): void {
        // A Provider can take the push and still refuse some of its devices
        $this->sendForReal();
        TestNotificationSender::setError("Invalid device");

        $this->assertOutput(NotificationResult::Sent, "the-external-id", "Invalid device", $this->send());
    }

    public function testThePushCarriesTheIconOfTheConfig(): void {
        $this->sendForReal();
        $this->setConfig("NOTIFICATION_ICON", "icon.png");

        $this->send();

        // It goes out as a full url, so the device can fetch it
        $icon = TestNotificationSender::getLast()["icon"];
        $this->assertStringStartsWith(self::Url, $icon);
        $this->assertStringEndsWith("icon.png", $icon);
    }

    public function testThereIsNoIconWhenNoneIsSet(): void {
        $this->sendForReal();

        $this->send();

        $this->assertSame("", TestNotificationSender::getLast()["icon"]);
    }

    /**
     * The url a push is given, and the one it goes out with
     * @param string $url
     * @param bool   $isFull
     * @return void
     */
    #[DataProvider("providerUrl")]
    public function testTheUrlIsMadeAFullOne(string $url, bool $isFull): void {
        $this->sendForReal();

        $this->send($url);

        $result = TestNotificationSender::getLast()["url"];
        $this->assertSame($isFull ? $url : self::Url . $url, $result);
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerUrl(): array {
        return [
            "one of the app"  => [ "orders/7", false ],
            "one that is not" => [ "https://example.com/orders/7", true ],
        ];
    }
}
