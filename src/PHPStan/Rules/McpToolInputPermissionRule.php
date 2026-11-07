<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan\Rules;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;

/**
 * @implements Rule<InClassNode>
 */
final readonly class McpToolInputPermissionRule implements Rule
{
    public const string MESSAGE = 'MCP tool %s takes a request that implements %s, so __invoke() must call denyAccessUnlessGranted(%s, $%s) before any other statement.';

    private const string CHECK_METHOD = 'denyAccessUnlessGranted';

    /**
     * @param array<class-string, string> $inputPermissions the permission a request implementing the interface needs
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $inputPermissions,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (empty($classReflection->getNativeReflection()->getAttributes(AsMcpTool::class))) {
            return [];
        }
        $method = $node->getOriginalNode()
            ->getMethod(McpToolSignatureResolver::INVOKE_METHOD);
        $parameter = array_first($method?->getParams() ?? []);
        if (null === $method || null === $parameter || false === $parameter->var instanceof Variable) {
            return [];
        }
        $requestType = $parameter->type instanceof NullableType ? $parameter->type->type : $parameter->type;
        if (false === $requestType instanceof Name) {
            return [];
        }
        $requestClass = $requestType->toString();
        if (false === $this->reflectionProvider->hasClass($requestClass)) {
            return [];
        }
        $request = $this->reflectionProvider->getClass($requestClass);
        $parameterName = (string) $parameter->var->name;

        $errors = [];
        foreach ($this->inputPermissions as $interface => $permission) {
            if (false === $request->implementsInterface($interface) || $this->isRequestChecked(method: $method, permission: $permission, parameterName: $parameterName, scope: $scope)) {
                continue;
            }
            $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE, $classReflection->getName(), $interface, $permission, $parameterName))
                ->identifier('anzu.mcp.toolInputPermission')
                ->line($method->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function isRequestChecked(ClassMethod $method, string $permission, string $parameterName, Scope $scope): bool
    {
        foreach ($method->getStmts() ?? [] as $statement) {
            if (false === $statement instanceof Expression || false === $this->isCheckCall($statement->expr)) {
                return false;
            }
            if ($this->isPermissionChecked(call: $statement->expr, permission: $permission, parameterName: $parameterName, scope: $scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @phpstan-assert-if-true MethodCall $expression
     */
    private function isCheckCall(Node $expression): bool
    {
        return $expression instanceof MethodCall && $expression->name instanceof Identifier && self::CHECK_METHOD === $expression->name->toString();
    }

    private function isPermissionChecked(MethodCall $call, string $permission, string $parameterName, Scope $scope): bool
    {
        [$attribute, $subject] = array_pad(array: $call->getArgs(), length: 2, value: null);

        if (null === $attribute || false === $subject?->value instanceof Variable || $parameterName !== $subject->value->name) {
            return false;
        }

        return array_any(
            $scope->getType($attribute->value)
                ->getConstantStrings(),
            static fn (ConstantStringType $type): bool => $permission === $type->getValue(),
        );
    }
}
