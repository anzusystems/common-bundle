<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

interface FieldCallbackInterface
{
    public function field(): string;

    public function __invoke(string | int $value): void;
}
