<?php
namespace Framework\Database\Migration;

use Framework\Database\Migration\BaseMigration;
use Framework\Database\Database;

/**
 * The Deploy Migration, which runs once the code is deployed
 */
interface DeployMigration extends BaseMigration {

    /**
     * Migrates what the old code wrote between the migrate and the deploy
     * @param Database $db
     * @return void
     */
    public static function postDeploy(Database $db): void;
}
