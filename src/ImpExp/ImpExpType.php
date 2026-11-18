<?php
namespace Framework\ImpExp;

use Framework\Enum\Enum;
use Framework\Enum\IsEnum;

use JsonSerializable;

/**
 * The Import and Export Types
 */
enum ImpExpType implements Enum, JsonSerializable {
    use IsEnum;

    case None;

    case Export;
    case Import;
}
