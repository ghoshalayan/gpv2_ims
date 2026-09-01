<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class BusinessProfileRepository
{
    /** Singleton row — always returns the id=1 record. */
    public function get(): array
    {
        $row = Db::pdo()->query(
            'SELECT id, legal_name, display_name, gstin, state, address, phone, email,
                    financial_year_start_month, updated_at
             FROM business_profile WHERE id = 1 LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        // Migration seeds row 1, so this should always exist. Defensive fallback:
        return $row === false ? [] : $row;
    }

    public function update(array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE business_profile
             SET legal_name = ?, display_name = ?, gstin = ?, state = ?,
                 address = ?, phone = ?, email = ?, financial_year_start_month = ?,
                 updated_by = ?
             WHERE id = 1'
        );
        $stmt->execute([
            $data['legal_name'], $data['display_name'], $data['gstin'], $data['state'],
            $data['address'], $data['phone'], $data['email'],
            $data['financial_year_start_month'], $updatedBy,
        ]);
    }
}
