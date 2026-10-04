<?php
namespace Framework\Notification\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;

/**
 * The Notification Content Model
 */
#[Model(
    description: "The title and message of every push, one row per code and language.",
    canCreate:   true,
    canEdit:     true,
)]
class NotificationContentModel {

    #[Field(isID: true), Requested]
    public int $notificationContentID = 0;

    #[Field]
    public string $notificationCode = "";

    #[Field]
    public string $language = "";

    #[Field]
    public string $languageName = "";

    #[Field]
    public int $version = 0;

    #[Field]
    public string $description = "";

    #[Field, Requested]
    public string $title = "";

    #[Field(isText: true), Requested]
    public string $message = "";

    #[Field(isPosition: true)]
    public int $position = 0;
}
