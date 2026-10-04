<?php
namespace Framework\Notification;

use Framework\Discovery\Type\DiscoveryMigration;
use Framework\Notification\NotificationMessage;
use Framework\Notification\Schema\NotificationContentSchema;
use Framework\Notification\Schema\NotificationContentEntity;
use Framework\Notification\Schema\NotificationContentQuery;
use Framework\Provider\Mustache;
use Framework\System\Language;
use Framework\System\NotificationCode;
use Framework\Utils\Arrays;

/**
 * The Notification Contents
 */
class NotificationContent extends NotificationContentSchema implements DiscoveryMigration {

    /**
     * Returns a Notification Content for the Notification Sender
     * @param NotificationCode|string $notificationCode
     * @param string                  $language         Optional.
     * @return NotificationContentEntity
     */
    public static function get(
        NotificationCode|string $notificationCode,
        string $language = "root",
    ): NotificationContentEntity {
        $langCode = Language::getCode($language);
        if ($notificationCode instanceof NotificationCode) {
            $notificationCode = $notificationCode->name;
        }

        $query = new NotificationContentQuery();
        $query->notificationCode->equal($notificationCode);
        $query->language->equal($langCode);
        return self::getEntity($query);
    }

    /**
     * Edits the texts of the given Notification Content and gives it a new version, when it has one
     * @param int    $notificationContentID
     * @param string $title
     * @param string $message
     * @return bool
     */
    public static function edit(int $notificationContentID, string $title, string $message): bool {
        $content = self::getByID($notificationContentID);
        if ($content->isEmpty() || $content->version === 0) {
            return false;
        }

        // The version is only raised by an edit that changes something
        if ($content->title === $title && $content->message === $message) {
            return true;
        }
        return self::editEntity(
            $notificationContentID,
            version: $content->version + 1,
            title:   $title,
            message: $message,
        );
    }

    /**
     * Renders the Notification Content message with Mustache
     * @param string              $message
     * @param array<string,mixed> $data    Optional.
     * @return string
     */
    public static function render(string $message, array $data = []): string {
        return Mustache::render($message, $data);
    }



    /**
     * Migrates the Notification Contents data
     * @return void
     */
    #[\Override]
    public static function migrateData(): void {
        self::migrateContents(NotificationCode::getMessages());
    }

    /**
     * Migrates the Notification Contents from the given Notification Messages
     * @param array<string,class-string<NotificationMessage>> $messages
     * @return void
     */
    public static function migrateContents(array $messages): void {
        $contents  = self::getAllContents();
        $languages = Language::getAll();
        $position  = 0;
        $didUpdate = false;
        $keys      = [];

        foreach ($languages as $language => $languageName) {
            $total = 0;

            // A Notification Message with a version only replaces a Content with a lower
            // one, so one that was edited after the class was written is kept. Without a
            // version it can not be edited, and it is written again
            foreach ($messages as $code => $messageClass) {
                $key       = self::getKey($code, $language);
                $keys[]    = $key;
                $content   = $contents[$key] ?? null;
                $version   = $messageClass::$version;
                $position += 1;

                if ($content !== null && $version > 0 && $content->version >= $version) {
                    self::editEntity($content->id, position: $position, skipOrder: true);
                    continue;
                }

                $total += 1;
                self::saveContent(
                    content:          $content,
                    notificationCode: $code,
                    language:         $language,
                    languageName:     $languageName,
                    version:          $version,
                    description:      $messageClass::$description,
                    title:            $messageClass::getTitleText($language),
                    message:          $messageClass::getMessageText($language),
                    position:         $position,
                );
            }

            if ($total > 0) {
                print("- Updated $total notifications for language $languageName\n");
                $didUpdate = true;
            }
        }

        // The Contents of a Notification whose class is gone are removed
        foreach ($contents as $key => $content) {
            if (!Arrays::contains($keys, $key)) {
                self::removeEntity($content->id);
            }
        }

        if (!$didUpdate) {
            print("- No notifications updated\n");
        }
    }

    /**
     * Returns all the Notification Contents by their code and language
     * @return array<string,NotificationContentEntity>
     */
    private static function getAllContents(): array {
        $result = [];
        foreach (self::getEntityList() as $content) {
            $key          = self::getKey($content->notificationCode, $content->language);
            $result[$key] = $content;
        }
        return $result;
    }

    /**
     * Returns the key of the Content of a Notification in a Language
     * @param string $notificationCode
     * @param string $language
     * @return string
     */
    private static function getKey(string $notificationCode, string $language): string {
        return "$notificationCode-$language";
    }

    /**
     * Creates the Notification Content, or edits the given one
     * @param NotificationContentEntity|null $content
     * @param string                         $notificationCode
     * @param string                         $language
     * @param string                         $languageName
     * @param int                            $version
     * @param string                         $description
     * @param string                         $title
     * @param string                         $message
     * @param int                            $position
     * @return void
     */
    private static function saveContent(
        ?NotificationContentEntity $content,
        string $notificationCode,
        string $language,
        string $languageName,
        int $version,
        string $description,
        string $title,
        string $message,
        int $position,
    ): void {
        if ($content === null) {
            self::createEntity(
                notificationCode: $notificationCode,
                language:         $language,
                languageName:     $languageName,
                version:          $version,
                description:      $description,
                title:            $title,
                message:          $message,
                position:         $position,
                skipOrder:        true,
            );
            return;
        }

        self::editEntity(
            $content->id,
            languageName: $languageName,
            version:      $version,
            description:  $description,
            title:        $title,
            message:      $message,
            position:     $position,
            skipOrder:    true,
        );
    }
}
