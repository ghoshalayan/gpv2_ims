<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class StockAdjustmentRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(?string $reasonFilter = null, ?string $directionFilter = null, ?string $statusFilter = null): array
    {
        $sql = "SELECT sa.*,
                       (SELECT COUNT(*) FROM stock_adjustment_lines sal WHERE sal.stock_adjustment_id = sa.id) AS line_count
                FROM stock_adjustments sa";
        $params = [];
        $conds  = [];
        if ($reasonFilter    !== null && $reasonFilter    !== '') { $conds[] = 'sa.reason = ?';    $params[] = $reasonFilter; }
        if ($directionFilter !== null && $directionFilter !== '') { $conds[] = 'sa.direction = ?'; $params[] = $directionFilter; }
        if ($statusFilter    !== null && $statusFilter    !== '') { $conds[] = 'sa.status = ?';    $params[] = $statusFilter; }
        if ($conds !== []) { $sql .= ' WHERE ' . \implode(' AND ', $conds); }
        $sql .= ' ORDER BY sa.adjustment_date DESC, sa.id DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['reason']))    { $where[] = 'sa.reason = ?';    $params[] = (string) $filters['reason']; }
        if (!empty($filters['direction'])) { $where[] = 'sa.direction = ?'; $params[] = (string) $filters['direction']; }
        if (!empty($filters['status']))    { $where[] = 'sa.status = ?';    $params[] = (string) $filters['status']; }
        if ($search !== '') {
            $where[]  = '(sa.adjustment_number LIKE ?)';
            $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM stock_adjustments sa {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT sa.*,
                    (SELECT COUNT(*) FROM stock_adjustment_lines sal WHERE sal.stock_adjustment_id = sa.id) AS line_count
             FROM stock_adjustments sa
             {$whereSql}
             ORDER BY sa.adjustment_date DESC, sa.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM stock_adjustments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO stock_adjustments
             (adjustment_number, adjustment_date, reason, direction, total_value, remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['adjustment_number'], $data['adjustment_date'], $data['reason'],
            $data['direction'], $data['total_value'], $data['remarks'] ?? null, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function setTotalValue(int $id, float $totalValue): void
    {
        $stmt = Db::pdo()->prepare('UPDATE stock_adjustments SET total_value = ? WHERE id = ?');
        $stmt->execute([\round($totalValue, 2), $id]);
    }

    public function markVoided(int $id, string $reason, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE stock_adjustments
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE id = ? AND status = 'posted'"
        );
        $stmt->execute([$userId, $reason, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(adjustment_number, "/", -1) AS UNSIGNED)), 0)
             FROM stock_adjustments
             WHERE adjustment_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }
}
