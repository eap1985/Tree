<?php

declare(strict_types=1);

namespace Tree;

use PDO;
use Throwable;
use Tree\Exception\InvalidIdentifierException;
use Tree\Exception\NodeNotFoundException;
use Tree\Exception\TreeException;

/**
 * Persistence for an adjacency-list tree (the `parent_id` column is the single
 * source of truth). The PDO connection is injected; every value is bound and
 * the table identifier is validated and quoted exactly once.
 */
final class TreeRepository
{
    private readonly string $table;

    public function __construct(
        private readonly PDO $pdo,
        string $table = 'categories',
    ) {
        $this->table = $this->quoteIdentifier($table);
    }

    /** @return array{id: int, name: string, parent_id: int|null} */
    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, parent_id FROM {$this->table} WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            throw NodeNotFoundException::forId($id);
        }

        return $row;
    }

    /** @return list<array{id: int, name: string, parent_id: int|null}> */
    public function fetchAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, name, parent_id FROM {$this->table}
             ORDER BY (parent_id IS NOT NULL), parent_id, id"
        );

        return $stmt->fetchAll();
    }

    /**
     * Fetch a node and all of its descendants with a single recursive CTE.
     *
     * @return list<array{id: int, name: string, parent_id: int|null}>
     */
    public function fetchSubtree(int $rootId): array
    {
        $sql = "
            WITH RECURSIVE subtree AS (
                SELECT id, name, parent_id
                FROM {$this->table}
                WHERE id = :root
                UNION ALL
                SELECT c.id, c.name, c.parent_id
                FROM {$this->table} c
                INNER JOIN subtree s ON c.parent_id = s.id
            )
            SELECT id, name, parent_id FROM subtree
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['root' => $rootId]);

        return $stmt->fetchAll();
    }

    public function insert(string $name, ?int $parentId = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (name, parent_id) VALUES (:name, :parent_id)"
        );
        $stmt->execute(['name' => $name, 'parent_id' => $parentId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET name = :name WHERE id = :id"
        );
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public function move(int $id, ?int $newParentId): void
    {
        if ($id === $newParentId) {
            throw new TreeException('A node cannot be its own parent.');
        }

        if ($newParentId !== null && $this->isDescendant($newParentId, $id)) {
            throw new TreeException('Cannot move a node into its own subtree.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET parent_id = :parent WHERE id = :id"
        );
        $stmt->execute(['parent' => $newParentId, 'id' => $id]);
    }

    /** Delete a node and its whole subtree atomically. */
    public function delete(int $id): void
    {
        $ids = array_map('intval', array_column($this->fetchSubtree($id), 'id'));
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table} WHERE id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function isDescendant(int $candidate, int $ancestor): bool
    {
        $ids = array_map('intval', array_column($this->fetchSubtree($ancestor), 'id'));

        return in_array($candidate, $ids, true);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidIdentifierException("Invalid table name: {$identifier}");
        }

        return '`' . $identifier . '`';
    }
}
