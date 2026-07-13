<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Monolog;

use JsonException;
use Monolog\LogRecord;

/**
 * Expands the JSON-encoded LogContext `content` string back into an array so handlers reporting
 * structured context (e.g. the Sentry monolog handler with fillExtraContext) show the individual
 * fields instead of one opaque JSON string.
 *
 * Not registered globally on purpose — the journal/audit mongo pipeline persists `content` as
 * a string. Push it onto the specific outbound handler instead:
 *
 *   $services->set('sentry.monolog.handler', SentryHandler::class)
 *       ->call('pushProcessor', [service(LogContextContentProcessor::class)]);
 */
final class LogContextContentProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $content = $record->context['content'] ?? null;
        if (false === is_string($content) || '' === $content) {
            return $record;
        }

        try {
            $decodedContent = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $record;
        }
        if (false === is_array($decodedContent)) {
            return $record;
        }

        return $record->with(context: array_merge($record->context, ['content' => $decodedContent]));
    }
}
