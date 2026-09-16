<?php
namespace Framework\Core;

use Framework\Core\Schema\MigrationsSchema;
use Framework\Core\Schema\MigrationsQuery;
use Framework\Date\Date;

/**
 * The Migration Data
 */
class MigrationData extends MigrationsSchema {

    /**
     * Returns the Names of the Migrations that were already applied
     * @return list<string>
     */
    public static function getAppliedNames(): array {
        if (!self::tableExists()) {
            return [];
        }

        $query = new MigrationsQuery();
        $query->name->orderByAsc();

        $result = [];
        foreach (self::getEntityList($query) as $elem) {
            $result[] = $elem->name;
        }
        return $result;
    }

    /**
     * Returns the Names of the applied Migrations whose post deploy did not run yet
     * @return list<string>
     */
    public static function getNotDeployedNames(): array {
        if (!self::tableExists()) {
            return [];
        }

        $query = new MigrationsQuery();
        $query->deployedTime->isEmpty();
        $query->name->orderByAsc();

        $result = [];
        foreach (self::getEntityList($query) as $elem) {
            $result[] = $elem->name;
        }
        return $result;
    }

    /**
     * Returns true if there is no Migration applied yet
     * @return bool
     */
    public static function isEmpty(): bool {
        if (!self::tableExists()) {
            return true;
        }
        return self::getEntityTotal() === 0;
    }

    /**
     * Stores the given Migration as applied
     * @param string $name
     * @param string $title
     * @param bool   $isDeployed Optional.
     * @return void
     */
    public static function add(string $name, string $title, bool $isDeployed = false): void {
        if (!self::tableExists()) {
            return;
        }

        self::createEntity(
            name:         $name,
            title:        $title,
            deployedTime: $isDeployed ? Date::now() : null,
        );
    }

    /**
     * Stores the post deploy of the given Migration as run
     * @param string $name
     * @param string $title
     * @return void
     */
    public static function setDeployed(string $name, string $title): void {
        if (!self::tableExists()) {
            return;
        }

        // A Migration with only a post deploy is not stored by the migrate, so it
        // gets its row here
        $query = new MigrationsQuery();
        $query->name->equal($name);
        if (self::entityExists($query)) {
            self::editEntity($query, deployedTime: Date::now());
            return;
        }
        self::add($name, $title, isDeployed: true);
    }
}
