<?php
namespace {{namespace}};

use Framework\File\Storage;{{#hasFields}}
use Framework\Database\Query\Query;
use Framework\Database\Query\Op;
use Framework\Database\Query\Assign;
use Framework\Database\Query\Exp;
use Framework\Utils\Strings;{{/hasFields}}

/**
 * The Media Schema
 */
class MediaSchema {

    /**
     * Updates the Paths in the Database
     * @param string $oldPath
     * @param string $newPath
     * @return void
     */
    public static function updatePaths(string $oldPath, string $newPath): void {
        if ($oldPath === $newPath || $oldPath === "") {
            return;
        }

        $files = [
            [
                "old" => $oldPath,
                "new" => $newPath,
            ],
            [
                "old" => Storage::addFirstSlash($oldPath),
                "new" => Storage::addFirstSlash($newPath),
            ],
        ];

        foreach ($files as $file) {
            $old = $file["old"];
            $new = $file["new"];

            if ($newPath === "") {
                self::removePath($old);
            } else {
                self::replacePath($old, $new);
            }
        }
    }

    /**
     * Replaces the Path in the Database
     * @param string $old
     * @param string $new
     * @return void
     */
    private static function replacePath(string $old, string $new): void {
    {{#hasFields}}
        // A Path can be a Directory, so the Files inside it change too
        $oldDir = "$old/";
        $newDir = "$new/";
        $length = Strings::length($oldDir);
    {{/hasFields}}
    {{#fields}}

        // Replace the File Path in {{name}}.{{fieldName}}
        {{#isSet}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", $new)
            ->where("{{fieldName}}", Op::Equal, $old)
            ->execute();
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::replacePrefix($oldDir, $newDir))
            ->where(Exp::create("LEFT(`{{fieldName}}`, ?)", $length), Op::Equal, $oldDir, caseSensitive: true)
            ->execute();
        {{/isSet}}
        {{#isReplace}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::replace($old, $new))
            ->where("{{fieldName}}", Op::Like, "\"$old\"")
            ->execute();
        {{/isReplace}}
        {{#isJSON}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::jsonReplace($old, $new))
            ->where(Exp::jsonValid("`{{fieldName}}`"))
            ->where(Exp::jsonSearch("`{{fieldName}}`", $old)->isNotNull())
            ->execute();
        {{/isJSON}}
    {{/fields}}
    }

    /**
     * Removes the Path from the Database
     * @param string $old
     * @return void
     */
    private static function removePath(string $old): void {
    {{#hasFields}}
        // A Path can be a Directory, so the Files inside it are removed too
        $oldDir = "$old/";
        $length = Strings::length($oldDir);
    {{/hasFields}}
    {{#fields}}

        // Remove the File Path in {{name}}.{{fieldName}}
        {{#isSet}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", "")
            ->where("{{fieldName}}", Op::Equal, $old)
            ->execute();
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", "")
            ->where(Exp::create("LEFT(`{{fieldName}}`, ?)", $length), Op::Equal, $oldDir, caseSensitive: true)
            ->execute();
        {{/isSet}}
        {{#isReplace}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::replace($old, ""))
            ->where("{{fieldName}}", Op::Like, "\"$old\"")
            ->execute();
        {{/isReplace}}
        {{#isJSON}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::jsonRemove($old))
            ->where(Exp::jsonValid("`{{fieldName}}`"))
            ->where(Exp::jsonSearch("`{{fieldName}}`", $old)->isNotNull())
            ->execute();
        {{/isJSON}}
    {{/fields}}
    }
}
