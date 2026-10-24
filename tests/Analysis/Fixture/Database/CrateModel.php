<?php
namespace Tests\Analysis\Fixture\Database;

use Framework\Database\Model\Field;
use Framework\Database\Model\Model;

#[Model(description: "A well formed model, for the others to point at")]
class CrateModel {

    #[Field(isID: true)]
    public int $crateID;

    #[Field]
    public string $name;

    #[Field]
    public int $shopID;

    #[Field(hasFile: true, fileOwner: "shopID")]
    public string $image;
}
