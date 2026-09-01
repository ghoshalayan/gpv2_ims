<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesInvoiceExtraRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM sales_invoice_extras
             WHERE sales_invoice_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM sales_invoice_extras WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(int $invoiceId, array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_invoice_extras
             (sales_invoice_id, label, amount, tax_rate, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $invoiceId,
            $data['label'],
            $data['amount'],
            $data['tax_rate'] ?? 0,
            $data['sort_order'] ?? 0,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoice_extras
             SET label = ?, amount = ?, tax_rate = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['label'],
            $data['amount'],
            $data['tax_rate'] ?? 0,
            $id,
        ]);
    }

    public function updateComputed(int $id, float $taxAmount): void
    {
        $stmt = Db::pdo()->prepare('UPDATE sales_invoice_extras SET tax_amount = ? WHERE id = ?');
        $stmt->execute([$taxAmount, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM sales_invoice_extras WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function nextSortOrder(int $invoiceId): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM sales_invoice_extras WHERE sales_invoice_id = ?'
        );
        $stmt->execute([$invoiceId]);
        return (int) $stmt->fetchColumn();
    }
}
