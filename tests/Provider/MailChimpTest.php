<?php
namespace Tests\Provider;

use Framework\Core\Configs;
use Framework\Date\Date;
use Framework\Provider\MailChimp;
use Framework\Utils\Dictionary;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The MailChimp Provider
 *
 * Nothing here reaches MailChimp: what a request carries and how an answer is
 * read are checked apart from the call, and the calls are made switched off.
 */
class MailChimpTest extends TestCase {
    use TestHelpers;

    /** @var mixed */
    private mixed $data = null;


    protected function setUp(): void {
        Configs::load();
        $this->data = $this->getPrivateStaticProperty(Configs::class, "data");
        $this->setConfig("MAILCHIMP_ACTIVE", false);
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Configs::class, "data", $this->data);
    }



    #[DataProvider("providerSubscriberHash")]
    public function testTheHashIgnoresTheCase(string $email): void {
        $this->assertSame(md5("a@test.com"), MailChimp::getSubscriberHash($email));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerSubscriberHash(): array {
        return [
            "lower case" => [ "a@test.com" ],
            "upper case" => [ "A@Test.COM" ],
        ];
    }

    /**
     * @param array<string,mixed> $response
     */
    #[DataProvider("providerIsSuccess")]
    public function testTheAnswerSaysIfItWorked(array $response, bool $expected): void {
        $result = $this->callPrivateStaticMethod(MailChimp::class, "isSuccess", new Dictionary($response));
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string,array{array<string,mixed>,bool}>
     */
    public static function providerIsSuccess(): array {
        return [
            "an action"     => [ [], true ],
            "a member"      => [ [ "id" => "abc", "status" => "subscribed" ], true ],
            "a campaign"    => [ [ "id" => "1f0d5af8f4", "status" => "save" ], true ],
            "a report"      => [ [ "id" => "1f0d5af8f4", "emails_sent" => 10 ], true ],
            "an api error"  => [ [ "status" => 404, "title" => "Resource Not Found" ], false ],
            "a curl error"  => [ [ "error" => "28: Operation timed out" ], false ],
            "not json"      => [ [ "error" => "<html>Bad Gateway</html>" ], false ],
        ];
    }

    #[DataProvider("providerScheduleTime")]
    public function testTheScheduleTimeIsInUTC(string $timeZone, int $time, string $expected): void {
        $current = date_default_timezone_get();
        date_default_timezone_set($timeZone);
        try {
            $result = $this->callPrivateStaticMethod(MailChimp::class, "getScheduleTime", Date::create($time));
        } finally {
            date_default_timezone_set($current);
        }
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string,array{string,int,string}>
     */
    public static function providerScheduleTime(): array {
        return [
            "from UTC"       => [ "UTC", 1700000000, "2023-11-14 22:13:20" ],
            "from Argentina" => [ "America/Argentina/Buenos_Aires", 1700000000, "2023-11-14 22:13:20" ],
            "from Berlin"    => [ "Europe/Berlin", 1700000000, "2023-11-14 22:13:20" ],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider("providerMemberValues")]
    public function testTheMembersGiveTheirEmailOrAMergeField(string $mergeField, array $expected): void {
        $members = [
            new Dictionary([ "email_address" => "a@test.com", "merge_fields" => [ "CODE" => "101" ] ]),
            new Dictionary([ "email_address" => "b@test.com", "merge_fields" => [ "CODE" => "102" ] ]),
        ];
        $result = $this->callPrivateStaticMethod(MailChimp::class, "getMemberValues", $members, $mergeField);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string,array{string,list<string>}>
     */
    public static function providerMemberValues(): array {
        return [
            "the emails"        => [ "", [ "a@test.com", "b@test.com" ] ],
            "a merge field"     => [ "CODE", [ "101", "102" ] ],
            "a missing field"   => [ "CUIT", [ "", "" ] ],
        ];
    }

    /**
     * @param list<string>|null        $emails
     * @param array<string,mixed>|null $expected
     */
    #[DataProvider("providerRecipients")]
    public function testTheRecipientsAreTheListOrSomeOfIt(?array $emails, ?array $expected): void {
        $this->setConfig("MAILCHIMP_LIST", "a-list");
        $this->setConfig("EMAIL_USE_WHITE_LIST", false);

        $result = $this->callPrivateStaticMethod(MailChimp::class, "parseRecipients", $emails);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string,array{list<string>|null,array<string,mixed>|null}>
     */
    public static function providerRecipients(): array {
        $condition = [
            "condition_type" => "EmailAddress",
            "op"             => "is",
            "field"          => "EMAIL",
            "value"          => "a@test.com",
        ];
        return [
            "no emails"    => [ null, [ "list_id" => "a-list" ] ],
            "empty emails" => [ [], [ "list_id" => "a-list" ] ],
            "an email"     => [ [ "a@test.com" ], [
                "list_id"      => "a-list",
                "segment_opts" => [ "match" => "any", "conditions" => [ $condition ] ],
            ] ],
        ];
    }

    #[DataProvider("providerHasReport")]
    public function testOnlyACampaignThatReachedMailChimpHasAReport(
        bool $isActive,
        string $mailChimpID,
        bool $expected,
    ): void {
        $this->setConfig("MAILCHIMP_ACTIVE", $isActive);
        $this->assertSame($expected, MailChimp::hasReport($mailChimpID));
    }

    /**
     * @return array<string,array{bool,string,bool}>
     */
    public static function providerHasReport(): array {
        return [
            "a campaign"     => [ true, "1f0d5af8f4", true ],
            "no campaign"    => [ true, "", false ],
            "a disabled one" => [ true, MailChimp::Disabled, false ],
            "inactive"       => [ false, "1f0d5af8f4", false ],
        ];
    }

    public function testADisabledCampaignHasNothingToUnschedule(): void {
        $this->assertTrue(MailChimp::unscheduleCampaign(MailChimp::Disabled));
    }

    public function testNothingIsSentWhileItIsInactive(): void {
        $this->assertSame(MailChimp::Disabled, MailChimp::sendCampaign("A subject", null, 1));
        $this->assertFalse(MailChimp::updateCampaign("an-id", "A subject", null, 1));
        $this->assertFalse(MailChimp::addMember("a@test.com", [ "CODE" => "101" ]));
        $this->assertFalse(MailChimp::editMember("a@test.com", [ "CODE" => "101" ]));
        $this->assertFalse(MailChimp::addSubscriber("a@test.com", "A", "B"));
        $this->assertFalse(MailChimp::editSubscriber("a@test.com", "A", "B"));
        $this->assertFalse(MailChimp::addSubscriberBatch([ [ "email" => "a@test.com" ] ]));
        $this->assertFalse(MailChimp::deleteSubscriber("a@test.com"));
        $this->assertFalse(MailChimp::unscheduleCampaign("an-id"));
        $this->assertFalse(MailChimp::deleteCampaign("an-id"));
        $this->assertSame("", MailChimp::getSubscriberStatus("a@test.com"));
        $this->assertNull(MailChimp::getSubscriber("a@test.com"));
        $this->assertTrue(MailChimp::getAllSubscribers()->isEmpty());
        $this->assertSame([], MailChimp::getTemplates());
        $this->assertSame([], MailChimp::getContent("an-id"));
        $this->assertSame([
            "hasReport"    => false,
            "emailsSent"   => 0,
            "opensUnique"  => 0,
            "opensRate"    => 0.0,
            "clicksUnique" => 0,
            "clicksRate"   => 0.0,
        ], MailChimp::getReport("an-id"));
        $this->assertSame([], MailChimp::getOpenDetails("an-id", "CODE"));
        $this->assertSame([], MailChimp::getClickDetails("an-id", "CODE"));
        $this->assertSame([], MailChimp::getSendDetails("an-id"));
    }
}
