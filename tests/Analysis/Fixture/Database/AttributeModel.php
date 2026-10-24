<?php
namespace Tests\Analysis\Fixture\Database;

use Tests\Analysis\Fixture\General\Box;

use Framework\Database\Model\Field;
use Framework\Database\Model\Model;
use Framework\Database\Model\Relation;
use Framework\Database\Model\SubRequest;
use Framework\Database\Model\Validate;
use Framework\Database\Status\Status;

#[Model(description: "A model breaking each of the attribute rules once")]
class AttributeModel {

    #[Field(isID: true)]
    public int $crateID;

    /** Nothing describes this one at all */
    public string $undescribed;

    /** Two main attributes on the one property */
    #[Field]
    #[Relation]
    public CrateModel $combined;

    /** Validate needs Requested beside it */
    #[Validate(isRequired: true)]
    public string $validated;

    /** A Status column has to be a Field */
    public Status $status;

    /** The type it relates to carries no #[Model] */
    #[Relation]
    public Box $notAModel;

    /** A SubRequest has to be an array */
    #[SubRequest(CrateModel::class)]
    public string $notAnArray;

    /** The owner of the files has to be a Field of the Model */
    #[Field(hasFile: true, fileOwner: "shopID")]
    public string $lostFile;

    /** Only a field that keeps files has an owner */
    #[Field(fileOwner: "crateID")]
    public string $notAFile;
}
