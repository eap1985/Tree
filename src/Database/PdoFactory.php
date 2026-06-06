<?php

declare(strict_types=1);

namespace Tree\Database;

use PDO;
use Tree\Exception\TreeException;

/**
 * Builds a configured PDO connection.
 *
 * Configuration is read from environment variables first (so secrets stay out
 * of version control) and falls back to src/data/options.ini for local/dev use.
 */
final class PdoFactory
{
    /** @param array<string, mixed>|null $config */
    public static function create(?array $config = null): PDO
    {
        $config ??= self::resolveConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['schema'] ?? 'tree',
        );

        return new PDO(
            $dsn,
            $config['username'] ?? null,
            $config['password'] ?? null,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /** @return array<string, mixed> */
    private static function resolveConfig(): array
    {
        $host = getenv('DB_HOST');
        if ($host !== false && $host !== '') {
            return [
                'host'     => $host,
                'port'     => getenv('DB_PORT') ?: '3306',
                'schema'   => getenv('DB_NAME') ?: 'tree',
                'username' => getenv('DB_USER') ?: null,
                'password' => getenv('DB_PASS') ?: null,
            ];
        }

        $ini = dirname(__DIR__) . '/data/options.ini';
        if (is_file($ini)) {
            $options = parse_ini_file($ini, true);
            if (is_array($options) && isset($options['mysql']) && is_array($options['mysql'])) {
                return $options['mysql'];
            }
        }

        throw new TreeException(
            'No database configuration found. Set DB_HOST/DB_NAME/DB_USER/DB_PASS '
            . 'or provide src/data/options.ini.'
        );
    }
}
