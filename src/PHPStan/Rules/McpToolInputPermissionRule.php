<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan\Rules;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;

/**
 * A forgotten check is no failure a caller would see: the tool just reads what the user may not.
 *
 * @implements Rule<InClassNode>
 */
final readonly class McpToolInputPermissionRule implements Rule
{
    public const string MESSAGE = 'MCP tool %s takes a request that implements %s, so __invoke() must call denyAccessUnlessGranted(%s, $%s) outside any branch.';

    private const string INVOKE_METHOD = '__invoke';
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
            ->getMethod(self::INVOKE_METHOD);
        $parameter = array_first($method?->getParams() ?? []);
        if (null === $method || null === $parameter || false === $parameter->type instanceof Name || false === $parameter->var instanceof Variable) {
            return [];
        }
        $requestClass = $parameter->type->toString();
        if (false === $this->reflectionProvider->hasClass($requestClass)) {
            return [];
        }
        $request = $this->reflectionProvider->getClass($requestClass);
        $parameterName = (string) $parameter->var->name;

        $errors = [];
        foreach ($this->inputPermissions as $interface => $permission) {
            if (false === $request->implementsInterface($interface) || $this->checksRequest($method, $permission, $parameterName, $scope)) {
                continue;
            }
            $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE, $classReflection->getName(), $interface, $permission, $parameterName))
                ->identifier('anzu.mcp.toolInputPermission')
                ->line($method->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function checksRequest(ClassMethod $method, string $permission, string $parameterName, Scope $scope): bool
    {
        foreach ($method->getStmts() ?? [] as $statement) {
            if ($statement instanceof Expression && $this->isCheck($statement->expr, $permission, $parameterName, $scope)) {
                return true;
            }
        }

        return false;
    }

    private function isCheck(Node $expression, string $permission, string $parameterName, Scope $scope): bool
    {
        if (false === $expression instanceof MethodCall || false === $expression->name instanceof Identifier || self::CHECK_METHOD !== $expression->name->toString()) {
            return false;
        }
        [$attribute, $subject] = array_pad($expression->getArgs(), 2, null);

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
