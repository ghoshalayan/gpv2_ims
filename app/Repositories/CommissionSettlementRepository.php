<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class CommissionSettlementRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(?int $salesmanId = null): array
    {
        $sql = "SELECT scs.*,
                       sm.name AS salesman_name,
                       p.name  AS goods_product_name,
                       p.unit  AS goods_product_unit,
                       CASE scs.paid_from_kind
                           WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = scs.paid_from_id)
                           WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = scs.paid_from_id)
                       END AS paid_from_name
                FROM salesman_commission_settlements scs
                JOIN salesmen sm ON sm.id = scs.salesman_id
                LEFT JOIN products p ON p.id = scs.goods_product_id";
        $params = [];
        if ($salesmanId !== null) {
            $sql .= ' WHERE scs.salesman_id = ?';
            $params[] = $salesmanId;
        }
        $sql .= ' ORDER BY scs.settlement_date DESC, scs.id DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['salesman_id'])) { $where[] = 'scs.salesman_id = ?';   $params[] = (int) $filters['salesman_id']; }
        if (!empty($filters['payout_mode'])) { $where[] = 'scs.payout_mode = ?';   $params[] = (string) $filters['payout_mode']; }
        if (!empty($filters['status']))      { $where[] = 'scs.status = ?';        $params[] = (string) $filters['status']; }
        if (!empty($filters['date_from']))   { $where[] = 'scs.settlement_date >= ?'; $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))     { $where[] = 'scs.settlement_date <= ?'; $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[]  = '(scs.settlement_number LIKE ? OR sm.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM salesman_commission_settlements scs
             JOIN salesmen sm ON sm.id = scs.salesman_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT scs.*,
                    sm.name AS salesman_name,
                    p.name  AS goods_product_name,
                    p.unit  AS goods_product_unit,
                    CASE scs.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = scs.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = scs.paid_from_id)
                    END AS paid_from_name
             FROM salesman_commission_settlements scs
             JOIN salesmen sm ON sm.id = scs.salesman_id
             LEFT JOIN products p ON p.id = scs.goods_product_id
             {$whereSql}
             ORDER BY scs.settlement_date DESC, scs.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT scs.*,
                    sm.name AS salesman_name,
                    p.name  AS goods_product_name,
                    p.unit  AS goods_product_unit,
                    CASE scs.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = scs.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = scs.paid_from_id)
                    END AS paid_from_name
             FROM salesman_commission_settlements scs
             JOIN salesmen sm ON sm.id = scs.salesman_id
             LEFT JOIN products p ON p.id = scs.goods_product_id
             WHERE scs.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO salesman_commission_settlements
             (settlement_number, settlement_date, salesman_id, payout_mode,
              cash_amount, paid_from_kind, paid_from_id, payment_mode, payment_reference,
              goods_product_id, goods_quantity, goods_unit_cost, goods_total_value,
              total_settled_amount, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['settlement_number'], $data['settlement_date'], $data['salesman_id'], $data['payout_mode'],
            $data['cash_amount'] ?? 0,
            $data['paid_from_kind'] ?? null, $data['paid_from_id'] ?? null,
            $data['payment_mode']   ?? null, $data['payment_reference'] ?? null,
            $data['goods_product_id']  ?? null,
            $data['goods_quantity']    ?? null,
            $data['goods_unit_cost']   ?? null,
            $data['goods_total_value'] ?? null,
            $data['total_settled_amount'],
            $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function markVoided(int $id, string $reason, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE salesman_commission_settlements
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?, updated_by = ?
             WHERE id = ? AND status = 'posted'"
        );
        $stmt->execute([$userId, $reason, $userId, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(settlement_number, "/", -1) AS UNSIGNED)), 0)
             FROM salesman_commission_settlements
             WHERE settlement_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Net commission outstanding for one salesman:
     *   earned − reversed − settled  (posted-only).
     */
    public function outstandingForSalesman(int $salesmanId): float
    {
        $stmt = Db::pdo()->prepare(
            "SELECT
                COALESCE((SELECT SUM(sil.commission_amount)
                          FROM sales_invoice_lines sil
                          JOIN sales_invoices si ON si.id = sil.sales_invoice_id
                          WHERE sil.salesman_id = ? AND si.status = 'finalized'), 0)
              - COALESCE((SELECT SUM(srl.commission_reversed)
                          FROM sales_return_lines srl
                          JOIN sales_returns sr ON sr.id = srl.sales_return_id
                          WHERE srl.salesman_id = ? AND sr.status = 'finalized'), 0)
              - COALESCE((SELECT SUM(scs.total_settled_amount)
                          FROM salesman_commission_settlements scs
                          WHERE scs.salesman_id = ? AND scs.status = 'posted'), 0)"
        );
        $stmt->execute([$salesmanId, $salesmanId, $salesmanId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Outstanding-commission summary per salesman:
     *   earned − reversed − settled (where status = 'posted' for settlements).
     */
    public function outstandingPerSalesman(): array
    {
        $stmt = Db::pdo()->query(
            "SELECT
                sm.id AS salesman_id,
                sm.name AS salesman_name,
                sm.commission_rate,
                COALESCE((SELECT SUM(sil.commission_amount)
                          FROM sales_invoice_lines sil
                          JOIN sales_invoices si ON si.id = sil.sales_invoice_id
                          WHERE sil.salesman_id = sm.id AND si.status = 'finalized'), 0)        AS earned,
                COALESCE((SELECT SUM(srl.commission_reversed)
                          FROM sales_return_lines srl
                          JOIN sales_returns sr ON sr.id = srl.sales_return_id
                          WHERE srl.salesman_id = sm.id AND sr.status = 'finalized'), 0)        AS reversed,
                COALESCE((SELECT SUM(scs.total_settled_amount)
                          FROM salesman_commission_settlements scs
                          WHERE scs.salesman_id = sm.id AND scs.status = 'posted'), 0)          AS settled
             FROM salesmen sm
             WHERE sm.status = 'active'
             ORDER BY sm.name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
