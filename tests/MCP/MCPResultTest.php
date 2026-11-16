<?php
namespace Tests\MCP;

use Framework\MCP\MCPResult;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;

/**
 * The MCP Result
 */
class MCPResultTest extends TestCase {

    public function testAnErrorMakesItAnError(): void {
        $result = new MCPResult(error: "wrong");

        $this->assertTrue($result->isError);
        $this->assertSame("wrong", $result->error);
        $this->assertTrue($result->data->isEmpty());
    }

    public function testTheDataIsKept(): void {
        $result = new MCPResult(new Dictionary([ "id" => 5 ]));

        $this->assertFalse($result->isError);
        $this->assertSame([ "id" => 5 ], $result->data->toArray());
    }
}
