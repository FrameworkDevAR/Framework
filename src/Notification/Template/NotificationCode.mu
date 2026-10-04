<?php
namespace {{namespace}};

use Framework\Notification\NotificationMessage;

/**
 * The Notification Codes
 */
enum NotificationCode {

    case None;
{{#codes}}
    case {{.}};
{{/codes}}



    /**
     * Returns the Notification Messages by their Code
     * @return array<string,class-string<NotificationMessage>>
     */
    public static function getMessages(): array {
{{#hasMessages}}
        return [
{{#messages}}
            {{{key}}} => \{{class}}::class,
{{/messages}}
        ];
{{/hasMessages}}
{{^hasMessages}}
        return [];
{{/hasMessages}}
    }

    /**
     * Returns the Notification Message of the Code, or null if it has none
     * @return class-string<NotificationMessage>|null
     */
    public function getMessage(): ?string {
        return self::getMessages()[$this->name] ?? null;
    }
}
