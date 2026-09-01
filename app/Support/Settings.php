<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Lazy-loaded, in-process cache over the `system_settings` table.
 * Reads all settings on first access; writes go through and update the cache.
 */
class Settings
{
    /** @var array<string, string> */
    private static array $cache = [];
    private static bool $loaded = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (!self::$loaded) {
            self::loadAll();
        }
        return self::$cache[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return \in_array(\strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, string $value, int $updatedBy): void
    {
        Db::pdo()->prepare(
            'INSERT INTO system_settings (setting_key, value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)'
        )->execute([$key, $value, $updatedBy]);
        self::$cache[$key] = $value;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::loadAll();
        }
        return self::$cache;
    }

    private static function loadAll(): void
    {
        $rows = Db::pdo()->query(
            'SELECT setting_key, value FROM system_settings'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            self::$cache[$r['setting_key']] = $r['value'];
        }
        self::$loaded = true;
    }
}
