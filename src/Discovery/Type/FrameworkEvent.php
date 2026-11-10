<?php
namespace Framework\Discovery\Type;

use Framework\Enum\Enum;
use Framework\Enum\IsEnum;
use Framework\Utils\Strings;

use JsonSerializable;

/**
 * The Framework Events
 */
enum FrameworkEvent implements Enum, JsonSerializable {
    use IsEnum;

    case None;

    case EmailQueued;
    case NotificationQueued;



    /**
     * Returns the name of the method that triggers the Event
     * @return string
     */
    public function getMethod(): string {
        return Strings::lowerCaseFirst($this->toString());
    }

    /**
     * Returns the Params of the Event, with their names and types
     * @return array<string,string>
     */
    public function getParams(): array {
        return match ($this) {
            self::None               => [],
            self::EmailQueued        => [ "emailQueueID" => "int" ],
            self::NotificationQueued => [ "notificationQueueID" => "int" ],
        };
    }
}
