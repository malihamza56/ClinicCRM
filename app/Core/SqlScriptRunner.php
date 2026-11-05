<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Executes a .sql file statement by statement.
 * Understands the "DELIMITER $$" lines that phpMyAdmin and the mysql client use for triggers.
 */
final class SqlScriptRunner
{
    public static function run(PDO $pdo, string $file): int
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException("Cannot read SQL file: $file");
        }

        $delimiter = ';';
        $buffer = '';
        $count = 0;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                continue;
            }
            if (preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $m)) {
                $delimiter = $m[1];
                continue;
            }

            $buffer .= $line . "\n";
            if (str_ends_with($trimmed, $delimiter)) {
                $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $pdo->exec($statement);
                    $count++;
                }
                $buffer = '';
            }
        }

        return $count;
    }
}
