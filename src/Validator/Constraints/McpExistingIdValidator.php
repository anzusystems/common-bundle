<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class McpExistingIdValidator extends ConstraintValidator
{
    private const string ID_FORMAT = '"%d"';
    private const string ID_SEPARATOR = ', ';
    private const string ALIAS = 'entity';
    private const string IDS_PARAMETER = 'ids';

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
            ->setParameter('{{ id }}', (string) $value)
            ->setParameter('{{ resolverTool }}', $constraint->resolverTool)
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
            ->setParameter('{{ ids }}', $this->formatIds($missingIds))
            ->setParameter('{{ resolverTool }}', $constraint->resolverTool)
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
        $entityManager = $this->managerRegistry->getManagerForClass($entityClass);
        if (false === $entityManager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('No Doctrine ORM entity manager manages "%s".', $entityClass));
        }
        $identifierFields = $entityManager->getClassMetadata($entityClass)
            ->getIdentifierFieldNames();
        if (1 !== count($identifierFields)) {
            throw new LogicException(sprintf('Entity "%s" must have a single identifier field, it has "%s".', $entityClass, implode(self::ID_SEPARATOR, $identifierFields)));
        }
        $identifier = sprintf('%s.%s', self::ALIAS, array_first($identifierFields));

        /** @var list<int> $existingIds */
        $existingIds = $entityManager->createQueryBuilder()
            ->select($identifier)
            ->from($entityClass, self::ALIAS)
            ->where(sprintf('%s IN (:%s)', $identifier, self::IDS_PARAMETER))
            ->setParameter(self::IDS_PARAMETER, $ids)
            ->getQuery()
            ->getSingleColumnResult();

        return $existingIds;
    }

    /**
     * @param list<int> $ids
     */
    private function formatIds(array $ids): string
    {
        return implode(self::ID_SEPARATOR, array_map(static fn (int $id): string => sprintf(self::ID_FORMAT, $id), $ids));
    }
}
