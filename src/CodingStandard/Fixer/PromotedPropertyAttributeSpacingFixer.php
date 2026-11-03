<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\CodingStandard\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class PromotedPropertyAttributeSpacingFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    private const array PROMOTION_KINDS = [
        CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC,
        CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED,
        CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE,
        T_READONLY,
        T_PUBLIC_SET,
        T_PROTECTED_SET,
        T_PRIVATE_SET,
    ];

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'In a multi-line constructor with an attributed promoted parameter, parameters are separated by one blank line.',
            [
                new CodeSample(
                    "<?php\nfinal class Foo\n{\n    public function __construct(\n        #[Serialize]\n        private(set) string \$headline,\n        private readonly Bar \$bar,\n    ) {\n    }\n}\n"
                ),
            ]
        );
    }

    public function getName(): string
    {
        return 'AnzuSystems/promoted_property_attribute_spacing';
    }

    /**
     * Must run after MethodArgumentSpaceFixer (30), StandaloneLinePromotedPropertyFixer (40) and
     * MultilinePromotedPropertiesFixer (1), which rewrite the line breaks between parameters.
     */
    public function getPriority(): int
    {
        return -1;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_ATTRIBUTE) && $tokens->isAnyTokenKindsFound(self::PROMOTION_KINDS);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach (array_keys($tokens->findGivenKind(T_FUNCTION)) as $functionIndex) {
            $nameIndex = $tokens->getNextMeaningfulToken($functionIndex);

            if (null === $nameIndex || false === $tokens[$nameIndex]->equals([T_STRING, '__construct'], false)) {
                continue;
            }

            $openIndex = $tokens->getNextTokenOfKind($nameIndex, ['(']);
            $closeIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $openIndex);

            if (false === str_contains($tokens->generatePartialCode($openIndex, $closeIndex), "\n")) {
                continue;
            }

            $parameters = $this->findParameters(tokens: $tokens, openIndex: $openIndex, closeIndex: $closeIndex);
            $hasAttributedPromotedParameter = array_any(
                $parameters,
                fn (int $endIndex, int $startIndex): bool => $this->isAttributedPromotedParameter(
                    tokens: $tokens,
                    startIndex: $startIndex,
                    endIndex: $endIndex,
                ),
            );

            if (false === $hasAttributedPromotedParameter) {
                continue;
            }

            $this->fixParameterList(
                tokens: $tokens,
                openIndex: $openIndex,
                closeIndex: $closeIndex,
                parameters: $parameters,
            );
        }
    }

    /**
     * @param array<int, int> $parameters
     */
    private function fixParameterList(Tokens $tokens, int $openIndex, int $closeIndex, array $parameters): void
    {
        $this->setLineBreaks(tokens: $tokens, index: $openIndex + 1, lineBreaks: 1);
        $this->setLineBreaks(tokens: $tokens, index: $closeIndex - 1, lineBreaks: 1);

        foreach (array_slice(array_keys($parameters), 1) as $startIndex) {
            $separatorIndex = $this->findSeparatorIndex(tokens: $tokens, startIndex: $startIndex);

            if (is_int($separatorIndex)) {
                $this->setLineBreaks(tokens: $tokens, index: $separatorIndex, lineBreaks: 2);
            }
        }
    }

    /**
     * @return array<int, int> start index => end index of each parameter, without the commas
     */
    private function findParameters(Tokens $tokens, int $openIndex, int $closeIndex): array
    {
        $parameters = [];
        $startIndex = $openIndex + 1;

        for ($index = $startIndex; $index < $closeIndex; ++$index) {
            $block = Tokens::detectBlockType($tokens[$index]);

            if (null !== $block && $block['isStart']) {
                $index = $tokens->findBlockEnd($block['type'], $index);

                continue;
            }

            if (false === $tokens[$index]->equals(',')) {
                continue;
            }

            $parameters[$startIndex] = $index - 1;
            $startIndex = $index + 1;
        }

        if ($tokens->getNextMeaningfulToken($startIndex - 1) < $closeIndex) {
            $parameters[$startIndex] = $closeIndex - 1;
        }

        return $parameters;
    }

    private function isAttributedPromotedParameter(Tokens $tokens, int $startIndex, int $endIndex): bool
    {
        $indexes = range($startIndex, $endIndex);

        return array_any($indexes, static fn (int $index): bool => $tokens[$index]->isGivenKind(T_ATTRIBUTE))
            && array_any($indexes, static fn (int $index): bool => $tokens[$index]->isGivenKind(self::PROMOTION_KINDS));
    }

    /**
     * The first line break after the comma; a comment on the line of the comma stays with the previous parameter.
     */
    private function findSeparatorIndex(Tokens $tokens, int $startIndex): ?int
    {
        for ($index = $startIndex; $tokens[$index]->isWhitespace() || $tokens[$index]->isComment(); ++$index) {
            if ($this->isLineBreak($tokens[$index])) {
                return $index;
            }
        }

        return null;
    }

    private function setLineBreaks(Tokens $tokens, int $index, int $lineBreaks): void
    {
        if (false === $this->isLineBreak($tokens[$index])) {
            return;
        }

        $whitespace = $tokens[$index]->getContent();
        $indent = substr($whitespace, strrpos($whitespace, "\n") + 1);
        $fixed = str_repeat($this->whitespacesConfig->getLineEnding(), $lineBreaks) . $indent;

        if ($fixed !== $whitespace) {
            $tokens[$index] = new Token([T_WHITESPACE, $fixed]);
        }
    }

    private function isLineBreak(Token $token): bool
    {
        return $token->isWhitespace() && str_contains($token->getContent(), "\n");
    }
}
