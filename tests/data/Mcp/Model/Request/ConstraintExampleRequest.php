<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class ConstraintExampleRequest
{
    /**
     * @var list<string>
     */
    #[Assert\All([new Assert\Length(max: 5), new Assert\NotBlank()])]
    #[Serialize]
    public array $all = [];

    #[Assert\Range(min: 1, max: 5)]
    #[Serialize]
    public int $range = 1;

    #[Assert\GreaterThan(0)]
    #[Serialize]
    public int $greaterThanInteger = 1;

    #[Assert\GreaterThan(0)]
    #[Serialize]
    public float $greaterThanNumber = 1.0;

    #[Assert\GreaterThan(0.5)]
    #[Serialize]
    public float $greaterThanFraction = 1.0;

    #[Assert\Positive]
    #[Serialize]
    public int $positive = 1;

    #[Assert\GreaterThanOrEqual(1)]
    #[Serialize]
    public int $greaterThanOrEqual = 1;

    #[Assert\LessThan(10)]
    #[Serialize]
    public int $lessThanInteger = 1;

    #[Assert\LessThan(10)]
    #[Serialize]
    public float $lessThanNumber = 1.0;

    #[Assert\LessThanOrEqual(10)]
    #[Serialize]
    public int $lessThanOrEqual = 1;

    #[Assert\Choice(choices: ['first', 'second'])]
    #[Serialize]
    public string $choice = 'first';

    #[Assert\Choice(choices: ['first', 'second'])]
    #[Serialize]
    public ?string $nullableChoice = null;

    #[Assert\NotBlank]
    #[Serialize]
    public string $notBlank = '';

    #[Assert\NotBlank(allowNull: true)]
    #[Serialize]
    public ?string $notBlankAllowingNull = null;

    #[Assert\NotNull]
    #[Serialize]
    public ?string $notNull = null;

    /**
     * @var list<string>
     */
    #[Assert\Count(min: 1)]
    #[Serialize]
    public array $countMin = [];
}
