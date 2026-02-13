<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\AnzuTap;

use AnzuSystems\CommonBundle\AnzuTap\AnzuTapEditor;
use AnzuSystems\CommonBundle\Model\AnzuTap\AnzuTapBody;

final readonly class TestAnzuTapEditor
{
    public function __construct(
        private AnzuTapEditor $testEditor
    ) {
    }

    public function transform(string $data): AnzuTapBody
    {
        return $this->testEditor->transform($data);
    }
}
