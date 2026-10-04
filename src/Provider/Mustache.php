<?php
namespace Framework\Provider;

use Mustache\Engine;
use Exception;

/**
 * The Mustache Provider
 */
class Mustache {

    private static ?Engine $engine = null;


    /**
     * Returns true if the Mustache Engine is available
     * @return bool
     */
    public static function isAvailable(): bool {
        return class_exists(Engine::class);
    }

    /**
     * Returns the Mustache Engine instance
     * @return Engine
     */
    private static function getEngine(): Engine {
        if (self::$engine === null) {
            self::$engine = new Engine();
        }
        return self::$engine;
    }



    /**
     * Validates a Mustache template and returns an error
     * @param string $template
     * @return string
     */
    public static function getError(string $template): string {
        try {
            self::getEngine()->render($template, []);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "";
    }

    /**
     * Renders a Mustache template
     * @param string               $template
     * @param array<string,mixed>  $data
     * @param array<string,string> $partials Optional.
     * @return string
     */
    public static function render(string $template, array $data, array $partials = []): string {
        if (count($partials) === 0) {
            return self::getEngine()->render($template, $data);
        }

        // The partials are given to the Engine when it is created, so the shared
        // one can not take them
        $engine = new Engine([ "partials" => $partials ]);
        return $engine->render($template, $data);
    }
}
