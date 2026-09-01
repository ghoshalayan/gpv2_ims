<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class BankAccountRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT * FROM bank_accounts ORDER BY status DESC, name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    public function listActive(): array
    {
        return Db::pdo()->query(
            "SELECT id, name, bank_name, current_balance FROM bank_accounts WHERE status='active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM bank_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM bank_accounts WHERE name = ?';
        $params = [$name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO bank_accounts
             (name, bank_name, account_number, ifsc, branch, opening_balance, current_balance, remarks,
              created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['bank_name'], $data['account_number'], $data['ifsc'], $data['branch'],
            $data['opening_balance'], $data['opening_balance'],   // current = opening at creation
            $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE bank_accounts
             SET name = ?, bank_name = ?, account_number = ?, ifsc = ?, branch = ?, remarks = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['bank_name'], $data['account_number'], $data['ifsc'], $data['branch'],
            $data['remarks'] ?? null, $updatedBy, $id,
        ]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE bank_accounts SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }

    /**
     * Atomic balance mutation. delta can be positive (credit/in) or negative (debit/out).
     * Throws if the row doesn't exist — defensive guard against silent no-ops where a
     * caller passes a stale/invalid id and the missing balance change masquerades as
     * success.
     */
    public function applyDelta(int $id, float $delta): void
    {
        $check = Db::pdo()->prepare('SELECT 1 FROM bank_accounts WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            throw new \RuntimeException("Bank account #{$id} not found — cannot applyDelta.");
        }
        $stmt = Db::pdo()->prepare(
            'UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?'
        );
        $stmt->execute([$delta, $id]);
    }
}
