<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpCursorResponseTrait;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetPage;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\ExampleStateItem;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\Collection;

final class CursorExampleResponse
{
    use McpCursorResponseTrait;

    /**
     * @var Collection<int, ExampleStateItem>
     */
    #[McpField(description: 'Newest first.')]
    #[Serialize(type: ExampleStateItem::class)]
    public Collection $items {
        get => $this->cursorPage->items;
    }

    /**
     * @param KeysetPage<ExampleStateItem> $cursorPage
     */
    public function __construct(
        private readonly KeysetPage $cursorPage,
    ) {
    }

    /**
     * @return KeysetPage<ExampleStateItem>
     */
    protected function getCursorPage(): KeysetPage
    {
        return $this->cursorPage;
    }
}
