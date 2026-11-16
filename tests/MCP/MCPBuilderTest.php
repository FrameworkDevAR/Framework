<?php
namespace Tests\MCP;

use Framework\MCP\MCPBuilder;
use Framework\MCP\Attr\MCPTool;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\IO\Request;
use Framework\System\Access;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TestMCPRequest {
    public string $value = "";
}

class TestMCPTools {
    #[MCPTool(Access::API, "A Tool without params", name: "plain")]
    public static function plain(): void {
    }

    #[MCPTool(Access::Admin, "A Tool with the Request", name: "with_request")]
    public static function withRequest(Request $request): void {
    }

    #[MCPTool(Access::API, "A Tool with a Schema Request", name: "with-custom")]
    public static function withCustom(TestMCPRequest $request): void {
    }

    #[MCPTool(Access::API, "A Tool with params of its own", name: "typed")]
    public static function typed(int $taskID): void {
    }

    #[MCPTool(Access::API, "A Tool with the name of its Method")]
    public static function getFiles(): void {
    }

    public static function helper(): void {
    }
}

class TestMCPSkipped {
    #[MCPTool(Access::API, "A name with a space", name: "not valid")]
    public static function badName(): void {
    }

    #[MCPTool(Access::API, "A name that is already taken", name: "plain")]
    public static function repeated(): void {
    }

    #[MCPTool(Access::API, "A param that can not be read", name: "no_getter")]
    public static function noGetter(object $value): void {
    }

    #[MCPTool(Access::API, name: "no_description")]
    public static function noDescription(): void {
    }
}


class MCPBuilderTest extends TestCase {

    /**
     * Builds the DiscoveryClasses for the given class names
     * @param list<class-string> $classNames
     * @return list<DiscoveryClass>
     */
    private static function toClasses(array $classNames): array {
        $result = [];
        foreach ($classNames as $className) {
            $result[] = new DiscoveryClass($className);
        }
        return $result;
    }

    /**
     * Returns the data of a Tool as the builder gives it
     * @param string $method
     * @param string $name
     * @param string $access
     * @param string $requestParam Optional.
     * @param bool   $addSpace     Optional.
     * @return array<string,mixed>
     */
    private static function tool(
        string $method,
        string $name,
        string $access,
        string $requestParam = "",
        bool $addSpace = false,
    ): array {
        return [
            "className"    => "\\Tests\\MCP\\TestMCPTools",
            "method"       => $method,
            "requestParam" => $requestParam,
            "name"         => str_pad("\"$name\"", 26),
            "access"       => $access,
            "addSpace"     => $addSpace,
        ];
    }


    #[DataProvider("providerCollectTools")]
    public function testCollectTools(array $classNames, array $expected): void {
        $result = MCPBuilder::collectTools(self::toClasses($classNames));

        $this->assertSame($expected, $result["tools"]);
        $this->assertSame(count($expected), $result["total"]);
        $this->assertSame(count($expected) > 0, $result["hasTools"]);
    }

    public static function providerCollectTools(): array {
        $custom = "\\Tests\\MCP\\TestMCPRequest::fromRequest(\$request)";
        $tools  = [
            self::tool("plain", "plain", "API"),
            self::tool("withRequest", "with_request", "Admin", "\$request"),
            self::tool("withCustom", "with-custom", "API", $custom),
            self::tool("typed", "typed", "API", "taskID: \$request->getInt(\"taskID\")"),
            self::tool("getFiles", "test_mcp_tools_get_files", "API"),
        ];

        return [
            "no classes"                    => [ [], [] ],
            "collects and aligns the tools" => [ [ TestMCPTools::class ], $tools ],
            "skips the invalid ones"        => [
                [ TestMCPTools::class, TestMCPSkipped::class ],
                [ ...array_slice($tools, 0, 4), [ ...$tools[4], "addSpace" => true ] ],
            ],
        ];
    }


    #[DataProvider("providerGetToolName")]
    public function testGetToolName(string $className, string $methodName, string $expected): void {
        $this->assertSame($expected, MCPBuilder::getToolName($className, $methodName));
    }

    public static function providerGetToolName(): array {
        return [
            "an MCP class"    => [ "App\\Task\\TaskMCP", "search", "task_search" ],
            "another class"   => [ "App\\User\\UserRole", "getDefaults", "user_role_get_defaults" ],
            "without a space" => [ "TicketMCP", "getOne", "ticket_get_one" ],
        ];
    }


    #[DataProvider("providerGetToolTitle")]
    public function testGetToolTitle(string $className, string $methodName, string $expected): void {
        $this->assertSame($expected, MCPBuilder::getToolTitle($className, $methodName));
    }

    public static function providerGetToolTitle(): array {
        return [
            "an MCP class"    => [ "App\\Task\\TaskMCP", "search", "Task: Search" ],
            "another class"   => [ "App\\User\\UserRole", "getDefaults", "User role: Get defaults" ],
            "without a space" => [ "TicketMCP", "getOne", "Ticket: Get one" ],
        ];
    }


    public function testDestroyCode(): void {
        $this->assertSame(1, MCPBuilder::destroyCode());
    }
}
