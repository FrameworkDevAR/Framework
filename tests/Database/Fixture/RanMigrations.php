<?php
namespace Tests\Database\Fixture;

/**
 * What the Data Migrations of the tests write down when they run
 *
 * A migration is a file the scanner reads and includes, so the ones of the
 * tests are written out rather than declared, and this is how they say they
 * were applied.
 */
class RanMigrations {

    /** @var list<string> */
    private static array $names = [];

    /** @var list<string> */
    private static array $deployed = [];


    /**
     * Writes down that the Migration of the given name ran
     * @param string $name
     * @return void
     */
    public static function add(string $name): void {
        self::$names[] = $name;
    }

    /**
     * Returns the Migrations that ran, in the order they did
     * @return list<string>
     */
    public static function getAll(): array {
        return self::$names;
    }

    /**
     * Writes down that the Post Deploy of the Migration of the given name ran
     * @param string $name
     * @return void
     */
    public static function addDeployed(string $name): void {
        self::$deployed[] = $name;
    }

    /**
     * Returns the Migrations whose Post Deploy ran, in the order they did
     * @return list<string>
     */
    public static function getDeployed(): array {
        return self::$deployed;
    }

    /**
     * Forgets the ones that ran
     * @return void
     */
    public static function reset(): void {
        self::$names    = [];
        self::$deployed = [];
    }
}
