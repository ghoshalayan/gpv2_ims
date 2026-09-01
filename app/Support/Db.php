<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'gpv2_ims')
        );
        self::$pdo = new PDO(
            $dsn,
            env('DB_USER', 'root'),
            env('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return self::$pdo;
    }

    /** Nesting depth — only the outermost call issues BEGIN/COMMIT on the PDO connection. */
    private static int $txnDepth = 0;

    /**
     * Run $fn inside a DB transaction. Re-entrant: nested calls execute the closure
     * inside the current transaction without issuing a second BEGIN (PDO/MySQL native
     * transactions don't nest). If any nested closure throws, the WHOLE outer transaction
     * is rolled back — we mark a "rollback only" flag so the outer level finalizes
     * with rollBack() instead of commit().
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $isOuter = self::$txnDepth === 0;
        if ($isOuter) {
            $pdo->beginTransaction();
        }
        self::$txnDepth++;
        try {
            $result = $fn($pdo);
            self::$txnDepth--;
            if ($isOuter) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txnDepth--;
            if ($isOuter && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
