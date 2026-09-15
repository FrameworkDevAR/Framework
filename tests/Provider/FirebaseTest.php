<?php
namespace Tests\Provider;

use Framework\Date\Date;
use Framework\Provider\Firebase;
use Framework\Utils\Dictionary;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Firebase Provider
 *
 * The message is built apart from the call that posts it, so what a push
 * carries is checked here without Firebase to answer. The sends are reached
 * without a service account, which stops them before anything goes out.
 */
class FirebaseTest extends TestCase {
    use TestHelpers;

    /**
     * Puts a token in place as though one had just been asked for
     * @param int $seconds
     * @return void
     */
    private function keepToken(int $seconds): void {
        $this->setPrivateStaticProperty(Firebase::class, "accessToken", "a-token");
        $this->setPrivateStaticProperty(Firebase::class, "expiresAt", Date::now()->toTime() + $seconds);
    }

    protected function tearDown(): void {
        // The token is kept in a static, so one left there would have the next
        // test posting with it instead of stopping for want of a service account
        $this->setPrivateStaticProperty(Firebase::class, "accessToken", "");
        $this->setPrivateStaticProperty(Firebase::class, "expiresAt", 0);
    }



    public function testWithoutAServiceAccountNothingIsSent(): void {
        $this->assertSame("", Firebase::sendToAll("A title", "A message", "", "", "order", 7));
        $this->assertSame("", Firebase::sendToSome("A title", "A message", "", "", "order", 7, [ "a-token" ]));
    }

    public function testWithNoDeviceNothingIsPosted(): void {
        // A token is there, so a device to send to would have been posted to
        $this->keepToken(3600);

        $this->assertSame("", Firebase::sendToSome("A title", "A message", "", "", "order", 7, []));
    }

    public function testTheTokenIsKeptWhileItIsFresh(): void {
        $this->keepToken(3600);

        $result = $this->callPrivateStaticMethod(Firebase::class, "getAccessToken");
        $this->assertSame("a-token", $result);
    }

    public function testATokenAboutToExpireIsLeftBehind(): void {
        // Under the minute it is not used again, and with no service account
        // here there is nothing to take its place
        $this->keepToken(30);

        $result = $this->callPrivateStaticMethod(Firebase::class, "getAccessToken");
        $this->assertSame("", $result);
    }


    /**
     * What Firebase answered, and whether it says the token is gone
     * @param array<string,mixed> $response
     * @param bool                $expected
     * @return void
     */
    #[DataProvider("providerIsUnregistered")]
    public function testATokenThatIsGoneIsRecognised(array $response, bool $expected): void {
        $result = $this->callPrivateStaticMethod(
            Firebase::class,
            "isUnregistered",
            new Dictionary($response),
        );
        $this->assertSame($expected, $result);
    }

    /**
     * Only an UNREGISTERED in the details is the device being gone. Anything
     * else went wrong for a reason that says nothing about the token
     * @return array<string,array{array<string,mixed>,bool}>
     */
    public static function providerIsUnregistered(): array {
        $fcmError = "type.googleapis.com/google.firebase.fcm.v1.FcmError";

        return [
            "it went through"        => [
                [ "name" => "projects/the-app/messages/1234" ],
                false,
            ],
            "the token is gone"      => [
                [ "error" => [
                    "code"    => 404,
                    "status"  => "NOT_FOUND",
                    "details" => [
                        [ "@type" => $fcmError, "errorCode" => "UNREGISTERED" ],
                    ],
                ] ],
                true,
            ],
            "one of several details" => [
                [ "error" => [
                    "details" => [
                        [ "@type" => "type.googleapis.com/google.rpc.BadRequest" ],
                        [ "@type" => $fcmError, "errorCode" => "UNREGISTERED" ],
                    ],
                ] ],
                true,
            ],
            "another error"          => [
                [ "error" => [
                    "code"    => 400,
                    "status"  => "INVALID_ARGUMENT",
                    "details" => [
                        [ "@type" => $fcmError, "errorCode" => "INVALID_ARGUMENT" ],
                    ],
                ] ],
                false,
            ],
            "an error with no detail" => [
                [ "error" => [ "code" => 500, "status" => "INTERNAL" ] ],
                false,
            ],
            "nothing at all"         => [ [], false ],
        ];
    }


    #[DataProvider("providerCreateMessage")]
    public function testCreateMessage(array $args, array $expected): void {
        $this->assertSame($expected, Firebase::createMessage(...$args));
    }

    /**
     * @return array<string,array{array<string,mixed>,array<string,mixed>}>
     */
    public static function providerCreateMessage(): array {
        $notification = [ "title" => "A title", "body" => "A message" ];
        $data         = [ "type" => "order", "dataID" => "7", "url" => "https://app.test/orders/7" ];
        $apns         = [ "payload" => [ "aps" => [ "sound" => "default" ] ] ];
        $icon         = "https://app.test/icon.png";
        $url          = "https://app.test/orders/7";

        return [
            "a device"         => [
                [ "A title", "A message", $url, $icon, "order", 7, "token" => "the-token" ],
                [
                    "token"        => "the-token",
                    "notification" => $notification,
                    "data"         => $data,
                    "apns"         => $apns,
                    "webpush"      => [
                        "notification" => [ "icon" => $icon ],
                        "fcm_options"  => [ "link" => $url ],
                    ],
                ],
            ],
            "everyone"         => [
                [ "A title", "A message", $url, $icon, "order", 7, "topic" => Firebase::Topic ],
                [
                    "topic"        => "all",
                    "notification" => $notification,
                    "data"         => $data,
                    "apns"         => $apns,
                    "webpush"      => [
                        "notification" => [ "icon" => $icon ],
                        "fcm_options"  => [ "link" => $url ],
                    ],
                ],
            ],
            "without an icon"  => [
                [ "A title", "A message", $url, "", "order", 7, "token" => "the-token" ],
                [
                    "token"        => "the-token",
                    "notification" => $notification,
                    "data"         => $data,
                    "apns"         => $apns,
                    "webpush"      => [ "fcm_options" => [ "link" => $url ] ],
                ],
            ],
            "with a badge"     => [
                [ "A title", "A message", "", "", "order", 7, 3, "token" => "the-token" ],
                [
                    "token"        => "the-token",
                    "notification" => $notification,
                    "data"         => [ "type" => "order", "dataID" => "7", "url" => "" ],
                    "apns"         => [ "payload" => [ "aps" => [ "sound" => "default", "badge" => 3 ] ] ],
                ],
            ],
            "without a url"    => [
                [ "A title", "A message", "", "", "order", 7, "token" => "the-token" ],
                [
                    "token"        => "the-token",
                    "notification" => $notification,
                    "data"         => [ "type" => "order", "dataID" => "7", "url" => "" ],
                    "apns"         => $apns,
                ],
            ],
        ];
    }
}
