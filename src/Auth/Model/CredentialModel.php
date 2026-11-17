<?php
namespace Framework\Auth\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Virtual;
use Framework\Database\Model\Requested;
use Framework\Database\Status\Status;
use Framework\System\Access;
use Framework\Date\Date;
use Framework\File\File;

/**
 * The Credential Model
 */
#[Model(
    description:   "The people who can sign in, with their access level and password hash.",
    hasTimestamps: true,
    canCreate:     true,
    canEdit:       true,
    canDelete:     true,
    skipList:      true,
)]
class CredentialModel {

    #[Field(isID: true)]
    public int $credentialID = 0;

    #[Field]
    public int $currentUser = 0;

    #[Field, Requested(canEdit: false)]
    public string $name = "";


    #[Field, Requested]
    public string $firstName = "";

    #[Field, Requested]
    public string $lastName = "";

    #[Field, Requested]
    public string $email = "";

    #[Field, Requested]
    public string $phone = "";

    #[Field, Requested]
    public string $language = "";

    #[Field(isText: true), Requested]
    public string $observations = "";

    #[Field, Requested]
    public bool $sendEmails = false;

    #[Field, Requested]
    public bool $sendEmailNotis = false;

    #[Field, Requested]
    public bool $sendTickets = false;

    #[Field(isSigned: true), Requested(canEdit: false)]
    public int $timezone = 0;


    #[Field]
    public string $avatar = "";

    #[Field, Requested(canEdit: false)]
    public string $appearance = "";

    #[Field, Requested(canEdit: false)]
    public Access $access = Access::None;

    #[Field, Requested(canEdit: false)]
    public string $password = "";

    #[Field, Requested(canEdit: false)]
    public string $salt = "";

    #[Field, Requested(canEdit: false)]
    public bool $reqPassChange = false;

    #[Field]
    public ?Date $passExpiration = null;

    #[Field, Requested(canEdit: false)]
    public string $accessToken = "";

    #[Field]
    public ?Date $tokenExpiration = null;

    #[Field]
    public ?Date $currentLogin = null;

    #[Field]
    public ?Date $lastLogin = null;

    #[Field]
    public bool $askNotifications = true;

    #[Field(isText: true)]
    public string $askedDevices = "";

    #[Field]
    public int $progressValue = 0;

    #[Field, Requested(canEdit: false)]
    public Status $status = Status::None;



    #[Virtual]
    public int $adminID = 0;

    #[Virtual]
    public Access $userAccess = Access::None;

    #[Virtual]
    public string $avatarFile = "";

    #[Virtual]
    public string $accessName = "";



    #[Requested]
    public string $value = "";

    #[Requested]
    public string $code = "";

    #[Requested]
    public string $resetCode = "";

    #[Requested]
    public string $oldPassword = "";

    #[Requested]
    public string $newPassword = "";

    #[Requested]
    public string $confirmPassword = "";

    #[Requested]
    public ?File $file = null;
}
