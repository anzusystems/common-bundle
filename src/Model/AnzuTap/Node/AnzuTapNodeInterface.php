<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

interface AnzuTapNodeInterface
{
    public const string PARAGRAPH = 'paragraph';
    public const string HEADING = 'heading';
    public const string HORIZONTAL_RULE = 'horizontalRule';
    public const string HARD_BREAK = 'hardBreak';
    public const string DOC = 'doc';
    public const string LIST_ITEM = 'listItem';
    public const string BULLET_LIST = 'bulletList';
    public const string ORDERED_LIST = 'orderedList';
    public const string TABLE_CELL = 'tableCell';
    public const string TABLE_HEADER = 'tableHeader';
    public const string TABLE = 'table';
    public const string TABLE_ROW = 'tableRow';
    public const string TEXT = 'text';

    public function getType(): string;

    public function setParent(?AnzuTapNode $parent): static;

    public function getParent(): ?self;

    public function addContent(self $node): self;

    /**
     * @return array<int, AnzuTapNodeInterface>
     */
    public function getContent(): array;

    public function setContent(array $content): self;

    public function getNodeText(): ?string;

    public function setMarks(?array $marks = null): self;

    public function getMarks(): ?array;

    public function addAttr(string $name, string $value): self;

    public function toArray(): array;
}
