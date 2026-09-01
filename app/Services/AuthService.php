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
}
