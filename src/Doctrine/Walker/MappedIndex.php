<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Walker;

use Doctrine\ORM\Mapping\ClassMetadata;

final readonly class MappedIndex
{
    /**
     * @param ClassMetadata<object> $tableMetadata the metadata of the class whose table maps the index
     * @param list<string> $columns
     */
    public function __construct(
        public ClassMetadata $tableMetadata,
        public array $columns,
    ) {
    }
}
