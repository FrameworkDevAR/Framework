<?php
namespace Framework\Log\Model;

use Framework\Auth\Model\CredentialModel;
use Framework\Log\Model\LogSessionModel;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Index;
use Framework\Database\Model\Virtual;
use Framework\Database\Model\Requested;
use Framework\Database\Model\Relation;

/**
 * The Log Action Model
 */
#[Model(
    description:   "What each credential did, as a module and an action, tied to its session.",
    hasTimestamps: true,
    canCreate:     true,
)]
#[Index([ "sessionID", "credentialID", "currentUser" ], name: "idx_session_credential_user")]
class LogActionModel {

    #[Field(isID: true)]
    public int $actionID = 0;

    #[Field]
    public int $sessionID = 0;

    #[Field(isKey: true), Requested]
    public int $credentialID = 0;

    #[Field]
    public int $currentUser = 0;

    #[Field]
    public string $module = "";

    #[Field]
    public string $action = "";

    #[Field(isText: true)]
    public string $dataID = "";



    #[Virtual]
    public string $moduleName = "";

    #[Virtual]
    public string $actionName = "";



    #[Requested]
    public string $search = "";



    #[Relation(fieldNames: [ "createdTime", "ip", "userAgent" ])]
    public ?LogSessionModel $session = null;

    #[Relation(fieldNames: [ "name", "firstName", "lastName", "email" ])]
    public ?CredentialModel $credential = null;
}
