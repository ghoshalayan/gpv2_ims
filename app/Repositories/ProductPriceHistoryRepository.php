<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class ProductPriceHistoryRepository
{
    /**
     * Optional snapshot keys in $snapshot:
     *   prior_qty, prior_avg, event_qty, event_unit_cost, new_qty, new_avg
     * — populated by InventoryService when the math context is available.
     */
    public function insert(
        int    $productId,
        float  $landedCost,
        string $source,
        ?string $sourceRefType = null,
        ?int   $sourceRefId = null,
        ?string $notes = null,
        ?int   $userId = null,
        array  $snapshot = []
    ): int {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO product_price_history
             (product_id, landed_cost, prior_qty, prior_avg, event_qty, event_unit_cost,
              new_qty, new_avg, source, source_ref_type, source_ref_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $productId, $landedCost,
            $snapshot['prior_qty']        ?? null,
            $snapshot['prior_avg']        ?? null,
            $snapshot['event_qty']        ?? null,
            $snapshot['event_unit_cost']  ?? null,
            $snapshot['new_qty']          ?? null,
            $snapshot['new_avg']          ?? null,
            $source, $sourceRefType, $sourceRefId, $notes, $userId,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> Most-recent first. */
    public function byProduct(int $productId, int $limit = 50): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT pph.*, u.name AS created_by_name
             FROM product_price_history pph
             LEFT JOIN users u ON u.id = pph.created_by
             WHERE pph.product_id = ?
             ORDER BY pph.effective_at DESC, pph.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginated history for the product edit page.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(int $productId, int $page, int $perPage, string $search = ''): array
    {
        $where  = ['pph.product_id = ?'];
        $params = [$productId];
        if ($search !== '') {
            $where[]  = '(pph.source LIKE ? OR pph.notes LIKE ? OR pph.source_ref_type LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM product_price_history pph {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT pph.*, u.name AS created_by_name
             FROM product_price_history pph
             LEFT JOIN users u ON u.id = pph.created_by
             {$whereSql}
             ORDER BY pph.effective_at DESC, pph.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** Used by the back-fill script to update an existing row's snapshot in place. */
    public function updateSnapshot(int $id, array $snapshot): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE product_price_history
             SET prior_qty = ?, prior_avg = ?, event_qty = ?, event_unit_cost = ?,
                 new_qty = ?, new_avg = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $snapshot['prior_qty']        ?? null,
            $snapshot['prior_avg']        ?? null,
            $snapshot['event_qty']        ?? null,
            $snapshot['event_unit_cost']  ?? null,
            $snapshot['new_qty']          ?? null,
            $snapshot['new_avg']          ?? null,
            $id,
        ]);
    }
}
