<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

use Closure;

abstract class AbstractAnzuTapNode implements AnzuTapNodeInterface
{
    protected ?AnzuTapNodeInterface $parent = null;
    protected string $type;
    protected ?array $attrs = null;
    protected ?array $marks = null;

    /**
     * @var array<int, AnzuTapNodeInterface>
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

    public function getAttrs(): ?array
    {
        return $this->attrs;
    }

    public function getAttr(string $key): mixed
    {
        return $this->attrs[$key] ?? null;
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

    public function setMarks(?array $marks = null): self
    {
        $marksAllowList = $this->getMarksAllowList();
        if (null === $marks || (is_array($marksAllowList)  && 0 === count($marksAllowList))) {
            $this->marks = null;

            return $this;
        }

        if (null === $marksAllowList) {
            $this->marks = $marks;

            return $this;
        }

        foreach ($marks as $mark) {
            if (in_array($mark['type'] ?? '', $marksAllowList)) {
                $this->marks[] = $mark;
            }
        }

        return $this;
    }

    public function isValid(): bool
    {
        return true;
    }

    protected function getMarksAllowList(): ?array
    {
        return null;
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
     * @param Closure(AnzuTapNodeInterface $removeFn, mixed $key): bool $removeFn
     */
    public function removeNode(Closure $removeFn): ?AnzuTapNodeInterface
    {
        $removeNodeKey = $this->findNode($removeFn);
        if (null === $removeNodeKey) {
            return null;
        }

        if (array_key_exists($removeNodeKey, $this->content) && $this->content[$removeNodeKey] instanceof AnzuTapNodeInterface) {
            $removed = $this->content[$removeNodeKey];
            unset($this->content[$removeNodeKey]);
            $this->content = array_values($this->content);

            return $removed;
        }

        return $this;
    }

    /**
     * @param Closure(AnzuTapNodeInterface $filterFn, mixed $key): bool $filterFn
     *
     * @return array-key
     */
    public function findNode(Closure $filterFn): int|string|null
    {
        $key = null;
        foreach ($this->content as $currentKey => $value) {
            if ($filterFn($value, $currentKey)) {
                $key = $currentKey;
                break;
            }
        }
        if (is_string($key) || is_int($key)) {
            return $key;
        }

        return null;
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
