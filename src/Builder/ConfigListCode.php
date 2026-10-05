<?php
namespace Framework\Builder;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\DiscoveryConfig;
use Framework\Discovery\Attr\Priority;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Builder\Builder;

/**
 * The Config List Code
 * @phpstan-type ConfigListResult array{
 *   files: list<string>,
 *   total: int,
 * }
 */
#[Priority(Priority::Highest)]
class ConfigListCode implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $data = self::collectFiles();
        return Builder::generateCode("ConfigList", $data);
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 1;
    }



    /**
     * Collects the Config files that a request loads
     * @return ConfigListResult
     */
    public static function collectFiles(): array {
        $files = DiscoveryConfig::getRelativePaths();
        return [
            "files" => $files,
            "total" => count($files),
        ];
    }
}
