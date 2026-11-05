<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

final readonly class McpToolErrorResolver
{
    public const string ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to access the requested resource.';
    public const string INVALID_ARGUMENTS_PREFIX = 'Invalid arguments: ';

    private const string VIOLATION_FORMAT = '%s: %s';
    private const string VIOLATION_SEPARATOR = ' ';

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    public function __construct(
        private array $toolErrorExceptions = [],
    ) {
    }

    /**
     * What the agent can fix or has to accept; the message is safe to show as it is.
     */
    public function resolveRejectionMessage(Throwable $exception): ?string
    {
        return match (true) {
            $exception instanceof McpToolCallRejectedException,
            $exception instanceof McpToolInputException => $exception->getMessage(),
            $exception instanceof ValidationException => $this->formatValidationException($exception),
            $exception instanceof AccessDeniedException => self::ACCESS_DENIED_MESSAGE,
            default => null,
        };
    }

    public function resolveBackendMessage(Throwable $exception): ?string
    {
        foreach ($this->toolErrorExceptions as $exceptionClass => $message) {
            if ($exception instanceof $exceptionClass) {
                return $message;
            }
        }

        return null;
    }

    public function formatViolations(ConstraintViolationListInterface $violations): string
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf(self::VIOLATION_FORMAT, $violation->getPropertyPath(), $violation->getMessage());
        }

        return $this->formatInvalidArguments($messages);
    }

    private function formatValidationException(ValidationException $exception): string
    {
        $messages = [];
        foreach ($exception->getFormattedErrors() as $field => $fieldMessages) {
            foreach ($fieldMessages as $fieldMessage) {
                $messages[] = sprintf(self::VIOLATION_FORMAT, $field, $fieldMessage);
            }
        }

        return $this->formatInvalidArguments($messages);
    }

    /**
     * @param list<string> $messages
     */
    private function formatInvalidArguments(array $messages): string
    {
        return self::INVALID_ARGUMENTS_PREFIX . implode(self::VIOLATION_SEPARATOR, $messages);
    }
}
