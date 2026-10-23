<?php
namespace Framework\Builder;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\DiscoveryConfig;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Attr\ConsoleCommand;
use Framework\File\Storage;
use Framework\Provider\Mustache;
use Framework\Date\Date;
use Framework\Date\Timer;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Builder
 */
class Builder {

    private const BuildingFile = ".building";
    private const BuildingTime = 120;

    /** @var array<string,string> */
    private static array $templates = [];


    /**
     * Builds all the Code
     * @return void
     */
    #[ConsoleCommand("build")]
    #[NotTested("It generates a file")]
    public static function build(): void {
        // Everything is rendered through Mustache, and the build empties the
        // generated directory before it starts, so stop before it does
        if (!Mustache::isAvailable()) {
            print("The build needs mustache/mustache, run: composer require mustache/mustache\n");
            return;
        }

        $timer = new Timer();
        print("Building the Code...\n");

        DiscoveryConfig::load();
        self::loadTemplates();
        $writePath = Package::getBuildPath();
        $created   = 0;

        // The requests that arrive while the code is generated fail with classes that are
        // not there yet, so the file tells the Error Log that those errors are not real
        Storage::createFile(Package::getBasePath(), self::BuildingFile, "");
        Storage::createDir($writePath);
        Storage::emptyDir($writePath);


        // Find all the Builders in the Framework and App
        $classes = Discovery::findClasses(
            interface:    DiscoveryBuilder::class,
            forAll:       !Package::isFramework(),
            forFramework: true,
        );

        // Execute each Builder
        foreach ($classes as $class) {
            $instance = $class->newInstance();
            if ($instance instanceof DiscoveryBuilder) {
                $created += $instance::generateCode();
            }
        }


        Storage::deleteFile(Package::getBasePath(), self::BuildingFile);

        // Calculate and show the time taken
        $time = $timer->getElapsedText();
        print("\nGenerated $created files in $time\n");
    }

    /**
     * Returns true while the Code is being built
     * @return bool
     */
    public static function isBuilding(): bool {
        // A build that failed leaves the file behind, so it only counts for some time
        $time = Storage::getModifiedTime(Package::getBasePath(self::BuildingFile));
        return $time > 0 && Date::now()->toTime() - $time < self::BuildingTime;
    }

    /**
     * Destroys all the Code
     * @return void
     */
    #[ConsoleCommand("destroy")]
    #[NotTested("It generates a file")]
    public static function destroy(): void {
        $writePath = Package::getBuildPath();
        $deleted   = 0;

        // Find all the Builders in the Framework and App
        $classes = Discovery::findClasses(
            interface:    DiscoveryBuilder::class,
            forAll:       !Package::isFramework(),
            forFramework: true,
        );
        $classes = array_reverse($classes);

        // Execute each Destroyer
        foreach ($classes as $class) {
            $instance = $class->newInstance();
            if ($instance instanceof DiscoveryBuilder) {
                $deleted += $instance::destroyCode();
            }
        }

        Storage::deleteDir($writePath, $deleted);

        print("\nDestroyed $deleted generated files\n\n");
    }



    /**
     * Loads all the Templates
     * @return void
     */
    private static function loadTemplates(): void {
        $path      = Package::getBasePath();
        $filePaths = Storage::getFilesInDir($path, recursive: true, skipVendor: true);

        foreach ($filePaths as $filePath) {
            if (Strings::endsWith($filePath, ".mu")) {
                $fileName = Storage::getBaseName(Storage::getFileName($filePath));
                self::$templates[$fileName] = Storage::readFile($filePath);
            }
        }
    }

    /**
     * Generates a single System Code
     * @param string              $name
     * @param array<string,mixed> $data Optional.
     * @return int
     */
    public static function generateCode(string $name, array $data = []): int {
        if (Arrays::isEmpty($data)) {
            print("- Skipping the $name code\n");
            return 0;
        }

        $writePath = Package::getBuildPath();
        $contents  = self::render($name, $data + [
            "namespace" => Package::Namespace . Package::SystemDir,
        ]);
        Storage::createFile($writePath, "$name.php", $contents);

        self::printResult($name, $data);
        return 1;
    }

    /**
     * Prints the result of a generated code
     * @param string              $name
     * @param array<string,mixed> $data Optional.
     * @return void
     */
    public static function printResult(string $name, array $data = []): void {
        $total = "";
        if (isset($data["total"]) && is_int($data["total"])) {
            $plural = $data["total"] !== 1 ? "s" : "";
            $total  = "-> {$data["total"]} item$plural";
        }
        print("- $name code $total\n");
    }

    /**
     * Renders a template with the given data
     * @param string              $name
     * @param array<string,mixed> $data
     * @return string
     */
    public static function render(string $name, array $data): string {
        if (!isset(self::$templates[$name])) {
            return "";
        }

        $content = self::$templates[$name];
        $result  = Mustache::render($content, $data);
        $result  = self::alignParams($result);
        $result  = Strings::replace($result, "(\n    ): ", "(): ");
        return $result;
    }

    /**
     * Aligns the Params of the given content
     * @param string $contents
     * @return string
     */
    public static function alignParams(string $contents): string {
        $lines      = Strings::split($contents, "\n");
        $typeLength = 0;
        $varLength  = 0;
        $result     = [];

        foreach ($lines as $index => $line) {
            if (Strings::contains($line, "/**")) {
                [ $typeLength, $varLength ] = self::getLongestParam($lines, $index);
            } elseif (Strings::contains($line, "@param")) {
                $docType    = Strings::substringBetween($line, "@param ", " ");
                $docTypePad = Strings::padRight(" $docType", $typeLength + 1);
                $line       = Strings::replace($line, "@param $docType", "@param$docTypePad");

                if (Strings::contains($line, "Optional.")) {
                    $varName    = Strings::substringBetween($line, "$docTypePad ", " Optional.");
                    $varNamePad = Strings::padRight($varName, $varLength);
                    $line       = Strings::replace($line, $varName, $varNamePad);
                }
            }
            $result[] = $line;
        }
        return Strings::join($result, "\n");
    }

    /**
     * Returns the longest Param and Type of the current Doc comment
     * @param list<string> $lines
     * @param int          $index
     * @return array{int,int}
     */
    private static function getLongestParam(array $lines, int $index): array {
        $line       = $lines[$index] ?? "";
        $typeLength = 0;
        $varLength  = 0;

        while (!Strings::contains($line, "*/")) {
            if (Strings::contains($line, "@param")) {
                $docType    = Strings::substringBetween($line, "@param ", " ");
                $typeLength = max($typeLength, Strings::length($docType));
                $varName    = Strings::substringBetween($line, "$docType ", " Optional.");
                $varLength  = max($varLength, Strings::length($varName));
            }
            $index += 1;
            $line   = $lines[$index] ?? "";
        }
        return [ $typeLength, $varLength ];
    }
}
