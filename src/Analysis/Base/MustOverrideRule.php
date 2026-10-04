<?php
namespace Framework\Analysis\Base;

use Framework\Analysis\Attr\MustOverride;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Reflection\ReflectionProvider;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The Must Override Rule
 * @implements Rule<Class_>
 */
class MustOverrideRule implements Rule {

    /**
     * Creates a new Must Override Rule
     * @param ReflectionProvider $reflectionProvider
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * Returns the type of node this rule is interested in
     * @return class-string<Class_>
     */
    #[\Override]
    public function getNodeType(): string {
        return Class_::class;
    }

    /**
     * Processes the node and returns an array of errors if any
     * @param Class_ $node
     * @param Scope  $scope
     * @return list<IdentifierRuleError>
     */
    #[\Override]
    public function processNode(Node $node, Scope $scope): array {
        if (!isset($node->namespacedName)) {
            return [];
        }

        // An abstract class hands the obligation down to whoever can be built
        $className = $node->namespacedName->toString();
        if ($node->isAbstract() || !$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $reflection = $this->reflectionProvider->getClass($className)->getNativeReflection();
        $result     = [];

        foreach (self::getMarkedMethods($reflection) as $method) {
            $declaring = $reflection->getMethod($method->getName())->getDeclaringClass()->getName();
            if ($declaring !== $method->getDeclaringClass()->getName()) {
                continue;
            }

            $name     = $method->getName();
            $baseName = $method->getDeclaringClass()->getShortName();
            $message  = "{$className} does not override {$name}() of {$baseName}";
            $result[] = self::buildError($node, $message);
        }

        foreach (self::getMarkedProperties($reflection) as $property) {
            $declaring = $reflection->getProperty($property->getName())->getDeclaringClass();
            $declaring = $declaring->getName();
            if ($declaring !== $property->getDeclaringClass()->getName()) {
                continue;
            }

            $name     = $property->getName();
            $baseName = $property->getDeclaringClass()->getShortName();
            $message  = "{$className} does not override \${$name} of {$baseName}";
            $result[] = self::buildError($node, $message);
        }
        return $result;
    }

    /**
     * Builds the error of a Class that does not override what was marked
     * @param Class_ $node
     * @param string $message
     * @return IdentifierRuleError
     */
    private static function buildError(Class_ $node, string $message): IdentifierRuleError {
        return RuleErrorBuilder::message("Class {$message}, which is marked with #[MustOverride].")
            ->line($node->getStartLine())
            ->identifier("framework.mustOverride")
            ->build();
    }

    /**
     * Returns the Methods marked in the parents of the given Class
     * @param ReflectionClass<object> $reflection
     * @return list<ReflectionMethod>
     */
    private static function getMarkedMethods(ReflectionClass $reflection): array {
        $result = [];
        $parent = $reflection->getParentClass();

        while ($parent !== false) {
            foreach ($parent->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $parent->getName()) {
                    continue;
                }
                if (count($method->getAttributes(MustOverride::class)) > 0) {
                    $result[] = $method;
                }
            }
            $parent = $parent->getParentClass();
        }
        return $result;
    }

    /**
     * Returns the Properties marked in the parents of the given Class
     * @param ReflectionClass<object> $reflection
     * @return list<ReflectionProperty>
     */
    private static function getMarkedProperties(ReflectionClass $reflection): array {
        $result = [];
        $parent = $reflection->getParentClass();

        while ($parent !== false) {
            foreach ($parent->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $parent->getName()) {
                    continue;
                }
                if (count($property->getAttributes(MustOverride::class)) > 0) {
                    $result[] = $property;
                }
            }
            $parent = $parent->getParentClass();
        }
        return $result;
    }
}
