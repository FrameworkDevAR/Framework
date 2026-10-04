<?php
namespace Tests\Database\Linked\Model;

use Framework\Auth\Model\CredentialModel;
use Framework\Database\Model\Field;
use Framework\Database\Model\Model;
use Framework\Database\Model\Relation;

/**
 * A Model of an App that relates to a Model of the Framework without extending it
 */
#[Model(
    description: "A note written by a credential, kept for the tests.",
)]
class LinkedNoteModel {

    #[Field(isID: true)]
    public int $noteID = 0;

    #[Field]
    public int $credentialID = 0;

    #[Field]
    public string $text = "";



    #[Relation(fieldNames: [ "name", "email" ])]
    public ?CredentialModel $credential = null;
}
