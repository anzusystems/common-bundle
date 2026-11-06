<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Repository\AnzuRepositoryInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class McpExistingIdValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === $constraint instanceof McpExistingId) {
            throw new UnexpectedTypeException($constraint, McpExistingId::class);
        }
        if (null === $value) {
            return;
        }
        if (is_array($value)) {
            $this->validateIds($value, $constraint);

            return;
        }
        if (false === is_int($value)) {
            throw new UnexpectedValueException($value, 'int|list<int>');
        }
        if (false === empty($this->findExistingIds($constraint->entity, [$value]))) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->setParameter(McpExistingId::ID_PLACEHOLDER, (string) $value)
            ->setParameter(McpExistingId::RESOLVER_TOOL_PLACEHOLDER, $constraint->resolverTool)
            ->addViolation()
        ;
    }

    /**
     * @param array<array-key, mixed> $ids
     */
    private function validateIds(array $ids, McpExistingId $constraint): void
    {
        if (empty($ids)) {
            return;
        }
        $ids = $this->assertIntIds($ids);
        $missingIds = array_diff($ids, $this->findExistingIds($constraint->entity, $ids))
            |> array_unique(...)
            |> array_values(...);
        if (empty($missingIds)) {
            return;
        }

        $this->context
            ->buildViolation($constraint->listMessage)
            ->setParameter(McpExistingId::IDS_PLACEHOLDER, $this->formatIds($missingIds))
            ->setParameter(McpExistingId::RESOLVER_TOOL_PLACEHOLDER, $constraint->resolverTool)
            ->addViolation()
        ;
    }

    /**
     * @param array<array-key, mixed> $ids
     *
     * @return list<int>
     */
    private function assertIntIds(array $ids): array
    {
        foreach ($ids as $id) {
            if (false === is_int($id)) {
                throw new UnexpectedValueException($id, 'int');
            }
        }

        /** @var list<int> $intIds */
        $intIds = array_values($ids);

        return $intIds;
    }

    /**
     * @param class-string $entityClass
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function findExistingIds(string $entityClass, array $ids): array
    {
        $repository = $this->managerRegistry->getRepository($entityClass);
        if (false === $repository instanceof AnzuRepositoryInterface) {
            throw new LogicException(sprintf('The repository of "%s" must implement %s.', $entityClass, AnzuRepositoryInterface::class));
        }

        return $repository->findExistingIds($ids);
    }

    /**
     * @param list<int> $ids
     */
    private function formatIds(array $ids): string
    {
        return implode(McpExistingId::ID_SEPARATOR, array_map(static fn (int $id): string => sprintf(McpExistingId::ID_FORMAT, $id), $ids));
    }
}
