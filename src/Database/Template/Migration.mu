<?php
use Framework\Database\Database;
use Framework\Database\Migration\DataMigration;

class {{class}} implements DataMigration {

    #[\Override]
    public static function getTitle(): string {
        return "{{title}}";
    }

    #[\Override]
    public static function migrate(Database $db): void {
    }
}
