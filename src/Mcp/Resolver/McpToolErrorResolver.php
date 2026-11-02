<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use Throwable;

final readonly class McpToolErrorResolver
{
    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    public function __construct(
        private array $toolErrorExceptions = [],
    ) {
    }

    public function resolveMessage(Throwable $exception): ?string
    {
        foreach ($this->toolErrorExceptions as $exceptionClass => $message) {
            if ($exception instanceof $exceptionClass) {
                return $message;
            }
        }

        return null;
    }
}
