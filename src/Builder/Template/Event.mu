<?php
namespace {{namespace}};

/**
 * The Event
 */
class Event {
{{#events}}

    /**
     * Triggers the {{name}} Event{{#params}}
     * @param {{type}} ${{name}}{{/params}}
     * @return void
     */
    public static function {{method}}({{signature}}): void {
        {{#handlers}}
        {{.}}({{arguments}});
        {{/handlers}}
    }
{{/events}}
}
