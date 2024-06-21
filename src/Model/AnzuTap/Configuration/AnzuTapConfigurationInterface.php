<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Configuration;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\AnzuMarkTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnzuNodeTransformerInterface;

interface AnzuTapConfigurationInterface
{
    /**
     * @return array<class-string<AnzuNodeTransformerInterface>>
     */
    public function getAllowedNodeTransformers(): array;

    /**
     * @return array<class-string<AnzuMarkTransformerInterface>>
     */
    public function getAllowedMarkTransformers(): array;

    public function getPreprocessorFormat(): ?string;

    /**
     * @return array<int, string>
     */
    public function getRemove(): array;

    /**
     * @return array<int, string>
     */
    public function getSkip(): array;

    /**
     * @return class-string<AnzuNodeTransformerInterface>
     */
    public function getDefaultTransformer(): string;
}
