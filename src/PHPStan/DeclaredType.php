<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan;

use PHPStan\Type\Type;

final readonly class DeclaredType
{
    public function __construct(
        public string $subject,
        public Type $type,
        public int $line,
    ) {
    }
}
