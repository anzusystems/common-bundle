<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTextNode extends AbstractAnzuTapNode
{
    public const string NODE_NAME = 'text';

    public function __construct(
        private readonly string $text,
        ?array $marks = null,
    ) {
        parent::__construct(
            type: self::NODE_NAME,
            marks: $marks
        );
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function addContent(AnzuTapNodeInterface $node): static
    {
        $this->parent?->addContent($node);

        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->getType(),
            'text' => $this->getText(),
        ];
        if ($this->marks) {
            $data['marks'] = $this->marks;
        }

        return $data;
    }

    public function getMarks(): ?array
    {
        return $this->marks;
    }
}
