<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class UserRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT u.id, u.name, u.phone, u.email, u.username, u.role_id, u.is_protected,
                    r.role_key AS role, r.label AS role_label,
                    u.status, u.last_login_at,
                    e.id AS linked_employee_id
             FROM users u
             JOIN roles r        ON r.id = u.role_id
             LEFT JOIN employees e ON e.linked_user_id = u.id
             ORDER BY u.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT u.id, u.name, u.phone, u.email, u.username, u.password_hash,
                    u.role_id, u.is_protected, r.role_key AS role, r.label AS role_label,
                    u.status, u.last_login_at
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT u.id, u.name, u.phone, u.email, u.address, u.username,
                    u.role_id, u.is_protected, r.role_key AS role, r.label AS role_label,
                    u.status, u.last_login_at
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function touchLastLogin(int $id): void
    {
        $stmt = Db::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        return $this->existsByField('username', $username, $excludeId);
    }

    public function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        return $this->existsByField('phone', $phone, $excludeId);
    }

    private function existsByField(string $field, string $value, ?int $excludeId): bool
    {
        $sql = "SELECT 1 FROM users WHERE {$field} = ?";
        $params = [$value];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data, string $passwordHash): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO users (name, phone, email, address, username, password_hash, role_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'],
            $data['username'], $passwordHash, (int) $data['role_id'], $data['status'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE users
             SET name = ?, phone = ?, email = ?, address = ?, username = ?, role_id = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'],
            $data['username'], (int) $data['role_id'], $data['status'], $id,
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Db::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = Db::pdo()->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /** Count active admins excluding a given user id (used for last-admin protection). */
    public function countActiveAdminsExcept(int $excludeId): int
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.role_key = 'admin' AND u.status = 'active' AND u.id <> ?"
        );
        $stmt->execute([$excludeId]);
        return (int) $stmt->fetchColumn();
    }

    /** Count users assigned to a given role (blocks role deletion). */
    public function countByRoleId(int $roleId): int
    {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return (int) $stmt->fetchColumn();
    }
}
