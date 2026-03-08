<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\UserRepository;
class AuthService
{
    public function attempt(string $username, string $password): bool
    {
        $user = (new UserRepository())->findByUsername(trim($username));
        if (!$user) {
            return false;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        auth_login($user);
        return true;
    }
}
