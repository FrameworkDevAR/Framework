<?php
namespace Framework\Discovery\Type;

use Framework\Discovery\Attr\Priority;
use Framework\Utils\Strings;

use ReflectionClass;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The DiscoveryClass class
 */
class DiscoveryClass {

    /** @var ReflectionClass<object>|null */
    private ?ReflectionClass $reflection = null;

    /** @var list<string> */
    private array $uses = [];



    /**
     * Creates a new DiscoveryClass from a class name and its uses
     * @param ReflectionClass<object>|class-string|null $class Optional.
     * @param list<string>                              $uses  Optional.
     */
    public function __construct(
        ReflectionClass|string|null $class = null,
        array $uses = [],
    ) {
        if (is_string($class)) {
            $class = new ReflectionClass($class);
        }
        $this->reflection = $class;
        $this->uses       = $uses;
    }



    /**
     * Returns true if the Class is empty
     * @return bool
      */
    public function isEmpty(): bool {
        return $this->reflection === null;
    }

    /**
     * Returns true if the Class is not empty
     * @return bool
      */
    public function isNotEmpty(): bool {
        return $this->reflection !== null;
    }

    /**
     * Returns the full class name from a use statement
     * @param string $name
     * @return string
     */
    public function getUseClassName(string $name): string {
        foreach ($this->uses as $use) {
            if (Strings::endsWith($use, "\\$name")) {
                return $use;
            }
        }
        return "";
    }

    /**
     * Get the name of the class
     * @return string
     */
    public function getName(): string {
        return $this->reflection?->getName() ?? "";
    }

    /**
     * Returns the fully qualified name of the class, including the namespace
     * @return string
     */
    public function getFullyQualifiedName(): string {
        $result = $this->getName();
        if ($result !== "") {
            return "\\{$result}";
        }
        return "";
    }

    /**
     * Returns the parent class of the class
     * @return DiscoveryClass
     */
    public function getParentClass(): DiscoveryClass {
        $result = $this->reflection?->getParentClass();
        if ($result === false || $result === null) {
            return new DiscoveryClass();
        }
        return new DiscoveryClass($result);
    }

    /**
     * Returns the File name of the class
     * @return string
     */
    public function getFileName(): string {
        $result = $this->reflection?->getFileName();
        return $result !== false && $result !== null ? $result : "";
    }

    /**
     * Returns the Namespace name of the class
     * @return string
     */
    public function getNamespaceName(): string {
        return $this->reflection?->getNamespaceName() ?? "";
    }

    /**
     * Returns the attributes of the class
     * @template T of object
     * @param class-string<T> $className
     * @return ReflectionAttribute<T>|null
     */
    public function getAttribute(string $className): ReflectionAttribute|null {
        $attributes = $this->reflection?->getAttributes($className);
        return $attributes[0] ?? null;
    }

    /**
     * Returns every attribute of the class of the given name, for the repeatable ones
     * @template T of object
     * @param class-string<T> $className
     * @return list<ReflectionAttribute<T>>
     */
    public function getAttributes(string $className): array {
        return $this->reflection?->getAttributes($className) ?? [];
    }

    /**
     * Returns the given Constant of the class as a string, or an empty one
     * @param string $name
     * @return string
     */
    public function getConstant(string $name): string {
        if ($this->reflection === null || !$this->reflection->hasConstant($name)) {
            return "";
        }
        return Strings::toString($this->reflection->getConstant($name));
    }

    /**
     * Returns the priority of the class
     * @return int
     */
    public function getPriority(): int {
        $attribute = $this->getAttribute(Priority::class);
        if ($attribute !== null) {
            return $attribute->newInstance()->priority;
        }
        return Priority::Normal;
    }

    /**
     * Returns the constructor of the class
     * @return ReflectionMethod|null
     */
    public function getConstructor(): ReflectionMethod|null {
        return $this->reflection?->getConstructor() ?? null;
    }

    /**
     * Returns the methods of the class
     * @return list<ReflectionMethod>
     */
    public function getMethods(): array {
        return $this->reflection?->getMethods() ?? [];
    }

    /**
     * Returns the properties of the class
     * @return list<ReflectionProperty>
     */
    public function getProperties(): array {
        return $this->reflection?->getProperties() ?? [];
    }

    /**
     * Returns the properties of the class, starting from the base class
     * @return list<ReflectionProperty>
      */
    public function getPropertiesBaseFirst(): array {
        if ($this->reflection === null) {
            return [];
        }

        $class  = $this->reflection;
        $result = [];
        do {
            $properties = [];
            foreach ($class->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() === $class->getName()) {
                    $properties[] = $property;
                }
            }
            $result = array_merge($properties, $result);
            $class  = $class->getParentClass();
        } while ($class !== false);
        return $result;
    }



    /**
     * Creates a new instance of the class
     * @param mixed ...$args
     * @return object|null
     */
    public function newInstance(mixed ...$args): object|null {
        if ($this->reflection === null) {
            return null;
        }
        return $this->reflection->newInstance(...$args);
    }

    /**
     * Creates a new instance of the class without calling the constructor
     * @return object|null
     */
    public function newInstanceWithoutConstructor(): object|null {
        if ($this->reflection === null) {
            return null;
        }
        return $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * Invokes the constructor of the class on an instance
     * @param object $instance
     * @param mixed  ...$args
     * @return void
     */
    public function invokeConstructor(object $instance, mixed ...$args): void {
        $constructor = $this->getConstructor();
        if ($constructor !== null) {
            $constructor->invoke($instance, ...$args);
        }
    }
}
