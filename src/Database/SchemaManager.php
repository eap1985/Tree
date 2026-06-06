<?php

declare(strict_types=1);

namespace Tree\Database;

use PDO;
use Tree\Exception\InvalidIdentifierException;

/**
 * Creates the adjacency-list schema. The table is a self-referencing
 * structure with an ON DELETE CASCADE foreign key so the database enforces
 * referential integrity. Supports MySQL and SQLite (used by the test suite).
 */
final class SchemaManager
{
    private readonly string $table;

    public function __construct(
        private readonly PDO $pdo,
        string $table = 'categories',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new InvalidIdentifierException("Invalid table name: {$table}");
        }
        $this->table = $table;
    }

    /** Drop (if present) and recreate the table. Intended for setup/seed flows. */
    public function recreate(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec("DROP TABLE IF EXISTS \"{$this->table}\"");
            $this->pdo->exec(
                "CREATE TABLE \"{$this->table}\" (
                    id        INTEGER PRIMARY KEY AUTOINCREMENT,
                    name      TEXT NOT NULL,
                    parent_id INTEGER NULL
                        REFERENCES \"{$this->table}\"(id) ON DELETE CASCADE
                )"
            );

            return;
        }

        $this->pdo->exec("DROP TABLE IF EXISTS `{$this->table}`");
        $this->pdo->exec(
            "CREATE TABLE `{$this->table}` (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                name      VARCHAR(190) NOT NULL,
                parent_id INT NULL,
                CONSTRAINT fk_{$this->table}_parent
                    FOREIGN KEY (parent_id) REFERENCES `{$this->table}`(id)
                    ON DELETE CASCADE,
                INDEX idx_{$this->table}_parent (parent_id)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci"
        );
    }
}
