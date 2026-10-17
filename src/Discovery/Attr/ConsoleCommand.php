<?php
namespace Framework\Discovery\Attr;

use Framework\Utils\Numbers;
use Framework\Utils\Strings;

use Attribute;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionException;

/**
 * The Console Command Attribute
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ConsoleCommand {

    public string $name  = "";
    public string $alias = "";

    // A private Command is only available while working on the Framework itself
    public bool $isPrivate = false;

    private ?ReflectionMethod $handler = null;



    /**
     * The Console Command Attribute
     * @param string $name
     * @param string $alias     Optional.
     * @param bool   $isPrivate Optional.
     */
    public function __construct(
        string $name,
        string $alias = "",
        bool $isPrivate = false,
    ) {
        $this->name      = $name;
        $this->alias     = $alias;
        $this->isPrivate = $isPrivate;
    }



    /**
     * Returns the Command Name
     * @return string
     */
    public function getName(): string {
        $result = $this->name;
        if ($this->alias !== "") {
            $result .= " ({$this->alias})";
        }
        return $result;
    }

    /**
     * Returns the Command Arguments
     * @return string
     */
    public function getArguments(): string {
        if ($this->handler === null) {
            return "";
        }

        $params    = $this->handler->getParameters();
        $argValues = [];
        foreach ($params as $param) {
            $name = $param->getName();
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === "bool") {
                $name = "--$name";
            } else {
                $name = "--$name=<value>";
            }
            $argValues[] = $name;
        }
        return Strings::join($argValues, " ");
    }

    /**
     * Checks if the command should be invoked
     * @param string $name
     * @return bool
     */
    public function shouldInvoke(string $name): bool {
        return $this->name === $name ||
            ($this->alias !== "" && $this->alias === $name);
    }

    /**
     * Sets the Command Handler
     * @param ReflectionMethod $handler
     * @return ConsoleCommand
     */
    public function setHandler(ReflectionMethod $handler): ConsoleCommand {
        $this->handler = $handler;
        return $this;
    }

    /**
     * Invokes the command handler
     * @param list<string> $arguments
     * @return bool
     */
    public function invoke(array $arguments): bool {
        if ($this->handler === null) {
            return false;
        }

        // Parse the Arguments
        $argsData = [];
        $argsText = [];
        foreach ($arguments as $argument) {
            $name  = Strings::substringBefore($argument, "=");
            $name  = Strings::stripStart($name, "--");
            $name  = Strings::toLowerCase($name);

            $value = true;
            unset($argsText[$name]);
            if (Strings::contains($argument, "=")) {
                $value = Strings::substringAfter($argument, "=");
                $argsText[$name] = $value;
                if (Numbers::isValid($value)) {
                    // Kept apart, since casting every number the same way handed
                    // a float parameter asked for 1.5 the integer 1
                    $value = Strings::contains($value, ".") ? (float)$value : (int)$value;
                } elseif (Strings::toLowerCase($value) === "true") {
                    $value = true;
                } elseif (Strings::toLowerCase($value) === "false") {
                    $value = false;
                }
            }
            $argsData[$name] = $value;
        }

        // Match the Arguments to the Parameters
        $params    = $this->handler->getParameters();
        $argValues = [];
        foreach ($params as $param) {
            $name    = $param->getName();
            $nameIdx = Strings::toLowerCase($name);

            if (!isset($argsData[$nameIdx])) {
                // Counting the values instead of naming the missing one let an
                // optional argument stand in for a required one, and the call
                // below died with an ArgumentCountError rather than showing the usage
                if (!$param->isOptional()) {
                    return false;
                }
                continue;
            }
            // A string parameter takes what was written, as the number it was read as
            // turned a version like 4.0 into 4
            $type     = $param->getType();
            $isString = $type instanceof ReflectionNamedType && $type->getName() === "string";
            if ($isString && isset($argsText[$nameIdx])) {
                $argValues[$name] = $argsText[$nameIdx];
            } else {
                $argValues[$name] = $argsData[$nameIdx];
            }
        }

        try {
            $this->handler->invokeArgs(null, $argValues);
        } catch (ReflectionException $e) {
            return false;
        }
        return true;
    }
}
