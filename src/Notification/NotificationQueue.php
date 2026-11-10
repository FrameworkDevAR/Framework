<?php
namespace Framework\Notification;

use Framework\Auth\Device;
use Framework\Notification\Notification;
use Framework\Notification\NotificationResult;
use Framework\Notification\Schema\NotificationQueueSchema;
use Framework\Notification\Schema\NotificationQueueRequest;
use Framework\Notification\Schema\NotificationQueueEntity;
use Framework\Notification\Schema\NotificationQueueColumn;
use Framework\Notification\Schema\NotificationQueueQuery;
use Framework\System\Config;
use Framework\System\Event;
use Framework\Date\Date;

/**
 * The Notification Queue
 */
class NotificationQueue extends NotificationQueueSchema {

    /**
     * Returns true if there is a Notification with the given ID for the given Credential
     * @param int $notificationQueueID
     * @param int $credentialID
     * @return bool
     */
    public static function existsForCredential(
        int $notificationQueueID,
        int $credentialID,
    ): bool {
        $query = new NotificationQueueQuery();
        $query->notificationQueueID->equal($notificationQueueID);
        $query->credentialID->equal($credentialID);
        return self::entityExists($query);
    }



    /**
     * Creates the List Query
     * @param NotificationQueueRequest $request
     * @return NotificationQueueQuery
     */
    #[\Override]
    protected static function createListQuery(
        NotificationQueueRequest $request,
    ): NotificationQueueQuery {
        $query = new NotificationQueueQuery();
        $query->search([
            NotificationQueueColumn::Title,
            NotificationQueueColumn::Message,
            NotificationQueueColumn::CredentialFirstName,
            NotificationQueueColumn::CredentialLastName,
        ], $request->search);

        $query->createdTime->greaterThan($request->fromDate);
        $query->createdTime->lessThan($request->toDate);
        $query->notificationResult->in(NotificationResult::fromList($request->results));
        return $query;
    }



    /**
     * Returns the Unset Notifications in the last hour
     * @return list<NotificationQueueEntity>
     */
    public static function getAllUnsent(): array {
        $time  = Date::now()->subtract(hours: 1);

        $query = new NotificationQueueQuery();
        $query->notificationResult->equal(NotificationResult::NotProcessed);
        $query->createdTime->greaterThan($time);
        $query->inCurrentEnv();
        $query->createdTime->orderByDesc();
        $query->limit(Config::getNotificationLimit());
        return self::getEntityList($query);
    }

    /**
     * Returns the IDs of the Unsent Notifications since the given time, oldest first
     * @param Date $since
     * @return list<int>
     */
    public static function getUnsentIDs(Date $since): array {
        $query = new NotificationQueueQuery();
        $query->notificationResult->equal(NotificationResult::NotProcessed);
        $query->createdTime->greaterOrEqual($since);
        $query->createdTime->orderByAsc();

        $result = [];
        foreach (self::getEntityList($query) as $elem) {
            $result[] = $elem->notificationQueueID;
        }
        return $result;
    }

    /**
     * Returns the Unset Notifications for the given Credential
     * @param int  $credentialID
     * @param int  $currentUser
     * @param Date $time
     * @return list<NotificationQueueEntity>
     */
    public static function getUnsentForCredential(
        int $credentialID,
        int $currentUser,
        Date $time,
    ): array {
        $time  = $time->subtract(hours: 1);

        $query = new NotificationQueueQuery();
        $query->credentialID->equal($credentialID);
        $query->currentUser->equal($currentUser);
        $query->sentTime->isEmpty();
        $query->createdTime->greaterThan($time);
        $query->createdTime->orderByDesc();
        return self::getEntityList($query);
    }

    /**
     * Returns all the Notifications for the given Credential
     * @param int                      $credentialID
     * @param int                      $currentUser
     * @param NotificationQueueRequest $sort
     * @return list<NotificationQueueEntity>
     */
    public static function getAllForCredential(
        int $credentialID,
        int $currentUser,
        NotificationQueueRequest $sort,
    ): array {
        $time  = Date::now()->subtract(days: 30);

        $query = new NotificationQueueQuery();
        $query->credentialID->equal($credentialID);
        $query->currentUser->equal($currentUser);
        $query->isDiscarded->isFalse();
        $query->createdTime->greaterThan($time);
        $query->createdTime->orderByDesc();
        return self::getEntityList($query, $sort);
    }

    /**
     * Returns the total amount of unread Notifications
     * @param int $credentialID
     * @param int $currentUser
     * @return int
     */
    public static function getUnreadAmount(int $credentialID, int $currentUser): int {
        $time  = Date::now()->subtract(days: 30);

        $query = new NotificationQueueQuery();
        $query->credentialID->equal($credentialID);
        $query->currentUser->equal($currentUser);
        $query->isRead->isFalse();
        $query->isDiscarded->isFalse();
        $query->createdTime->greaterThan($time);
        return self::getEntityTotal($query);
    }



    /**
     * Adds a new Notification
     * @param int    $credentialID
     * @param int    $currentUser
     * @param string $title
     * @param string $message
     * @param string $url
     * @param string $dataType
     * @param int    $dataID
     * @return int
     */
    public static function add(
        int $credentialID,
        int $currentUser,
        string $title,
        string $message,
        string $url,
        string $dataType,
        int $dataID,
    ): int {
        $notificationQueueID = self::createEntity(
            credentialID:       $credentialID,
            currentUser:        $currentUser,
            title:              $title,
            message:            $message,
            url:                $url,
            dataType:           $dataType,
            dataID:             $dataID,
            notificationResult: NotificationResult::NotProcessed,
        );

        Event::notificationQueued($notificationQueueID);
        return $notificationQueueID;
    }

    /**
     * Deletes the given Notification
     * @param int $notificationQueueID
     * @return bool
     */
    public static function delete(int $notificationQueueID): bool {
        $query = new NotificationQueueQuery();
        $query->notificationQueueID->equal($notificationQueueID);
        return self::removeEntity($query);
    }

    /**
     * Deletes the items older than some days
     * @return bool
     */
    public static function deleteOld(): bool {
        $days  = Config::getNotificationDeleteDays();
        $time  = Date::now()->subtract(days: $days);

        $query = new NotificationQueueQuery();
        $query->createdTime->lessThan($time);
        return self::removeEntity($query);
    }



    /**
     * Marks the given Notification as read for the given Credential
     * @param int $notificationQueueID
     * @return bool
     */
    public static function markAsRead(int $notificationQueueID): bool {
        $query = new NotificationQueueQuery();
        $query->notificationQueueID->equal($notificationQueueID);
        return self::editEntity($query, isRead: true);
    }

    /**
     * Discards the given Notification for the given Credential
     * @param int $notificationQueueID
     * @return bool
     */
    public static function discard(int $notificationQueueID): bool {
        $query = new NotificationQueueQuery();
        $query->notificationQueueID->equal($notificationQueueID);
        return self::editEntity($query, isDiscarded: true);
    }



    /**
     * Sends all the Unsent Notifications
     * @return void
     */
    public static function sendAll(): void {
        foreach (self::getAllUnsent() as $elem) {
            self::send($elem);
        }
    }

    /**
     * Sends the Queued Notification with the given ID, if it was not sent yet
     * @param int $notificationQueueID
     * @return bool
     */
    public static function sendOne(int $notificationQueueID): bool {
        $elem = self::getByID($notificationQueueID);
        if ($elem->isEmpty() || $elem->notificationResult !== NotificationResult::NotProcessed) {
            return false;
        }

        // A Notification queued in another Environment, as its database was copied, is never sent
        if (!$elem->isCurrentEnv()) {
            return false;
        }

        self::send($elem);
        return true;
    }

    /**
     * Sends the given Notification
     * @param NotificationQueueEntity $elem
     * @return void
     */
    private static function send(NotificationQueueEntity $elem): void {
        $playerIDs = Device::getAllForCredential($elem->credentialID);
        $badge     = self::getUnreadAmount($elem->credentialID, $elem->currentUser);

        $output = Notification::sendToSome(
            $elem->title,
            $elem->message,
            $elem->url,
            $elem->dataType,
            $elem->dataID,
            $playerIDs,
            $badge,
        );

        self::editEntity(
            $elem->notificationQueueID,
            notificationResult: $output->result,
            externalID:         $output->externalID,
            providerError:      $output->error,
            playerIDs:          $playerIDs,
            sentTime:           Date::now(),
        );
    }
}
