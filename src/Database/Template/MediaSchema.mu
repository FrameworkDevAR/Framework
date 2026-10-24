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
     * Updates the Paths in the Database, only in the rows of the given ID of the Media
     * @param string $oldPath
     * @param string $newPath
     * @param int    $id      Optional.
     * @return void
     */
    public static function updatePaths(string $oldPath, string $newPath, int $id = 0): void {
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
                self::removePath($old, $id);
            } else {
                self::replacePath($old, $new, $id);
            }
        }
    }

    /**
     * Replaces the Path in the Database
     * @param string $old
     * @param string $new
     * @param int    $id
     * @return void
     */
    private static function replacePath(string $old, string $new, int $id): void {
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
            {{#hasOwner}}
            ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
            {{/hasOwner}}
            ->execute();
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::replacePrefix($oldDir, $newDir))
            ->where(Exp::create("LEFT(`{{fieldName}}`, ?)", $length), Op::Equal, $oldDir, caseSensitive: true)
            {{#hasOwner}}
            ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
            {{/hasOwner}}
            ->execute();
        {{/isSet}}
        {{#isReplace}}
        foreach (self::getTexts($old, $new, withDir: true) as [ $oldText, $newText ]) {
            Query::update("{{tableName}}")
                ->set("{{fieldName}}", Assign::replace($oldText, $newText))
                ->where(Exp::create("LOCATE(?, `{{fieldName}}`)", $oldText), Op::GreaterThan, 0)
                {{#hasOwner}}
                ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
                {{/hasOwner}}
                ->execute();
        }
        {{/isReplace}}
        {{#isJSON}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::jsonReplace($old, $new))
            ->where(Exp::jsonValid("`{{fieldName}}`"))
            ->where(Exp::jsonSearch("`{{fieldName}}`", $old)->isNotNull())
            {{#hasOwner}}
            ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
            {{/hasOwner}}
            ->execute();
        {{/isJSON}}
    {{/fields}}
    }

    /**
     * Removes the Path from the Database
     * @param string $old
     * @param int    $id
     * @return void
     */
    private static function removePath(string $old, int $id): void {
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
            {{#hasOwner}}
            ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
            {{/hasOwner}}
            ->execute();
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", "")
            ->where(Exp::create("LEFT(`{{fieldName}}`, ?)", $length), Op::Equal, $oldDir, caseSensitive: true)
            {{#hasOwner}}
            ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
            {{/hasOwner}}
            ->execute();
        {{/isSet}}
        {{#isReplace}}
        foreach (self::getTexts($old, "") as [ $oldText, $newText ]) {
            Query::update("{{tableName}}")
                ->set("{{fieldName}}", Assign::replace($oldText, $newText))
                ->where(Exp::create("LOCATE(?, `{{fieldName}}`)", $oldText), Op::GreaterThan, 0)
                {{#hasOwner}}
                ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
                {{/hasOwner}}
                ->execute();
        }
        {{/isReplace}}
        {{#isJSON}}
        Query::update("{{tableName}}")
            ->set("{{fieldName}}", Assign::jsonRemove($old))
            ->where(Exp::jsonValid("`{{fieldName}}`"))
            ->where(Exp::jsonSearch("`{{fieldName}}`", $old)->isNotNull())
            {{#hasOwner}}
            ->where(Exp::create("{{ownerWhere}}", $id), condition: $id > 0)
            {{/hasOwner}}
            ->execute();
        {{/isJSON}}
    {{/fields}}
    }
{{#hasText}}

    /**
     * Returns the texts of a Path inside a text and the ones that replace them
     * @param string $old
     * @param string $new
     * @param bool   $withDir Optional.
     * @return list<array{string,string}>
     */
    private static function getTexts(string $old, string $new, bool $withDir = false): array {
        // A Path is written as a quoted value, and a JSON that was not encoded by the
        // Framework escapes its slashes, so both ways are looked for
        $result = [];
        foreach ([ "/", "\\/" ] as $slash) {
            $oldText = Strings::replace($old, "/", $slash);
            $newText = Strings::replace($new, "/", $slash);
            $result[] = [ "\"$oldText\"", "\"$newText\"" ];

            // A Path can be a Directory, so the Files inside it change too
            if ($withDir) {
                $result[] = [ "\"$oldText$slash", "\"$newText$slash" ];
            }
        }
        return $result;
    }
{{/hasText}}
}
