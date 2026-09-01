<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\ActivityLogRepository;

/**
 * Append-only audit-trail helper. Static facade so service code can call:
 *
 *   ActivityLog::log('finalize', 'sales_invoice', $id, ['label' => $invoiceNumber]);
 *
 * Captures the current authenticated user (if any) and request IP automatically.
 * Failures are swallowed (logged via error_log) — audit trail breakage must NEVER
 * abort the underlying business transaction.
 *
 * Allowed actions match the migration ENUM. See the activity_log table comment for
 * the full lifecycle description.
 */
class ActivityLog
{
    private static ?ActivityLogRepository $repo = null;

    /**
     * @param string                   $action       create|update|delete|void|refund|finalize|cancel|restore|login|logout|purge
     * @param string                   $entityKind   'sales_invoice', 'brand_rebate', 'user', etc.
     * @param int|null                 $entityId     entity primary key (NULL for non-row events like login)
     * @param array{
     *     label?:string,
     *     before?:mixed,
     *     after?:mixed,
     *     notes?:string,
     *     user_id?:int,                              // override (used during login when session not yet set)
     *     user_name?:string,
     * } $opts
     */
    public static function log(string $action, string $entityKind, ?int $entityId, array $opts = []): void
    {
        try {
            $userId   = $opts['user_id']   ?? Auth::id();
            $userName = $opts['user_name'] ?? Auth::user()['name'] ?? null;

            $payload = [
                'user_id'      => $userId !== null ? (int) $userId : null,
                'user_name'    => $userName,
                'action'       => $action,
                'entity_kind'  => $entityKind,
                'entity_id'    => $entityId,
                'entity_label' => $opts['label'] ?? null,
                'ip_address'   => self::clientIp(),
                'before_json'  => isset($opts['before']) ? self::encodeJson($opts['before']) : null,
                'after_json'   => isset($opts['after'])  ? self::encodeJson($opts['after'])  : null,
                'notes'        => $opts['notes'] ?? null,
            ];

            self::repo()->insert($payload);
        } catch (\Throwable $e) {
            // Never let audit-log failures abort business transactions.
            \error_log('[ActivityLog] failed to record: ' . $e->getMessage());
        }
    }

    private static function repo(): ActivityLogRepository
    {
        if (self::$repo === null) {
            self::$repo = new ActivityLogRepository();
        }
        return self::$repo;
    }

    private static function clientIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private static function encodeJson(mixed $value): ?string
    {
        if ($value === null) { return null; }
        $json = \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
