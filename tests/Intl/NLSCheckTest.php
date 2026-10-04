<?php
namespace Tests\Intl;

use Framework\Intl\NLSCheck;
use Framework\Application;
use Framework\File\Storage;
use Framework\Utils\Strings;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Language Files Checker
 *
 * The command itself reads the three directories of the app and exits with a
 * failure, so what is run here is the check each of them goes through: the
 * files of a case are written to a directory, compared against each other, and
 * what the check said about them is read back.
 */
class NLSCheckTest extends TestCase {
    use TestHelpers;

    private const FixtureDir = "tests/Intl/.tmp_nls_check";

    // The strings file the cases below are written against, with its keys at
    // the lines 2, 4, 5, 6 and 7
    private const RootFile = <<<JSON
    {
        "NAME"               : "English",

        "GENERAL_ACCEPT"     : "Accept",
        "DATE_TIME_SCHEDULE" : "{0}: from {1} to {2}",
        "SELECT_YES_NO"      : { "0" : "No", "1" : "Yes" },
        "DATE_TIME_DAYS"     : [ "Sunday", "Monday" ]
    }
    JSON;

    // And its translation, which every case starts from
    private const LangFile = <<<JSON
    {
        "NAME"               : "Español",

        "GENERAL_ACCEPT"     : "Aceptar",
        "DATE_TIME_SCHEDULE" : "{0}: de {1} a {2}",
        "SELECT_YES_NO"      : { "0" : "No", "1" : "Sí" },
        "DATE_TIME_DAYS"     : [ "Domingo", "Lunes" ]
    }
    JSON;

    // An app writes the same thing as a script, with its keys unquoted, a comma
    // after the last entry, comments, and the object it exports
    private const RootScript = <<<JS
    const strings = {

        // The app itself: its name
        TITLE          : "Conversana",
        LANGUAGES      : { en : "English", es : "Español" },
        GENERAL_ACCEPT : "Accept",
        GENERAL_SITE   : "https://conversana.com",
        DATE_TIME_DAYS : [
            "Sunday",
            "Monday",
        ],
    };

    export default strings;
    JS;

    private const LangScript = <<<JS
    const strings = {

        // The app itself: its name
        TITLE          : "Conversana",
        LANGUAGES      : { en : "English", es : "Español" },
        GENERAL_ACCEPT : "Aceptar",
        GENERAL_SITE   : "https://conversana.com",
        DATE_TIME_DAYS : [
            "Domingo",
            "Lunes",
        ],
    };

    export default strings;
    JS;

    // A file of blocks holds one per code, and both of them the same blocks
    private const RootBlocks = <<<JSON
    {
        "Welcome" : {
            "description" : "Sent when a user is created",
            "title"       : "Welcome to [site]",
            "message"     : [ "Hello {{name}}", "Your account is ready" ]
        },
        "Reset"   : {
            "description" : "Sent when a password is reset",
            "title"       : "Your new password",
            "message"     : [ "Hello {{name}}" ]
        }
    }
    JSON;

    private const LangBlocks = <<<JSON
    {
        "Welcome" : {
            "description" : "Se envía al crear un usuario",
            "title"       : "Bienvenido a [site]",
            "message"     : [ "Hola {{name}}", "Su cuenta está lista" ]
        },
        "Reset"   : {
            "description" : "Se envía al reiniciar la clave",
            "title"       : "Su nueva clave",
            "message"     : [ "Hola {{name}}" ]
        }
    }
    JSON;

    // The same two blocks, with the one that used to be first written last
    private const SwappedBlocks = <<<JSON
    {
        "Reset"   : {
            "description" : "Se envía al reiniciar la clave",
            "title"       : "Su nueva clave",
            "message"     : [ "Hola {{name}}" ]
        },
        "Welcome" : {
            "description" : "Se envía al crear un usuario",
            "title"       : "Bienvenido a [site]",
            "message"     : [ "Hola {{name}}", "Su cuenta está lista" ]
        }
    }
    JSON;

    private string $fixtureBase = "";


    protected function setUp(): void {
        $this->fixtureBase = Application::getBasePath(self::FixtureDir);
        Storage::createDir($this->fixtureBase);
    }

    protected function tearDown(): void {
        Storage::deleteDir($this->fixtureBase);
    }

    /**
     * Writes the given files and returns what the check made of them
     * @param array<string,string> $files
     * @param string               $label
     * @param bool                 $byLine
     * @return array{int,string}
     */
    private function runCheck(array $files, string $label, bool $byLine): array {
        foreach ($files as $fileName => $contents) {
            Storage::writeFile($this->fixtureBase . "/" . $fileName, $contents);
        }
        $read = $this->callPrivateStaticMethod(NLSCheck::class, "readFiles", $this->fixtureBase);

        ob_start();
        /** @var int */
        $errors = $this->callPrivateStaticMethod(
            NLSCheck::class,
            "printSection",
            $label,
            $this->fixtureBase,
            $read,
            null,
            $byLine,
        );
        $output = Strings::toString(ob_get_clean());

        return [ $errors, $output ];
    }



    /**
     * The files of a directory, and what the check makes of them
     * @param array<string,string> $files
     * @param int                  $errors
     * @param string               $message
     * @return void
     */
    #[DataProvider("providerCheckFiles")]
    public function testTheFilesAreCompared(array $files, int $errors, string $message): void {
        [ $found, $output ] = $this->runCheck($files, "Strings", byLine: true);

        $this->assertSame($errors, $found, $output);
        $this->assertStringContainsString($message, $output);
    }

    /**
     * @return array<string,array{array<string,string>,int,string}>
     */
    public static function providerCheckFiles(): array {
        return [
            "a translation" => [
                [ "en.json" => self::RootFile, "es.json" => self::LangFile ],
                0,
                "- Compared 2 files, there are no errors",
            ],
            "nothing to compare" => [
                [ "en.json" => self::RootFile ],
                0,
                "- There is a single file, so there is nothing to compare",
            ],
            // The files of an app are read the same way, as is what they hold
            "a script" => [
                [ "en.js" => self::RootScript, "es.js" => self::LangScript ],
                0,
                "- Compared 2 files, there are no errors",
            ],
            "a shifted script" => [
                [
                    "en.js" => self::RootScript,
                    "es.js" => Strings::replace(self::LangScript, "\n    // The app", "\n\n    // The app"),
                ],
                5,
                "- Found 5 errors in es.js\n  - TITLE is at line 5, and at line 4 in en.js\n",
            ],
            "a script with an extra key" => [
                [
                    "en.js" => self::RootScript,
                    "es.js" => Strings::replace(
                        self::LangScript,
                        "GENERAL_ACCEPT : \"Aceptar\",",
                        "GENERAL_ACCEPT : \"Aceptar\",\n    GENERAL_CANCEL : \"Cancelar\",",
                    ),
                ],
                1,
                "- Found 1 error in es.js\n  - GENERAL_CANCEL is not in en.js\n",
            ],
            // The errors go under the file they are about, and name the rest
            "a missing key" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, "\"GENERAL_ACCEPT\"     : \"Aceptar\",\n", ""),
                ],
                1,
                "- Found 1 error in es.json\n  - GENERAL_ACCEPT is missing, and is in en.json\n",
            ],
            "an extra key" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(
                        self::LangFile,
                        "\"GENERAL_ACCEPT\"     : \"Aceptar\",",
                        "\"GENERAL_ACCEPT\"     : \"Aceptar\",\n    \"GENERAL_CANCEL\"     : \"Cancelar\",",
                    ),
                ],
                1,
                "- Found 1 error in es.json\n  - GENERAL_CANCEL is not in en.json\n",
            ],
            // The one file that is behind is the one it is reported for, and the
            // ones that agree are named together
            "three files" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, "\"GENERAL_ACCEPT\"     : \"Aceptar\",\n", ""),
                    "pt.json" => Strings::replace(self::LangFile, "Español", "Português"),
                ],
                1,
                "- Found 1 error in es.json\n  - GENERAL_ACCEPT is missing, and is in en.json and pt.json\n",
            ],
            // One blank line more moves the four keys below it
            "a shifted file" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, "\"Español\",\n", "\"Español\",\n\n"),
                ],
                4,
                "- Found 4 errors in es.json\n  - GENERAL_ACCEPT is at line 5, and at line 4 in en.json\n",
            ],
            "a missing option" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, ", \"1\" : \"Sí\"", ""),
                ],
                1,
                "- Found 1 error in es.json\n  - the 1 key of SELECT_YES_NO is missing, and is in en.json\n",
            ],
            "an extra option" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, "\"1\" : \"Sí\"", "\"1\" : \"Sí\", \"2\" : \"Tal vez\""),
                ],
                1,
                "- Found 1 error in es.json\n  - the 2 key of SELECT_YES_NO is not in en.json\n",
            ],
            "a select as a string" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, "{ \"0\" : \"No\", \"1\" : \"Sí\" }", "\"Sí\""),
                ],
                1,
                "- Found 1 error in es.json\n  - SELECT_YES_NO is a string, and a Select in en.json\n",
            ],
            "a shorter list" => [
                [
                    "en.json" => self::RootFile,
                    "es.json" => Strings::replace(self::LangFile, ", \"Lunes\"", ""),
                ],
                1,
                "- Found 1 error in es.json\n  - the 1 key of DATE_TIME_DAYS is missing, and is in en.json\n",
            ],
            // Nothing else is said about it, since it holds no key to compare
            "a broken file" => [
                [ "en.json" => self::RootFile, "es.json" => "{ \"NAME\" : }" ],
                1,
                "- Found 1 error in es.json\n  - the file is empty, or could not be read\n",
            ],
            "an empty file" => [
                [ "en.json" => self::RootFile, "es.json" => "" ],
                1,
                "- Found 1 error in es.json\n  - the file is empty, or could not be read\n",
            ],
        ];
    }



    /**
     * The files of blocks, which are compared by their order
     * @param array<string,string> $files
     * @param int                  $errors
     * @param string               $message
     * @return void
     */
    #[DataProvider("providerCheckOrder")]
    public function testTheOrderIsCompared(array $files, int $errors, string $message): void {
        [ $found, $output ] = $this->runCheck($files, "Blocks", byLine: false);

        $this->assertSame($errors, $found, $output);
        $this->assertStringContainsString($message, $output);
    }

    /**
     * @return array<string,array{array<string,string>,int,string}>
     */
    public static function providerCheckOrder(): array {
        return [
            "a translation" => [
                [ "en.json" => self::RootBlocks, "es.json" => self::LangBlocks ],
                0,
                "- Compared 2 files, there are no errors",
            ],
            // The lines are left alone here, so a block that takes more of them
            // than its translation is no problem at all
            "a longer message" => [
                [
                    "en.json" => self::RootBlocks,
                    "es.json" => Strings::replace(
                        self::LangBlocks,
                        "\"Hola {{name}}\", \"Su cuenta está lista\"",
                        "\"Hola {{name}}\",\n            \"Su cuenta está lista\"",
                    ),
                ],
                0,
                "- Compared 2 files, there are no errors",
            ],
            // Only the first key out of place is named, since the one that moved
            // pushes every key after it out of place as well
            "a moved block" => [
                [ "en.json" => self::RootBlocks, "es.json" => self::SwappedBlocks ],
                1,
                "- Found 1 error in es.json\n  - position 1 holds Reset, and Welcome in en.json\n",
            ],
            "a missing part" => [
                [
                    "en.json" => self::RootBlocks,
                    "es.json" => Strings::replace(self::LangBlocks, "\"title\"       : \"Su nueva clave\",\n", ""),
                ],
                1,
                "- Found 1 error in es.json\n  - the title key of Reset is missing, and is in en.json\n",
            ],
        ];
    }



    /**
     * The keys of a section that the source never names, which are reported
     * under it once the files themselves agree
     * @return void
     */
    public function testTheUnusedKeysAreShown(): void {
        Storage::writeFile($this->fixtureBase . "/en.json", self::RootFile);
        Storage::writeFile($this->fixtureBase . "/es.json", self::LangFile);
        $read = $this->callPrivateStaticMethod(NLSCheck::class, "readFiles", $this->fixtureBase);

        // Everything but GENERAL_ACCEPT and the days is named by the source
        $usage = [
            "words"    => [
                "DATE_TIME_SCHEDULE" => true,
                "SELECT_YES_NO"      => true,
            ],
            "prefixes" => [],
            "calls"    => [],
            "props"    => [],
        ];

        ob_start();
        /** @var int */
        $errors = $this->callPrivateStaticMethod(
            NLSCheck::class,
            "printSection",
            "Strings",
            $this->fixtureBase,
            $read,
            $usage,
            true,
        );
        $output = Strings::toString(ob_get_clean());

        $this->assertSame(2, $errors, $output);
        $this->assertStringContainsString(
            "- Found 2 keys that no source uses\n  - GENERAL_ACCEPT\n  - DATE_TIME_DAYS\n",
            $output,
        );
    }

    /**
     * The section names the directory it read, as it is written in the config
     * @return void
     */
    public function testTheSectionNamesThePath(): void {
        $files = [ "en.json" => self::RootFile, "es.json" => self::LangFile ];
        [ , $output ] = $this->runCheck($files, "Strings", byLine: true);

        $this->assertStringStartsWith("Strings: " . self::FixtureDir . "\n", $output);
    }

    /**
     * A directory with no files at all is not a section, so nothing is read
     * @return void
     */
    public function testAnEmptyDirIsSkipped(): void {
        $this->assertSame([], $this->callPrivateStaticMethod(
            NLSCheck::class,
            "readFiles",
            $this->fixtureBase,
        ));
    }



    /**
     * The keys of a file, with the line and the keys inside each of them
     * @param string                                            $contents
     * @param array<string,array{line:int,shape:string,keys:list<string>}> $values
     * @return void
     */
    #[DataProvider("providerParseFile")]
    public function testTheFileIsParsed(string $contents, array $values): void {
        $found = $this->callPrivateStaticMethod(NLSCheck::class, "parseFile", $contents);
        $this->assertSame($values, $found);
    }

    /**
     * @return array<string,array{string,array<string,array{line:int,shape:string,keys:list<string>}>}>
     */
    public static function providerParseFile(): array {
        return [
            "one key per line" => [
                self::RootFile,
                [
                    "NAME"               => [ "line" => 2, "shape" => "a string", "keys" => [] ],
                    "GENERAL_ACCEPT"     => [ "line" => 4, "shape" => "a string", "keys" => [] ],
                    "DATE_TIME_SCHEDULE" => [ "line" => 5, "shape" => "a string", "keys" => [] ],
                    "SELECT_YES_NO"      => [
                        "line"  => 6,
                        "shape" => "a Select",
                        "keys"  => [ "0", "1" ],
                    ],
                    "DATE_TIME_DAYS"     => [
                        "line"  => 7,
                        "shape" => "a Select",
                        "keys"  => [ "0", "1" ],
                    ],
                ],
            ],
            // The options of a Select are not keys of the file, wherever they sit
            "a select of its own" => [
                <<<JSON
                {
                    "NAME"          : "English",
                    "SELECT_YES_NO" : {
                        "0" : "No",
                        "1" : "Yes"
                    },
                    "GENERAL_OR"    : "or"
                }
                JSON,
                [
                    "NAME"          => [ "line" => 2, "shape" => "a string", "keys" => [] ],
                    "SELECT_YES_NO" => [
                        "line"  => 3,
                        "shape" => "a Select",
                        "keys"  => [ "0", "1" ],
                    ],
                    "GENERAL_OR"    => [ "line" => 7, "shape" => "a string", "keys" => [] ],
                ],
            ],
            // A value that reads like the structure of the file
            "a value with braces" => [
                <<<JSON
                {
                    "DATE_TIME_SCHEDULE" : "{0}: from {1} to {2}",
                    "GENERAL_QUOTE"      : "he said \\"yes\\": nothing else",
                    "GENERAL_OR"         : "or"
                }
                JSON,
                [
                    "DATE_TIME_SCHEDULE" => [ "line" => 2, "shape" => "a string", "keys" => [] ],
                    "GENERAL_QUOTE"      => [ "line" => 3, "shape" => "a string", "keys" => [] ],
                    "GENERAL_OR"         => [ "line" => 4, "shape" => "a string", "keys" => [] ],
                ],
            ],
            // A script says the same thing with the keys unquoted, a comma after
            // the last entry, comments, and the object wrapped in an export
            "a script" => [
                self::RootScript,
                [
                    "TITLE"          => [ "line" => 4, "shape" => "a string", "keys" => [] ],
                    "LANGUAGES"      => [
                        "line"  => 5,
                        "shape" => "a Select",
                        "keys"  => [ "en", "es" ],
                    ],
                    "GENERAL_ACCEPT" => [ "line" => 6, "shape" => "a string", "keys" => [] ],
                    "GENERAL_SITE"   => [ "line" => 7, "shape" => "a string", "keys" => [] ],
                    "DATE_TIME_DAYS" => [
                        "line"  => 8,
                        "shape" => "a Select",
                        "keys"  => [ "0", "1" ],
                    ],
                ],
            ],
        ];
    }
}
