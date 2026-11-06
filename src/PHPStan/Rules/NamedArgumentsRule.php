<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan\Rules;

use AnzuSystems\CommonBundle\PHPStan\ClassNamespaceMatcher;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * @implements Rule<CallLike>
 */
final readonly class NamedArgumentsRule implements Rule
{
    public const string MESSAGE = 'The call of %s() passes %d arguments, name them: %s (named arguments are required whenever a call passes more than two arguments).';

    private const int MAX_UNNAMED_ARGUMENTS = 2;
    private const string CONSTRUCTOR = '__construct';

    private ClassNamespaceMatcher $classNamespaceMatcher;

    /**
     * @param list<string> $classNamespaces the namespaces whose classes are checked; `*` stands for one segment, e.g. `App\Domain\*\Mcp`
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        array $classNamespaces,
    ) {
        $this->classNamespaceMatcher = new ClassNamespaceMatcher($classNamespaces);
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isFirstClassCallable() || false === $this->isCheckedClass($scope)) {
            return [];
        }
        $arguments = $node->getArgs();
        $unnamedArguments = array_filter($arguments, static fn (Arg $argument): bool => null === $argument->name);
        if (count($arguments) <= self::MAX_UNNAMED_ARGUMENTS || empty($unnamedArguments)) {
            return [];
        }
        if (array_any($arguments, static fn (Arg $argument): bool => $argument->unpack)) {
            return [];
        }

        $callee = $this->findCallee($node, $scope);
        if (null === $callee || false === $callee->acceptsNamedArguments()->yes()) {
            return [];
        }
        $nameableParameterNames = $this->resolveNameableParameterNames(callee: $callee, arguments: $arguments, scope: $scope);
        // PHP refuses a named argument before a positional one, so a value for a variadic parameter keeps the whole call positional.
        if (count($unnamedArguments) > count($nameableParameterNames)) {
            return [];
        }

        $parameterNames = array_slice(array: $nameableParameterNames, offset: 0, length: count($unnamedArguments));

        return [
            RuleErrorBuilder::message(sprintf(self::MESSAGE, $callee->getName(), count($arguments), implode(', ', $parameterNames)))
                ->identifier('anzu.namedArguments')
                ->build(),
        ];
    }

    /**
     * @param array<int, Arg> $arguments
     *
     * @return list<string>
     */
    private function resolveNameableParameterNames(ExtendedMethodReflection|FunctionReflection $callee, array $arguments, Scope $scope): array
    {
        $parameters = $this->findNativeParameters($callee) ?? ParametersAcceptorSelector::selectFromArgs(
            scope: $scope,
            args: $arguments,
            parametersAcceptors: $callee->getVariants(),
            namedArgumentsVariants: $callee->getNamedArgumentsVariants(),
        )->getParameters();
        $nameableParameters = array_filter(
            $parameters,
            static fn (ParameterReflection|ReflectionParameter $parameter): bool => false === $parameter->isVariadic(),
        );

        return array_map(
            static fn (ParameterReflection|ReflectionParameter $parameter): string => $parameter->getName(),
            $nameableParameters,
        ) |> array_values(...);
    }

    /**
     * The stubs of PHPStan can differ from PHP: array_udiff() takes `arr1, arr2, data_comp_func` there and
     * `$array, ...$rest` in PHP, where those names fail at run time.
     *
     * @return list<ReflectionParameter>|null
     */
    private function findNativeParameters(ExtendedMethodReflection|FunctionReflection $callee): ?array
    {
        if ($callee instanceof FunctionReflection) {
            return $callee->isBuiltin() && function_exists($callee->getName())
                // @phpstan-ignore phpstanApi.runtimeReflection (the names must be those of the running PHP, not of the stubs)
                ? new ReflectionFunction($callee->getName())
                    ->getParameters()
                : null;
        }

        $class = $callee->getDeclaringClass();

        return $class->isBuiltin() && method_exists($class->getName(), $callee->getName())
            // @phpstan-ignore phpstanApi.runtimeReflection (the names must be those of the running PHP, not of the stubs)
            ? new ReflectionMethod($class->getName(), $callee->getName())
                ->getParameters()
            : null;
    }

    private function isCheckedClass(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        return null !== $classReflection && $this->classNamespaceMatcher->matches($classReflection->getName());
    }

    private function findCallee(CallLike $call, Scope $scope): ExtendedMethodReflection|FunctionReflection|null
    {
        return match (true) {
            $call instanceof FuncCall => $this->findFunction($call, $scope),
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $this->findMethod(
                type: $scope->getType($call->var),
                name: $call->name,
                scope: $scope,
            ),
            $call instanceof StaticCall => $this->findMethod(
                type: $this->resolveClassType($call->class, $scope),
                name: $call->name,
                scope: $scope,
            ),
            $call instanceof New_ => $this->findConstructor($call, $scope),
            default => null,
        };
    }

    private function findFunction(FuncCall $call, Scope $scope): ?FunctionReflection
    {
        if (false === $call->name instanceof Name || false === $this->reflectionProvider->hasFunction($call->name, $scope)) {
            return null;
        }

        return $this->reflectionProvider->getFunction($call->name, $scope);
    }

    private function findConstructor(New_ $call, Scope $scope): ?ExtendedMethodReflection
    {
        if (false === $call->class instanceof Name && false === $call->class instanceof Expr) {
            return null;
        }

        return $scope->getMethodReflection($this->resolveClassType($call->class, $scope), self::CONSTRUCTOR);
    }

    private function findMethod(Type $type, Identifier|Expr $name, Scope $scope): ?ExtendedMethodReflection
    {
        if (false === $name instanceof Identifier) {
            return null;
        }

        return $scope->getMethodReflection($type, $name->toString());
    }

    private function resolveClassType(Name|Expr $class, Scope $scope): Type
    {
        if ($class instanceof Name) {
            return $scope->resolveTypeByName($class);
        }

        return $scope->getType($class);
    }
}
