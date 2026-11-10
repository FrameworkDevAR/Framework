<?php
namespace Framework\Email;

use Framework\Application;
use Framework\IO\Request;
use Framework\Discovery\Discovery;
use Framework\Email\EmailWhiteList;
use Framework\Email\EmailResult;
use Framework\Email\EmailSender;
use Framework\Email\Schema\EmailContentEntity;
use Framework\Provider\Mustache;
use Framework\System\Config;
use Framework\System\EmailProvider;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Utils;

/**
 * The Email Provider
 */
class Email {

    /** @var class-string<EmailSender>|null */
    private static ?string $sender = null;


    /**
     * Sets the Sender to use, rather than the one of the config
     * @param class-string<EmailSender>|null $sender Optional.
     * @return void
     */
    public static function setSender(?string $sender = null): void {
        self::$sender = $sender;
    }

    /**
     * Sends an Email
     * @param string $toEmail
     * @param string $subject
     * @param string $message
     * @param bool   $sendAlways      Optional.
     * @param bool   $sendTest        Optional.
     * @param bool   $withoutTemplate Optional.
     * @param string $replyTo         Optional.
     * @return EmailResult
     */
    public static function send(
        string $toEmail,
        string $subject,
        string $message,
        bool $sendAlways = false,
        bool $sendTest = false,
        bool $withoutTemplate = false,
        string $replyTo = "",
    ): EmailResult {
        // Return some possible errors
        if (!$sendTest && !Config::isEmailActive()) {
            return EmailResult::InactiveSend;
        }
        if (!$sendTest && !$sendAlways && Config::isEmailUseWhiteList() &&
            !EmailWhiteList::emailExists($toEmail)
        ) {
            return EmailResult::WhiteListFilter;
        }
        if (!Utils::isValidEmail($toEmail)) {
            return EmailResult::InvalidEmail;
        }

        // Nothing is set up to send through, which is a config that was never
        // finished rather than a send that went wrong
        $provider = EmailProvider::fromValue(Config::getEmailProvider());
        $sender   = self::$sender ?? $provider->getSender();
        if ($sender === null) {
            return EmailResult::NoProvider;
        }

        // Create the template
        if ($withoutTemplate) {
            $body = $message;
        } else {
            $tempFile = Config::getEmailTemplate();
            $template = Discovery::loadEmailTemplate($tempFile);
            $body     = Mustache::render($template, [
                "url"        => Config::getEmailUrl(),
                "name"       => Config::getName(),
                "files"      => Application::getUrl(),
                "logo"       => Config::getEmailLogo(),
                "logoHeight" => Config::getEmailLogoHeight(),
                "siteName"   => Config::getName(),
                "message"    => $message,
            ]);
        }

        // Configure the variables
        $fromName  = Config::getName();
        $fromEmail = Config::getEmailEmail();
        $replyTo   = $replyTo !== "" ? $replyTo : Config::getEmailReplyTo();


        // Hand it to whatever the Provider sends through
        $wasSent = $sender::sendEmail(
            $toEmail,
            $fromEmail,
            $fromName,
            $replyTo,
            $subject,
            $body,
        );

        if (!$wasSent) {
            return EmailResult::ProviderError;
        }
        return EmailResult::Sent;
    }

    /**
     * Sends the given Email Content
     * @param EmailContentEntity  $content
     * @param list<string>|string $sendTo
     * @param string|null         $message    Optional.
     * @param string|null         $subject    Optional.
     * @param bool                $sendAlways Optional.
     * @return EmailResult
     */
    public static function sendContent(
        EmailContentEntity $content,
        array|string $sendTo,
        ?string $message = null,
        ?string $subject = null,
        bool $sendAlways = false,
    ): EmailResult {
        $sendTos   = Arrays::toStrings($sendTo);
        $subject ??= $content->subject;
        $message ??= $content->message;
        $result    = EmailResult::NoEmails;

        foreach ($sendTos as $toEmail) {
            $result = self::send($toEmail, $subject, $message, $sendAlways);
        }
        return $result;
    }



    /**
     * Checks if the Recaptcha is Valid
     * @param Request $request
     * @param bool    $withScore Optional.
     * @return bool
     */
    public static function isCaptchaValid(
        Request $request,
        bool $withScore = false,
    ): bool {
        $recaptchaSecret = Config::getEmailRecaptchaSecret();
        if (!$request->has("g-recaptcha-response") || $recaptchaSecret === "") {
            return false;
        }
        $secretKey = urlencode($recaptchaSecret);
        $captcha   = urlencode($request->getString("g-recaptcha-response"));
        $url       = "https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$captcha";  // phpcs:ignore
        $response  = JSON::readUrl($url);

        // An answer that says the captcha was not passed carries the key just
        // the same, so it is the value of it that decides
        if (($response["success"] ?? false) !== true) {
            return false;
        }
        if ($withScore && isset($response["score"]) && $response["score"] <= 0.5) {
            return false;
        }
        return true;
    }
}
