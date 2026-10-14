<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(private UserRepository $users = new UserRepository()) {}

    /**
     * Validate credentials. Returns the user row on success, null on failure.
     * Inactive users are rejected even with correct credentials.
     */
    public function attempt(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') {
            return null;
        }

        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return null;
        }
        if ($user['status'] !== 'active') {
            return null;
        }
        if (!\password_verify($password, $user['password_hash'])) {
            return null;
        }

        $this->users->touchLastLogin((int) $user['id']);
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Validate credentials WITHOUT knowing the business in advance.
     *
     * The username decides which business the sign-in belongs to, so the lookup
     * spans every tenant. The password is verified BEFORE any tenant is bound to
     * the connection: a wrong password must not cause the application to start
     * operating inside someone else's books, however briefly.
     *
     * Returns the user row with its `tenant_id`; the caller binds the tenant.
     */
    public function attemptAcrossTenants(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') {
            return null;
        }

        $user = $this->users->findLoginAcrossTenants($username);
        if ($user === null) {
            return null;
        }
        if ($user['status'] !== 'active') {
            return null;
        }
        if (!\password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    /**
     * Stamp the sign-in time. Separate from attemptAcrossTenants() because the
     * row lives in the tenant's own data, so it can only be written once the
     * caller has bound that tenant to the connection.
     */
    public function touchLogin(int $userId): void
    {
        $this->users->touchLastLogin($userId);
    }
}
