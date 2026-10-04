<?php
namespace Framework\Analysis\Email;

use Framework\Email\EmailMessage;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Reflection\ReflectionProvider;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;

use ReflectionClass;

/**
 * The Email Message Rule
 * @implements Rule<Class_>
 */
class EmailMessageRule implements Rule {

    /** @var list<string> */
    private array $requiredLanguages;


    /**
     * Creates the Email Message Rule
     * @param ReflectionProvider $reflectionProvider
     * @param list<string>       $requiredLanguages  Optional.
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        array $requiredLanguages = [],
    ) {
        $this->requiredLanguages = array_values(array_unique($requiredLanguages));
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
        if (!isset($node->namespacedName) || $node->isAbstract()) {
            return [];
        }

        $className = $node->namespacedName->toString();
        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }
        $classReflection = $this->reflectionProvider->getClass($className);
        if (!$classReflection->isSubclassOf(EmailMessage::class)) {
            return [];
        }

        // The send, the body and the template are looked for in the class and in the ones
        // it extends, as what an Email takes from another is its own too
        $reflection = $classReflection->getNativeReflection();
        $properties = self::getStaticProperties($node);
        $errors     = [];

        // The base can not declare send(), as each Email takes its own data
        if (!$reflection->hasMethod("send") || !$reflection->getMethod("send")->isStatic()) {
            $errors[] = self::buildError($node, "The Email {$className} has no static send().");
        }

        // Without a Template the body is the whole Email
        if (!self::declares($reflection, "body") && !self::declares($reflection, "template")) {
            $message  = "The Email {$className} needs a \$body or a \$template.";
            $errors[] = self::buildError($node, $message);
        }

        foreach ([ "subject", "body" ] as $name) {
            if (isset($properties[$name])) {
                $newErrors = $this->checkLanguages($className, $name, $properties[$name]);
                $errors    = array_merge($errors, $newErrors);
            }
        }
        return $errors;
    }

    /**
     * Returns true if the given Property is declared below the base of the Emails
     * @param ReflectionClass<object> $reflection
     * @param string                  $name
     * @return bool
     */
    private static function declares(ReflectionClass $reflection, string $name): bool {
        if (!$reflection->hasProperty($name)) {
            return false;
        }
        $declaring = $reflection->getProperty($name)->getDeclaringClass()->getName();
        return $declaring !== EmailMessage::class;
    }

    /**
     * Returns the static Properties that the given Class declares, by their name
     * @param Class_ $node
     * @return array<string,Property>
     */
    private static function getStaticProperties(Class_ $node): array {
        $result = [];
        foreach ($node->getProperties() as $property) {
            foreach ($property->props as $item) {
                if ($property->isStatic()) {
                    $result[$item->name->toString()] = $property;
                }
            }
        }
        return $result;
    }

    /**
     * Checks that the given Property has a text for each required language and no other
     * @param string   $className
     * @param string   $name
     * @param Property $property
     * @return list<IdentifierRuleError>
     */
    private function checkLanguages(string $className, string $name, Property $property): array {
        $default = null;
        foreach ($property->props as $item) {
            $default = $item->default;
        }
        if (!$default instanceof Array_) {
            $message = "The \${$name} of the Email {$className} must be an array by language.";
            return [ self::buildError($property, $message) ];
        }

        $languages = [];
        foreach ($default->items as $item) {
            if ($item->key instanceof String_) {
                $languages[] = $item->key->value;
            }
        }

        $errors = [];
        $prefix = "The \${$name} of the Email {$className}";
        foreach ($this->requiredLanguages as $language) {
            if (!in_array($language, $languages, strict: true)) {
                $message  = "{$prefix} is missing the language: {$language}.";
                $errors[] = self::buildError($property, $message);
            }
        }
        foreach ($languages as $language) {
            if (!in_array($language, $this->requiredLanguages, strict: true)) {
                $message  = "{$prefix} has an unknown language: {$language}.";
                $errors[] = self::buildError($property, $message);
            }
        }
        return $errors;
    }

    /**
     * Builds an error on the line of the given Node
     * @param Node   $node
     * @param string $message
     * @return IdentifierRuleError
     */
    private static function buildError(Node $node, string $message): IdentifierRuleError {
        return RuleErrorBuilder::message($message)
            ->line($node->getStartLine())
            ->identifier("framework.emailMessage")
            ->build();
    }
}
