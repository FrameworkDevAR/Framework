<?php
namespace Framework\Email;

use Framework\Analysis\Attr\MustOverride;
use Framework\Application;
use Framework\Discovery\Package;
use Framework\Email\EmailContent;
use Framework\Email\EmailQueue;
use Framework\File\Storage;
use Framework\Provider\Mustache;
use Framework\System\Config;
use Framework\System\EmailCode;
use Framework\System\Language;
use Framework\System\Template;
use Framework\Utils\Strings;

use ReflectionClass;

/**
 * An Email with its texts by language in its properties, sent with its own static send()
 */
abstract class EmailMessage {

    public static int $version = 0;

    #[MustOverride]
    public static string $description = "";

    public static ?Template $template = null;

    public static bool $sendNow = false;

    /** @var array<string,string> */
    #[MustOverride]
    public static array $subject = [];

    /** @var array<string,string> */
    public static array $body = [];

    /** @var array<string,string> */
    public static array $variables = [];

    private const PartialsDir = "data/email";

    /** @var array<string,string>|null */
    private static ?array $partials = null;


    /**
     * Returns the Code of the Email, the name of its class without the suffix
     * @return EmailCode
     */
    public static function getCode(): EmailCode {
        $name = (new ReflectionClass(static::class))->getShortName();
        return EmailCode::fromValue(Strings::stripEnd($name, "Email"));
    }

    /**
     * Returns the Subject of the Email in the given Language, without rendering it
     * @param string $language
     * @return string
     */
    public static function getSubjectText(string $language): string {
        return self::getText(static::$subject, $language);
    }

    /**
     * Returns the Body of the Email in the given Language, without rendering it
     * @param string $language
     * @return string
     */
    public static function getBodyText(string $language): string {
        return self::getText(static::$body, $language);
    }

    /**
     * Returns the Subject of the Email in the given Language, rendered with the given Data
     * @param string              $language
     * @param array<string,mixed> $data
     * @return string
     */
    public static function getSubject(string $language, array $data): string {
        return self::renderSubject(static::getSubjectText($language), $data);
    }

    /**
     * Returns the Body of the Email in the given Language, rendered with the given Data
     * @param string              $language
     * @param array<string,mixed> $data
     * @return string
     */
    public static function getBody(string $language, array $data): string {
        return self::renderBody(
            static::getSubjectText($language),
            static::getBodyText($language),
            $data,
        );
    }

    /**
     * Adds the Email to the Queue, rendered in the given Language with the given Data
     * @param string              $sendTo
     * @param string              $language
     * @param array<string,mixed> $data
     * @param int                 $dataID   Optional.
     * @return bool
     */
    protected static function queue(
        string $sendTo,
        string $language,
        array $data,
        int $dataID = 0,
    ): bool {
        $subject = static::getSubjectText($language);
        $body    = static::getBodyText($language);

        // An Email with a version can be edited, so the texts of its Content are the ones
        // sent, as it might hold a later version than the class
        if (static::$version > 0) {
            $content = EmailContent::get(static::getCode(), $language);
            if ($content->exists()) {
                $subject = $content->subject;
                $body    = $content->message;
            }
        }

        return EmailQueue::addEmail(
            emailCode: static::getCode(),
            sendTo:    $sendTo,
            subject:   self::renderSubject($subject, $data),
            message:   self::renderBody($subject, $body, $data),
            sendNow:   static::$sendNow,
            dataID:    $dataID,
        );
    }

    /**
     * Renders the given Subject with the given Data
     * @param string              $subject
     * @param array<string,mixed> $data
     * @return string
     */
    private static function renderSubject(string $subject, array $data): string {
        return Mustache::render($subject, self::addSite($data));
    }

    /**
     * Renders the given Body with the given Data, inside the Template when there is one
     * @param string              $subject
     * @param string              $body
     * @param array<string,mixed> $data
     * @return string
     */
    private static function renderBody(string $subject, string $body, array $data): string {
        $data = self::addSite($data);

        // Without a Template the body is made of paragraphs, like the Email Contents
        if (static::$template === null) {
            return EmailContent::render($body, $data);
        }

        $data["subject"] = self::renderSubject($subject, $data);
        $data["body"]    = Mustache::render($body, $data);
        return static::$template->render($data, self::getPartials());
    }

    /**
     * Returns the text of the given Language, or of the root one, with each line trimmed
     * @param array<string,string> $texts
     * @param string               $language
     * @return string
     */
    private static function getText(array $texts, string $language): string {
        $text = $texts[$language] ?? $texts[Language::getRootCode()] ?? "";

        // The texts are written indented inside the class
        $lines = [];
        foreach (Strings::split($text, "\n") as $line) {
            $lines[] = Strings::trim($line);
        }
        return Strings::trim(Strings::join($lines, "\n"));
    }

    /**
     * Returns the given Data with the name of the site
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function addSite(array $data): array {
        return $data + [
            "site" => Config::getName(),
        ];
    }

    /**
     * Returns the partials of the Framework and the App, by their name
     * @return array<string,string>
     */
    public static function getPartials(): array {
        if (self::$partials !== null) {
            return self::$partials;
        }

        // The ones of the App go last, so they replace the ones of the Framework
        $result = [];
        $paths  = [
            Package::getBasePath(self::PartialsDir),
            Application::getBasePath(self::PartialsDir),
        ];
        foreach ($paths as $path) {
            foreach (Storage::getFilesInDir($path) as $fileName) {
                if (Strings::endsWith($fileName, ".html")) {
                    $name = Storage::getFileName($fileName);
                    $result[$name] = Storage::readFile($path, $fileName);
                }
            }
        }

        self::$partials = $result;
        return $result;
    }
}
