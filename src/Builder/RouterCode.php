<?php
namespace Framework\Builder;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Discovery\Attr\Priority;
use Framework\Discovery\Attr\Route;
use Framework\Builder\Builder;
use Framework\Utils\Strings;

/**
 * The Router Code
 * @phpstan-type RouteData array{
 *   className:    string,
 *   method:       string,
 *   requestParam: string,
 *   route:        string,
 *   access:       string,
 *   addSpace:     bool,
 * }
 * @phpstan-type RouterResult array{
 *   hasRoutes: bool,
 *   routes:    list<RouteData>,
 *   total:     int,
 * }
 */
#[Priority(Priority::Lowest)]
class RouterCode implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $data = self::collectRoutes(Discovery::findClasses());
        return Builder::generateCode("Router", $data);
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
     * Collects the Routes from the given Classes
     * @param list<DiscoveryClass> $classes
     * @return RouterResult
     */
    public static function collectRoutes(array $classes): array {
        $routes = [];

        foreach ($classes as $class) {
            $methods = $class->getMethods();

            // Add a space between the classes
            $total = count($routes);
            if (count($methods) > 0 && $total > 0 && isset($routes[$total - 1])) {
                $routes[$total - 1]["addSpace"] = true;
            }

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(Route::class);
                if (!isset($attributes[0])) {
                    continue;
                }

                /** @var Route */
                $route      = $attributes[0]->newInstance();
                $methodName = $method->getName();

                $requestParam = Builder::getRequestParam($method);
                if ($requestParam === null) {
                    continue;
                }

                // Add the Route
                $routes[] = [
                    "className"    => $class->getFullyQualifiedName(),
                    "method"       => $methodName,
                    "requestParam" => $requestParam,
                    "route"        => $route->route,
                    "access"       => $route->access->toString(),
                    "addSpace"     => false,
                ];
            }
        }

        // Align the Routes
        $length = 0;
        foreach ($routes as $route) {
            $length = max($length, Strings::length($route["route"]) + 2);
        }
        foreach ($routes as $index => $route) {
            $routes[$index]["route"] = Strings::padRight("\"{$route["route"]}\"", $length);
        }


        $total = count($routes);
        return [
            "hasRoutes" => $total > 0,
            "routes"    => $routes,
            "total"     => $total,
        ];
    }
}
