<?php
namespace Framework\Intl;

use Framework\Application;
use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Package;
use Framework\Discovery\DiscoveryConfig;
use Framework\Discovery\Attr\ConsoleCommand;
use Framework\Intl\IntlConfig;
use Framework\Intl\NLSUsage;
use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * The Language Files Checker
 * @phpstan-import-type NLSUsageData from NLSUsage
 * @phpstan-type NLSValue array{
 *   line:  int,
 *   shape: string,
 *   keys:  list<string>,
 * }
 * @phpstan-type NLSFile array{
 *   name:   string,
 *   values: array<string,NLSValue>,
 * }
 */
class NLSCheck {

    // Every strings file names its own language, so it is the one key meant to differ
    private const SkipKey = "NAME";

    // A Language file is either a JSON or a script that exports an object
    private const Extensions = [ ".json", ".js", ".mjs", ".ts" ];



    /**
     * Checks that the Language files of every directory hold the same content
     * @return void
     */
    #[ConsoleCommand("nlsCheck")]
    #[NotTested("It is a console command")]
    public static function check(): void {
        DiscoveryConfig::load();

        // The strings are written to mirror each other line for line, while a
        // notification is a block of its own, so only its place among the others
        // can be compared
        $sections = [
            [ "Strings", IntlConfig::getStringsPath(), true ],
            [ "Notifications", IntlConfig::getNotificationsPath(), false ],
        ];

        // The directories of the apps, which hold their strings as a script
        foreach (IntlConfig::getScriptPaths() as $name => $path) {
            $sections[] = [ $name, $path, true ];
        }

        $languages = [];
        $found     = [];
        foreach ($sections as [ $label, $path, $byLine ]) {
            $files = self::readFiles($path);
            if (count($files) > 0) {
                $languages[] = $path;
                $found[]     = [ $label, $path, $files, $byLine ];
            }
        }

        if (count($found) === 0) {
            print("There are no language files to check\n");
            return;
        }

        $usage   = self::readUsage($languages);
        $defined = [];
        $errors  = 0;

        foreach ($found as [ $label, $path, $files, $byLine ]) {
            $errors += self::printSection($label, $path, $files, $usage, byLine: $byLine);
            foreach (self::getKeys($files) as $key) {
                $defined[$key] = true;
            }
        }

        $errors += self::printMissing($usage, $defined);

        // Left as a failure, so the check can be believed when something runs it
        if ($errors > 0) {
            exit(1);
        }
    }



    /**
     * Prints what the files of one directory disagree on, a file at a time
     * @param string            $label
     * @param string            $path
     * @param list<NLSFile>     $files
     * @param NLSUsageData|null $usage
     * @param bool              $byLine
     * @return int
     */
    private static function printSection(
        string $label,
        string $path,
        array $files,
        ?array $usage,
        bool $byLine,
    ): int {
        $total = count($files);
        print("$label: " . self::getRelativePath($path) . "\n");

        $errors = $total === 1 ? [] : self::getErrors($files, byLine: $byLine);
        $found  = 0;

        // Walked in the order the files were read, so the report follows them
        foreach ($files as $file) {
            $messages = $errors[$file["name"]] ?? [];
            $amount   = count($messages);
            if ($amount === 0) {
                continue;
            }

            $errorWord = $amount === 1 ? "error" : "errors";
            print("- Found $amount $errorWord in {$file["name"]}\n");
            foreach ($messages as $message) {
                print("  - $message\n");
            }
            $found += $amount;
        }

        // The files hold the same keys whether they agree on them or not, so
        // what the source never names is worth saying either way
        $unused = self::getUnused($files, $usage);
        $amount = count($unused);

        if ($amount > 0) {
            $keyWord = $amount === 1 ? "key" : "keys";
            print("- Found $amount $keyWord that no source uses\n");
            foreach ($unused as $key) {
                print("  - $key\n");
            }
            $found += $amount;
        }

        if ($found === 0) {
            $what = $total === 1 ? "There is a single file, so there is nothing to compare" :
                "Compared $total files, there are no errors";
            print("- $what\n");
        }

        print("\n");
        return $found;
    }

    /**
     * Prints the Keys the Source names that no Language file defines
     * @param NLSUsageData|null  $usage
     * @param array<string,bool> $defined
     * @return int
     */
    private static function printMissing(?array $usage, array $defined): int {
        if ($usage === null) {
            return 0;
        }

        $paths = [];
        foreach (IntlConfig::getSourcePaths() as $path) {
            $paths[] = self::getRelativePath($path);
        }
        print("Sources: " . self::join($paths, " and ") . "\n");

        $missing = NLSUsage::getMissing($usage, $defined);
        $amount  = count($missing);
        if ($amount === 0) {
            print("- Every key the source names is defined\n\n");
            return 0;
        }

        $keyWord = $amount === 1 ? "key" : "keys";
        print("- Found $amount $keyWord that no file defines\n");
        foreach ($missing as $key => $where) {
            print("  - $key, used in " . self::getRelativePath($where) . "\n");
        }

        print("\n");
        return $amount;
    }

    /**
     * Reads the Source of the app, which is where the Keys are used
     * @param list<string> $languages
     * @return NLSUsageData|null
     */
    private static function readUsage(array $languages): ?array {
        $paths = IntlConfig::getSourcePaths();
        if (count($paths) === 0) {
            return null;
        }

        // The framework names keys of its own — GENERAL_AND and the days among
        // them — and an app that never writes them still uses them through it.
        // Only what the app itself calls is read as a call, since a key of the
        // framework that the app never reaches is not one it is missing
        $usage = NLSUsage::read($paths, $languages, withCalls: true);
        $found = NLSUsage::read([ Package::getBasePath("src") ], $languages, withCalls: false);
        return NLSUsage::merge($usage, $found);
    }

    /**
     * Returns the Keys of the given files that the Source never names
     * @param list<NLSFile>     $files
     * @param NLSUsageData|null $usage
     * @return list<string>
     */
    private static function getUnused(array $files, ?array $usage): array {
        if ($usage === null) {
            return [];
        }

        $result = [];
        foreach (self::getKeys($files) as $key) {
            if (!NLSUsage::isUsed($usage, $key)) {
                $result[] = $key;
            }
        }
        return $result;
    }

    /**
     * Returns what each file gets wrong, against what the other ones hold
     * @param list<NLSFile> $files
     * @param bool          $byLine
     * @return array<string,list<string>>
     */
    private static function getErrors(array $files, bool $byLine): array {
        // A file that cannot be read holds no key at all, and comparing it would
        // report every key of every other file as missing from it
        $errors = [];
        $valid  = [];
        foreach ($files as $file) {
            if (count($file["values"]) === 0) {
                $errors[$file["name"]][] = "the file is empty, or could not be read";
            } else {
                $valid[] = $file;
            }
        }
        if (count($valid) < 2) {
            return $errors;
        }

        // The keys have to match before anything else means something, since a
        // key that only one file holds moves everything below it in that file
        $found = self::checkKeys($valid);
        if (count($found) === 0) {
            $found = $byLine ? self::checkLines($valid) : self::checkOrder($valid);
        }
        return self::merge($errors, $found);
    }

    /**
     * Checks that every file holds the same keys
     * @param list<NLSFile> $files
     * @return array<string,list<string>>
     */
    private static function checkKeys(array $files): array {
        $errors = [];

        foreach (self::getKeys($files) as $key) {
            $with    = [];
            $without = [];
            foreach ($files as $file) {
                if (array_key_exists($key, $file["values"])) {
                    $with[] = $file["name"];
                } else {
                    $without[] = $file["name"];
                }
            }

            if (count($without) > 0) {
                $errors = self::merge($errors, self::getPresence($files, $key, $with, $without));
                continue;
            }
            $errors = self::merge($errors, self::checkSelect($files, $key));
        }
        return $errors;
    }

    /**
     * Checks that every file holds the same keys inside a key: the options of a
     * Select, or the parts an email or a notification is made of
     * @param list<NLSFile> $files
     * @param string        $key
     * @return array<string,list<string>>
     */
    private static function checkSelect(array $files, string $key): array {
        $shapes = [];
        foreach ($files as $file) {
            $shapes[$file["name"]] = $file["values"][$key]["shape"] ?? "";
        }

        // One file writes the key as a Select and another as a plain string, so
        // there are no keys inside it to compare
        $groups = self::getGroups($shapes);
        if (count($groups) > 0) {
            return self::getDifference($groups, fn(string $shape) => "$key is $shape");
        }

        $errors = [];
        foreach (self::getOptions($files, $key) as $option) {
            $with    = [];
            $without = [];
            foreach ($files as $file) {
                $options = $file["values"][$key]["keys"] ?? [];
                if (in_array($option, $options, strict: true)) {
                    $with[] = $file["name"];
                } else {
                    $without[] = $file["name"];
                }
            }

            if (count($without) > 0) {
                $inside = "the $option key of $key";
                $errors = self::merge($errors, self::getPresence($files, $inside, $with, $without));
            }
        }
        return $errors;
    }

    /**
     * Checks that every file writes its keys at the same lines
     * @param list<NLSFile> $files
     * @return array<string,list<string>>
     */
    private static function checkLines(array $files): array {
        $errors = [];

        foreach (self::getKeys($files) as $key) {
            $lines = [];
            foreach ($files as $file) {
                $line = $file["values"][$key]["line"] ?? 0;
                $lines[$file["name"]] = "at line $line";
            }

            $groups = self::getGroups($lines);
            $errors = self::merge($errors, self::getDifference(
                $groups,
                fn(string $line) => "$key is $line",
            ));
        }
        return $errors;
    }

    /**
     * Checks that every file holds its keys in the same order
     * @param list<NLSFile> $files
     * @return array<string,list<string>>
     */
    private static function checkOrder(array $files): array {
        $keys  = [];
        $total = 0;
        foreach ($files as $file) {
            $fileKeys = self::getFileKeys($file);
            $keys[$file["name"]] = $fileKeys;
            $total = max($total, count($fileKeys));
        }

        // Only the first key out of place is reported, since the one that moved
        // pushes every key after it out of place as well
        for ($index = 0; $index < $total; $index += 1) {
            $found = [];
            foreach ($keys as $name => $fileKeys) {
                $found[$name] = $fileKeys[$index] ?? "";
            }

            $groups = self::getGroups($found);
            if (count($groups) > 0) {
                $position = $index + 1;
                return self::getDifference(
                    $groups,
                    fn(string $key) => "position $position holds $key",
                );
            }
        }
        return [];
    }



    /**
     * Returns the error of the files that are on their own, either way around
     * @param list<NLSFile> $files
     * @param string        $what
     * @param list<string>  $with
     * @param list<string>  $without
     * @return array<string,list<string>>
     */
    private static function getPresence(
        array $files,
        string $what,
        array $with,
        array $without,
    ): array {
        $errors = [];
        if (self::isExpected($files, $with, $without)) {
            $others = self::join($with, " and ");
            foreach ($without as $name) {
                $errors[$name][] = "$what is missing, and is in $others";
            }
            return $errors;
        }

        $others = self::join($without, " and ");
        foreach ($with as $name) {
            $errors[$name][] = "$what is not in $others";
        }
        return $errors;
    }

    /**
     * Returns the error of every file that is not in the first group, said
     * against what that group holds
     * @param list<array{string,list<string>}> $groups
     * @param callable(string):string          $getText
     * @return array<string,list<string>>
     */
    private static function getDifference(array $groups, callable $getText): array {
        if (count($groups) === 0) {
            return [];
        }

        [ $expected, $expectedFiles ] = $groups[0];
        $others = self::join($expectedFiles, " and ");
        $errors = [];

        foreach (array_slice($groups, 1) as [ $value, $names ]) {
            foreach ($names as $name) {
                $errors[$name][] = $getText($value) . ", and $expected in $others";
            }
        }
        return $errors;
    }

    /**
     * Groups the files by what each of them holds, with the group the rest are
     * compared against first. Nothing is returned when they all agree
     * @param array<string,string> $values
     * @return list<array{string,list<string>}>
     */
    private static function getGroups(array $values): array {
        $groups = [];
        foreach ($values as $name => $held) {
            $groups[$held][] = $name;
        }
        if (count($groups) < 2) {
            return [];
        }

        // The group the rest are compared against is the one most files are in.
        // Two of the same size are settled by the first file, which is the one
        // of the default language when the directory holds it
        $expected = "";
        $amount   = 0;
        foreach ($groups as $groupValue => $names) {
            if (count($names) > $amount) {
                $expected = Strings::toString($groupValue);
                $amount   = count($names);
            }
        }

        $result = [ [ $expected, $groups[$expected] ?? [] ] ];
        foreach ($groups as $otherValue => $names) {
            if (Strings::toString($otherValue) !== $expected) {
                $result[] = [ Strings::toString($otherValue), $names ];
            }
        }
        return $result;
    }

    /**
     * Returns whether the first group is the one the other one is compared
     * against: the one with more files, or the one the first file is in
     * @param list<NLSFile> $files
     * @param list<string>  $group
     * @param list<string>  $other
     * @return bool
     */
    private static function isExpected(array $files, array $group, array $other): bool {
        if (count($group) !== count($other)) {
            return count($group) > count($other);
        }

        // Both groups are read in the order the files were, so the one that
        // starts with the first file is the one that holds it
        $first = $files[0]["name"] ?? "";
        return ($group[0] ?? "") === $first;
    }

    /**
     * Adds the errors a check found to the ones of each file
     * @param array<string,list<string>> $errors
     * @param array<string,list<string>> $found
     * @return array<string,list<string>>
     */
    private static function merge(array $errors, array $found): array {
        foreach ($found as $name => $messages) {
            foreach ($messages as $message) {
                $errors[$name][] = $message;
            }
        }
        return $errors;
    }



    /**
     * Returns the given path as it is written in the config, which is what
     * whoever reads the report has to go and open
     * @param string $path
     * @return string
     */
    private static function getRelativePath(string $path): string {
        $basePath = Application::getBasePath();
        return Strings::stripStart($path, "$basePath/");
    }

    /**
     * Reads every Language file of the given directory
     * @param string $path
     * @return list<NLSFile>
     */
    private static function readFiles(string $path): array {
        $result = [];
        foreach (Storage::getFilesInDir($path) as $fileName) {
            if (!Strings::endsWith($fileName, ...self::Extensions)) {
                continue;
            }

            // The reading below takes what a script is allowed to write, so a
            // JSON is asked to be one, or nothing would be said about a broken file
            $contents = Storage::readFile($path, $fileName);
            $isBroken = Strings::endsWith($fileName, ".json") && !JSON::isValid($contents);

            $result[] = [
                "name"   => $fileName,
                "values" => $isBroken ? [] : self::parseFile($contents),
            ];
        }
        return self::sortFiles($result);
    }

    /**
     * Puts the file of the default language first, since it is the one the rest
     * are written from, and the one that settles a difference of two files
     * @param list<NLSFile> $files
     * @return list<NLSFile>
     */
    private static function sortFiles(array $files): array {
        $langCode = IntlConfig::getDefaultLanguage();
        $result   = [];

        foreach ($files as $file) {
            if (Strings::substringBefore($file["name"], ".") === $langCode) {
                $result[] = $file;
            }
        }
        foreach ($files as $file) {
            if (Strings::substringBefore($file["name"], ".") !== $langCode) {
                $result[] = $file;
            }
        }
        return $result;
    }

    /**
     * Returns every key of every file, in the order they first show up
     * @param list<NLSFile> $files
     * @return list<string>
     */
    private static function getKeys(array $files): array {
        $result = [];
        foreach ($files as $file) {
            foreach (self::getFileKeys($file) as $key) {
                $result[$key] = true;
            }
        }

        // A key that is only digits is an integer once it indexes an array, and
        // it would no longer be the string the files are compared by
        return Arrays::toStrings(array_keys($result));
    }

    /**
     * Returns the keys of one file, in the order it writes them
     * @param NLSFile $file
     * @return list<string>
     */
    private static function getFileKeys(array $file): array {
        $result = [];
        foreach (Arrays::toStrings(array_keys($file["values"])) as $key) {
            if ($key !== self::SkipKey) {
                $result[] = $key;
            }
        }
        return $result;
    }

    /**
     * Returns every key the files write inside a key, as they first show up
     * @param list<NLSFile> $files
     * @param string        $key
     * @return list<string>
     */
    private static function getOptions(array $files, string $key): array {
        $result = [];
        foreach ($files as $file) {
            foreach ($file["values"][$key]["keys"] ?? [] as $option) {
                $result[$option] = true;
            }
        }
        return Arrays::toStrings(array_keys($result));
    }

    /**
     * Joins the given values, as "one", "one and two" or "one, two and three"
     * @param list<string> $values
     * @param string       $glue
     * @return string
     */
    private static function join(array $values, string $glue): string {
        $total = count($values);
        if ($total < 2) {
            return $values[0] ?? "";
        }

        $last = $values[$total - 1] ?? "";
        return Strings::join(array_slice($values, 0, $total - 1), ", ") . $glue . $last;
    }



    /**
     * Returns the keys of a Language file, with the line each one is written at
     * and the keys it holds inside. A JSON and a script that exports an object
     * are read the same way, since the difference is only in what is allowed:
     * a key that is not quoted, a comma after the last one, and comments
     * @param string $contents
     * @return array<string,NLSValue>
     */
    private static function parseFile(string $contents): array {
        $result   = [];
        $length   = strlen($contents);
        $index    = 0;
        $line     = 1;
        $depth    = 0;
        $text     = "";
        $textLine = 0;
        $word     = "";
        $wordLine = 0;
        $key      = "";
        $keyLine  = 0;
        $isList   = false;
        $commas   = 0;
        $lastChar = "";
        $inner    = [];

        while ($index < $length) {
            $char = $contents[$index];
            $next = $contents[$index + 1] ?? "";

            // A comment can hold anything, a quote or a colon included
            if ($char === "/" && ($next === "/" || $next === "*")) {
                [ $index, $line ] = self::skipComment($contents, $index, $line);
                continue;
            }

            // The entries of a list have no key of their own, so the commas
            // between them are counted, and the amount is compared instead
            if ($isList && $depth === 2 && $char !== "]" && !self::isSpace($char)) {
                if ($char === ",") {
                    $commas += 1;
                }
                $lastChar = $char;
            }

            // A key of a script is written without quotes, so it is read as the
            // word it is, and a colon is what turns either of them into a key
            if (self::isWordChar($char)) {
                if ($word === "") {
                    $wordLine = $line;
                }
                $word  .= $char;
                $index += 1;
                continue;
            }
            if ($word !== "") {
                $text     = $word;
                $textLine = $wordLine;
                $word     = "";
            }

            if ($char === "\"" || $char === "'" || $char === "`") {
                $textLine = $line;
                [ $text, $index, $line ] = self::readString($contents, $index, $line);
                continue;
            }

            if ($char === "{" || $char === "[") {
                // The value of the key opens here, so it is a Select
                if ($depth === 1 && $key !== "") {
                    $isList   = $char === "[";
                    $commas   = 0;
                    $lastChar = "";
                    $inner    = [];
                }
                $depth += 1;
            } elseif ($char === "}" || $char === "]") {
                $depth -= 1;
                if ($depth === 1 && $key !== "") {
                    // A comma after the last entry is allowed, and adds none
                    $amount = $lastChar === "" || $lastChar === "," ? $commas : $commas + 1;
                    $result[$key] = [
                        "line"  => $keyLine,
                        "shape" => "a Select",
                        "keys"  => $isList ? self::getIndexes($amount) : $inner,
                    ];
                    $key = "";
                }
            } elseif ($char === ":" && $text !== "") {
                if ($depth === 1) {
                    // Stored as a plain string, which the closing of a Select
                    // written for it overwrites, in the place it already has
                    $key     = $text;
                    $keyLine = $textLine;
                    $result[$key] = [ "line" => $keyLine, "shape" => "a string", "keys" => [] ];
                } elseif ($depth === 2 && $key !== "" && !$isList) {
                    $inner[] = $text;
                }
                $text = "";
            } elseif ($char === "," && $depth === 1) {
                // Nothing else is coming for this key, its value was a string
                $key = "";
            } elseif ($char === "\n") {
                $line += 1;
            }
            $index += 1;
        }
        return $result;
    }

    /**
     * Reads a quoted string, which can hold anything the file is made of
     * @param string $contents
     * @param int    $index
     * @param int    $line
     * @return array{string,int,int}
     */
    private static function readString(string $contents, int $index, int $line): array {
        $quote  = $contents[$index] ?? "";
        $length = strlen($contents);
        $result = "";
        $index += 1;

        while ($index < $length) {
            $char = $contents[$index];
            if ($char === "\\") {
                if (($contents[$index + 1] ?? "") === "\n") {
                    $line += 1;
                }
                $index += 2;
                continue;
            }
            if ($char === $quote) {
                return [ $result, $index + 1, $line ];
            }
            if ($char === "\n") {
                $line += 1;
            }
            $result .= $char;
            $index  += 1;
        }
        return [ $result, $index, $line ];
    }

    /**
     * Skips a comment, of either kind, and returns where it ends
     * @param string $contents
     * @param int    $index
     * @param int    $line
     * @return array{int,int}
     */
    private static function skipComment(string $contents, int $index, int $line): array {
        $length = strlen($contents);
        $isLine = ($contents[$index + 1] ?? "") === "/";
        $index += 2;

        while ($index < $length) {
            $char = $contents[$index];
            if ($char === "\n") {
                $line += 1;
                if ($isLine) {
                    return [ $index + 1, $line ];
                }
            } elseif (!$isLine && $char === "*" && ($contents[$index + 1] ?? "") === "/") {
                return [ $index + 2, $line ];
            }
            $index += 1;
        }
        return [ $index, $line ];
    }

    /**
     * Returns the given amount of entries as the keys of a list
     * @param int $amount
     * @return list<string>
     */
    private static function getIndexes(int $amount): array {
        $result = [];
        for ($index = 0; $index < $amount; $index += 1) {
            $result[] = Strings::toString($index);
        }
        return $result;
    }

    /**
     * Returns whether the given character is part of a word
     * @param string $char
     * @return bool
     */
    private static function isWordChar(string $char): bool {
        return ($char >= "a" && $char <= "z") ||
            ($char >= "A" && $char <= "Z") ||
            ($char >= "0" && $char <= "9") ||
            $char === "_" || $char === "$";
    }

    /**
     * Returns whether the given character is a space of any kind
     * @param string $char
     * @return bool
     */
    private static function isSpace(string $char): bool {
        return $char === " " || $char === "\n" || $char === "\r" || $char === "\t";
    }
}
