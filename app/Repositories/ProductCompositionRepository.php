<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class ProductCompositionRepository
{
    /** Lazily create (or find) the composition row for a composite product. */
    public function ensureForProduct(int $productId, int $userId): int
    {
        $existing = $this->findByProduct($productId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $stmt = Db::pdo()->prepare(
            'INSERT INTO product_compositions (product_id, created_by, updated_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([$productId, $userId, $userId]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function findByProduct(int $productId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM product_compositions WHERE product_id = ? LIMIT 1'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> Items of a composition, joined with component product info. */
    public function itemsByComposition(int $compositionId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT pci.id, pci.composition_id, pci.component_product_id, pci.quantity, pci.notes,
                    p.name AS component_name, p.unit AS component_unit,
                    p.moving_avg_cost, p.latest_purchase_landed_cost,
                    p.quantity_on_hand AS component_stock,
                    p.status AS component_status
             FROM product_composition_items pci
             JOIN products p ON p.id = pci.component_product_id
             WHERE pci.composition_id = ?
             ORDER BY pci.id'
        );
        $stmt->execute([$compositionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateNotes(int $compositionId, ?string $notes, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE product_compositions SET notes = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$notes, $userId, $compositionId]);
    }

    public function addItem(int $compositionId, int $componentId, float $quantity, ?string $notes): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO product_composition_items (composition_id, component_product_id, quantity, notes)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$compositionId, $componentId, $quantity, $notes]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function deleteItem(int $itemId, int $compositionId): void
    {
        $stmt = Db::pdo()->prepare(
            'DELETE FROM product_composition_items WHERE id = ? AND composition_id = ?'
        );
        $stmt->execute([$itemId, $compositionId]);
    }

    /** Edit a recipe row's quantity and/or notes. Component product is immutable — delete + add to swap. */
    public function updateItem(int $itemId, int $compositionId, float $quantity, ?string $notes): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE product_composition_items
             SET quantity = ?, notes = ?
             WHERE id = ? AND composition_id = ?'
        );
        $stmt->execute([$quantity, $notes, $itemId, $compositionId]);
    }

    public function findItem(int $itemId, int $compositionId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM product_composition_items WHERE id = ? AND composition_id = ? LIMIT 1'
        );
        $stmt->execute([$itemId, $compositionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** True if the composition already includes the given component. */
    public function hasComponent(int $compositionId, int $componentId): bool
    {
        $stmt = Db::pdo()->prepare(
            'SELECT 1 FROM product_composition_items WHERE composition_id = ? AND component_product_id = ? LIMIT 1'
        );
        $stmt->execute([$compositionId, $componentId]);
        return (bool) $stmt->fetchColumn();
    }

    /** True if the composite product depends transitively on $candidateComponentId (prevents cycles). */
    public function wouldCreateCycle(int $parentProductId, int $candidateComponentId): bool
    {
        if ($parentProductId === $candidateComponentId) {
            return true;
        }
        // BFS through nested compositions.
        $visited = [];
        $queue = [$candidateComponentId];
        while ($queue !== []) {
            $current = (int) \array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            if ($current === $parentProductId) {
                return true;
            }
            // If $current is itself composite, walk its components.
            $stmt = Db::pdo()->prepare(
                'SELECT pci.component_product_id
                 FROM product_composition_items pci
                 JOIN product_compositions pc ON pc.id = pci.composition_id
                 WHERE pc.product_id = ?'
            );
            $stmt->execute([$current]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $nextId) {
                $queue[] = (int) $nextId;
            }
        }
        return false;
    }
}
