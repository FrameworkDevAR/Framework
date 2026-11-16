<?php
namespace {{namespace}};

{{#hasImports}}
{{#imports}}use {{.}};
{{/imports}}

{{/hasImports}}
use Framework\IO\Request;
use Framework\Database\Type\SchemaRequest;{{#hasDictionaries}}
use Framework\Utils\Dictionary;{{/hasDictionaries}}

use ReflectionClass;

/**
 * The {{name}} Request
 */
class {{requestClass}} extends SchemaRequest {
{{#hasID}}

    public bool $isCreate = false;
    public bool $isEdit = false;

    {{#hasIntID}}
    public int $id;
    {{/hasIntID}}
    {{#hasStringID}}
    public string $code;
    {{/hasStringID}}
    {{#hasEnumID}}
    public {{idEnumName}} $code = {{idEnumName}}::None;
    {{/hasEnumID}}
{{/hasID}}
{{#hasMultiID}}

    {{#hasIntID}}
    /** @var list<int> */
    public array $ids = [];
    {{/hasIntID}}
    {{#hasStringID}}
    /** @var list<string> */
    public array $codes = [];
    {{/hasStringID}}
{{/hasMultiID}}
{{#hasFields}}

    // Fields
    {{#fields}}
    {{#subType}}

    /** @var {{{subType}}} */
{{/subType}}    public {{type}} ${{name}};
    {{/fields}}
{{/hasFields}}
{{#hasDictionaries}}

    // Dictionaries
    {{#dictionaries}}
    public Dictionary ${{.}};
    {{/dictionaries}}
{{/hasDictionaries}}



    /**
     * Creates a new {{requestClass}} instance{{#hasIntID}}
     * @param int $id Optional.{{/hasIntID}}{{#hasStringID}}
     * @param string $code Optional.{{/hasStringID}}{{#hasEnumID}}
     * @param {{idEnumName}} $code Optional.{{/hasEnumID}}{{#fields}}
     * @param {{{docType}}} ${{name}} Optional.{{/fields}}{{#values}}
     * @param {{{docType}}} ${{name}} Optional.{{/values}}{{#dictionaries}}
     * @param Dictionary ${{.}} Optional.{{/dictionaries}}
     */
    public function __construct({{#hasIntID}}
        int $id = 0,{{/hasIntID}}{{#hasStringID}}
        string $code = "",{{/hasStringID}}{{#hasEnumID}}
        {{idEnumName}} $code = {{idEnumName}}::None,{{/hasEnumID}}{{#fields}}
        {{argType}} ${{name}} = {{{default}}},{{/fields}}{{#values}}
        {{argType}} ${{name}} = {{{default}}},{{/values}}{{#dictionaries}}
        Dictionary ${{.}} = new Dictionary(),{{/dictionaries}}
    ) {
        parent::__construct();
    {{#hasID}}

        // Set the ID
        {{#hasIntID}}
        $this->id = $id;
        {{/hasIntID}}
        {{#hasStringID}}
        $this->code = $code;
        {{/hasStringID}}
        {{#hasEnumID}}
        $this->code = $code;
        {{/hasEnumID}}
    {{/hasID}}
    {{#hasFields}}

        // Set the Fields
        {{#fields}}
        $this->{{name}} = {{{setter}}};
        {{/fields}}
    {{/hasFields}}
    {{#hasDictionaries}}

        // Set the Dictionaries
        {{#dictionaries}}
        $this->{{.}} = ${{.}};
        {{/dictionaries}}
    {{/hasDictionaries}}
    }

{{#hasEntityFields}}
    /**
     * Creates a new {{requestClass}} instance from a Entity
     * @param {{entityClass}} $entity
     * @return self
     */
    public static function fromEntity({{entityClass}} $entity): self {
        $instance = new self();

        {{#entityFields}}
        $instance->{{.}} = $entity->{{.}};
        {{/entityFields}}
        return $instance;
    }

{{/hasEntityFields}}
    /**
     * Returns the JSON Schema of each input of the Request, by its name
     * @return array<string,array<string,mixed>>
     */
    #[\Override]
    public static function getInputSchema(): array {
    {{#hasInputs}}
        return [
        {{#inputs}}
            {{{key}}} => {{{schema}}},
        {{/inputs}}
        ];
    {{/hasInputs}}
    {{^hasInputs}}
        return [];
    {{/hasInputs}}
    }

    /**
     * Creates a new {{requestClass}} instance from a Request
     * @param SchemaRequest|Request $request
     * @return self
     */
    public static function fromRequest(SchemaRequest|Request $request): self {
        $reflection  = new ReflectionClass(self::class);
        $parentClass = $reflection->getParentClass();

        /** @var self */
        $instance = $reflection->newInstanceWithoutConstructor();
        if ($parentClass !== false) {
            $parentClass->getConstructor()?->invoke($instance, $request);
        }
    {{#hasID}}

        $instance->isCreate = !$instance->request->hasValue("{{idName}}");
        $instance->isEdit = $instance->request->hasValue("{{idName}}");

        // Set the ID
        {{#hasIntID}}
        $instance->id = $instance->request->getInt("{{idName}}");
        {{/hasIntID}}
        {{#hasStringID}}
        $instance->code = $instance->request->getString("{{idName}}");
        {{/hasStringID}}
        {{#hasEnumID}}
        $instance->code = {{idEnumName}}::fromValue($instance->request->getString("{{idName}}"));
        {{/hasEnumID}}
    {{/hasID}}
    {{#hasMultiID}}
        {{#hasIntID}}
        $instance->ids = $instance->request->getInts("{{idName}}s");
        {{/hasIntID}}
        {{#hasStringID}}
        $instance->codes = $instance->request->getStrings("{{idName}}s");
        {{/hasStringID}}
    {{/hasMultiID}}
    {{#hasFields}}

        // Set the Fields
        {{#fields}}
        $instance->{{name}} = {{{getter}}};
        {{/fields}}
    {{/hasFields}}
    {{#hasDictionaries}}

        // Set the Dictionaries
        {{#dictionaries}}
        $instance->{{.}} = $instance->request->getDict("{{.}}");
        {{/dictionaries}}
    {{/hasDictionaries}}

        return $instance;
    }
}
