<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * The single place where a database connection is created.
 *
 * Every query in the project goes through PDO prepared statements, so user input is never
 * concatenated into SQL. Models call the small helpers below (all, one, value, run, insert).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $c = Config::get('database');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);

        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Never leak the DSN or password: log the cause, show a generic message.
            ErrorHandler::log('Database connection failed: ' . $e->getMessage());
            throw new DatabaseUnavailableException('The database is not reachable.', 0, $e);
        }

        // Keep MySQL's NOW()/CURDATE() on the same clock as PHP, and keep strict SQL mode on.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return self::$pdo = $pdo;
    }

    /** Forgets the connection (used by tests after switching database). */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /** Makes user text safe inside a LIKE pattern, so "%" and "_" typed by a user are matched literally. */
    public static function escapeLike(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Runs $work inside a transaction. Either every step succeeds, or none of them is saved.
     * If a transaction is already open (nested call), the outer one stays in charge.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $work();
        }

        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
