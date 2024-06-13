<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

abstract class AbstractAnzuTapNode implements AnzuTapNodeInterface
{
    protected ?AnzuTapNodeInterface $parent = null;
    protected string $type;
    protected ?array $attrs = null;
    protected ?array $marks = null;

    /**
     * @var array<int, AnzuTapNode>
     */
    protected array $content = [];

    public function __construct(
        string $type,
        ?array $attrs = null,
        ?array $marks = null,
    ) {
        $this->type = $type;
        $this->attrs = $attrs;
        $this->marks = $marks;
    }

    public function getParent(): ?AnzuTapNodeInterface
    {
        return $this->parent;
    }

    public function setParent(?AnzuTapNodeInterface $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function addMark(string $markName): AnzuTapNodeInterface
    {
        if (null === $this->marks) {
            $this->marks = [];
        }

        $this->marks[] = [
            'type' => $markName,
        ];

        return $this;
    }

    public function setMarks(?array $marks = null): self
    {
        $this->marks = $marks;

        return $this;
    }

    public function addAttr(string $name, string $value): self
    {
        if (null === $this->attrs) {
            $this->attrs = [];
        }

        $this->attrs[$name] = $value;

        return $this;
    }

    public function setContent(array $content): AnzuTapNodeInterface
    {
        $this->content = $content;

        return $this;
    }

    public function addContent(AnzuTapNodeInterface $node): AnzuTapNodeInterface
    {
        $this->content[] = $node;
        $node->setParent($this);

        return $this;
    }

    /**
     * @param array<int, AnzuTapNodeInterface> $nodes
     */
    public function addContents(array $nodes): AnzuTapNodeInterface
    {
        foreach ($nodes as $node) {
            $this->addContent($node);
        }

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getNodeText(): ?string
    {
        $text = [];
        if ($this instanceof AnzuTapTextNode) {
            $text[] = $this->getText();
        }
        foreach ($this->content as $node) {
            $childText = $node->getNodeText();
            if (is_string($childText)) {
                $text[] = $childText;
            }
        }

        if (empty($text)) {
            return null;
        }

        return implode(' ', $text);
    }

    /**
     * Helper function for wrapping child nodes into paragraphs
     */
    protected function upsertFirstContentParagraph(): AnzuTapNodeInterface
    {
        foreach ($this->content as $item) {
            if (AnzuTapParagraphNode::NODE_NAME === $item->getType()) {
                return $item;
            }
        }

        $paragraphNode = new AnzuTapParagraphNode();
        $this->addContent($paragraphNode);

        return $paragraphNode;
    }
}
