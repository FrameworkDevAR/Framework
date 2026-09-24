<?php
namespace Tests\Database\Fixture\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Index;
use Framework\Database\Model\Requested;

/**
 * The parts of a Thing, which are the rows a SubRequest brings back
 *
 * It cannot be deleted, which is the other thing no Model of the Framework
 * is: the Schema still answers for a delete it will not do.
 */
#[Model(
    description: "A part of a thing kept for the tests.",
    canCreate:   true,
    canEdit:     true,
)]
#[Index([ "name", "testThingID" ], name: "byName")]
class TestPartModel {

    #[Field(isID: true)]
    public int $testPartID = 0;

    #[Field(isParent: true, isKey: true)]
    public int $testThingID = 0;

    #[Field, Requested]
    public string $name = "";
}
