<?php
namespace {{namespace}};

use Framework\Core\Configs;
use Framework\Enum\Enum;
use Framework\Enum\IsEnum;

use JsonSerializable;

/**
 * The Environments
 */
enum Environment implements Enum, JsonSerializable {
    use IsEnum;

    case None;

    case Local;
{{#environments}}
    case {{name}};
{{/environments}}



    /**
     * Returns the current Environment
     * @return Environment
     */
    public static function getCurrent(): Environment {
        return self::fromValue(Configs::getEnvironment());
    }

    /**
     * Returns true if the Environment is the current one
     * @return bool
     */
    public function isCurrent(): bool {
        return $this === self::getCurrent();
    }
}
