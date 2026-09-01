<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Settlements (cash movements) against legacy obligations or unallocated.
 * After 0046, attaches directly to a customer/supplier/salesman id (exactly
 * one of customer_id / supplier_id / salesman_id is non-null).
 */
class LegacySettlementRepository
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
            "SELECT ls.*,
                    lo.obligation_number AS obligation_number,
                    CASE ls.account_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ls.account_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ls.account_id)
                    END AS account_name
             FROM legacy_settlements ls
             LEFT JOIN legacy_obligations lo ON lo.id = ls.obligation_id
             WHERE ls.{$col} = ?
             ORDER BY ls.settlement_date DESC, ls.id DESC"
        );
        $stmt->execute([$entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM legacy_settlements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO legacy_settlements
             (customer_id, supplier_id, salesman_id, obligation_id,
              settlement_date, amount, payment_mode, payment_reference,
              account_kind, account_id, remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['customer_id'] ?? null,
            $data['supplier_id'] ?? null,
            $data['salesman_id'] ?? null,
            $data['obligation_id'] ?? null,
            $data['settlement_date'],
            $data['amount'], $data['payment_mode'], $data['payment_reference'] ?? null,
            $data['account_kind'], $data['account_id'],
            $data['remarks'] ?? null,
            $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function delete(int $id): int
    {
        $stmt = Db::pdo()->prepare('DELETE FROM legacy_settlements WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }
}
