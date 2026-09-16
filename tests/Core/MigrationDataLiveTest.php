<?php
namespace Tests\Core;

use Framework\Core\MigrationData;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Migration Data, the names of the data migrations already applied
 */
class MigrationDataLiveTest extends LiveTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->query("DELETE FROM `migrations`");
    }



    public function testThereIsNoneToStartWith(): void {
        $this->assertTrue(MigrationData::isEmpty());
        $this->assertSame([], MigrationData::getAppliedNames());
    }

    public function testOneAppliedIsRemembered(): void {
        MigrationData::add("the-first-one", "The first one");

        $this->assertFalse(MigrationData::isEmpty());
        $this->assertSame([ "the-first-one" ], MigrationData::getAppliedNames());
    }

    public function testTheNamesComeBackInOrder(): void {
        MigrationData::add("c-one", "The third");
        MigrationData::add("a-one", "The first");
        MigrationData::add("b-one", "The second");

        $this->assertSame([ "a-one", "b-one", "c-one" ], MigrationData::getAppliedNames());
    }

    /**
     * One stored is waiting for its post deploy unless it is stored as deployed
     * @param bool $isDeployed
     * @param list<string> $expected
     * @return void
     */
    #[DataProvider("providerNotDeployed")]
    public function testOneAppliedWaitsForItsDeploy(bool $isDeployed, array $expected): void {
        MigrationData::add("the-one", "The one", isDeployed: $isDeployed);

        $this->assertSame($expected, MigrationData::getNotDeployedNames());
    }

    /**
     * @return array<string,array{bool,list<string>}>
     */
    public static function providerNotDeployed(): array {
        return [
            "stored as applied"  => [ false, [ "the-one" ] ],
            "stored as deployed" => [ true,  [] ],
        ];
    }

    /**
     * One set as deployed stops waiting, and is stored when the migrate never did
     * @param bool $isStored
     * @return void
     */
    #[DataProvider("providerSetDeployed")]
    public function testOneSetDeployedStopsWaiting(bool $isStored): void {
        MigrationData::add("b-one", "The second");
        if ($isStored) {
            MigrationData::add("a-one", "The first");
        }

        MigrationData::setDeployed("a-one", "The first");

        $this->assertSame([ "b-one" ], MigrationData::getNotDeployedNames());
        $this->assertSame([ "a-one", "b-one" ], MigrationData::getAppliedNames());
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function providerSetDeployed(): array {
        return [
            "stored by the migrate"  => [ true  ],
            "never stored before it" => [ false ],
        ];
    }

    public function testTheTitleIsKeptBesideTheName(): void {
        MigrationData::add("the-one", "The one that was applied");

        $this->assertSame(
            "The one that was applied",
            MigrationData::getList()[0]->title,
        );
    }
}
