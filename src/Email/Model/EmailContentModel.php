<?php
namespace Framework\Email\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\System\EmailCode;

/**
 * The Email Content Model
 */
#[Model(
    description: "The subject and body of every email, one row per code and language.",
    canCreate:   true,
    canEdit:     true,
)]
class EmailContentModel {

    #[Field(isID: true), Requested]
    public int $emailContentID = 0;

    #[Field]
    public EmailCode $emailCode = EmailCode::None;

    #[Field]
    public string $language = "";

    #[Field]
    public string $languageName = "";

    #[Field]
    public int $version = 0;

    #[Field]
    public string $description = "";

    #[Field, Requested]
    public string $subject = "";

    #[Field(isText: true), Requested]
    public string $message = "";

    #[Field(isPosition: true)]
    public int $position = 0;
}
