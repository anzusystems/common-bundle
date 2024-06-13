<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

interface AnzuTapNodeInterface
{
    public function getType(): string;

    public function setParent(?AnzuTapNode $parent): static;

    public function getParent(): ?AnzuTapNodeInterface;

    public function addContent(self $node): self;

    /**
     * @return array<int, AnzuTapNodeInterface>
     */
    public function getContent(): array;

    public function setContent(array $content): AnzuTapNodeInterface;

    public function getNodeText(): ?string;

    public function setMarks(?array $marks = null): self;

    public function getMarks(): ?array;

    public function addAttr(string $name, string $value): self;

    public function toArray(): array;
}
