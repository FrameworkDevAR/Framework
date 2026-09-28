<?php
namespace Framework\Notification;

use Framework\Notification\NotificationResult;

/**
 * The Notification Output
 */
class NotificationOutput {

    /**
     * Creates a new NotificationOutput instance
     * @param NotificationResult $result     Optional.
     * @param string             $externalID Optional.
     * @param string             $error      Optional.
     */
    public function __construct(
        public NotificationResult $result = NotificationResult::None,
        public string $externalID = "",
        public string $error = "",
    ) {
    }

    /**
     * Creates the Output of a push that the Provider took
     * @param string $externalID
     * @param string $error      Optional.
     * @return NotificationOutput
     */
    public static function sent(string $externalID, string $error = ""): self {
        // A Provider that took the push can still give the errors of some of its devices
        return new self(NotificationResult::Sent, $externalID, $error);
    }

    /**
     * Creates the Output of a push that the Provider refused
     * @param string $error Optional.
     * @return NotificationOutput
     */
    public static function failed(string $error = ""): self {
        if ($error === "") {
            $error = "No response";
        }
        return new self(NotificationResult::ProviderError, "", $error);
    }

    /**
     * Creates the Output of what a Provider answered
     * @param string $externalID
     * @param string $error      Optional.
     * @return NotificationOutput
     */
    public static function fromProvider(string $externalID, string $error = ""): self {
        // A Provider that would not take the push gives no ID for it, and one that
        // took it can still give the errors of some of its devices
        $result = $externalID !== "" ? NotificationResult::Sent : NotificationResult::ProviderError;
        return new self($result, $externalID, $error);
    }
}
