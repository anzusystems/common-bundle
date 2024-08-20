<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

interface FieldCallbackInterface
{
    public function __invoke(string | int $value): void;
    public function field(): string;
}
