<?php

declare(strict_types=1);

namespace Tree;

use Tree\Exception\CircularReferenceException;

/**
 * Assembles a forest (or a single subtree) from flat adjacency-list rows.
 *
 * Runs in O(n): one pass to index nodes by parent, then a linked assembly.
 * A path set guards against circular references so malformed data raises a
 * catchable exception instead of recursing forever.
 */
final class TreeBuilder
{
    /**
     * @param iterable<array{id: int|string, name: string, parent_id: int|string|null}> $rows
     * @return list<Node>
     */
    public function build(iterable $rows, ?int $rootId = null): array
    {
        /** @var array<int, Node> $nodes */
        $nodes = [];
        /** @var array<int, list<int>> $childIds  parentKey => childId[] (0 = no parent) */
        $childIds = [];

        foreach ($rows as $row) {
            $id       = (int) $row['id'];
            $parentId = isset($row['parent_id']) && $row['parent_id'] !== null
                ? (int) $row['parent_id']
                : null;

            $nodes[$id]                 = new Node($id, (string) $row['name'], $parentId);
            $childIds[$parentId ?? 0][] = $id;
        }

        if ($rootId !== null) {
            return isset($nodes[$rootId])
                ? [$this->attach($nodes[$rootId], $nodes, $childIds, [])]
                : [];
        }

        $roots = [];
        foreach ($childIds[0] ?? [] as $id) {
            $roots[] = $this->attach($nodes[$id], $nodes, $childIds, []);
        }

        return $roots;
    }

    /**
     * @param array<int, Node>      $nodes
     * @param array<int, list<int>> $childIds
     * @param array<int, true>      $path     ids currently on the recursion stack
     */
    private function attach(Node $node, array $nodes, array $childIds, array $path): Node
    {
        if (isset($path[$node->id])) {
            throw CircularReferenceException::atNode($node->id);
        }
        $path[$node->id] = true;

        foreach ($childIds[$node->id] ?? [] as $childId) {
            $node->addChild($this->attach($nodes[$childId], $nodes, $childIds, $path));
        }

        return $node;
    }
}
