<?php
namespace Tests\Provider\Type;

use Framework\Provider\Type\OpenAIOutput;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The OpenAI Output
 */
class OpenAIOutputTest extends TestCase {

    /**
     * @param list<array<string,mixed>> $output
     */
    #[DataProvider("providerSearchCalls")]
    public function testTheSearchesAreTheOnesTheResponseRan(
        array $output,
        int $webSearches,
        int $fileSearches,
    ): void {
        $result = new OpenAIOutput(new Dictionary());
        $result->setSearchCalls(new Dictionary([ "output" => $output ]));

        $this->assertSame($webSearches, $result->webSearchCalls);
        $this->assertSame($fileSearches, $result->fileSearchCalls);
    }

    /**
     * @return array<string,array{list<array<string,mixed>>,int,int}>
     */
    public static function providerSearchCalls(): array {
        $message = [ "type" => "message", "content" => [[ "text" => "Hi" ]] ];
        $web     = [ "type" => "web_search_call", "status" => "completed" ];
        $file    = [ "type" => "file_search_call", "status" => "completed" ];
        $call    = [ "type" => "function_call", "name" => "save" ];

        return [
            "only a message"     => [ [ $message ], 0, 0 ],
            "a web search"       => [ [ $web, $message ], 1, 0 ],
            "a file search"      => [ [ $file, $message ], 0, 1 ],
            "several searches"   => [ [ $web, $file, $web, $message ], 2, 1 ],
            "a function call"    => [ [ $call ], 0, 0 ],
            "no output"          => [ [], 0, 0 ],
        ];
    }

    public function testTheSearchesAreCountedAgainForEachResponse(): void {
        $result = new OpenAIOutput(new Dictionary());
        $result->setSearchCalls(new Dictionary([ "output" => [[ "type" => "web_search_call" ]] ]));
        $result->setSearchCalls(new Dictionary([ "output" => [[ "type" => "message" ]] ]));

        $this->assertSame(0, $result->webSearchCalls);
        $this->assertSame(0, $result->fileSearchCalls);
    }
}
