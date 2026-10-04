<?php
namespace Tests\Core;

use Framework\Core\SettingConfig;
use Framework\Core\SettingData;
use Framework\Core\VariableType;

use Tests\LiveTestCase;
use Tests\TestHelpers;

/**
 * The Settings, a row per variable and the migration that keeps them in step
 *
 * A Setting is declared with SettingConfig::register in a config file of the
 * App, which this repository has none of, so each test registers its own and
 * the migration is what puts them in the table.
 */
class SettingDataLiveTest extends LiveTestCase {
    use TestHelpers;

    /** @var mixed */
    private mixed $settings = null;


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();

        $this->settings = $this->getPrivateStaticProperty(SettingConfig::class, "settings");
        $this->setPrivateStaticProperty(SettingConfig::class, "settings", []);
        $this->query("DELETE FROM `settings`");
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(SettingConfig::class, "settings", $this->settings);
    }

    /**
     * Runs the migration, which prints what it did
     * @return string
     */
    private function migrate(): string {
        ob_start();
        try {
            SettingData::migrateData();
        } finally {
            $output = ob_get_clean();
        }
        return (string)$output;
    }

    /**
     * Registers one Setting and puts it in the table
     * @param string       $variable Optional.
     * @param VariableType $type     Optional.
     * @param mixed        $value    Optional.
     * @return void
     */
    private function register(
        string $variable = "example",
        VariableType $type = VariableType::String,
        mixed $value = "a value",
    ): void {
        SettingConfig::register($variable, SettingConfig::General, $type, $value);
    }



    public function testASettingIsAdded(): void {
        $this->register();

        $this->assertStringContainsString("Added 1 settings", $this->migrate());

        $this->assertSame("a value", SettingData::get(SettingConfig::General, "example"));
    }

    public function testTheValueIsEncoded(): void {
        // An array is kept as JSON, and an empty one as an empty object
        $this->register("list", VariableType::Array, [ "a", "b" ]);
        $this->migrate();

        $this->assertSame('["a","b"]', SettingData::get(SettingConfig::General, "list"));
    }

    public function testASettingThatIsNotThereIsEmpty(): void {
        $this->assertSame("", SettingData::get(SettingConfig::General, "nothing"));
    }

    public function testASettingIsSet(): void {
        $this->register();
        $this->migrate();

        $this->assertTrue(SettingData::set(SettingConfig::General, "example", "another"));

        $this->assertSame("another", SettingData::get(SettingConfig::General, "example"));
    }

    public function testOneThatIsNotThereIsNotSet(): void {
        $this->assertFalse(SettingData::set(SettingConfig::General, "nothing", "a value"));
    }



    public function testACoreSettingIsKept(): void {
        $this->assertTrue(SettingData::setCore("movement", 42));

        $this->assertSame(42, SettingData::getCore("movement"));
    }

    public function testACoreSettingStartsAtNothing(): void {
        $this->assertSame(0, SettingData::getCore("movement"));
    }

    public function testACoreSettingIsWrittenOver(): void {
        SettingData::setCore("movement", 42);

        SettingData::setCore("movement", 7);

        $this->assertSame(7, SettingData::getCore("movement"));
    }



    public function testTheSettingsAreGroupedBySection(): void {
        $this->register();
        SettingConfig::register("other", "Another", VariableType::String, "a third");
        $this->migrate();

        $this->assertSame([
            "Another" => [ "other" => "a third" ],
            "General" => [ "example" => "a value" ],
        ], SettingData::getAll());
    }

    public function testOnlyOneSectionIsAskedFor(): void {
        $this->register();
        SettingConfig::register("other", "Another", VariableType::String, "a third");
        $this->migrate();

        $this->assertSame([ "example" => "a value" ], SettingData::getAll(SettingConfig::General));
    }

    public function testTheValuesComeBackInTheirTypes(): void {
        $this->register("name", VariableType::String, "a value");
        $this->register("amount", VariableType::Integer, 42);
        $this->register("isActive", VariableType::Boolean, true);
        $this->register("emails", VariableType::List, [ "a@b.com", "c@d.com" ]);
        SettingConfig::register("other", "Another", VariableType::String, "a third");
        $this->migrate();

        $this->assertEquals([
            "name"     => "a value",
            "amount"   => 42,
            "isActive" => true,
            "emails"   => [ "a@b.com", "c@d.com" ],
        ], SettingData::getValues(SettingConfig::General));
    }

    public function testASectionThatIsNotThereIsEmpty(): void {
        $this->register();
        $this->migrate();

        $this->assertSame([], SettingData::getAll("Nothing"));
    }

    public function testTheSettingsComeBackAsAnObject(): void {
        $this->register();
        $this->migrate();

        $result = SettingData::getAll(asObject: true);

        $this->assertIsObject($result);
        $this->assertSame("a value", $result->General["example"]);
    }



    public function testSavingWritesTheValues(): void {
        $this->register();
        $this->migrate();

        SettingData::saveAll([ "General-example" => "another" ]);

        $this->assertSame("another", SettingData::get(SettingConfig::General, "example"));
    }

    public function testWhatIsNotGivenIsLeftAlone(): void {
        $this->register();
        $this->migrate();

        SettingData::saveAll([ "General-other" => "another" ]);

        $this->assertSame("a value", SettingData::get(SettingConfig::General, "example"));
    }

    public function testSavingASectionNamesItOnce(): void {
        $this->register();
        $this->migrate();

        SettingData::saveSection(SettingConfig::General, [ "example" => "another" ]);

        $this->assertSame("another", SettingData::get(SettingConfig::General, "example"));
    }

    public function testTheSavedValueIsEncoded(): void {
        $this->register("flag", VariableType::Boolean, false);
        $this->migrate();

        SettingData::saveSection(SettingConfig::General, [ "flag" => "1" ]);

        $this->assertSame("1", SettingData::get(SettingConfig::General, "flag"));
    }



    public function testWhatIsNoLongerRegisteredIsDeleted(): void {
        $this->register();
        $this->migrate();

        $this->setPrivateStaticProperty(SettingConfig::class, "settings", []);

        $this->assertStringContainsString("Deleted 1 settings", $this->migrate());
        $this->assertSame(0, SettingData::getEntityTotal());
    }

    public function testTheTypeIsModified(): void {
        $this->register();
        $this->migrate();

        $this->setPrivateStaticProperty(SettingConfig::class, "settings", []);
        $this->register("example", VariableType::Integer, 0);

        $this->assertStringContainsString("Modified 1 settings", $this->migrate());
        $this->assertSame(1, SettingData::getEntityTotal());
    }

    public function testOneWrittenAnotherWayIsRenamed(): void {
        // The section and the variable are matched without their case, so one
        // that was written differently is moved rather than added again
        $this->register();
        $this->migrate();
        $this->query("UPDATE `settings` SET `section` = 'general', `variable` = 'EXAMPLE'");

        $this->assertStringContainsString("Renamed 1 settings", $this->migrate());

        $this->assertSame(1, SettingData::getEntityTotal());
        $this->assertSame("a value", SettingData::get(SettingConfig::General, "example"));
    }

    public function testTheCoreSettingsAreLeftAlone(): void {
        // They are not registered anywhere, so the migration has to keep out
        // of the section it uses for itself
        SettingData::setCore("movement", 42);

        $this->migrate();

        $this->assertSame(42, SettingData::getCore("movement"));
    }

    public function testThereIsNothingToUpdate(): void {
        $this->assertStringContainsString("No settings updated", $this->migrate());
    }

    public function testASecondMigrationChangesNothing(): void {
        $this->register();
        $this->migrate();

        $this->assertStringContainsString("No settings updated", $this->migrate());
        $this->assertSame(1, SettingData::getEntityTotal());
    }
}
