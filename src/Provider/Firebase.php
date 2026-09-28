<?php
namespace Framework\Provider;

use Framework\Auth\Device;
use Framework\Date\Date;
use Framework\Notification\NotificationOutput;
use Framework\Notification\NotificationSender;
use Framework\Provider\Curl;
use Framework\Provider\Type\CurlMethod;
use Framework\System\Config;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

use Firebase\JWT\JWT;

/**
 * The Firebase Provider
 */
class Firebase implements NotificationSender {

    // Every device subscribes to it, so a push to everyone is a push to it
    public const Topic = "all";

    private const BaseUrl  = "https://fcm.googleapis.com/v1/projects/";
    private const TokenUrl = "https://oauth2.googleapis.com/token";
    private const Scope    = "https://www.googleapis.com/auth/firebase.messaging";

    private static string $accessToken = "";
    private static int    $expiresAt   = 0;


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
        $data = self::createMessage(
            $title,
            $message,
            $url,
            $icon,
            $dataType,
            $dataID,
            topic: self::Topic,
        );
        return self::send($data);
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
        // A message goes to one token, so each device is its own send
        $externalIDs = [];
        $errors      = [];
        foreach ($playerIDs as $playerID) {
            $data = self::createMessage(
                $title,
                $message,
                $url,
                $icon,
                $dataType,
                $dataID,
                $badge,
                token: $playerID,
            );
            $output = self::send($data, $playerID);
            if ($output->externalID !== "") {
                $externalIDs[] = $output->externalID;
            }
            if ($output->error !== "" && !Arrays::contains($errors, $output->error)) {
                $errors[] = $output->error;
            }
        }
        return NotificationOutput::fromProvider(
            Strings::join($externalIDs, ","),
            Strings::join($errors, ", "),
        );
    }

    /**
     * Creates the Message for a token or for a topic
     * @param string $title
     * @param string $message
     * @param string $url
     * @param string $icon
     * @param string $dataType
     * @param int    $dataID
     * @param int    $badge    Optional.
     * @param string $token    Optional.
     * @param string $topic    Optional.
     * @return array<string,mixed>
     */
    public static function createMessage(
        string $title,
        string $message,
        string $url,
        string $icon,
        string $dataType,
        int $dataID,
        int $badge = 0,
        string $token = "",
        string $topic = "",
    ): array {
        $result = $token !== "" ? [ "token" => $token ] : [ "topic" => $topic ];

        $result["notification"] = [
            "title" => $title,
            "body"  => $message,
        ];

        // The data only travels as strings
        $result["data"] = [
            "type"   => $dataType,
            "dataID" => Strings::toString($dataID),
            "url"    => $url,
        ];

        // Firebase only sets the badge to a number, so without one the
        // icon is left as it is
        $aps = [ "sound" => "default" ];
        if ($badge > 0) {
            $aps["badge"] = $badge;
        }
        $result["apns"] = [
            "payload" => [ "aps" => $aps ],
        ];

        // Only the web takes the icon and the url from here, the apps open
        // what the data says
        $webpush = [];
        if ($icon !== "") {
            $webpush["notification"] = [ "icon" => $icon ];
        }
        if ($url !== "") {
            $webpush["fcm_options"] = [ "link" => $url ];
        }
        if (count($webpush) > 0) {
            $result["webpush"] = $webpush;
        }

        return $result;
    }



    /**
     * Posts the Message and returns the ID Firebase gave it and its error
     * @param array<string,mixed> $message
     * @param string              $playerID Optional.
     * @return NotificationOutput
     */
    private static function send(array $message, string $playerID = ""): NotificationOutput {
        $accessToken = self::getAccessToken();
        if ($accessToken === "") {
            return NotificationOutput::failed("No access token");
        }

        $url      = self::BaseUrl . Config::getFirebaseProjectId() . "/messages:send";
        $headers  = [
            "Content-Type"  => "application/json; charset=utf-8",
            "Authorization" => "Bearer $accessToken",
        ];
        $response = Curl::execute(
            CurlMethod::POST,
            $url,
            [ "message" => $message ],
            $headers,
            jsonBody: true,
        );
        $data  = new Dictionary($response);
        $error = $data->getDict("error")->getString("message");

        // A token Firebase no longer knows is a device that is gone, so
        // the credential stops being sent to it
        if ($playerID !== "" && self::isUnregistered($data)) {
            Device::removeByPlayer($playerID);
            return NotificationOutput::failed($error);
        }

        // The name is projects/<project>/messages/<id>
        $name = $data->getString("name");
        if ($name === "") {
            return NotificationOutput::failed($error);
        }
        return NotificationOutput::sent(Strings::substringAfter($name, "/"));
    }

    /**
     * Returns true if the response says the token is no longer registered
     * @param Dictionary $response
     * @return bool
     */
    private static function isUnregistered(Dictionary $response): bool {
        $details = $response->getDict("error")->getList("details");
        foreach ($details as $detail) {
            if ($detail->getString("errorCode") === "UNREGISTERED") {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns an Access Token, asking for a new one when the last expired
     * @return string
     */
    private static function getAccessToken(): string {
        $time = Date::now()->toTime();
        if (self::$accessToken !== "" && self::$expiresAt > $time + 60) {
            return self::$accessToken;
        }

        // The key is pasted from the service account file, where the
        // line breaks are escaped
        $clientEmail = Config::getFirebaseClientEmail();
        $privateKey  = Strings::replace(Config::getFirebasePrivateKey(), "\\n", "\n");
        if ($clientEmail === "" || $privateKey === "") {
            return "";
        }

        $assertion = JWT::encode([
            "iss"   => $clientEmail,
            "scope" => self::Scope,
            "aud"   => self::TokenUrl,
            "iat"   => $time,
            "exp"   => $time + 3600,
        ], $privateKey, "RS256");

        $response = Curl::execute(CurlMethod::POST, self::TokenUrl, [
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion"  => $assertion,
        ], urlBody: true);
        $data = new Dictionary($response);

        self::$accessToken = $data->getString("access_token");
        self::$expiresAt   = $time + $data->getInt("expires_in");
        return self::$accessToken;
    }
}
