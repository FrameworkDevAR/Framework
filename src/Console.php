<?php
namespace Framework;

use Framework\Discovery\Discovery;
use Framework\Discovery\Package;
use Framework\Discovery\Attr\Priority;
use Framework\Discovery\Attr\ConsoleCommand;
use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Framework Console
 */
class Console {

    /**
     * Runs the Console
     * @return void
     */
    public static function run(): void {
        $argv = is_array($_SERVER["argv"] ?? null) ? $_SERVER["argv"] : [];
        $name = Strings::toString($argv[1] ?? "");
        $args = Arrays::toStrings(array_slice($argv, 2));

        // The quiet option is for a script that reads the output, so it only gets what the
        // command prints, without the Logo and the Done
        $isQuiet = Arrays::contains($args, "--quiet");
        if ($isQuiet) {
            $args = Arrays::removeValue($args, "--quiet");
        } else {
            self::printLogo();
        }

        // Try to execute one of the commands
        $commands = self::getCommands();
        foreach ($commands as $command) {
            if ($command->shouldInvoke($name)) {
                if ($command->invoke($args)) {
                    if (!$isQuiet) {
                        echo "\033[32m" . "\nDone!\n" . "\033[0m";
                    }
                } else {
                    print("Invalid arguments\n");
                    print("  Usage: {$command->getName()} {$command->getArguments()}\n");
                }
                return;
            }
        }

        // Show the usage since no command matched
        print("Available commands: \n");
        foreach ($commands as $command) {
            print(" - {$command->getName()} {$command->getArguments()}\n");
        }
    }

    /**
     * Installs the Framework
     * @return void
     */
    #[ConsoleCommand("install")]
    #[Priority(Priority::High)]
    public static function install(): void {
        print("Installing the Framework...\n\n");
        $framePath = dirname(__DIR__);
        $appPath   = getcwd();

        if (self::confirm("- Install Framework console command?")) {
            $fromPath = "$framePath/framework";
            $toPath   = "$appPath/framework";
            Storage::copyFile($fromPath, $toPath);
            chmod($toPath, 0755);
        }

        if (self::confirm("- Install phpcs file?")) {
            $fromPath = "$framePath/phpcs.xml";
            $toPath   = "$appPath/phpcs.xml";
            Storage::copyFile($fromPath, $toPath);
        }

        print("\nInstallation completed.\n");
    }


    /**
     * Returns all the Console Commands
     * @return list<ConsoleCommand>
     */
    private static function getCommands(): array {
        $classes     = Discovery::findClasses(forAll: true);
        $isFramework = Package::isFramework();
        $priorities  = [];
        $instances   = [];
        $result      = [];

        foreach ($classes as $class) {
            $methods = $class->getMethods();
            foreach ($methods as $method) {
                $attributes = $method->getAttributes(ConsoleCommand::class);
                if (!$method->isPublic() || !isset($attributes[0])) {
                    continue;
                }

                // Private Commands are only available inside the Framework
                $instance = $attributes[0]->newInstance();
                if ($instance->isPrivate && !$isFramework) {
                    continue;
                }

                $priority = Discovery::getPriority($method);
                if (!isset($instances[$priority])) {
                    $priorities[] = $priority;
                }
                $instances[$priority][] = $instance->setHandler($method);
            }
        }
        sort($priorities);

        foreach ($priorities as $priority) {
            if (isset($instances[$priority])) {
                foreach ($instances[$priority] as $instance) {
                    $result[] = $instance;
                }
            }
        }
        return $result;
    }

    /**
     * Prints the Framework Logo
     * @return void
     */
    private static function printLogo(): void {
        $version = Package::getVersion();

        echo "\033[33m";
        print(" ______                                           _     \n");
        print("|  ____|                                         | |    \n");
        print("| |__ _ __ __ _ _ __ ___   _____      _____  _ __| | __ \n");
        print("|  __| '__/ _` | '_ ` _ \ / _ \ \ /\ / / _ \| '__| |/ / \n");
        print("| |  | | | (_| | | | | | |  __/\ V  V / (_) | |  |   <  \n");
        print("|_|  |_|  \__,_|_| |_| |_|\___| \_/\_/ \___/|_|  |_|\_\ \n");
        print("$version\n\n");
        echo "\033[0m";
    }

    /**
     * Prompts the user in the console
     * @param string $prompt
     * @return string
     */
    public static function prompt(string $prompt): string {
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            return "";
        }

        print("$prompt: ");
        $line = fgets($handle);
        fclose($handle);

        $result = "";
        if (is_string($line)) {
            $result = rtrim($line, "\r\n");
        }
        return $result;
    }



    /**
     * Asks the user to choose one of the given Options, listing them numbered
     * @param string       $prompt
     * @param list<string> $options
     * @return string
     */
    public static function choose(string $prompt, array $options): string {
        if (count($options) === 0) {
            return "";
        }

        foreach ($options as $index => $option) {
            $number = $index + 1;
            print("  $number. $option\n");
        }

        $response = Strings::trim(self::prompt($prompt));
        return self::findOption($options, $response);
    }

    /**
     * Returns the Option the given response asks for, by its number or its name
     * @param list<string> $options
     * @param string       $response
     * @return string
     */
    private static function findOption(array $options, string $response): string {
        // The list is numbered from one, and a number outside it picks nothing
        if (ctype_digit($response)) {
            return $options[(int)$response - 1] ?? "";
        }

        // A name is answered with the option as the list spells it
        foreach ($options as $option) {
            if (Strings::isEqual($option, $response)) {
                return $option;
            }
        }
        return "";
    }

    /**
     * Opens the given File in the editor, when running inside VS Code
     * @param string $filePath
     * @return bool
     */
    public static function openFile(string $filePath): bool {
        // A path that is not there would open an empty buffer named after it,
        // rather than nothing at all
        if ($filePath === "" || !Storage::fileExists($filePath)) {
            return false;
        }
        if (getenv("TERM_PROGRAM") !== "vscode") {
            return false;
        }

        // Run it in the background, so the console does not wait for the editor
        $command = "code " . escapeshellarg($filePath);
        if (PHP_OS_FAMILY !== "Windows") {
            $command .= " > /dev/null 2>&1 &";
        }

        exec($command, result_code: $resultCode);
        return $resultCode === 0;
    }

    /**
     * Prompts the user for confirmation in the console
     * @param string $prompt
     * @return bool
     */
    public static function confirm(string $prompt): bool {
        $response = self::prompt("$prompt (y/n)");
        $response = Strings::toLowerCase($response);
        return $response === "y" || $response === "yes";
    }
}
