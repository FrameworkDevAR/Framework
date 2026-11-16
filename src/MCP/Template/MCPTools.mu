<?php
namespace {{namespace}};

use Framework\IO\Request;
use Framework\IO\Response;
use Framework\System\Access;

/**
 * The MCP Tools
 */
class MCPTools {

    /**
     * Returns the Class and the Method of each Tool, by its Name
     * @return array<string,array{class-string,string}>
     */
    public static function getHandlers(): array {
    {{#hasTools}}
        return [
        {{#tools}}
            {{{name}}} => [ {{className}}::class, "{{method}}" ],
            {{#addSpace}}

            {{/addSpace}}
        {{/tools}}
        ];
    {{/hasTools}}
    {{^hasTools}}
        return [];
    {{/hasTools}}
    }

    /**
     * Returns the Access of the given Tool, if it exists
     * @param string $name
     * @return Access
     */
    public static function getAccess(string $name): Access {
    {{#hasTools}}
        return match ($name) {
        {{#tools}}
            {{{name}}} => Access::{{access}},
            {{#addSpace}}

            {{/addSpace}}
        {{/tools}}

            default => Access::None,
        };
    {{/hasTools}}
    {{^hasTools}}
        return Access::None;
    {{/hasTools}}
    }

    /**
     * Calls the given Tool with the given params, if it exists
     * @param string $name
     * @param Request $request
     * @return Response
     */
    public static function call(string $name, Request $request): Response {
    {{#hasTools}}
        return match ($name) {
        {{#tools}}
            {{{name}}} => {{className}}::{{method}}({{{requestParam}}}),
            {{#addSpace}}

            {{/addSpace}}
        {{/tools}}

            default => Response::empty(),
        };
    {{/hasTools}}
    {{^hasTools}}
        return Response::empty();
    {{/hasTools}}
    }
}
