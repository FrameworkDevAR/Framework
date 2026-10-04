<?php
namespace Tests\Provider;

use Framework\Provider\Mustache;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Mustache Provider
 */
class MustacheTest extends TestCase {

    public function testTheEngineIsAvailable(): void {
        $this->assertTrue(Mustache::isAvailable());
    }

    /**
     * A template with its data and its partials, and what it renders into
     * @param string               $template
     * @param array<string,mixed>  $data
     * @param array<string,string> $partials
     * @param string               $expected
     * @return void
     */
    #[DataProvider("providerRender")]
    public function testATemplateIsRendered(string $template, array $data, array $partials, string $expected): void {
        $this->assertSame($expected, Mustache::render($template, $data, $partials));
    }

    /**
     * @return array<string,array{string,array<string,mixed>,array<string,string>,string}>
     */
    public static function providerRender(): array {
        return [
            "a variable"          => [ "Hello {{name}}", [ "name" => "Ana" ], [], "Hello Ana" ],
            "one that is escaped" => [ "{{name}}", [ "name" => "<b>" ], [], "&lt;b&gt;" ],
            "one that is not"     => [ "{{{name}}}", [ "name" => "<b>" ], [], "<b>" ],
            "a missing one"       => [ "Hello {{name}}", [], [], "Hello " ],
            "a section"           => [ "{{#on}}Yes{{/on}}", [ "on" => true ], [], "Yes" ],
            "a partial"           => [
                "{{> Greeting}}!",
                [ "name" => "Ana" ],
                [ "Greeting" => "Hello {{name}}" ],
                "Hello Ana!",
            ],
        ];
    }

    /**
     * A template, and whether rendering it gives an error
     * @param string $template
     * @param bool   $hasError
     * @return void
     */
    #[DataProvider("providerGetError")]
    public function testATemplateThatDoesNotRenderGivesAnError(string $template, bool $hasError): void {
        $this->assertSame($hasError, Mustache::getError($template) !== "");
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerGetError(): array {
        return [
            "a template that renders"         => [ "Hello {{name}}", false ],
            "a section that is never closed"  => [ "{{#reason}}Because", true ],
            "a section closed by another one" => [ "{{#a}}x{{/b}}", true ],
        ];
    }
}
