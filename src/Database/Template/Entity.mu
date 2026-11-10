<?php
namespace {{namespace}};

{{#imports}}use {{.}};
{{/imports}}{{#hasImports}}
{{/hasImports}}
use Framework\Database\Type\Entity;
use Framework\Utils\Dictionary;

/**
 * The {{name}} Entity
 */
class {{entityClass}} extends Entity {

{{#hasID}}
    {{#hasIntID}}
    public int $id = 0;
    {{/hasIntID}}
    {{#hasStringID}}
    public string $id = "";
    {{/hasStringID}}
    {{#hasEnumID}}
    public {{idEnumName}} $id = {{idEnumName}}::None;
    {{/hasEnumID}}


{{/hasID}}
{{#properties}}
    // {{name}} Fields
{{#list}}{{#subType}}
    /** @var {{{subType}}} */
{{/subType}}    public {{type}} ${{name}}{{#hasDefault}} = {{{default}}}{{/hasDefault}};

{{/list}}

{{/properties}}

    /**
     * Creates a new {{name}} Entity instance
     * @param Dictionary|null $data Optional.{{#mainFields}}
     * @param {{{docType}}} ${{name}} Optional.{{/mainFields}}{{#dictionaries}}
     * @param Dictionary|null ${{.}} Optional.{{/dictionaries}}{{#hasStatus}}
     * @param {{statusClass}} $status Optional.{{/hasStatus}}
     */
    public function __construct(
        ?Dictionary $data = null,{{#mainFields}}
        {{paramType}} ${{name}} = {{{paramDefault}}},{{/mainFields}}{{#dictionaries}}
        ?Dictionary ${{.}} = null,{{/dictionaries}}{{#hasStatus}}
        {{statusClass}} $status = {{statusClass}}::None,{{/hasStatus}}
    ) {
        // Set the Main Fields
        {{#mainFields}}
        $this->{{name}} = {{{setter}}};
        {{/mainFields}}
    {{#hasDictionaries}}

        // Set the Dictionaries
        {{#dictionaries}}
        $this->{{.}} = new Dictionary(${{.}});
        {{/dictionaries}}
    {{/hasDictionaries}}

        // Set all the Fields using the Dictionary
        if ($data !== null) {
            parent::__construct($data);
            {{#subTypes}}

            $this->{{name}} = [];
            foreach ($data->getDict("{{name}}") as {{#useIndex}}$index => {{/useIndex}}$subData) {
                $this->{{name}}[{{#useIndex}}{{keyType}}$index{{/useIndex}}] = new {{type}}Entity($subData);
            }
            {{/subTypes}}
        }
    {{#hasStatus}}

        // Set the Status
        if ($status !== {{statusClass}}::None) {
            $this->status = $status;
        }
        $this->statusName  = {{statusClass}}::getName($this->status);
        $this->statusColor = {{statusClass}}::getColor($this->status);
    {{/hasStatus}}
    {{#hasID}}

        // Set ID and isEmpty
        $this->id      = $this->{{idName}};
        {{#hasIntID}}
        $this->isEmpty = $this->{{idName}} === 0;
        {{/hasIntID}}
        {{#hasStringID}}
        $this->isEmpty = $this->{{idName}} === "";
        {{/hasStringID}}
        {{#hasEnumID}}
        $this->isEmpty = $this->{{idName}} === {{idEnumName}}::None;
        {{/hasEnumID}}
    {{/hasID}}
    }
{{#hasEnv}}

    /**
     * Returns true if the work of the Entity can run in the current Environment
     * @return bool
     */
    public function isCurrentEnv(): bool {
        return Configs::isCurrentEnv($this->env);
    }
{{/hasEnv}}
}
