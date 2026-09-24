<?php
namespace Tests\Database\Broken\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Index;

/**
 * A Model whose Index names a column it has not
 */
#[Model(description: "A model with an index over nothing, kept for the tests.")]
#[Index([ "name", "nothing" ])]
#[Index("name")]
class BadIndexModel {

    #[Field(isID: true)]
    public int $badIndexID = 0;

    #[Field]
    public string $name = "";
}
