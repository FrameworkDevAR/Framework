<?php
namespace Framework\Email\Model;

use Framework\Email\EmailResult;

use Framework\Database\Model\Model;
use Framework\Database\Model\Index;
use Framework\Database\Model\Field;
use Framework\Database\Model\Requested;
use Framework\Database\Model\Expression;
use Framework\System\EmailCode;
use Framework\Date\Date;
use Framework\Date\Type\DateType;
use Framework\Utils\JSON;

/**
 * The Email Queue Model
 */
#[Model(
    description:   "The emails waiting to be sent and the sent ones, with each attempt result.",
    hasTimestamps: true,
    hasEnv:        true,
    canCreate:     true,
    canEdit:       true,
)]
#[Index([ "emailResult", "sendTime" ])]
class EmailQueueModel {

    #[Field(isID: true), Requested(isMultiID: true)]
    public int $emailQueueID = 0;

    #[Field]
    public EmailCode $emailCode = EmailCode::None;

    #[Field]
    public ?JSON $sendTo = null;

    #[Field]
    public string $subject = "";

    #[Field(isText: true)]
    public string $message = "";

    #[Field]
    public string $replyTo = "";

    #[Field]
    public EmailResult $emailResult = EmailResult::None;

    #[Field, Requested]
    public int $dataID = 0;


    #[Field]
    public ?Date $sendTime = null;

    #[Field]
    public ?Date $sentTime = null;



    #[Requested]
    public string $search = "";

    #[Requested(dateType: DateType::Start, hourInput: "fromHour")]
    public ?Date $fromDate = null;

    #[Requested(dateType: DateType::End, hourInput: "toHour")]
    public ?Date $toDate = null;

    /** @var list<string> */
    #[Requested]
    public array $results = [];





    #[Expression("IF(emailResult = 'NotProcessed', 1, 0)")]
    public bool $isPending = false;

    #[Expression("IF(emailResult <> 'Sent', 1, 0)")]
    public bool $isError = false;
}
