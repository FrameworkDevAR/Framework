<?php
namespace Tests\Database\Fixture\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\Database\Model\SubRequest;

/**
 * A Model of the tests, carrying what none of the Framework does
 *
 * The Schema is written for Apps, so a few of its paths are ones no Model
 * shipped with the Framework ever takes: an encrypted column, a flag only one
 * row may hold, and the rows of a second table pulled in with the first.
 */
#[Model(
    description:   "A thing kept for the tests.",
    hasTimestamps: true,
    hasUsers:      true,
    canCreate:     true,
    canEdit:       true,
    canDelete:     true,
)]
class TestThingModel {

    #[Field(isID: true)]
    public int $testThingID = 0;

    #[Field(isUnique: true), Requested]
    public string $name = "";

    #[Field(isEncrypt: true), Requested]
    public string $secret = "";

    #[Field, Requested]
    public int $isDefault = 0;

    #[Field(isPosition: true), Requested]
    public int $position = 0;

    /** @var list<TestPartModel> */
    #[SubRequest]
    public array $parts = [];

    // The same rows, keyed by their name rather than gathered in a list
    /** @var array<string,TestPartModel> */
    #[SubRequest(fieldName: "name")]
    public array $partsByName = [];
}
