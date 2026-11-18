<?php
namespace Framework\ImpExp\Model;

use Framework\Auth\Model\CredentialModel;
use Framework\ImpExp\ImpExpType;
use Framework\ImpExp\ImpExpStatus;

use Framework\Database\Model\Model;
use Framework\Database\Model\Index;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\Database\Model\Relation;
use Framework\Date\Date;
use Framework\Date\Type\DateType;
use Framework\Utils\JSON;

/**
 * The Import and Export Model
 */
#[Model(
    description:   "The exports and imports too big for a request, done in blocks.",
    hasTimestamps: true,
    hasEnv:        true,
    canCreate:     true,
    canEdit:       true,
)]
#[Index([ "credentialID", "module" ])]
class ImpExpModel {

    #[Field(isID: true)]
    public int $impExpID = 0;

    #[Field]
    public int $credentialID = 0;

    #[Field]
    public ImpExpType $impExpType = ImpExpType::None;

    #[Field]
    public string $module = "";

    #[Field]
    public int $dataID = 0;

    #[Field]
    public ?JSON $options = null;

    #[Field]
    public string $fileName = "";

    #[Field]
    public string $fileCode = "";

    #[Field]
    public int $totalRows = 0;

    #[Field]
    public int $doneRows = 0;

    #[Field]
    public int $importedRows = 0;

    #[Field]
    public ?Date $expirationTime = null;

    #[Field]
    public ImpExpStatus $impExpStatus = ImpExpStatus::None;



    #[Requested]
    public string $search = "";

    #[Requested(dateType: DateType::Start, hourInput: "fromHour")]
    public ?Date $fromDate = null;

    #[Requested(dateType: DateType::End, hourInput: "toHour")]
    public ?Date $toDate = null;

    /** @var list<string> */
    #[Requested]
    public array $types = [];

    /** @var list<string> */
    #[Requested]
    public array $statuses = [];



    #[Relation(fieldNames: [ "name", "firstName", "lastName" ])]
    public ?CredentialModel $credential = null;
}
