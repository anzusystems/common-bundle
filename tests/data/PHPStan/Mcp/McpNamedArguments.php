<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp;

use Closure;

final class McpNamedArguments
{
    public function __construct(
        public int $first = 0,
        public int $second = 0,
        public int $third = 0,
    ) {
    }

    public static function createFromThree(int $first, int $second, int $third): self
    {
        return new self(first: $first, second: $second, third: $third);
    }

    public function reported(): void
    {
        $this->takeThree(1, 2, 3);
        $this->takeThree(1, second: 2, third: 3);
        self::createFromThree(1, 2, 3);
        new self(1, 2, 3);
        str_replace('a', 'b', 'c');
    }

    /**
     * @param list<int> $numbers
     */
    public function allowed(array $numbers, Closure $closure): void
    {
        $this->takeThree(first: 1, second: 2, third: 3);
        $this->takeTwo(1, 2);
        sprintf('%d %d', 1, 2);
        $this->takeVariadic(1, 2, 3);
        array_udiff($numbers, $numbers, static fn (int $first, int $second): int => $first <=> $second);
        $this->takeThree(...$numbers);
        $closure(1, 2, 3);
        $this->takeWithoutNames(1, 2, 3);
        $firstClassCallable = $this->takeThree(...);
        $firstClassCallable(1, 2, 3);
    }

    private function takeThree(int $first, int $second, int $third): void
    {
    }

    private function takeTwo(int $first, int $second): void
    {
    }

    private function takeVariadic(int $first, int ...$others): void
    {
    }

    /**
     * @no-named-arguments
     */
    private function takeWithoutNames(int $first, int $second, int $third): void
    {
    }
}
