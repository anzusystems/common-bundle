<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Generator;
use OpenApi\Attributes\QueryParameter;
use Symfony\Component\HttpFoundation\Request;

final class ApiParams
{
    public const string FILTER_LT = 'lt';
    public const string FILTER_IN = 'in';
    public const string FILTER_NIN = 'notIn';
    public const string FILTER_ENDS_WITH = 'endsWith';
    public const string FILTER_STARTS_WITH = 'startsWith';
    public const string FILTER_MEMBER_OF = 'memberOf';
    public const string FILTER_CONTAINS = 'contains';
    public const string FILTER_NEQ = 'neq';
    public const string FILTER_GTE = 'gte';
    public const string FILTER_GT = 'gt';
    public const string FILTER_EQ = 'eq';
    public const string FILTER_CUSTOM = 'custom';
    public const string FILTER_LTE = 'lte';
    public const array AVAILABLE_FILTERS = [
        self::FILTER_EQ,
        self::FILTER_NEQ,
        self::FILTER_IN,
        self::FILTER_NIN,
        self::FILTER_STARTS_WITH,
        self::FILTER_ENDS_WITH,
        self::FILTER_CONTAINS,
        self::FILTER_GT,
        self::FILTER_LT,
        self::FILTER_GTE,
        self::FILTER_LTE,
        self::FILTER_MEMBER_OF,
        self::FILTER_CUSTOM,
    ];
    public const array ARRAY_FILTERS = [
        self::FILTER_IN,
        self::FILTER_NIN,
    ];
    private const string LIMIT = 'limit';
    private const string OFFSET = 'offset';
    private const string FILTER = 'filter';
    private const string ORDER = 'order';
    private const string BIG_TABLE = 'bigTable';

    private const array DEFAULTS = [
        self::LIMIT => 20,
        self::OFFSET => 0,
        self::FILTER => [],
        self::ORDER => [],
        self::BIG_TABLE => true,
    ];

    #[Serialize]
    private int $limit;

    #[Serialize]
    private int $offset;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $filter;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $order;

    #[Serialize]
    private bool $bigTable;

    public function __construct()
    {
        $this->limit = self::DEFAULTS[self::LIMIT];
        $this->offset = self::DEFAULTS[self::OFFSET];
        $this->filter = self::DEFAULTS[self::FILTER];
        $this->order = self::DEFAULTS[self::ORDER];
        $this->bigTable = self::DEFAULTS[self::BIG_TABLE];
    }

    public static function generateAllAvailableOAQueryParams(): Generator
    {
        foreach (self::AVAILABLE_FILTERS as $filterName) {
            yield new QueryParameter(name: self::FILTER . '_' . $filterName);
        }
        yield new QueryParameter(name: self::LIMIT);
        yield new QueryParameter(name: self::OFFSET);
        yield new QueryParameter(name: self::ORDER);
    }

    public function setFromRequest(Request $request): self
    {
        $this->limit = $request->query->getInt(self::LIMIT, self::DEFAULTS[self::LIMIT]);
        $this->offset = $request->query->getInt(self::OFFSET, self::DEFAULTS[self::OFFSET]);
        $this->order = $request->query->all(self::ORDER) ?: self::DEFAULTS[self::ORDER];
        $this->bigTable = $request->query->getBoolean(self::BIG_TABLE, self::DEFAULTS[self::BIG_TABLE]);
        foreach (self::AVAILABLE_FILTERS as $filterVariant) {
            $filter = $request->query->all(self::FILTER . '_' . $filterVariant);
            if ($filter) {
                $this->filter[$filterVariant] = $filter;
            }
        }

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getOrder(): array
    {
        return $this->order;
    }

    public function getFilter(): array
    {
        return $this->filter;
    }

    public function setLimit(mixed $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function setOffset(mixed $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function setFilter(mixed $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    public function setOrder(mixed $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @deprecated
     */
    public function isBigTable(): bool
    {
        return $this->bigTable;
    }

    /**
     * @deprecated
     */
    public function setBigTable(mixed $bigTable): self
    {
        $this->bigTable = $bigTable;

        return $this;
    }
}
