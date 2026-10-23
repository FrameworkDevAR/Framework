<?php
namespace Tests\Framework;

use Framework\Application;
use Framework\Console;
use Framework\Discovery\Attr\ConsoleCommand;
use Framework\Discovery\Package;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;
use ReflectionProperty;

/**
 * The Console, which finds the commands and hands the arguments to one
 *
 * Only names that are safe to run are given to it. Every other command here
 * builds, migrates, rewrites the version or releases, and a test run has no
 * business doing any of that.
 */
class ConsoleTest extends TestCase {
    use TestHelpers;

    /** @var list<ConsoleCommand>|null */
    private static ?array $commands = null;


    /**
     * Returns every Command the Console finds, walked once for the whole class
     * @return list<ConsoleCommand>
     */
    private function commands(): array {
        if (self::$commands === null) {
            /** @var list<ConsoleCommand> */
            $found = (new ReflectionMethod(Console::class, "getCommands"))->invoke(null);
            self::$commands = $found;
        }
        return self::$commands;
    }

    /**
     * Returns the names of every Command, without their aliases
     * @return list<string>
     */
    private function names(): array {
        $result = [];
        foreach ($this->commands() as $command) {
            $result[] = $command->name;
        }
        return $result;
    }

    /**
     * Runs the Console with the given arguments, and returns what it printed
     * @param string ...$args
     * @return string
     */
    private function runWith(string ...$args): string {
        $was = $_SERVER["argv"] ?? null;
        $_SERVER["argv"] = array_merge([ "framework" ], $args);

        ob_start();
        Console::run();
        $output = ob_get_clean();

        if ($was === null) {
            unset($_SERVER["argv"]);
        } else {
            $_SERVER["argv"] = $was;
        }
        return $output === false ? "" : $output;
    }



    public function testTheCommandsAreFound(): void {
        $commands = $this->commands();

        $this->assertGreaterThan(10, count($commands));
        $this->assertSame(count($commands), count(array_unique($this->names())));
    }

    public function testEveryCommandCanBeInvoked(): void {
        // A command without its handler would report itself and then do nothing
        foreach ($this->commands() as $command) {
            $handler = (new ReflectionProperty($command, "handler"))->getValue($command);

            $this->assertNotNull($handler, "{$command->name} has no handler");
            $this->assertNotSame("", $command->name);
        }
    }

    public function testTheCommandsComeBackInPriorityOrder(): void {
        // version is Highest and install is High, so they lead in that order
        $names = $this->names();

        $this->assertSame("version", $names[0]);
        $this->assertSame("install", $names[1]);
    }

    /**
     * A command the Console is expected to offer
     * @param string $name
     * @param bool   $isPrivate
     * @return void
     */
    #[DataProvider("providerCommands")]
    public function testTheCommandIsOffered(string $name, bool $isPrivate): void {
        foreach ($this->commands() as $command) {
            if ($command->name !== $name) {
                continue;
            }
            $this->assertSame($isPrivate, $command->isPrivate);
            return;
        }
        $this->fail("$name is not one of the commands");
    }

    /**
     * The private ones are only offered inside the Framework, which is where
     * the tests run, so all of them are here
     * @return array<string,array{string,bool}>
     */
    public static function providerCommands(): array {
        return [
            "version"    => [ "version", false ],
            "install"    => [ "install", false ],
            "build"      => [ "build", false ],
            "migrate"    => [ "migrate", false ],
            "docs"       => [ "docs", true ],
            "docsCheck"  => [ "docsCheck", true ],
            "release"    => [ "release", true ],
            "incVersion" => [ "incVersion", true ],
        ];
    }



    /**
     * A name that matches nothing, which leaves the Console listing what it has
     * @param string $name
     * @return void
     */
    #[DataProvider("providerUnmatched")]
    public function testAnUnmatchedNameListsTheCommands(string $name): void {
        $output = $name === "" ? $this->runWith() : $this->runWith($name);

        $this->assertStringContainsString("Available commands:", $output);
        $this->assertStringContainsString(" - version (-v)", $output);
        $this->assertStringContainsString(" - release --patch --push", $output);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerUnmatched(): array {
        return [
            "nothing given"   => [ "" ],
            "an unknown name" => [ "notACommand" ],
            "half a name"     => [ "vers" ],
            "another case"    => [ "Version" ],
        ];
    }

    /**
     * The one command that is safe to run, by its name and by its alias
     * @param string $given
     * @return void
     */
    #[DataProvider("providerVersion")]
    public function testACommandThatMatchesIsRun(string $given): void {
        $output = $this->runWith($given);

        $this->assertStringContainsString("Version: " . Application::getVersion(), $output);
        $this->assertStringContainsString("Done!", $output);
        $this->assertStringNotContainsString("Available commands:", $output);
    }

    /**
     * The quiet option leaves only what the command prints
     * @return void
     */
    public function testTheQuietOptionHidesTheLogoAndTheDone(): void {
        $output = $this->runWith("version", "--quiet");

        $this->assertStringContainsString("Version: " . Application::getVersion(), $output);
        $this->assertStringNotContainsString("Done!", $output);
        $this->assertStringNotContainsString("|_|", $output);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerVersion(): array {
        return [
            "the name"  => [ "version" ],
            "the alias" => [ "-v" ],
        ];
    }

    /**
     * What the chooser makes of the response, against the Options it listed
     * @param string $response
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerFindOption")]
    public function testFindOption(string $response, string $expected): void {
        $options = [ "dashboard", "editor" ];
        $result  = $this->callPrivateStaticMethod(Console::class, "findOption", $options, $response);

        $this->assertSame($expected, $result);
    }

    /**
     * The list is numbered from one, and a name is answered with the option itself
     * @return array<string,array{string,string}>
     */
    public static function providerFindOption(): array {
        return [
            "the first"       => [ "1", "dashboard" ],
            "the second"      => [ "2", "editor" ],
            "past the last"   => [ "3", "" ],
            "below the first" => [ "0", "" ],
            "not a number"    => [ "-1", "" ],
            "a name"          => [ "editor", "editor" ],
            "another case"    => [ "Editor", "editor" ],
            "one off the list" => [ "admin", "" ],
            "nothing at all"  => [ "", "" ],
        ];
    }

    public function testTheLogoIsPrintedWithTheVersion(): void {
        $output = $this->runWith("notACommand");

        $this->assertStringContainsString(Application::getVersion(), $output);
    }



    /**
     * A path the editor is not asked to open
     *
     * Only paths it refuses before reaching the editor belong here. A file
     * that is there would be opened for real when the tests are run from
     * inside VS Code, which is where they usually are.
     * @param string $filePath
     * @return void
     */
    #[DataProvider("providerNotOpened")]
    public function testTheEditorIsNotAskedToOpenNothing(string $filePath): void {
        $this->assertFalse(Console::openFile($filePath));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerNotOpened(): array {
        return [
            "nothing given"    => [ "" ],
            "one that is gone" => [ "/tmp/notAFile.php" ],
            "a folder that is" => [ "/tmp/notADirectory" ],
        ];
    }

    public function testThePrivateCommandsAreOnlyOfferedInsideTheFramework(): void {
        /** @var string */
        $was = $this->getPrivateStaticProperty(Application::class, "baseDir");
        $this->setPrivateStaticProperty(Application::class, "baseDir", Package::DocsDir);

        try {
            $this->assertFalse(Package::isFramework());

            /** @var list<ConsoleCommand> */
            $commands = (new ReflectionMethod(Console::class, "getCommands"))->invoke(null);
            $this->assertNotEmpty($commands);

            foreach ($commands as $command) {
                $this->assertFalse($command->isPrivate, "{$command->name} should not be offered");
            }
        } finally {
            $this->setPrivateStaticProperty(Application::class, "baseDir", $was);
        }
    }
}
