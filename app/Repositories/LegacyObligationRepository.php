<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Old (pre-v2) bills / commission entries owed BY or TO a customer / supplier /
 * salesman. After 0046, an obligation attaches directly to ONE live entity via
 * one of customer_id / supplier_id / salesman_id (exactly one is non-null;
 * application enforces, schema permits all three nullable).
 */
class LegacyObligationRepository
{
    private const KIND_COL = [
        'customer' => 'customer_id',
        'supplier' => 'supplier_id',
        'salesman' => 'salesman_id',
    ];

    /** @return array<int, array<string, mixed>> */
    public function byEntity(string $kind, int $entityId): array
    {
        $col = self::KIND_COL[$kind] ?? throw new \InvalidArgumentException("Unknown kind: {$kind}");
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM legacy_obligations
             WHERE {$col} = ?
             ORDER BY obligation_date ASC, id ASC"
        );
        $stmt->execute([$entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unpaidForEntity(string $kind, int $entityId): array
    {
        $col = self::KIND_COL[$kind] ?? throw new \InvalidArgumentException("Unknown kind: {$kind}");
        $stmt = Db::pdo()->prepare(
            "SELECT id, obligation_number, obligation_date, obligation_amount, due_amount
             FROM legacy_obligations
             WHERE {$col} = ? AND payment_status IN ('unpaid', 'partial') AND due_amount > 0
             ORDER BY obligation_date ASC, id ASC"
        );
        $stmt->execute([$entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Per-entity rollup: total bills, total settled, outstanding, unallocated. */
    public function rollupForEntity(string $kind, int $entityId): array
    {
        $col = self::KIND_COL[$kind] ?? throw new \InvalidArgumentException("Unknown kind: {$kind}");
        $stmt = Db::pdo()->prepare(
            "SELECT
                COALESCE(SUM(lo.obligation_amount), 0) AS total_billed,
                COALESCE(SUM(lo.paid_amount), 0)        AS total_paid,
                COALESCE(SUM(lo.due_amount), 0)         AS total_due,
                COUNT(*)                                AS bill_count,
                (SELECT COALESCE(SUM(amount), 0)
                 FROM legacy_settlements ls
                 WHERE ls.{$col} = ? AND ls.obligation_id IS NULL) AS unallocated
             FROM legacy_obligations lo
             WHERE lo.{$col} = ?"
        );
        $stmt->execute([$entityId, $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : ['total_billed' => 0, 'total_paid' => 0, 'total_due' => 0, 'bill_count' => 0, 'unallocated' => 0];
    }

    /**
     * List ALL entities of a given kind that have any legacy obligations or
     * unallocated settlements — entry point for /old-records?kind=customer.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function entitiesWithLegacyData(string $kind, string $search = '', int $page = 1, int $perPage = 25): array
    {
        $col = self::KIND_COL[$kind] ?? throw new \InvalidArgumentException("Unknown kind: {$kind}");
        $table = match ($kind) {
            'customer' => 'customers',
            'supplier' => 'suppliers',
            'salesman' => 'salesmen',
        };
        $where  = ["(EXISTS (SELECT 1 FROM legacy_obligations lo WHERE lo.{$col} = e.id)
                     OR EXISTS (SELECT 1 FROM legacy_settlements ls WHERE ls.{$col} = e.id))"];
        $params = [];
        if ($search !== '') {
            $where[] = '(e.name LIKE ? OR e.phone LIKE ?)';
            $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }
        $whereSql = ' WHERE ' . \implode(' AND ', $where);
        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM {$table} e {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = "SELECT e.id, e.name, e.phone, e.status,
                       (SELECT COALESCE(SUM(lo.obligation_amount), 0) FROM legacy_obligations lo WHERE lo.{$col} = e.id) AS total_billed,
                       (SELECT COALESCE(SUM(lo.due_amount), 0)         FROM legacy_obligations lo WHERE lo.{$col} = e.id) AS total_due,
                       (SELECT COALESCE(SUM(ls.amount), 0)
                          FROM legacy_settlements ls
                          WHERE ls.{$col} = e.id AND ls.obligation_id IS NULL)                  AS unallocated,
                       (SELECT COUNT(*) FROM legacy_obligations lo WHERE lo.{$col} = e.id)        AS bill_count
                FROM {$table} e
                {$whereSql}
                ORDER BY e.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM legacy_obligations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findForUpdate(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM legacy_obligations WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Insert. $data must include exactly one of customer_id / supplier_id / salesman_id.
     * The other two columns are stored as NULL.
     */
    public function insert(array $data, int $createdBy): int
    {
        $opening = (float) ($data['opening_paid_amount'] ?? 0);
        $stmt = Db::pdo()->prepare(
            'INSERT INTO legacy_obligations
             (customer_id, supplier_id, salesman_id,
              obligation_number, obligation_date, obligation_amount,
              opening_paid_amount, paid_amount, due_amount, payment_status, remarks,
              created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['customer_id'] ?? null,
            $data['supplier_id'] ?? null,
            $data['salesman_id'] ?? null,
            $data['obligation_number'], $data['obligation_date'], $data['obligation_amount'],
            $opening, $opening, $data['due_amount'], $data['payment_status'],
            $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function recompute(int $obligationId, int $updatedBy): void
    {
        $row = $this->find($obligationId);
        if ($row === null) { return; }
        $opening = (float) $row['opening_paid_amount'];
        $sumPays = (float) Db::pdo()
            ->query("SELECT COALESCE(SUM(amount), 0) FROM legacy_settlements WHERE obligation_id = {$obligationId}")
            ->fetchColumn();
        $paid    = \round($opening + $sumPays, 2);
        $amount  = (float) $row['obligation_amount'];
        $due     = \round($amount - $paid, 2);
        if ($due < 0.01 && $due > -0.01) { $due = 0.0; }

        $status = 'unpaid';
        if ($paid > 0 && $due > 0)  { $status = 'partial'; }
        elseif ($due <= 0.0)        { $status = 'paid'; }

        $stmt = Db::pdo()->prepare(
            'UPDATE legacy_obligations SET paid_amount = ?, due_amount = ?, payment_status = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([$paid, \max(0.0, $due), $status, $updatedBy, $obligationId]);
    }

    public function delete(int $id): int
    {
        $stmt = Db::pdo()->prepare('DELETE FROM legacy_obligations WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function hasAnySettlement(int $obligationId): bool
    {
        $stmt = Db::pdo()->prepare('SELECT 1 FROM legacy_settlements WHERE obligation_id = ? LIMIT 1');
        $stmt->execute([$obligationId]);
        return (bool) $stmt->fetchColumn();
    }
}
