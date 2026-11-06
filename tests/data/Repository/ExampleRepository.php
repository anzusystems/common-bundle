<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Repository;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;

/**
 * @extends AbstractAnzuRepository<Example>
 */
final class ExampleRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return Example::class;
    }
}
