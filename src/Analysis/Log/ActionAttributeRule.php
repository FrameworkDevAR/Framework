<?php
namespace Framework\Analysis\Log;

use Framework\Log\Attr\Action;
use Framework\Log\Attr\Section;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;

/**
 * The Action Attribute Rule
 * @implements Rule<Class_>
 */
class ActionAttributeRule implements Rule {

    private const DefaultLanguages = [ "en", "es" ];

    /** @var list<string> */
    private array $requiredLanguages;

    /**
     * Creates the Action Attribute Rule
     * @param list<string> $requiredLanguages Optional.
     */
    public function __construct(array $requiredLanguages = []) {
        if (count($requiredLanguages) === 0) {
            $requiredLanguages = self::DefaultLanguages;
        }
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
        $errors = $this->processAttributeGroups($node->attrGroups, $scope);

        foreach ($node->getMethods() as $method) {
            $newErrors = $this->processAttributeGroups($method->attrGroups, $scope);
            $errors    = array_merge($errors, $newErrors);
        }

        return $errors;
    }

    /**
     * Processes the Attribute Groups and returns an array of errors if any
     * @param array<AttributeGroup> $attributeGroups
     * @param Scope                 $scope
     * @return list<IdentifierRuleError>
     */
    private function processAttributeGroups(
        array $attributeGroups,
        Scope $scope,
    ): array {
        $errors = [];

        foreach ($attributeGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeName = $scope->resolveName($attribute->name);
                if ($attributeName === Action::class) {
                    $name = "Action";
                } elseif ($attributeName === Section::class) {
                    $name = "Section";
                } else {
                    continue;
                }

                $newErrors = $this->processAttribute($attribute, $attributeGroup, $name);
                $errors    = array_merge($errors, $newErrors);
            }
        }

        return $errors;
    }

    /**
     * Processes the Attribute and returns an array of errors if any
     * @param Attribute      $attribute
     * @param AttributeGroup $attributeGroup
     * @param string         $name
     * @return list<IdentifierRuleError>
     */
    private function processAttribute(
        Attribute $attribute,
        AttributeGroup $attributeGroup,
        string $name,
    ): array {
        $errors    = [];
        $arguments = $attribute->args;

        // Check for the Name (the first positional parameter)
        if (count($arguments) === 0) {
            return [
                RuleErrorBuilder::message(
                    "The #[$name] attribute requires a name as its first argument."
                )
                    ->line($attributeGroup->getLine())
                    ->identifier("framework.actionAttribute")
                    ->build(),
            ];
        }

        // Validate the Remaining Arguments
        $translationArguments = array_slice($arguments, 1);
        $providedLanguages    = [];
        foreach ($translationArguments as $index => $argument) {
            $argName  = $argument->name !== null ? $argument->name->toString() : null;
            $position = $index + 2;

            if ($argName === null) {
                $errors[] = RuleErrorBuilder::message(
                    "Argument $position in #[$name] must be a named parameter."
                )
                    ->line($attributeGroup->getLine())
                    ->identifier("framework.actionAttribute")
                    ->build();
            } elseif (!in_array($argName, $this->requiredLanguages, strict: true)) {
                $errors[] = RuleErrorBuilder::message(
                    "Argument $position in #[$name] is not a valid language."
                )
                    ->line($attributeGroup->getLine())
                    ->identifier("framework.actionAttribute")
                    ->build();
            } else {
                $providedLanguages[] = $argName;
            }
        }

        // Verify each configured language is explicitly provided as a named argument
        foreach ($this->requiredLanguages as $language) {
            if (in_array($language, $providedLanguages, strict: true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                "The #[$name] attribute is missing the language: $language."
            )
                ->line($attributeGroup->getLine())
                ->identifier("framework.actionAttribute")
                ->build();
        }

        return $errors;
    }
}
