<?php
namespace Framework\Auth\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\System\NotificationProvider;

/**
 * The Credential Device Model
 */
#[Model(
    description:   "The devices a credential signs in from, used to reach them with a push.",
    hasTimestamps: true,
    canEdit:       true,
)]
class CredentialDeviceModel {

    #[Field(isPrimary: true), Requested]
    public int $credentialID = 0;

    #[Field(isPrimary: true)]
    public string $userAgent = "";

    #[Field(isPrimary: true), Requested]
    public string $playerID = "";

    #[Field, Requested]
    public NotificationProvider $provider = NotificationProvider::None;
}
