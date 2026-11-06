<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan\Rules;

use AnzuSystems\CommonBundle\PHPStan\ClassNamespaceMatcher;
use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

/**
 * #[Serialize] properties are checked too: McpSchemaFactory describes Collection<int, X> exactly like list<X>, so the
 * tool schema stays the same.
 *
 * @implements Rule<InClassNode>
 */
final readonly class McpObjectArrayRule implements Rule
{
    public const string MESSAGE = '%s is %s. Use Collection<int, %s> (Collection<string, …> for a keyed map) instead of an array of objects (objects go in a Collection, arrays hold only ids and scalars, see src/Resources/doc/mcp.md of anzusystems/common-bundle).';

    private const string PROPERTY_SUBJECT_FORMAT = 'Property %s::$%s';

    private ClassNamespaceMatcher $classNamespaceMatcher;
    private ClassNamespaceMatcher $objectNamespaceMatcher;

    /**
     * @param list<string> $classNamespaces the namespaces whose classes are checked; `*` stands for one segment, e.g. `App\Domain\*\Mcp`
     * @param string $objectNamespace arrays of objects from this namespace are reported (`*` stands for one segment), other objects (Uuid, DateTimeImmutable) are allowed
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        array $classNamespaces,
        string $objectNamespace,
    ) {
        $this->classNamespaceMatcher = new ClassNamespaceMatcher($classNamespaces);
        $this->objectNamespaceMatcher = new ClassNamespaceMatcher([$objectNamespace]);
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return $this->findErrors(classLike: $node->getOriginalNode(), classReflection: $node->getClassReflection());
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function findErrors(ClassLike $classLike, ClassReflection $classReflection): array
    {
        if (false === $this->classNamespaceMatcher->matches($classReflection->getName())) {
            return [];
        }

        $errors = [];
        foreach ($this->getDeclaredTypes($classLike, $classReflection) as [$subject, $type, $line]) {
            $objectClassName = $this->findArrayObjectClassName($type);
            if (null === $objectClassName) {
                continue;
            }
            $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE, $subject, $type->describe(VerbosityLevel::typeOnly()), $objectClassName))
                ->identifier('anzu.mcp.objectArray')
                ->line($line)
                ->build();
        }

        return $errors;
    }

    /**
     * @return Generator<int, array{string, Type, int}>
     */
    private function getDeclaredTypes(ClassLike $classLike, ClassReflection $classReflection): Generator
    {
        $className = $classReflection->getName();
        foreach ($classLike->getProperties() as $property) {
            foreach ($property->props as $propertyItem) {
                $propertyName = $propertyItem->name->toString();

                yield [
                    sprintf(self::PROPERTY_SUBJECT_FORMAT, $className, $propertyName),
                    $classReflection->getNativeProperty($propertyName)
                        ->getReadableType(),
                    $property->getStartLine(),
                ];
            }
        }
        foreach ($classLike->getMethods() as $method) {
            $methodName = $method->name->toString();
            $variant = ParametersAcceptorSelector::combineAcceptors($classReflection->getNativeMethod($methodName)->getVariants());
            foreach ($method->params as $position => $param) {
                if (false === $param->var instanceof Variable || false === is_string($param->var->name)) {
                    continue;
                }
                $paramName = $param->var->name;
                if ($param->isPromoted()) {
                    yield [
                        sprintf(self::PROPERTY_SUBJECT_FORMAT, $className, $paramName),
                        $classReflection->getNativeProperty($paramName)
                            ->getReadableType(),
                        $param->getStartLine(),
                    ];

                    continue;
                }

                yield [
                    sprintf('Parameter $%s of method %s::%s()', $paramName, $className, $methodName),
                    $variant->getParameters()[$position]->getType(),
                    $param->getStartLine(),
                ];
            }

            yield [
                sprintf('Return type of method %s::%s()', $className, $methodName),
                $variant->getReturnType(),
                $method->getStartLine(),
            ];
        }
    }

    private function findArrayObjectClassName(Type $type): ?string
    {
        foreach (TypeCombinator::removeNull($type)->getArrays() as $arrayType) {
            $valueType = $arrayType->getIterableValueType();
            $objectClassName = array_find($valueType->getObjectClassNames(), $this->isReportedObject(...))
                ?? $this->findArrayObjectClassName($valueType);
            if (is_string($objectClassName)) {
                return $objectClassName;
            }
        }

        return null;
    }

    private function isReportedObject(string $className): bool
    {
        if (false === $this->objectNamespaceMatcher->matches($className) || false === $this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return false === $this->reflectionProvider->getClass($className)
            ->isEnum();
    }
}
