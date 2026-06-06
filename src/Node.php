<?php

declare(strict_types=1);

namespace Tree;

/**
 * Immutable-scalar value object representing one node in the tree.
 * Only the children list is mutable, so the builder can assemble the graph.
 */
final class Node
{
    /** @var list<Node> */
    private array $children = [];

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $parentId = null,
    ) {
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    /** @return list<Node> */
    public function children(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function isLeaf(): bool
    {
        return $this->children === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'parent_id' => $this->parentId,
            'children'  => array_map(static fn (Node $c): array => $c->toArray(), $this->children),
        ];
    }
}
