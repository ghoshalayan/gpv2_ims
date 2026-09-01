<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class ActivityLogRepository
{
    /**
     * Filtered, paginated tail of the activity log.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = \max(0, ($page - 1) * $perPage);

        $countStmt = Db::pdo()->prepare("SELECT COUNT(*) FROM activity_log {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM activity_log {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return [
            'rows'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function insert(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO activity_log
             (user_id, user_name, action, entity_kind, entity_id, entity_label,
              ip_address, before_json, after_json, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id']      ?? null,
            $data['user_name']    ?? null,
            $data['action'],
            $data['entity_kind'],
            $data['entity_id']    ?? null,
            $data['entity_label'] ?? null,
            $data['ip_address']   ?? null,
            $data['before_json']  ?? null,
            $data['after_json']   ?? null,
            $data['notes']        ?? null,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function distinctEntityKinds(): array
    {
        return Db::pdo()->query(
            'SELECT DISTINCT entity_kind FROM activity_log ORDER BY entity_kind'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    private function buildWhere(array $filters): array
    {
        $conds  = [];
        $params = [];
        if (!empty($filters['user_id']))      { $conds[] = 'user_id = ?';      $params[] = (int) $filters['user_id']; }
        if (!empty($filters['entity_kind']))  { $conds[] = 'entity_kind = ?';  $params[] = (string) $filters['entity_kind']; }
        if (!empty($filters['action']))       { $conds[] = 'action = ?';       $params[] = (string) $filters['action']; }
        if (!empty($filters['date_from']))    { $conds[] = 'created_at >= ?';  $params[] = $filters['date_from'] . ' 00:00:00'; }
        if (!empty($filters['date_to']))      { $conds[] = 'created_at <= ?';  $params[] = $filters['date_to']   . ' 23:59:59'; }
        if (!empty($filters['entity_id']))    { $conds[] = 'entity_id = ?';    $params[] = (int) $filters['entity_id']; }
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $conds[]  = '(entity_label LIKE ? OR notes LIKE ? OR user_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        return [$conds === [] ? '' : 'WHERE ' . \implode(' AND ', $conds), $params];
    }
}
