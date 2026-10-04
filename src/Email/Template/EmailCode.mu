<?php
namespace {{namespace}};

use Framework\Email\EmailMessage;
use Framework\Enum\Enum;
use Framework\Enum\IsEnum;

use JsonSerializable;

/**
 * The Email Codes
 */
enum EmailCode implements Enum, JsonSerializable {
    use IsEnum;

    case None;

{{#codes}}
    case {{.}};
{{/codes}}



    /**
     * Returns the Email Messages by their Code
     * @return array<string,class-string<EmailMessage>>
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
     * Returns the Email Message of the Code, or null if it has none
     * @return class-string<EmailMessage>|null
     */
    public function getMessage(): ?string {
        return self::getMessages()[$this->name] ?? null;
    }
}
