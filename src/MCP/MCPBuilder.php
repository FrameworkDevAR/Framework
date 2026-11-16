<?php
namespace Framework\MCP;

use Framework\Analysis\Attr\NotTested;
use Framework\Builder\Builder;
use Framework\Discovery\Discovery;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\MCP\Attr\MCPTool;
use Framework\MCP\MCPSignature;
use Framework\Discovery\Attr\Priority;
use Framework\Utils\Strings;

/**
 * The MCP Builder
 * @phpstan-type ToolData array{
 *   className:    string,
 *   method:       string,
 *   requestParam: string,
 *   name:         string,
 *   access:       string,
 *   addSpace:     bool,
 * }
 * @phpstan-type ToolResult array{
 *   hasTools: bool,
 *   tools:    list<ToolData>,
 *   total:    int,
 * }
 */
#[Priority(Priority::Lowest)]
class MCPBuilder implements DiscoveryBuilder {

    private const NamePattern = '/^[a-zA-Z0-9_-]{1,64}$/';



    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $data = self::collectTools(Discovery::findClasses());
        return Builder::generateCode("MCPTools", $data);
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 1;
    }



    /**
     * Returns the name of a Tool from its Class and its Method, like task_search for TaskMCP
     * @param string $className
     * @param string $methodName
     * @return string
     */
    public static function getToolName(string $className, string $methodName): string {
        $prefix = Strings::substringAfter($className, "\\");
        $prefix = Strings::toSnakeCase(Strings::stripEnd($prefix, "MCP"));
        return $prefix . "_" . Strings::toSnakeCase($methodName);
    }

    /**
     * Returns the title of a Tool from its Class and its Method, like Task: Search for TaskMCP
     * @param string $className
     * @param string $methodName
     * @return string
     */
    public static function getToolTitle(string $className, string $methodName): string {
        $prefix = Strings::substringAfter($className, "\\");
        $prefix = Strings::toSnakeCase(Strings::stripEnd($prefix, "MCP"));
        $prefix = Strings::upperCaseFirst(Strings::replace($prefix, "_", " "));
        $method = Strings::replace(Strings::toSnakeCase($methodName), "_", " ");
        return $prefix . ": " . Strings::upperCaseFirst($method);
    }

    /**
     * Collects the Tools from the given Classes
     * @param list<DiscoveryClass> $classes
     * @return ToolResult
     */
    public static function collectTools(array $classes): array {
        $tools = [];
        $names = [];

        foreach ($classes as $class) {
            $methods = $class->getMethods();

            $total = count($tools);
            if (count($methods) > 0 && $total > 0 && isset($tools[$total - 1])) {
                $tools[$total - 1]["addSpace"] = true;
            }

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(MCPTool::class);
                if (!isset($attributes[0])) {
                    continue;
                }

                /** @var MCPTool */
                $tool = $attributes[0]->newInstance();
                $name = $tool->name;
                if ($name === "") {
                    $name = self::getToolName($class->getName(), $method->getName());
                }

                // The clients only accept these names, and a repeated one could not be called
                if (!Strings::match($name, self::NamePattern) || isset($names[$name])) {
                    continue;
                }
                // An Assistant can not know what a Tool without a description is for
                $requestParam = MCPSignature::getArguments($method);
                if ($requestParam === null || MCPSignature::getDescription($method) === "") {
                    continue;
                }

                $names[$name] = true;
                $tools[] = [
                    "className"    => $class->getFullyQualifiedName(),
                    "method"       => $method->getName(),
                    "requestParam" => $requestParam,
                    "name"         => "\"$name\"",
                    "access"       => $tool->access->toString(),
                    "addSpace"     => false,
                ];
            }
        }

        // Align the Names
        $length = 0;
        foreach ($tools as $tool) {
            $length = max($length, Strings::length($tool["name"]));
        }
        foreach ($tools as $index => $tool) {
            $tools[$index]["name"] = Strings::padRight($tool["name"], $length);
        }

        $total = count($tools);
        return [
            "hasTools" => $total > 0,
            "tools"    => $tools,
            "total"    => $total,
        ];
    }
}
