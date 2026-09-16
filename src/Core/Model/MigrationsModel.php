<?php
namespace Framework\Core\Model;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Date\Date;

/**
 * The Migrations Model
 */
#[Model(
    description:   "The data migrations that already ran, so each one is applied once.",
    hasTimestamps: true,
    canCreate:     true,
    canEdit:       true,
)]
class MigrationsModel {

    #[Field(isPrimary: true)]
    public string $name = "";

    #[Field]
    public string $title = "";

    // Set when the post deploy of the migration ran, or when it has none to run
    #[Field]
    public ?Date $deployedTime = null;
}
