<?php
use Framework\Database\Migration\Migration;

// Migrations Examples
// The table names can be in snake_case or PascalCase
// The column names can be in camelCase or SNAKE_CASE for ID columns

Migration::renameTable("old_table_name", "new_table_name");
Migration::renameTable("OldTableName", "NewTableName");

Migration::renameColumn("table_name", "oldColumnName", "newColumnName");
Migration::renameColumn("TableName", "oldColumnName", "newColumnName");
Migration::renameColumn("TableName", "OLD_COLUMN_ID", "NEW_COLUMN_ID");
Migration::renameColumn("TableName", "oldColumnID", "newColumnID");
