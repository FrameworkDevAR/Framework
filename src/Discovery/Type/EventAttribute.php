<?php
namespace Framework\Discovery\Type;

use Framework\Discovery\Type\FrameworkEvent;

/**
 * The Event Attribute
 */
interface EventAttribute {

    /**
     * Returns the Event the Attribute handles
     * @return FrameworkEvent
     */
    public function getEvent(): FrameworkEvent;
}
