<?php
namespace Framework\ImpExp;

use Framework\Enum\Enum;
use Framework\Enum\IsEnum;

use JsonSerializable;

/**
 * The Import and Export Statuses
 */
enum ImpExpStatus implements Enum, JsonSerializable {
    use IsEnum;

    case None;

    case Running;
    case Ready;
    case Canceled;
    case Failed;
}
