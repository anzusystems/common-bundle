<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use Mcp\Schema\Elicitation\BooleanSchemaDefinition;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Asks by returning an InputRequiredResult, never through ClientGateway::elicit(): on a handshake revision elicit() holds
 * the FPM worker until the answer, on a modern one it abandons the fiber and the finally of the handler logs a false success.
 */
final readonly class McpWriteConfirmation
{
    public const string CONFIRM_KEY_PREFIX = 'confirm_';
    public const string CONFIRM_FIELD = 'confirm';
    public const string FIELD_TITLE_KEY = 'writeConfirmation.confirm';
    public const string TRANSLATION_DOMAIN = 'mcp';
    public const string REFUSED_MESSAGE = 'The confirmation of "%s" was refused or could not be shown to the user, so nothing changed. Tell the user and do not call it again unless they ask for it.';
    public const string PREVIEW_HASH_STALE_MESSAGE = 'The arguments or the saved data differ from what %1$s returned this previewHash for: call %1$s again, show the user the result and pass the new previewHash.';

    public function __construct(
        private TranslatorInterface $translator,
        private CurrentAnzuUserProvider $currentUserProvider,
        private ?string $fallbackLocale = null,
    ) {
    }

    public function isSupportedBy(RequestContext $context): bool
    {
        return true === $context->getClientCapabilities()?->elicitationForm;
    }

    public function isAnswered(RequestContext $context, McpPreviewHashAwareInterface $input): bool
    {
        return $context->getInputContext()?->has($this->createKey($input)) ?? false;
    }

    public function assertConfirmed(string $toolName, RequestContext $context, McpPreviewHashAwareInterface $input): void
    {
        $answer = $context->getInputContext()?->elicitResult($this->createKey($input));
        if (null === $answer || false === $answer->isAccepted() || true !== ($answer->content[self::CONFIRM_FIELD] ?? null)) {
            throw new McpToolCallRejectedException(sprintf(self::REFUSED_MESSAGE, $toolName));
        }
    }

    public function createInputRequiredResult(
        string $previewToolName,
        McpPreviewHashAwareInterface $input,
        McpWritePreviewInterface $preview,
    ): ?InputRequiredResult {
        $previewHash = $preview->previewHash;
        if (null === $previewHash) {
            return null;
        }
        if (StringHelper::isNotSame($previewHash, $input->previewHash)) {
            throw new McpToolCallRejectedException(sprintf(self::PREVIEW_HASH_STALE_MESSAGE, $previewToolName));
        }
        $locale = $this->resolveLocale();
        $message = $preview->confirmationMessage;

        return new InputRequiredResult([
            $this->createKey($input) => ElicitRequest::forForm(
                $this->translate(key: $message->key, parameters: $message->parameters, locale: $locale),
                new ElicitationSchema(
                    // No default: a preset true would turn an unticked box into consent.
                    [self::CONFIRM_FIELD => new BooleanSchemaDefinition(title: $this->translate(key: self::FIELD_TITLE_KEY, parameters: [], locale: $locale))],
                    [self::CONFIRM_FIELD],
                ),
            ),
        ]);
    }

    private function createKey(McpPreviewHashAwareInterface $input): string
    {
        return self::CONFIRM_KEY_PREFIX . $input->previewHash;
    }

    private function resolveLocale(): ?string
    {
        return $this->currentUserProvider->getCurrentUser()
            ->getLocale() ?? $this->fallbackLocale;
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function translate(string $key, array $parameters, ?string $locale): string
    {
        return $this->translator->trans(
            id: $key,
            parameters: $parameters,
            domain: self::TRANSLATION_DOMAIN,
            locale: $locale,
        );
    }
}
