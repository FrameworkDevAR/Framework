<?php
namespace Tests\Auth;

use Framework\Auth\Device;
use Framework\System\NotificationProvider;

use Tests\LiveTestCase;
use Tests\TestHelpers;

/**
 * The Devices a Credential is reachable on, which a push is sent to
 */
class DeviceLiveTest extends LiveTestCase {
    use TestHelpers;

    private const CredentialID = 900001;
    private const OtherID      = 900002;
    private const PlayerID     = "a-player-of-the-tests";
    private const OtherPlayer  = "another-player";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        // Removing by the credential only reaches the devices of the provider of
        // the config, so what a test left of another one would stay behind
        foreach ([ self::PlayerID, self::OtherPlayer ] as $playerID) {
            Device::removeByPlayer($playerID);
        }
    }

    protected function tearDown(): void {
        $this->setConfig("NOTIFICATION_PROVIDER", "");
    }



    public function testACredentialWithNoDeviceHasNone(): void {
        $this->assertFalse(Device::has(self::CredentialID));
        $this->assertSame([], Device::getAllForCredential(self::CredentialID));
    }

    public function testTheDeviceIsAdded(): void {
        $this->assertTrue(Device::add(self::CredentialID, self::PlayerID));

        $this->assertTrue(Device::has(self::CredentialID));
        $this->assertSame([ self::PlayerID ], Device::getAllForCredential(self::CredentialID));
    }

    public function testACredentialHoldsMoreThanOneDevice(): void {
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::CredentialID, self::OtherPlayer);

        $result = Device::getAllForCredential(self::CredentialID);
        sort($result);

        $this->assertSame([ self::PlayerID, self::OtherPlayer ], $result);
    }

    public function testAddingTheSameDeviceTwiceLeavesOne(): void {
        // The row is replaced rather than added again, so the pair is unique
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::CredentialID, self::PlayerID);

        $this->assertSame([ self::PlayerID ], Device::getAllForCredential(self::CredentialID));
    }

    public function testTheDeviceIsRemoved(): void {
        Device::add(self::CredentialID, self::PlayerID);

        $this->assertTrue(Device::remove(self::CredentialID, self::PlayerID));
        $this->assertFalse(Device::has(self::CredentialID));
    }

    public function testRemovingLeavesTheOtherDevices(): void {
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::CredentialID, self::OtherPlayer);

        Device::remove(self::CredentialID, self::PlayerID);

        $this->assertSame([ self::OtherPlayer ], Device::getAllForCredential(self::CredentialID));
    }

    public function testTheDevicesOfTwoCredentialsAreKeptApart(): void {
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::OtherID, self::OtherPlayer);

        $this->assertSame([ self::PlayerID ], Device::getAllForCredential(self::CredentialID));
        $this->assertSame([ self::OtherPlayer ], Device::getAllForCredential(self::OtherID));
    }

    public function testTheDevicesOfSeveralCredentialsComeBackTogether(): void {
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::OtherID, self::OtherPlayer);

        $result = Device::getAllForCredential([ self::CredentialID, self::OtherID ]);
        sort($result);

        $this->assertSame([ self::PlayerID, self::OtherPlayer ], $result);
    }

    public function testTheDevicesOfAnotherProviderAreNotReached(): void {
        // The provider of the config is what a push goes through, so the
        // devices of the one it replaced are kept but not answered
        $this->setConfig("NOTIFICATION_PROVIDER", "Firebase");
        Device::add(self::CredentialID, self::PlayerID, NotificationProvider::OneSignal);
        Device::add(self::CredentialID, self::OtherPlayer, NotificationProvider::OneSignal);

        $this->assertSame([], Device::getAllForCredential(self::CredentialID));
        $this->assertFalse(Device::has(self::CredentialID));

        $result = Device::getAllForCredential(self::CredentialID, NotificationProvider::OneSignal);
        sort($result);
        $this->assertSame([ self::PlayerID, self::OtherPlayer ], $result);
        $this->assertTrue(Device::has(self::CredentialID, NotificationProvider::OneSignal));
    }

    public function testWithNoProviderEveryDeviceIsRead(): void {
        // None asks for no provider rather than for the ones of none, so with
        // the config naming none, what any of them left behind is read as well
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::CredentialID, self::OtherPlayer, NotificationProvider::OneSignal);

        $result = Device::getAllForCredential(self::CredentialID);
        sort($result);
        $this->assertSame([ self::PlayerID, self::OtherPlayer ], $result);
        $this->assertTrue(Device::has(self::CredentialID));
    }

    public function testADeviceIsOfTheProviderOfTheConfigUnlessToldOtherwise(): void {
        $this->setConfig("NOTIFICATION_PROVIDER", "Firebase");
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::CredentialID, self::OtherPlayer, NotificationProvider::OneSignal);

        $this->assertSame([ self::PlayerID ], Device::getAllForCredential(self::CredentialID));
        $this->assertSame(
            [ self::OtherPlayer ],
            Device::getAllForCredential(self::CredentialID, NotificationProvider::OneSignal),
        );
    }

    public function testRemovingReachesOnlyTheDevicesOfTheProvider(): void {
        $this->setConfig("NOTIFICATION_PROVIDER", "Firebase");
        Device::add(self::CredentialID, self::PlayerID, NotificationProvider::OneSignal);

        // Not of the provider of the config, so it is not the one removed
        $this->assertFalse(Device::remove(self::CredentialID, self::PlayerID));
        $this->assertTrue(Device::has(self::CredentialID, NotificationProvider::OneSignal));

        $this->assertTrue(Device::remove(self::CredentialID, self::PlayerID, NotificationProvider::OneSignal));
        $this->assertFalse(Device::has(self::CredentialID, NotificationProvider::OneSignal));
    }

    public function testRemovingAPlayerTakesItOffEveryCredential(): void {
        Device::add(self::CredentialID, self::PlayerID);
        Device::add(self::CredentialID, self::OtherPlayer);
        Device::add(self::OtherID, self::PlayerID);

        $this->assertTrue(Device::removeByPlayer(self::PlayerID));
        $this->assertFalse(Device::removeByPlayer(self::PlayerID));

        $this->assertSame([ self::OtherPlayer ], Device::getAllForCredential(self::CredentialID));
        $this->assertFalse(Device::has(self::OtherID));
    }

    public function testAskingForNoCredentialFindsNothing(): void {
        Device::add(self::CredentialID, self::PlayerID);

        $this->assertSame([], Device::getAllForCredential([]));
        $this->assertSame([], Device::getAllForCredential(0));
    }
}
