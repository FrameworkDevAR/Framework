<?php
namespace Framework\Database\Migration;

use Framework\Database\Migration\BaseMigration;
use Framework\Database\Database;

/**
 * The Data Migration, which runs before the code is deployed
 */
interface DataMigration extends BaseMigration {

    /**
     * Migrates the Data
     * @param Database $db
     * @return void
     */
    public static function migrate(Database $db): void;
}
