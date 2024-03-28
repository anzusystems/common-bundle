<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use AnzuSystems\Contracts\Document\Attributes\PersistedName;
use DateTimeImmutable;
use Doctrine\Common\Collections\Order;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use ReflectionClass;
use ReflectionProperty;

final class ApiQueryMongo
{
    private ReflectionClass $classReflection;

    /**
     * @psalm-param class-string $className
     */
    public function __construct(
        private readonly ApiParams $params,
        string $className,
        private readonly bool $fetchOneAdditionalRecord = false,
    ) {
        $this->classReflection = new ReflectionClass($className);
    }

    public function getFilter(): array
    {
        $filter = [];
        foreach ($this->params->getFilter() as $filterVariant => $filterFieldValue) {
            foreach ($filterFieldValue as $field => $value) {
                $persistedName = $this->getPersistedName($field);
                $value = $this->getFilterValue($filterVariant, $field, $value);
                switch ($filterVariant) {
                    case ApiParams::FILTER_GT:
                        $filter[$persistedName]['$gt'] = $value;
                        break;
                    case ApiParams::FILTER_GTE:
                        $filter[$persistedName]['$gte'] = $value;
                        break;
                    case ApiParams::FILTER_LT:
                        $filter[$persistedName]['$lt'] = $value;
                        break;
                    case ApiParams::FILTER_LTE:
                        $filter[$persistedName]['$lte'] = $value;
                        break;
                    case ApiParams::FILTER_IN:
                        $filter[$persistedName]['$in'] = $value;
                        break;
                    case ApiParams::FILTER_NIN:
                        $filter[$persistedName]['$nin'] = $value;
                        break;
                    case ApiParams::FILTER_EQ:
                        $filter[$persistedName]['$eq'] = $value;
                        break;
                    case ApiParams::FILTER_STARTS_WITH:
                        $filter[$persistedName]['$regex'] = new Regex('^' . $value);
                        break;
                    case ApiParams::FILTER_ENDS_WITH:
                        $filter[$persistedName]['$regex'] = new Regex($value . '$');
                        break;
                    case ApiParams::FILTER_CONTAINS:
                        $filter[$persistedName]['$regex'] = new Regex($value);
                        break;
                }
            }
        }

        return $filter;
    }

    public static function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'string' => (string) $value,
            'DateTimeImmutable' => new UTCDateTime(new DateTimeImmutable($value)),
            'oid' => new ObjectId($value),
            default => $value
        };
    }

    public function getOptions(): array
    {
        $options = [];
        $order = $this->params->getOrder();
        if ($order) {
            $sort = [];
            foreach ($order as $field => $direction) {
                $sort[$this->getPersistedName($field)] = Order::Ascending->value === strtoupper($direction) ? 1 : -1;
            }
            $options['sort'] = $sort;
        }
        $limit = $this->params->getLimit();
        if ($this->fetchOneAdditionalRecord) {
            ++$limit;
        }
        $options['limit'] = $limit;
        $options['skip'] = $this->params->getOffset();

        return $options;
    }

    private function getPersistedName(string $field): string
    {
        $fieldAttributes = $this->getPropertyByField($field)->getAttributes(PersistedName::class);
        if (array_key_exists(0, $fieldAttributes)) {
            $persistedNameAttribute = $fieldAttributes[0]->newInstance();
            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if ($persistedNameAttribute instanceof PersistedName) {
                return $persistedNameAttribute->name;
            }
        }

        return $field;
    }

    private function getFilterValue(string $filterVariant, string $field, mixed $value): mixed
    {
        $type = $this->getPropertyType($field);
        if (in_array($filterVariant, ApiParams::ARRAY_FILTERS, true)) {
            return array_map(
                static fn (mixed $val): mixed => self::castValue($type, $val),
                explode(',', $value)
            );
        }

        return self::castValue($type, $value);
    }

    private function getPropertyType(string $field): string
    {
        if ('id' === $field) {
            return 'oid';
        }

        return (string) $this->getPropertyByField($field)->getType();
    }

    /**
     * @todo Rework this to not use reflection, but serialize Metadata
     */
    private function getPropertyByField(string $field): ReflectionProperty
    {
        /** @var non-empty-list<non-empty-string> $propertyPath */
        $propertyPath = explode('.', $field);
        $propertyName = array_pop($propertyPath);
        $class = $this->classReflection;
        foreach ($propertyPath as $curPropName) {
            /** @psalm-var class-string $curClassType */
            $curClassType = (string) $class->getProperty($curPropName)->getType();
            $class = new ReflectionClass($curClassType);
        }

        return $class->getProperty($propertyName);
    }
}
