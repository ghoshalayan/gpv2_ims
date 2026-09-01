<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class PurchaseInvoiceExpenseRepository
{
    public function byInvoice(int $invoiceId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM purchase_invoice_expenses
             WHERE purchase_invoice_id = ? ORDER BY id'
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchase_invoice_expenses
             (purchase_invoice_id, expense_type, amount, tax_rate, tax_amount, total_with_tax,
              allocation_method, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['purchase_invoice_id'], $data['expense_type'], $data['amount'],
            $data['tax_rate'], $data['tax_amount'], $data['total_with_tax'],
            $data['allocation_method'], $data['remarks'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function deleteExpense(int $expenseId, int $invoiceId): void
    {
        $stmt = Db::pdo()->prepare(
            'DELETE FROM purchase_invoice_expenses WHERE id = ? AND purchase_invoice_id = ?'
        );
        $stmt->execute([$expenseId, $invoiceId]);
    }
}
