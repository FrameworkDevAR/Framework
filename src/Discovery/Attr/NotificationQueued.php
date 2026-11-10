<?php
namespace Framework\Discovery\Attr;

use Framework\Discovery\Type\EventAttribute;
use Framework\Discovery\Type\FrameworkEvent;

use Attribute;

/**
 * The Notification Queued Attribute
 */
#[Attribute(Attribute::TARGET_METHOD)]
class NotificationQueued implements EventAttribute {

    /**
     * Returns the Event the Attribute handles
     * @return FrameworkEvent
     */
    #[\Override]
    public function getEvent(): FrameworkEvent {
        return FrameworkEvent::NotificationQueued;
    }
}
