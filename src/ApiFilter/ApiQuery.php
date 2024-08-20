<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use Closure;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Uid\Uuid;

class ApiQuery
{
    private const string UUID_TYPE = 'uuid';

    protected QueryBuilder $dqb;

    /**
     * @var array<string, list<CustomInnerFilterInterface>>
     */
    protected array $customInnerFilters = [];


    /**
     * @var array<string, list<FieldCallbackInterface>>
     */
    protected array $fieldCallbacks = [];

    /**
     * @param list<CustomFilterInterface> $customFilters
     * @param list<CustomOrderInterface> $customOrders
     * @param list<CustomInnerFilterInterface> $customInnerFilters
     * @param list<FieldCallbackInterface> $fieldCallbacks
     *
     * @throws ORMException
     */
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected ClassMetadata $metadata,
        protected ApiParams $apiParams,
        #[Deprecated] protected ?CustomFilterInterface $customFilter = null,
        protected bool $fetchOneAdditionalRecord = false,
        protected array $customFilters = [],
        protected array $customOrders = [],
        array $customInnerFilters = [],
        array $fieldCallbacks = [],
    ) {
        if ($this->customFilter instanceof CustomFilterInterface && empty($this->customFilters)) {
            $this->customFilters = [$this->customFilter];
        }

        foreach ($customInnerFilters as $filter) {
            if (false === isset($this->customInnerFilters[$filter->field()])) {
                $this->customInnerFilters[$filter->field()] = [];
            }
            $this->customInnerFilters[$filter->field()][] = $filter;
        }

        foreach ($fieldCallbacks as $fieldCallback) {
            if (false === isset($this->fieldCallbacks[$fieldCallback->field()])) {
                $this->fieldCallbacks[$fieldCallback->field()] = [];
            }
            $this->fieldCallbacks[$fieldCallback->field()][] = $fieldCallback;
        }

        $this->setQueryBuilder();
        $this->applyFilters();
    }

    /**
     * @throws ORMException
     */
    public function getTotalCount(): int
    {
        if ($this->apiParams->isBigTable()) {
            return $this->apiParams->getLimit() + $this->apiParams->getOffset() + 1;
        }

        return (int) $this->dqb
            ->select('count(t)')
            ->getQuery()->getSingleScalarResult()
        ;
    }

    public function getData(): array
    {
        $this->applyOrders();

        $limit = $this->apiParams->getLimit();
        if ($this->fetchOneAdditionalRecord) {
            ++$limit;
        }

        return $this->dqb
            ->select('t')
            ->setFirstResult($this->apiParams->getOffset())
            ->setMaxResults($limit)
            ->getQuery()->getResult()
        ;
    }

    private function setQueryBuilder(): void
    {
        $this->dqb = $this->entityManager->createQueryBuilder();
        $this->dqb->from($this->metadata->getName(), 't');
    }

    private function applyOrders(): void
    {
        foreach ($this->apiParams->getOrder() as $field => $direction) {
            $this->dqb->addOrderBy('t.' . $field, $direction);
        }

        foreach ($this->customOrders as $customOrder) {
            $customOrder->apply($this->dqb, $this->apiParams);
        }
    }

    /**
     * @throws ORMException
     */
    private function applyFilters(): void
    {
        $iter = 0;
        foreach ($this->apiParams->getFilter() as $filterVariant => $filter) {
            foreach ($filter as $field => $value) {
                /** @var string $fieldName */
                $fieldName = str_replace('.', '_', $field);
                $paramName = $fieldName . '_' . ++$iter;

                $this->applyFieldCallbacks($field, $value);
                if ($this->applyInnerFiltersOnField($field)) {
                    continue;
                }

                switch ($filterVariant) {
                    case ApiParams::FILTER_CUSTOM:
                        foreach ($this->customFilters as $customFilter) {
                            $customFilter->apply($this->dqb, $field, $value);
                        }
                        break;
                    case ApiParams::FILTER_STARTS_WITH:
                        $this->dqb->andWhere("t.{$field} LIKE :{$paramName}");
                        $this->dqb->setParameter($paramName, trim($value) . '%');

                        break;
                    case ApiParams::FILTER_ENDS_WITH:
                        $this->dqb->andWhere("t.{$field} LIKE :{$paramName}");
                        $this->dqb->setParameter($paramName, '%' . trim($value));

                        break;
                    case ApiParams::FILTER_CONTAINS:
                        $this->dqb->andWhere("t.{$field} LIKE :{$paramName}");
                        $this->dqb->setParameter($paramName, '%' . trim($value) . '%');

                        break;
                    case ApiParams::FILTER_MEMBER_OF:
                        foreach ($this->getFilterValue($filterVariant, $field, $value) as $member) {
                            $this->dqb->andWhere(":{$paramName} MEMBER OF t.{$field}");
                            $this->dqb->setParameter($paramName, $member);
                        }

                        break;
                    default:
                        $this->dqb->andWhere(
                            $this->dqb->expr()->{$filterVariant}(
                                't.' . $field,
                                ':' . $paramName
                            )
                        );
                        $this->dqb->setParameter(
                            $paramName,
                            $this->getFilterValue($filterVariant, $field, $value)
                        );
                }
            }
        }

        $this->applyRemainingInnerFilters();
    }

    /**
     * @throws ORMException
     */
    private function getFilterValue(string $filterName, string $field, mixed $value): mixed
    {
        if (in_array($filterName, ApiParams::ARRAY_FILTERS, true)) {
            return explode(',', $value);
        }

        $fieldDbType = $this->metadata->getTypeOfField($field);
        switch ($fieldDbType) {
            case Types::DATETIME_MUTABLE:
                return new DateTime($value);
            case Types::DATETIME_IMMUTABLE:
                return new DateTimeImmutable($value);
            case Types::BOOLEAN:
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (self::UUID_TYPE === $fieldDbType && class_exists(Uuid::class)) {
            /** @psalm-suppress UndefinedClass */
            return Uuid::fromString($value)->toBinary();
        }

        if (ApiParams::FILTER_MEMBER_OF === $filterName) {
            $members = [];
            foreach (explode(',', $value) as $memberId) {
                $member = $this->entityManager->find(
                    $this->metadata->getAssociationMapping($field)['targetEntity'],
                    $memberId
                );

                if ($member) {
                    $members[] = $member;
                }
            }

            return $members;
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function applyFieldCallbacks(string $field, int|string $value): void
    {
        if (false === isset($this->fieldCallbacks[$field])) {
            return;
        }

        foreach ($this->fieldCallbacks[$field] as $fieldCallback) {
            $fieldCallback($value);
        }
    }

    private function applyInnerFiltersOnField(string $field): bool
    {
        if (false === isset($this->customInnerFilters[$field])) {
            return false;
        }

        $applied = false;
        foreach ($this->customInnerFilters[$field] as $innerFilter) {
            $applied = $innerFilter->apply($this->dqb, true) ?: $applied;
        }
        unset($this->customInnerFilters[$field]);

        return $applied;
    }

    private function applyRemainingInnerFilters(): void
    {
        foreach ($this->customInnerFilters as $customInnerFilters) {
            foreach ($customInnerFilters as $innerFilter) {
                $innerFilter->apply($this->dqb, false);
            }
        }
    }
}
