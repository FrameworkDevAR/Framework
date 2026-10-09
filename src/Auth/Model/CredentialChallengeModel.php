<?php
namespace Framework\Auth\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Date\Date;

/**
 * The Credential Challenge Model
 */
#[Model(
    description: "The passkey challenges waiting for an answer, with the time each one was made.",
    canEdit:     true,
)]
class CredentialChallengeModel {

    #[Field(isPrimary: true)]
    public string $challenge = "";

    #[Field]
    public int $credentialID = 0;

    #[Field]
    public ?Date $time = null;
}
