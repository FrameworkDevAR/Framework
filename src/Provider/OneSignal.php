<?php
namespace Framework\Provider;

use Framework\Notification\NotificationOutput;
use Framework\Notification\NotificationSender;
use Framework\Provider\Curl;
use Framework\Provider\Type\CurlMethod;
use Framework\System\Config;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\JSON;
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
     * @return NotificationOutput
     */
    #[\Override]
    public static function sendToAll(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
    ): NotificationOutput {
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
     * @return NotificationOutput
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
    ): NotificationOutput {
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
     * @return NotificationOutput
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
    ): NotificationOutput {
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

        // OneSignal answers with an empty ID when it would not take the push, and
        // it can also give errors for some devices of a push that it did take
        $data       = new Dictionary($response);
        $externalID = $data->getString("id");
        $error      = self::getError($data);
        if ($externalID === "") {
            return NotificationOutput::failed($error);
        }
        return NotificationOutput::sent($externalID, $error);
    }

    /**
     * Returns the Errors of the response as a single text
     * @param Dictionary $response
     * @return string
     */
    private static function getError(Dictionary $response): string {
        // The errors come as a list of texts, or as a map with the invalid devices
        $errors = $response->get("errors");
        if (Arrays::isList($errors)) {
            return Strings::join($errors, ", ");
        }
        return JSON::encode($errors);
    }
}
