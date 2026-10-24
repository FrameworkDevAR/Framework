<?php
namespace Framework\Analysis\Database;

use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\Virtual;
use Framework\Database\Model\Requested;
use Framework\Database\Model\Validate;
use Framework\Database\Model\Expression;
use Framework\Database\Model\Count;
use Framework\Database\Model\Relation;
use Framework\Database\Model\SubRequest;
use Framework\Database\Status\Status;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;

use PhpParser\Node;

/**
 * @implements Rule<ClassPropertyNode>
 */
class ModelAttributeRule implements Rule {

    /** @var list<class-string> */
    private const Attributes = [
        Field::class,
        Virtual::class,
        Requested::class,
        Expression::class,
        Count::class,
        Relation::class,
        SubRequest::class,
    ];


    /**
     * Creates the Model Enum Rule
     * @param ReflectionProvider $reflectionProvider
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * Returns the type of node this rule is interested in
     * @return class-string<ClassPropertyNode>
     */
    #[\Override]
    public function getNodeType(): string {
        return ClassPropertyNode::class;
    }

    /**
     * Processes the node and returns an array of errors if any
     * @param ClassPropertyNode $node
     * @param Scope             $scope
     * @return list<IdentifierRuleError>
     */
    #[\Override]
    public function processNode(Node $node, Scope $scope): array {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        // Check if the class has the #[Model] attribute
        if (!$this->isModelClass($classReflection)) {
            return [];
        }

        // Get some property info
        $propName         = $node->getName();
        $nativeReflection = $classReflection->getNativeReflection();
        $nativeProperty   = $nativeReflection->getProperty($propName);
        $propReflection   = $classReflection->getNativeProperty($propName);
        $propType         = $propReflection->getReadableType();
        $errors           = [];


        // Analyze attributes
        $hasField       = false;
        $hasRequested   = false;
        $hasValidate    = false;
        $hasRelation    = false;
        $hasSubRequest  = false;

        $hasAttribute   = false;
        $totalExclusive = 0;

        $fileOwner      = "";
        $holdsFile      = false;

        $attributes = $nativeProperty->getAttributes();
        foreach ($attributes as $attribute) {
            $attrName = $attribute->getName();
            if ($attrName === Field::class) {
                $hasField  = true;
                $arguments = $attribute->getArguments();
                if (is_string($arguments["fileOwner"] ?? null)) {
                    $fileOwner = $arguments["fileOwner"];
                }
                $isFile    = ($arguments["isFile"] ?? false) === true;
                $hasFile   = ($arguments["hasFile"] ?? false) === true;
                $holdsFile = $isFile || $hasFile;
            } elseif ($attrName === Requested::class) {
                $hasRequested = true;
            } elseif ($attrName === Validate::class) {
                $hasValidate = true;
            } elseif ($attrName === Relation::class) {
                $hasRelation = true;
            } elseif ($attrName === SubRequest::class) {
                $hasSubRequest = true;
            }

            if (in_array($attrName, self::Attributes, strict: true)) {
                $hasAttribute = true;
                if (!$hasRequested) {
                    $totalExclusive += 1;
                }
            }
        }


        // Every property must have one of the main attributes
        if (!$hasAttribute) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' must have an attribute."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }


        // A property cannot combine main attributes (except #[Requested])
        if ($totalExclusive > 1) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' cannot combine main attributes."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }


        // A property with #[Validate] requires #[Requested]
        if ($hasValidate && !$hasRequested) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' is missing the #[Requested] attribute."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }


        // A property with Type 'Status' requires #[Field]
        $refClasses = $propType->getReferencedClasses();
        $isStatus   = isset($refClasses[0]) && $refClasses[0] === Status::class;

        if ($isStatus && !$hasField) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' is missing the #[Field] attribute."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }


        // A property with #[Relation] must point to a class with #[Model]
        if ($hasRelation) {
            $referencedClasses = $propType->getReferencedClasses();

            foreach ($referencedClasses as $className) {
                // Use reflectionProvider instead of broker
                if (!$this->reflectionProvider->hasClass($className)) {
                    continue;
                }

                $targetClass = $this->reflectionProvider->getClass($className);
                if ($this->isModelClass($targetClass)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(
                    "Property '{$propName}' must relate to a #[Model]."
                )
                    ->line($node->getLine())
                    ->identifier("framework.modelAttribute")
                    ->build();
            }
        }


        // The Owner of the files must be a #[Field] of the Model, and only a field that
        // keeps files has one, as a name that is wrong would change the paths of every row
        if ($fileOwner !== "" && !$holdsFile) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' has a file owner but keeps no files."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }
        if ($fileOwner !== "" && !$this->isField($nativeReflection, $fileOwner)) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' has the file owner '{$fileOwner}', which is not a #[Field]."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }


        // A property with #[SubRequest] must be an array
        if ($hasSubRequest && !$propType->isArray()->yes()) {
            $errors[] = RuleErrorBuilder::message(
                "Property '{$propName}' must have type array."
            )
                ->line($node->getLine())
                ->identifier("framework.modelAttribute")
                ->build();
        }

        return $errors;
    }

    /**
     * Checks if the given class has a property with #[Field] with the given name
     * @param \ReflectionClass<object> $reflection
     * @param string                   $propName
     * @return bool
     */
    private function isField(\ReflectionClass $reflection, string $propName): bool {
        if (!$reflection->hasProperty($propName)) {
            return false;
        }
        $property = $reflection->getProperty($propName);
        return count($property->getAttributes(Field::class)) > 0;
    }

    /**
     * Checks if the given class or any of its parents has #[Model]
     * @param ClassReflection $classReflection
     * @return bool
     */
    private function isModelClass(ClassReflection $classReflection): bool {
        // Check current class
        $reflection = $classReflection->getNativeReflection();
        if (count($reflection->getAttributes(Model::class)) > 0) {
            return true;
        }

        // Check parents
        foreach ($classReflection->getParents() as $parent) {
            $reflection = $parent->getNativeReflection();
            if (count($reflection->getAttributes(Model::class)) > 0) {
                return true;
            }
        }

        return false;
    }
}
