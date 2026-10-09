<?php
namespace Framework\Auth\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\Date\Date;

/**
 * The Credential Passkey Model
 */
#[Model(
    description:   "The passkeys a credential signs in with, with the public key of each one.",
    hasTimestamps: true,
    canCreate:     true,
    canEdit:       true,
)]
class CredentialPasskeyModel {

    #[Field(isID: true)]
    public int $passkeyID = 0;

    #[Field(isKey: true)]
    public int $credentialID = 0;

    #[Field, Requested]
    public string $name = "";

    #[Field(isKey: true), Requested]
    public string $credentialKey = "";

    #[Field(isText: true)]
    public string $publicKey = "";

    #[Field(isSigned: true)]
    public int $algorithm = 0;

    #[Field]
    public int $signCount = 0;

    #[Field]
    public ?Date $lastUsedTime = null;



    #[Requested]
    public string $origin = "";

    #[Requested]
    public string $clientDataJSON = "";

    #[Requested]
    public string $authenticatorData = "";

    #[Requested]
    public string $signature = "";

    #[Requested]
    public string $attestationObject = "";

    #[Requested]
    public bool $isConditional = false;
}
