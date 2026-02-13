<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiQuery;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use AnzuSystems\CommonBundle\ApiFilter\CustomInnerFilterInterface;
use AnzuSystems\CommonBundle\ApiFilter\CustomOrderInterface;
use AnzuSystems\CommonBundle\ApiFilter\FieldCallbackInterface;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use Closure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use DomainException;
use JetBrains\PhpStorm\Deprecated;

/**
 * @template T of object
 *
 * @template-extends ServiceEntityRepository<T>
 */
abstract class AbstractAnzuRepository extends ServiceEntityRepository implements AnzuRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, $this->getEntityClass());
    }

    /**
     * @return ArrayCollection<int|string, T>
     */
    public function getAllById(int | string ...$ids): ArrayCollection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->where('entity.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('FIELD(entity.id, :ids)')
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * @return ArrayCollection<int|string, T>
     */
    public function getAllByIdIndexed(int | string ...$id): ArrayCollection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity', 'entity.id')
                ->where('entity.id IN (:ids)')
                ->setParameter('ids', $id)
                ->orderBy('FIELD(entity.id, :ids)')
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * @param list<CustomFilterInterface> $customFilters
     * @param list<CustomOrderInterface> $customOrders
     * @param list<CustomInnerFilterInterface> $customInnerFilters
     * @param list<FieldCallbackInterface> $fieldCallbacks
     *
     * @throws ORMException
     */
    public function findByApiParams(
        ApiParams $apiParams,
        #[Deprecated]
        ?CustomFilterInterface $customFilter = null,
        array $customFilters = [],
        array $customOrders = [],
        array $customInnerFilters = [],
        array $fieldCallbacks = [],
        ?string $objectClass = null,
        ?Closure $mapDataFn = null,
    ): ApiResponseList {
        if ($customFilter instanceof CustomFilterInterface && empty($customFilters)) {
            $customFilters = [$customFilter];
        }

        $apiQuery = new ApiQuery(
            entityManager: $this->getEntityManager(),
            metadata: $this->getClassMetadata(),
            apiParams: $apiParams,
            customFilters: $customFilters,
            customOrders: $customOrders,
            customInnerFilters: $customInnerFilters,
            fieldCallbacks: $fieldCallbacks
        );

        $data = $apiQuery->getData();
        $itemsCount = count($data);
        $totalCount = $apiQuery->getTotalCount();
        if ($apiParams->getLimit() > $itemsCount) {
            $totalCount = $itemsCount + $apiParams->getOffset();
        }

        if ($mapDataFn) {
            $data = array_map($mapDataFn, $data);
        }

        return (new ApiResponseList())
            ->setBigTable($apiParams->isBigTable())
            ->setTotalCount($totalCount)
            ->setData($data)
        ;
    }

    /**
     * @param list<CustomFilterInterface> $customFilters
     * @param list<CustomOrderInterface> $customOrders
     * @param list<CustomInnerFilterInterface> $customInnerFilters
     * @param list<FieldCallbackInterface> $fieldCallbacks
     *
     * @throws ORMException
     */
    public function findByApiParamsWithInfiniteListing(
        ApiParams $apiParams,
        #[Deprecated]
        ?CustomFilterInterface $customFilter = null,
        array $customFilters = [],
        array $customOrders = [],
        array $customInnerFilters = [],
        array $fieldCallbacks = [],
        ?string $objectClass = null,
        ?Closure $mapDataFn = null,
    ): ApiInfiniteResponseList {
        if ($customFilter instanceof CustomFilterInterface && empty($customFilters)) {
            $customFilters = [$customFilter];
        }

        $apiQuery = new ApiQuery(
            entityManager: $this->getEntityManager(),
            metadata: $this->getClassMetadata(),
            apiParams: $apiParams,
            fetchOneAdditionalRecord: true,
            customFilters: $customFilters,
            customOrders: $customOrders,
            customInnerFilters: $customInnerFilters,
            fieldCallbacks: $fieldCallbacks
        );
        $data = $apiQuery->getData();
        $totalCount = $apiParams->getLimit() + $apiParams->getOffset() + 1;
        if (empty($data)) {
            $totalCount = 0;
        }

        if ($mapDataFn) {
            $data = array_map($mapDataFn, $data);
        }

        return (new ApiInfiniteResponseList())
            ->setHasNextPage(
                count($data) > $apiParams->getLimit()
            )
            ->setData(
                array_slice($data, 0, $apiParams->getLimit())
            )
            ->setTotalCount($totalCount)
        ;
    }

    public function exists(int | string $id): bool
    {
        return (bool) $this->count([
            'id' => $id,
        ]);
    }

    public function getTouchedByUserQuery(AnzuUser $user): QueryBuilder
    {
        if (false === is_a($this->getEntityClass(), UserTrackingInterface::class, true)) {
            throw new DomainException(
                sprintf(
                    'The class `%s` is not of `%s`. Forgot to override `%s`?',
                    static::class,
                    UserTrackingInterface::class,
                    __METHOD__
                )
            );
        }

        return $this->createQueryBuilder('entity')
            ->select('entity')
            ->where('IDENTITY(entity.createdBy) = :userId')
            ->orWhere('IDENTITY(entity.modifiedBy) = :userId')
            ->setParameter('userId', $user->getId())
        ;
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;
}
