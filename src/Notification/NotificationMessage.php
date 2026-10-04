<?php
namespace Framework\Notification;

use Framework\Analysis\Attr\MustOverride;
use Framework\Notification\NotificationContent;
use Framework\Notification\NotificationQueue;
use Framework\Provider\Mustache;
use Framework\System\Language;
use Framework\Utils\Strings;

use ReflectionClass;

/**
 * A Notification with its texts by language in its properties, sent with its own static send()
 */
abstract class NotificationMessage {

    public static int $version = 0;

    #[MustOverride]
    public static string $description = "";

    /** @var array<string,string> */
    #[MustOverride]
    public static array $title = [];

    /** @var array<string,string> */
    #[MustOverride]
    public static array $message = [];

    /** @var array<string,string> */
    public static array $variables = [];


    /**
     * Returns the Code of the Notification, the name of its class without the suffix
     * @return string
     */
    public static function getCode(): string {
        $name = (new ReflectionClass(static::class))->getShortName();
        return Strings::stripEnd($name, "Notification");
    }

    /**
     * Returns the Title of the Notification in the given Language, without rendering it
     * @param string $language
     * @return string
     */
    public static function getTitleText(string $language): string {
        return self::getText(static::$title, $language);
    }

    /**
     * Returns the Message of the Notification in the given Language, without rendering it
     * @param string $language
     * @return string
     */
    public static function getMessageText(string $language): string {
        return self::getText(static::$message, $language);
    }

    /**
     * Returns the Title of the Notification in the given Language, rendered with the given Data
     * @param string              $language
     * @param array<string,mixed> $data
     * @return string
     */
    public static function getTitle(string $language, array $data): string {
        return Mustache::render(static::getTitleText($language), $data);
    }

    /**
     * Returns the Message of the Notification in the given Language, rendered with the given Data
     * @param string              $language
     * @param array<string,mixed> $data
     * @return string
     */
    public static function getMessage(string $language, array $data): string {
        return Mustache::render(static::getMessageText($language), $data);
    }

    /**
     * Adds the Notification to the Queue, rendered in the given Language with the given Data
     * @param int                 $credentialID
     * @param int                 $currentUser
     * @param string              $language
     * @param array<string,mixed> $data
     * @param string              $url          Optional.
     * @param string              $dataType     Optional.
     * @param int                 $dataID       Optional.
     * @return int
     */
    protected static function queue(
        int $credentialID,
        int $currentUser,
        string $language,
        array $data,
        string $url = "",
        string $dataType = "",
        int $dataID = 0,
    ): int {
        $title   = static::getTitleText($language);
        $message = static::getMessageText($language);

        // A Notification with a version can be edited, so the texts of its Content are the
        // ones sent, as it might hold a later version than the class
        if (static::$version > 0) {
            $content = NotificationContent::get(static::getCode(), $language);
            if ($content->exists()) {
                $title   = $content->title;
                $message = $content->message;
            }
        }

        return NotificationQueue::add(
            credentialID: $credentialID,
            currentUser:  $currentUser,
            title:        Mustache::render($title, $data),
            message:      Mustache::render($message, $data),
            url:          $url,
            dataType:     $dataType,
            dataID:       $dataID,
        );
    }

    /**
     * Returns the text of the given Language, or of the root one, with each line trimmed
     * @param array<string,string> $texts
     * @param string               $language
     * @return string
     */
    private static function getText(array $texts, string $language): string {
        $text = $texts[$language] ?? $texts[Language::getRootCode()] ?? "";

        // The texts might be written indented inside the class
        $lines = [];
        foreach (Strings::split($text, "\n") as $line) {
            $lines[] = Strings::trim($line);
        }
        return Strings::trim(Strings::join($lines, "\n"));
    }
}
