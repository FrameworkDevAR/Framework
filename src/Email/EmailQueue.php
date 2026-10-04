<?php
namespace Framework\Email;

use Framework\Email\Email;
use Framework\Email\EmailResult;
use Framework\Email\Schema\EmailQueueSchema;
use Framework\Email\Schema\EmailQueueRequest;
use Framework\Email\Schema\EmailQueueEntity;
use Framework\Email\Schema\EmailQueueColumn;
use Framework\Email\Schema\EmailQueueQuery;
use Framework\Email\Schema\EmailContentEntity;
use Framework\System\Config;
use Framework\System\EmailCode;
use Framework\Date\Date;
use Framework\Utils\Arrays;

/**
 * The Email Queue
 */
class EmailQueue extends EmailQueueSchema {

    /**
     * Creates the List Query
     * @param EmailQueueRequest $request
     * @return EmailQueueQuery
     */
    #[\Override]
    protected static function createListQuery(
        EmailQueueRequest $request,
    ): EmailQueueQuery {
        $query = new EmailQueueQuery();
        $query->search([
            EmailQueueColumn::SendTo,
            EmailQueueColumn::Subject,
            EmailQueueColumn::Message,
        ], $request->search);

        $query->createdTime->greaterThan($request->fromDate);
        $query->createdTime->lessThan($request->toDate);
        $query->dataID->equalIf($request->dataID);
        $query->emailResult->in(EmailResult::fromList($request->results));
        return $query;
    }

    /**
     * Returns all the not sent Queued Emails in the last hour
     * @return list<EmailQueueEntity>
     */
    public static function getAllUnsent(): array {
        $time  = Date::now()->subtract(hours: 1);
        $query = new EmailQueueQuery();
        $query->sentTime->isEmpty();
        $query->startOr();
        $query->createdTime->greaterThan($time);
        $query->sendTime->greaterThan($time);
        $query->endOr();
        $query->createdTime->orderByDesc();
        $query->limit(Config::getEmailLimit());
        return self::getEntityList($query);
    }



    /**
     * Adds the given Email to the Queue
     * @param EmailContentEntity  $content
     * @param list<string>|string $sendTo
     * @param string|null         $message Optional.
     * @param string|null         $subject Optional.
     * @param bool                $sendNow Optional.
     * @param int                 $dataID  Optional.
     * @return bool
     */
    public static function add(
        EmailContentEntity $content,
        array|string $sendTo,
        ?string $message = null,
        ?string $subject = null,
        bool $sendNow = false,
        int $dataID = 0,
    ): bool {
        return self::addEmail(
            emailCode: $content->emailCode,
            sendTo:    $sendTo,
            subject:   $subject ?? $content->subject,
            message:   $message ?? $content->message,
            sendNow:   $sendNow,
            dataID:    $dataID,
        );
    }

    /**
     * Adds an Email to the Queue and sends it if it is sent right away
     * @param EmailCode           $emailCode
     * @param list<string>|string $sendTo
     * @param string              $subject
     * @param string              $message
     * @param bool                $sendNow   Optional.
     * @param int                 $dataID    Optional.
     * @return bool
     */
    public static function addEmail(
        EmailCode $emailCode,
        array|string $sendTo,
        string $subject,
        string $message,
        bool $sendNow = false,
        int $dataID = 0,
    ): bool {
        $sendTos = Arrays::toStrings($sendTo);
        if (count($sendTos) === 0) {
            return false;
        }

        $emailQueueID = self::createEntity(
            emailCode:   $emailCode,
            sendTo:      $sendTos,
            subject:     $subject,
            message:     $message,
            emailResult: EmailResult::NotProcessed,
            sendTime:    Date::now(),
            sentTime:    Date::empty(),
            dataID:      $dataID,
        );

        if (!$sendNow) {
            return true;
        }

        $email = self::getByID($emailQueueID);
        self::send($email, $sendNow);
        return true;
    }



    /**
     * Sends all the Unsent Emails
     * @return void
     */
    public static function sendAll(): void {
        $emails = self::getAllUnsent();
        foreach ($emails as $email) {
            self::send($email, sendAlways: false);
        }
    }

    /**
     * Sends the given Email
     * @param EmailQueueEntity $email
     * @param bool             $sendAlways
     * @return void
     */
    public static function send(
        EmailQueueEntity $email,
        bool $sendAlways,
    ): void {
        $emailResult = EmailResult::NoEmails;
        $sendTos     = $email->sendTo->toStrings(withoutEmpty: true);

        foreach ($sendTos as $sendTo) {
            $emailResult = Email::send(
                $sendTo,
                $email->subject,
                $email->message,
                $sendAlways,
            );
        }
        self::markAsSent($email->id, $emailResult);
    }

    /**
     * Marks the given Email(s) as Not Sent
     * @param list<int>|int $emailQueueID
     * @return void
     */
    public static function markAsNotSent(array|int $emailQueueID): void {
        $query = new EmailQueueQuery();
        $query->emailQueueID->in(Arrays::toInts($emailQueueID));

        self::editEntity(
            $query,
            emailResult: EmailResult::NotProcessed,
            sendTime:    Date::now(),
            sentTime:    Date::empty(),
        );
    }

    /**
     * Marks the given Email as Sent
     * @param int         $emailQueueID
     * @param EmailResult $emailResult
     * @return void
     */
    public static function markAsSent(
        int $emailQueueID,
        EmailResult $emailResult,
    ): void {
        self::editEntity(
            $emailQueueID,
            emailResult: $emailResult,
            sentTime:    Date::now(),
        );
    }

    /**
     * Deletes the items older than some days
     * @return void
     */
    public static function deleteOld(): void {
        $days  = Config::getEmailDeleteDays();
        $time  = Date::now()->subtract(days: $days);

        $query = new EmailQueueQuery();
        $query->createdTime->lessThan($time);
        self::removeEntity($query);
    }
}
