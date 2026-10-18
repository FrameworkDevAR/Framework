<?php
namespace Framework\Log\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\Date\Date;
use Framework\Date\Type\DateType;

/**
 * The Log Socket Model
 */
#[Model(
    description:   "The publishes the Socket Server did not take, counted per minute.",
    hasTimestamps: true,
    canCreate:     true,
    canEdit:       true,
)]
class LogSocketModel {

    #[Field(isID: true), Requested(isMultiID: true)]
    public int $logID = 0;

    #[Field(isKey: true)]
    public int $minute = 0;

    #[Field]
    public string $environment = "";

    #[Field]
    public string $error = "";

    #[Field]
    public int $amount = 0;

    #[Field, Requested(isString: true, canEdit: false)]
    public bool $isResolved = false;



    #[Requested]
    public string $search = "";

    #[Requested(dateType: DateType::Start, hourInput: "fromHour")]
    public ?Date $fromDate = null;

    #[Requested(dateType: DateType::End, hourInput: "toHour")]
    public ?Date $toDate = null;
}
