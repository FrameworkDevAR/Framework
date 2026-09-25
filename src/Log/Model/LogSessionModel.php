<?php
namespace Framework\Log\Model;

use Framework\Auth\Model\CredentialModel;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Index;
use Framework\Database\Model\Relation;

/**
 * The Log Session Model
 */
#[Model(
    description:   "One row per sign-in, with the address, the device and whether it is open.",
    hasTimestamps: true,
    canCreate:     true,
    canEdit:       true,
)]
#[Index([ "credentialID", "currentUser" ], name: "idx_credential_user")]
class LogSessionModel {

    #[Field(isID: true)]
    public int $sessionID = 0;

    #[Field]
    public int $credentialID = 0;

    #[Field]
    public int $currentUser = 0;

    #[Field]
    public string $ip = "";

    #[Field]
    public string $userAgent = "";

    #[Field]
    public bool $isOpen = false;



    #[Relation(fieldNames: [ "name", "firstName", "lastName", "email" ])]
    public ?CredentialModel $credential = null;
}
