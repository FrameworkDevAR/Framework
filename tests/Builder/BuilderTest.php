<?php
namespace Tests\Builder;

use Framework\Builder\Builder;
use Framework\Discovery\Package;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class BuilderTest extends TestCase {
    use TestHelpers;

    private mixed $originalTemplates = null;


    protected function setUp(): void {
        $this->originalTemplates = $this->getPrivateStaticProperty(Builder::class, "templates");
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Builder::class, "templates", $this->originalTemplates);
    }

    /**
     * Captures whatever the given callback prints
     */
    private function captureOutput(callable $callback): string {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }


    public function testLoadTemplatesReadsTheMustacheFiles(): void {
        $this->setPrivateStaticProperty(Builder::class, "templates", []);

        $method = new ReflectionMethod(Builder::class, "loadTemplates");
        $method->invoke(null);

        $templates = $this->getPrivateStaticProperty(Builder::class, "templates");
        $this->assertNotEmpty($templates);

        // The templates are keyed by file name, without the .mu extension
        $this->assertArrayHasKey("Language", $templates);
        $this->assertArrayNotHasKey("Language.mu", $templates);
    }


    public function testIsBuildingWhileTheFileIsThere(): void {
        $filePath = Package::getBasePath(".building");
        try {
            $this->assertFalse(Builder::isBuilding());

            touch($filePath);
            $this->assertTrue(Builder::isBuilding());

            // A file left behind by a build that failed stops counting after a while
            touch($filePath, time() - 600);
            $this->assertFalse(Builder::isBuilding());
        } finally {
            @unlink($filePath);
        }
    }


    #[DataProvider("providerGenerateCodeWithoutData")]
    public function testGenerateCodeWithoutData(string $name, string $expected): void {
        $result = 1;
        $output = $this->captureOutput(function () use ($name, &$result): void {
            $result = Builder::generateCode($name);
        });

        // Without data nothing is written, it just reports the skip
        $this->assertSame(0, $result);
        $this->assertSame($expected, $output);
    }

    public static function providerGenerateCodeWithoutData(): array {
        return [
            "named"      => [ "Thing", "- Skipping the Thing code\n" ],
            "other name" => [ "Router", "- Skipping the Router code\n" ],
            "empty name" => [ "", "- Skipping the  code\n" ],
        ];
    }


    public function testGenerateCodeWritesTheFile(): void {
        // A name that is not a real generated class, so nothing existing is overwritten
        $name     = "TestBuilderOutput";
        $filePath = Package::getBuildPath() . DIRECTORY_SEPARATOR . "$name.php";

        $this->setPrivateStaticProperty(Builder::class, "templates", [
            $name => "namespace {{namespace}}; // {{label}}",
        ]);

        try {
            $result = 0;
            $output = $this->captureOutput(function () use ($name, &$result): void {
                $result = Builder::generateCode($name, [ "label" => "hello", "total" => 2 ]);
            });

            $this->assertSame(1, $result);
            $this->assertSame("- $name code -> 2 items\n", $output);

            // The namespace is injected into the data before rendering
            $this->assertFileExists($filePath);
            $this->assertSame(
                "namespace " . Package::Namespace . Package::SystemDir . "; // hello",
                (string)file_get_contents($filePath),
            );
        } finally {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }


    #[DataProvider("providerPrintResult")]
    public function testPrintResult(string $name, array $data, string $expected): void {
        $output = $this->captureOutput(function () use ($name, $data): void {
            Builder::printResult($name, $data);
        });
        $this->assertSame($expected, $output);
    }

    public static function providerPrintResult(): array {
        return [
            "singular"       => [ "Thing", [ "total" => 1 ], "- Thing code -> 1 item\n" ],
            "plural"         => [ "Thing", [ "total" => 3 ], "- Thing code -> 3 items\n" ],
            "zero is plural" => [ "Thing", [ "total" => 0 ], "- Thing code -> 0 items\n" ],
            "without total"  => [ "Thing", [], "- Thing code \n" ],
            "non int total"  => [ "Thing", [ "total" => "3" ], "- Thing code \n" ],
        ];
    }


    #[DataProvider("providerRenderUnknownTemplate")]
    public function testRenderUnknownTemplate(string $name): void {
        $this->setPrivateStaticProperty(Builder::class, "templates", [ "Known" => "hello" ]);
        $this->assertSame("", Builder::render($name, []));
    }

    public static function providerRenderUnknownTemplate(): array {
        return [
            "missing name" => [ "Unknown" ],
            "empty name"   => [ "" ],
            "wrong case"   => [ "known" ],
        ];
    }


    #[DataProvider("providerRender")]
    public function testRender(string $template, array $data, string $expected): void {
        $this->setPrivateStaticProperty(Builder::class, "templates", [ "Tpl" => $template ]);
        $this->assertSame($expected, Builder::render("Tpl", $data));
    }

    public static function providerRender(): array {
        return [
            "substitution"           => [
                "Hello {{name}}!",
                [ "name" => "World" ],
                "Hello World!",
            ],
            "missing value"          => [
                "Hello {{name}}!",
                [],
                "Hello !",
            ],
            "list section"           => [
                "{{#items}}[{{v}}]{{/items}}",
                [ "items" => [ [ "v" => 1 ], [ "v" => 2 ] ] ],
                "[1][2]",
            ],
            // The rendered output also gets its doc params aligned
            "aligns rendered params" => [
                "    /**\n     * @param {{type}} \$name\n     * @param int \$id\n     */",
                [ "type" => "string" ],
                "    /**\n     * @param string \$name\n     * @param int    \$id\n     */",
            ],
            // An empty param list collapses onto a single line
            "collapses empty params" => [
                "public function foo(\n    ): void {}",
                [],
                "public function foo(): void {}",
            ],
        ];
    }


    #[DataProvider("providerAlignParams")]
    public function testAlignParams(string $contents, string $expected): void {
        $this->assertSame($expected, Builder::alignParams($contents));
    }

    public static function providerAlignParams(): array {
        return [
            "aligns the types"          => [
                "    /**\n"
                    . "     * @param string \$name\n"
                    . "     * @param int \$credentialID\n"
                    . "     */",
                "    /**\n"
                    . "     * @param string \$name\n"
                    . "     * @param int    \$credentialID\n"
                    . "     */",
            ],
            "aligns optional names"     => [
                "    /**\n"
                    . "     * @param string \$name\n"
                    . "     * @param bool \$forFramework Optional.\n"
                    . "     * @param int \$id Optional.\n"
                    . "     */",
                "    /**\n"
                    . "     * @param string \$name\n"
                    . "     * @param bool   \$forFramework Optional.\n"
                    . "     * @param int    \$id           Optional.\n"
                    . "     */",
            ],
            // A type as short as Op is also the start of the Optional. beside it, and
            // the padding used to reach into the word and take it apart
            "a type inside a word"      => [
                "    /**\n"
                    . "     * @param string \$name\n"
                    . "     * @param Op \$operator Optional.\n"
                    . "     */",
                "    /**\n"
                    . "     * @param string \$name\n"
                    . "     * @param Op     \$operator Optional.\n"
                    . "     */",
            ],
            "single param is untouched" => [
                "    /**\n     * @param string \$name\n     */",
                "    /**\n     * @param string \$name\n     */",
            ],
            "without params"            => [
                "    /**\n     * Just text\n     * @return void\n     */",
                "    /**\n     * Just text\n     * @return void\n     */",
            ],
            "plain text"                => [ "hello\nworld", "hello\nworld" ],
            "empty"                     => [ "", "" ],
        ];
    }
}
