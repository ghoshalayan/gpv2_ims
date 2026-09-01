<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class InterAccountTransferRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            "SELECT iat.*,
                    CASE iat.from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.from_id)
                    END AS from_name,
                    CASE iat.to_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.to_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.to_id)
                    END AS to_name
             FROM inter_account_transfers iat
             ORDER BY iat.transfer_date DESC, iat.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['date_from'])) { $where[] = 'iat.transfer_date >= ?'; $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where[] = 'iat.transfer_date <= ?'; $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[]  = '(iat.transfer_number LIKE ? OR iat.reference LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM inter_account_transfers iat {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT iat.*,
                    CASE iat.from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.from_id)
                    END AS from_name,
                    CASE iat.to_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.to_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.to_id)
                    END AS to_name
             FROM inter_account_transfers iat
             {$whereSql}
             ORDER BY iat.transfer_date DESC, iat.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT iat.*,
                    CASE iat.from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.from_id)
                    END AS from_name,
                    CASE iat.to_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.to_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.to_id)
                    END AS to_name
             FROM inter_account_transfers iat
             WHERE iat.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO inter_account_transfers
             (transfer_number, transfer_date, from_kind, from_id, to_kind, to_id,
              amount, mode, reference, remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['transfer_number'], $data['transfer_date'],
            $data['from_kind'], $data['from_id'], $data['to_kind'], $data['to_id'],
            $data['amount'], $data['mode'], $data['reference'] ?? null, $data['remarks'] ?? null,
            $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** Returns the highest sequence number used in the given prefix. Caller adds 1. */
    public function maxSequenceForPrefix(string $prefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(transfer_number, "/", -1) AS UNSIGNED)), 0)
             FROM inter_account_transfers
             WHERE transfer_number LIKE ?'
        );
        $stmt->execute([$prefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /** All transfers touching a specific account (either side). */
    public function byAccount(string $kind, int $id): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT iat.*,
                    CASE iat.from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.from_id)
                    END AS from_name,
                    CASE iat.to_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts    WHERE id = iat.to_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers  WHERE id = iat.to_id)
                    END AS to_name
             FROM inter_account_transfers iat
             WHERE (iat.from_kind = ? AND iat.from_id = ?)
                OR (iat.to_kind   = ? AND iat.to_id   = ?)
             ORDER BY iat.transfer_date DESC, iat.id DESC"
        );
        $stmt->execute([$kind, $id, $kind, $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
