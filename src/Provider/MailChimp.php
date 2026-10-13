<?php
// spell-checker: ignore  apikey, timewarp
namespace Framework\Provider;

use Framework\IO\Select;
use Framework\Email\EmailWhiteList;
use Framework\Provider\Type\CurlMethod;
use Framework\System\Config;
use Framework\Date\Date;
use Framework\Date\Type\DateFormat;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

/**
 * The MailChimp Provider
 */
class MailChimp {

    /** The ID returned for a Campaign when MailChimp is not active */
    public const Disabled = "disabled";

    private const BaseUrl = "https://<dc>.api.mailchimp.com/3.0";


    /**
     * Returns the Base Url
     * @param string $route
     * @return string
     */
    private static function getUrl(string $route): string {
        $key     = Config::getMailchimpKey();
        $dc      = Strings::substringAfter($key, "-");
        $baseUrl = Strings::replace(self::BaseUrl, "<dc>", $dc);
        return "$baseUrl/$route";
    }

    /**
     * Returns the Headers
     * @return array<string,string>
     */
    private static function getHeaders(): array {
        $key = Config::getMailchimpKey();
        return [
            "Accept"        => "application/vnd.api+json",
            "Content-Type"  => "application/vnd.api+json",
            "Authorization" => "apikey $key",
        ];
    }

    /**
     * Does a Request
     * @param CurlMethod               $method
     * @param string                   $route
     * @param array<string,mixed>|null $request Optional.
     * @return Dictionary
     */
    private static function request(
        CurlMethod $method,
        string $route,
        ?array $request = null,
    ): Dictionary {
        $result = Curl::execute(
            method:      $method,
            url:         self::getUrl($route),
            params:      $request,
            headers:     self::getHeaders(),
            jsonBody:    $method !== CurlMethod::GET && $method !== CurlMethod::DELETE,
            returnError: true,
        );
        return new Dictionary($result);
    }

    /**
     * Returns true if the response is successful
     * @param Dictionary $response
     * @return bool
     */
    private static function isSuccess(Dictionary $response): bool {
        // A failed request comes as an error, and MailChimp answers its errors with a numeric
        // status, while a success has a text status, or no body at all for the actions
        return !$response->has("error") && !is_int($response->get("status"));
    }



    /**
     * Convert an email address into a subscriber hash
     * @param string $email
     * @return string
     */
    public static function getSubscriberHash(string $email): string {
        return md5(strtolower($email));
    }

    /**
     * Returns the Members Route
     * @param string $route Optional.
     * @return string
     */
    private static function getSubscribersRoute(string $route = ""): string {
        $listID = Config::getMailchimpList();
        $result = "lists/$listID/members";
        if ($route !== "") {
            $result .= "/$route";
        }
        return $result;
    }

    /**
     * Returns true if the Subscribers can be changed
     * @return bool
     */
    private static function canEditSubscribers(): bool {
        return Config::isMailchimpActive() && Config::isMailchimpSubscriberActive();
    }

    /**
     * Returns all the subscribers
     * @param int $count  Optional.
     * @param int $offset Optional.
     * @return Dictionary
     */
    public static function getAllSubscribers(int $count = 100, int $offset = 0): Dictionary {
        if (!Config::isMailchimpActive()) {
            return new Dictionary();
        }

        $route    = self::getSubscribersRoute();
        $response = self::request(CurlMethod::GET, $route, [
            "count"  => $count,
            "offset" => $offset,
        ]);
        return $response;
    }

    /**
     * Returns the Subscriber with the given email
     * @param string $email
     * @return Dictionary|null
     */
    public static function getSubscriber(string $email): ?Dictionary {
        if (!Config::isMailchimpActive()) {
            return null;
        }

        $hash     = self::getSubscriberHash($email);
        $route    = self::getSubscribersRoute($hash);
        $response = self::request(CurlMethod::GET, $route);
        if ($response->isEmpty() || !self::isSuccess($response)) {
            return null;
        }
        return $response;
    }

    /**
     * Returns the Subscriber status for the given email
     * @param string $email
     * @return string
     */
    public static function getSubscriberStatus(string $email): string {
        $subscriber = self::getSubscriber($email);
        if ($subscriber === null) {
            return "";
        }
        return $subscriber->getString("status");
    }

    /**
     * Adds a Subscriber with the given Merge Fields
     * @param string               $email
     * @param array<string,string> $mergeFields
     * @return bool
     */
    public static function addMember(string $email, array $mergeFields): bool {
        if (!self::canEditSubscribers()) {
            return false;
        }

        $response = self::request(CurlMethod::POST, self::getSubscribersRoute(), [
            "status"        => "subscribed",
            "email_address" => $email,
            "merge_fields"  => $mergeFields,
        ]);
        return self::isSuccess($response);
    }

    /**
     * Edits a Subscriber with the given Merge Fields
     * @param string               $email
     * @param array<string,string> $mergeFields
     * @param string               $status      Optional.
     * @return bool
     */
    public static function editMember(
        string $email,
        array $mergeFields,
        string $status = "subscribed",
    ): bool {
        if (!self::canEditSubscribers()) {
            return false;
        }

        $hash     = self::getSubscriberHash($email);
        $route    = self::getSubscribersRoute($hash);
        $response = self::request(CurlMethod::PATCH, $route, [
            "status"        => $status,
            "email_address" => $email,
            "merge_fields"  => $mergeFields,
        ]);
        return self::isSuccess($response);
    }

    /**
     * Adds a Subscriber
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @return bool
     */
    public static function addSubscriber(string $email, string $firstName, string $lastName): bool {
        return self::addMember($email, [
            "FNAME" => $firstName,
            "LNAME" => $lastName,
        ]);
    }

    /**
     * Adds a Subscriber Batch
     * @param list<array<string,string>> $subscribers
     * @return bool
     */
    public static function addSubscriberBatch(array $subscribers): bool {
        if (!self::canEditSubscribers()) {
            return false;
        }

        foreach ($subscribers as $subscriber) {
            self::addMember($subscriber["email"] ?? "", [
                "FNAME" => $subscriber["firstName"] ?? "",
                "LNAME" => $subscriber["lastName"] ?? "",
            ]);
        }
        return true;
    }

    /**
     * Edits a Subscriber
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $status    Optional.
     * @return bool
     */
    public static function editSubscriber(
        string $email,
        string $firstName,
        string $lastName,
        string $status = "subscribed",
    ): bool {
        return self::editMember($email, [
            "FNAME" => $firstName,
            "LNAME" => $lastName,
        ], $status);
    }

    /**
     * Deletes a Subscriber
     * @param string $email
     * @return bool
     */
    public static function deleteSubscriber(string $email): bool {
        if (!self::canEditSubscribers()) {
            return false;
        }

        $hash     = self::getSubscriberHash($email);
        $route    = self::getSubscribersRoute($hash);
        $response = self::request(CurlMethod::DELETE, $route);
        return self::isSuccess($response);
    }



    /**
     * Returns a list of Templates
     * @return list<Select>
     */
    public static function getTemplates(): array {
        if (!Config::isMailchimpActive()) {
            return [];
        }

        $response = self::request(CurlMethod::GET, "templates");
        if ($response->hasValue("templates")) {
            $templates = $response->getList("templates");
            return Select::create($templates, "id", "name");
        }
        return [];
    }

    /**
     * Sends a Campaign now, without a time or with one that is not in the future, or schedules it
     * @param string                    $subject
     * @param Date|null                 $time
     * @param int                       $templateID
     * @param array<string,string>|null $sections   Optional.
     * @param list<string>|null         $emails     Optional.
     * @param string                    $folderID   Optional.
     * @param string                    $toName     Optional.
     * @return string|null
     */
    public static function sendCampaign(
        string $subject,
        ?Date $time,
        int $templateID,
        ?array $sections = null,
        ?array $emails = null,
        string $folderID = "",
        string $toName = "*|FNAME|*",
    ): ?string {
        if (!Config::isMailchimpActive() || !Config::isMailchimpCreateActive()) {
            return self::Disabled;
        }

        // Create the Campaign
        $mailChimpID = self::createCampaign($subject, $emails, $folderID, $toName);
        if ($mailChimpID === "") {
            return null;
        }

        // Place the Content
        if (!self::placeContent($mailChimpID, $templateID, $sections)) {
            return null;
        }

        // We cant send
        if (!Config::isMailchimpSendActive()) {
            return $mailChimpID;
        }

        // Send/Schedule the Campaign
        if (self::mailCampaign($mailChimpID, $time)) {
            return $mailChimpID;
        }

        // Something went wrong
        return null;
    }

    /**
     * Updates a Campaign, sending it now without a time or with one that is not in the future
     * @param string                    $mailChimpID
     * @param string                    $subject
     * @param Date|null                 $time
     * @param int                       $templateID
     * @param array<string,string>|null $sections    Optional.
     * @param list<string>|null         $emails      Optional.
     * @param string                    $folderID    Optional.
     * @param string                    $toName      Optional.
     * @return bool
     */
    public static function updateCampaign(
        string $mailChimpID,
        string $subject,
        ?Date $time,
        int $templateID,
        ?array $sections = null,
        ?array $emails = null,
        string $folderID = "",
        string $toName = "*|FNAME|*",
    ): bool {
        if (!Config::isMailchimpActive() || !Config::isMailchimpCreateActive()) {
            return false;
        }

        // Edit the Campaign
        if (!self::editCampaign($mailChimpID, $subject, $emails, $folderID, $toName)) {
            return false;
        }

        // Place the Content
        if (!self::placeContent($mailChimpID, $templateID, $sections)) {
            return false;
        }

        // We cant send
        if (!Config::isMailchimpSendActive()) {
            return true;
        }

        // Send/Schedule the Campaign
        if (!self::mailCampaign($mailChimpID, $time)) {
            return false;
        }

        // All good
        return true;
    }

    /**
     * Creates a Campaign
     * @param string            $subject
     * @param list<string>|null $emails   Optional.
     * @param string            $folderID Optional.
     * @param string            $toName   Optional.
     * @return string
     */
    private static function createCampaign(
        string $subject,
        ?array $emails = null,
        string $folderID = "",
        string $toName = "*|FNAME|*",
    ): string {
        $recipients = self::parseRecipients($emails);
        if ($recipients === null || count($recipients) === 0) {
            return "";
        }

        $post = [
            "type"       => "regular",
            "recipients" => $recipients,
            "settings"   => [
                "subject_line" => $subject,
                "title"        => $subject,
                "from_name"    => Config::getMailchimpName(),
                "reply_to"     => Config::getMailchimpReplyTo(),
                "to_name"      => $toName,
            ],
        ];
        if ($folderID !== "") {
            $post["settings"]["folder_id"] = $folderID;
        }
        $response = self::request(CurlMethod::POST, "campaigns", $post);

        if (self::isSuccess($response)) {
            return $response->getString("id");
        }
        return "";
    }

    /**
     * Edits a Campaign
     * @param string            $mailChimpID
     * @param string            $subject
     * @param list<string>|null $emails      Optional.
     * @param string            $folderID    Optional.
     * @param string            $toName      Optional.
     * @return bool
     */
    private static function editCampaign(
        string $mailChimpID,
        string $subject,
        ?array $emails = null,
        string $folderID = "",
        string $toName = "*|FNAME|*",
    ): bool {
        $recipients = self::parseRecipients($emails);
        if ($recipients === null || count($recipients) === 0) {
            return false;
        }

        $post = [
            "recipients" => $recipients,
            "settings"   => [
                "subject_line" => $subject,
                "title"        => $subject,
                "to_name"      => $toName,
            ],
        ];
        if ($folderID !== "") {
            $post["settings"]["folder_id"] = $folderID;
        }

        $response = self::request(CurlMethod::PATCH, "campaigns/$mailChimpID", $post);
        return self::isSuccess($response);
    }

    /**
     * Parses the recipients of a Campaign
     * @param list<string>|null $emails Optional.
     * @return array<string,mixed>|null
     */
    private static function parseRecipients(?array $emails = null): ?array {
        $recipients = [ "list_id" => Config::getMailchimpList() ];
        if ($emails === null || count($emails) === 0) {
            return $recipients;
        }

        $conditions = [];
        foreach ($emails as $email) {
            if (Config::isEmailUseWhiteList() && !EmailWhiteList::emailExists($email)) {
                continue;
            }
            $conditions[] = [
                "condition_type" => "EmailAddress",
                "op"             => "is",
                "field"          => "EMAIL",
                "value"          => $email,
            ];
        }
        if (count($conditions) === 0) {
            return null;
        }

        $recipients["segment_opts"] = [
            "match"      => "any",
            "conditions" => $conditions,
        ];
        return $recipients;
    }

    /**
     * Puts the content into the given MailChimp campaign
     * @param string                    $mailChimpID
     * @param int                       $templateID
     * @param array<string,string>|null $sections    Optional.
     * @return bool
     */
    private static function placeContent(
        string $mailChimpID,
        int $templateID,
        ?array $sections = null,
    ): bool {
        $post = [ "template" => [ "id" => $templateID ] ];
        if ($sections !== null && count($sections) > 0) {
            $post["template"]["sections"] = $sections;
        }

        $response = self::request(CurlMethod::PUT, "campaigns/{$mailChimpID}/content", $post);
        return self::isSuccess($response);
    }

    /**
     * Sends the given MailChimp campaign now, or schedules it if the time is in the future
     * @param string    $mailChimpID
     * @param Date|null $time        Optional.
     * @return bool
     */
    private static function mailCampaign(string $mailChimpID, ?Date $time = null): bool {
        $route = "campaigns/{$mailChimpID}/actions";
        if ($time === null || !$time->isFuture()) {
            $response = self::request(CurlMethod::POST, "$route/send");
        } else {
            $response = self::request(CurlMethod::POST, "$route/schedule", [
                "schedule_time" => self::getScheduleTime($time),
                "timewarp"      => false,
            ]);
        }
        return self::isSuccess($response);
    }

    /**
     * Returns the given time as MailChimp schedules it, which is in UTC
     * @param Date $time
     * @return string
     */
    private static function getScheduleTime(Date $time): string {
        return gmdate(DateFormat::ReverseSeconds->value, $time->toTime());
    }

    /**
     * Un-schedules the given MailChimp campaign, where a disabled one has nothing to un-schedule
     * @param string $mailChimpID
     * @return bool
     */
    public static function unscheduleCampaign(string $mailChimpID): bool {
        if ($mailChimpID === self::Disabled) {
            return true;
        }
        if (!Config::isMailchimpActive()) {
            return false;
        }

        $response = self::request(CurlMethod::POST, "campaigns/{$mailChimpID}/actions/unschedule");
        return self::isSuccess($response);
    }

    /**
     * Deletes the given MailChimp campaign
     * @param string $mailChimpID
     * @return bool
     */
    public static function deleteCampaign(string $mailChimpID): bool {
        if (!Config::isMailchimpActive()) {
            return false;
        }

        $response = self::request(CurlMethod::DELETE, "campaigns/{$mailChimpID}");
        return self::isSuccess($response);
    }



    /**
     * Returns true if the given MailChimp campaign can have a Report, without asking MailChimp
     * @param string $mailChimpID
     * @return bool
     */
    public static function hasReport(string $mailChimpID): bool {
        return (
            Config::isMailchimpActive() &&
            $mailChimpID !== "" &&
            $mailChimpID !== self::Disabled
        );
    }

    /**
     * Returns the Content of the given MailChimp campaign
     * @param string $mailChimpID
     * @return array<int|string,mixed>
     */
    public static function getContent(string $mailChimpID): array {
        if (!Config::isMailchimpActive()) {
            return [];
        }

        $response = self::request(CurlMethod::GET, "campaigns/{$mailChimpID}/content");
        return $response->toArray();
    }

    /**
     * Returns the Report for the given MailChimp campaign
     * @param string $mailChimpID
     * @return array{hasReport:bool,emailsSent:int,opensUnique:int,opensRate:float,clicksUnique:int,clicksRate:float}
     */
    public static function getReport(string $mailChimpID): array {
        $result = [
            "hasReport"    => false,
            "emailsSent"   => 0,
            "opensUnique"  => 0,
            "opensRate"    => 0.0,
            "clicksUnique" => 0,
            "clicksRate"   => 0.0,
        ];
        if (!self::hasReport($mailChimpID)) {
            return $result;
        }

        $response = self::request(CurlMethod::GET, "reports/{$mailChimpID}");
        if ($response->isEmpty() || !self::isSuccess($response)) {
            return $result;
        }

        $opens  = $response->getDict("opens");
        $clicks = $response->getDict("clicks");

        $result["hasReport"]    = true;
        $result["emailsSent"]   = $response->getInt("emails_sent");
        $result["opensUnique"]  = $opens->getInt("unique_opens");
        $result["opensRate"]    = round($opens->getFloat("open_rate") * 1000) / 10;
        $result["clicksUnique"] = $clicks->getInt("unique_clicks");
        $result["clicksRate"]   = round($clicks->getFloat("click_rate") * 1000) / 10;
        return $result;
    }

    /**
     * Returns the Send Details Report for the given MailChimp campaign
     * @param string $mailChimpID
     * @param string $mergeField  Optional.
     * @return list<string>
     */
    public static function getSendDetails(string $mailChimpID, string $mergeField = ""): array {
        if (!self::hasReport($mailChimpID)) {
            return [];
        }

        $response = self::request(CurlMethod::GET, "reports/{$mailChimpID}/sent-to", [
            "count" => 2000,
        ]);
        return self::getMemberValues($response->getList("sent_to"), $mergeField);
    }

    /**
     * Returns the Open Details Report for the given MailChimp campaign
     * @param string $mailChimpID
     * @param string $mergeField  Optional.
     * @return list<string>
     */
    public static function getOpenDetails(string $mailChimpID, string $mergeField = ""): array {
        if (!self::hasReport($mailChimpID)) {
            return [];
        }

        $response = self::request(CurlMethod::GET, "reports/{$mailChimpID}/open-details", [
            "count" => 2000,
        ]);
        return self::getMemberValues($response->getList("members"), $mergeField);
    }

    /**
     * Returns the Click Details Report of the first url clicked in the given MailChimp campaign
     * @param string $mailChimpID
     * @param string $mergeField  Optional.
     * @return list<string>
     */
    public static function getClickDetails(string $mailChimpID, string $mergeField = ""): array {
        if (!self::hasReport($mailChimpID)) {
            return [];
        }

        $response   = self::request(CurlMethod::GET, "reports/{$mailChimpID}/click-details");
        $urlClicked = $response->getFirst("urls_clicked");
        $clickID    = $urlClicked->getString("id");
        if ($clickID === "") {
            return [];
        }

        $route  = "reports/{$mailChimpID}/click-details/{$clickID}/members";
        $report = self::request(CurlMethod::GET, $route, [
            "count" => 2000,
        ]);
        return self::getMemberValues($report->getList("members"), $mergeField);
    }

    /**
     * Returns the email of each member, or the given Merge Field when there is one
     * @param list<Dictionary> $members
     * @param string           $mergeField Optional.
     * @return list<string>
     */
    private static function getMemberValues(array $members, string $mergeField = ""): array {
        $result = [];
        foreach ($members as $member) {
            if ($mergeField !== "") {
                $result[] = $member->getDict("merge_fields")->getString($mergeField);
            } else {
                $result[] = $member->getString("email_address");
            }
        }
        return $result;
    }
}
