<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\AnzuTap;

use AnzuSystems\CommonBundle\AnzuTap\AnzuTapEditor;
use AnzuSystems\CommonBundle\Model\AnzuTap\AnzuTapBody;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class TestAnzuTapEditor
{
    public function __construct(
        #[Target('testEditor')]
        private AnzuTapEditor $testEditor,
    ) {
    }

    public function transform(string $data): AnzuTapBody
    {
        return $this->testEditor->transform($data);
    }
}
