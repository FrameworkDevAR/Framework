<?php
namespace Framework\Provider;

use Framework\Notification\NotificationSender;
use Framework\Provider\Curl;
use Framework\Provider\Type\CurlMethod;
use Framework\System\Config;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The OneSignal Provider
 */
class OneSignal implements NotificationSender {

    private const BaseUrl = "https://api.onesignal.com";


    /**
     * Sends the Notification to every device there is
     * @param string $title
     * @param string $message
     * @param string $url
     * @param string $icon
     * @param string $dataType
     * @param int    $dataID
     * @return string
     */
    #[\Override]
    public static function sendToAll(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
    ): string {
        return self::send($title, $message, $url, $icon, $dataType, $dataID, 0, [
            "included_segments" => [ "All" ],
        ]);
    }

    /**
     * Sends the Notification to the given devices
     * @param string       $title
     * @param string       $message
     * @param string       $url
     * @param string       $icon
     * @param string       $dataType
     * @param int          $dataID
     * @param list<string> $playerIDs
     * @param int          $badge     Optional.
     * @return string
     */
    #[\Override]
    public static function sendToSome(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
        array $playerIDs,
        int $badge = 0,
    ): string {
        $params = [
            "include_subscription_ids" => $playerIDs,
        ];
        if (Config::isOnesignalUseAlias()) {
            $params = [
                "include_aliases" => [
                    "onesignal_id" => $playerIDs,
                ],
            ];
        }

        return self::send($title, $message, $url, $icon, $dataType, $dataID, $badge, $params);
    }

    /**
     * Posts the Notification, with whoever it is for
     * @param string              $title
     * @param string              $message
     * @param string              $url
     * @param string              $icon
     * @param string              $dataType
     * @param int                 $dataID
     * @param int                 $badge
     * @param array<string,mixed> $params
     * @return string
     */
    private static function send(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
        int $badge,
        array $params,
    ): string {
        $data = [
            "app_id"         => Config::getOnesignalAppId(),
            "target_channel" => "push",
            "headings"       => [ "en" => $title ],
            "contents"       => [ "en" => $message ],
            "url"            => $url,
            "large_icon"     => $icon,
            // Without a badge the device counts up on its own
            "ios_badgeType"  => $badge > 0 ? "SetTo" : "Increase",
            "ios_badgeCount" => $badge > 0 ? $badge : 1,
            "data"           => [
                "type"   => $dataType,
                "dataID" => $dataID,
            ],
        ] + $params;

        $headers = [
            "Content-Type"  => "application/json; charset=utf-8",
            "Authorization" => "Basic " . Config::getOnesignalRestKey(),
        ];
        $response = Curl::execute(
            CurlMethod::POST,
            self::BaseUrl . "/notifications",
            $data,
            $headers,
            jsonBody: true,
        );

        if (!is_array($response) || !isset($response["id"]) ||
            Arrays::isEmpty($response["id"])
        ) {
            return "";
        }
        return Strings::toString($response["id"]);
    }
}
