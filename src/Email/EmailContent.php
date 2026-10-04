<?php
namespace Framework\Email;

use Framework\Discovery\Type\DiscoveryMigration;
use Framework\Email\EmailMessage;
use Framework\Email\Schema\EmailContentSchema;
use Framework\Email\Schema\EmailContentEntity;
use Framework\Email\Schema\EmailContentQuery;
use Framework\Provider\Mustache;
use Framework\System\Language;
use Framework\System\EmailCode;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Email Contents
 */
class EmailContent extends EmailContentSchema implements DiscoveryMigration {

    /**
     * Returns an Email Content for the Email Sender
     * @param EmailCode $emailCode
     * @param string    $language  Optional.
     * @return EmailContentEntity
     */
    public static function get(
        EmailCode $emailCode,
        string $language = "root",
    ): EmailContentEntity {
        $langCode = Language::getCode($language);

        // The condition of an Enum drops the case that has no value, so None
        // given as the Enum would ask for every code rather than for none of
        // them. Given as what it is worth, it asks for the empty code, which
        // is no email at all
        $query = new EmailContentQuery();
        $query->emailCode->equalName($emailCode->toString());
        $query->language->equal($langCode);
        return self::getEntity($query);
    }

    /**
     * Renders the Email Content message with Mustache
     * @param string              $message
     * @param array<string,mixed> $data    Optional.
     * @return string
     */
    public static function render(string $message, array $data = []): string {
        $html = $message;
        if (!Strings::contains($message, "</p>\n\n<p>")) {
            $html = Strings::toHtml($message);
        }

        $result = Mustache::render($html, $data);
        $result = Strings::replace($result, "<p></p>", "");
        while (Strings::contains($result, "<br><br><br>")) {
            $result = Strings::replace($result, "<br><br><br>", "<br><br>");
        }
        return $result;
    }



    /**
     * Migrates the Email Contents data
     * @return void
     */
    #[\Override]
    public static function migrateData(): void {
        self::migrateContents(EmailCode::getMessages());
    }

    /**
     * Migrates the Email Contents from the given Email Messages
     * @param array<string,class-string<EmailMessage>> $messages
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

            // An Email Message with a version only replaces a Content with a lower one,
            // so one that was edited after the class was written is kept. Without a
            // version it can not be edited, and it is written again
            foreach ($messages as $code => $emailClass) {
                $key       = self::getKey($code, $language);
                $keys[]    = $key;
                $content   = $contents[$key] ?? null;
                $version   = $emailClass::$version;
                $position += 1;

                if ($content !== null && $version > 0 && $content->version >= $version) {
                    self::editEntity($content->id, position: $position, skipOrder: true);
                    continue;
                }

                $total += 1;
                self::saveContent(
                    content:      $content,
                    emailCode:    EmailCode::fromValue($code),
                    language:     $language,
                    languageName: $languageName,
                    version:      $version,
                    description:  $emailClass::$description,
                    subject:      $emailClass::getSubjectText($language),
                    message:      $emailClass::getBodyText($language),
                    position:     $position,
                );
            }

            if ($total > 0) {
                print("- Updated $total emails for language $languageName\n");
                $didUpdate = true;
            }
        }

        // The Contents of an Email whose class is gone are removed
        foreach ($contents as $key => $content) {
            if (!Arrays::contains($keys, $key)) {
                self::removeEntity($content->id);
            }
        }

        if (!$didUpdate) {
            print("- No emails updated\n");
        }
    }

    /**
     * Returns all the Email Contents by their code and language
     * @return array<string,EmailContentEntity>
     */
    private static function getAllContents(): array {
        $result = [];
        foreach (self::getEntityList() as $content) {
            $key          = self::getKey($content->emailCode->toString(), $content->language);
            $result[$key] = $content;
        }
        return $result;
    }

    /**
     * Returns the key of the Content of an Email in a Language
     * @param string $emailCode
     * @param string $language
     * @return string
     */
    private static function getKey(string $emailCode, string $language): string {
        return "$emailCode-$language";
    }

    /**
     * Creates the Email Content, or edits the given one
     * @param EmailContentEntity|null $content
     * @param EmailCode               $emailCode
     * @param string                  $language
     * @param string                  $languageName
     * @param int                     $version
     * @param string                  $description
     * @param string                  $subject
     * @param string                  $message
     * @param int                     $position
     * @return void
     */
    private static function saveContent(
        ?EmailContentEntity $content,
        EmailCode $emailCode,
        string $language,
        string $languageName,
        int $version,
        string $description,
        string $subject,
        string $message,
        int $position,
    ): void {
        if ($content === null) {
            self::createEntity(
                emailCode:    $emailCode,
                language:     $language,
                languageName: $languageName,
                version:      $version,
                description:  $description,
                subject:      $subject,
                message:      $message,
                position:     $position,
                skipOrder:    true,
            );
            return;
        }

        self::editEntity(
            $content->id,
            languageName: $languageName,
            version:      $version,
            description:  $description,
            subject:      $subject,
            message:      $message,
            position:     $position,
            skipOrder:    true,
        );
    }
}
