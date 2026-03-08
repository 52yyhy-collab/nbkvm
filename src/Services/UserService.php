<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\UserRepository;
use RuntimeException;
class UserService
{
    public function create(string $username, string $password, string $role = 'admin'): void
    {
        $username = trim($username);
        if ($username === '') {
            throw new RuntimeException('用户名不能为空。');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('密码至少需要 8 位。');
        }
        $repo = new UserRepository();
        if ($repo->findByUsername($username)) {
            throw new RuntimeException('用户已存在。');
        }
        $repo->create([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role ?: 'admin',
            'created_at' => date('c'),
        ]);
    }
    public function updateRole(int $id, string $role): void
    {
        if (!in_array($role, ['admin', 'operator', 'readonly'], true)) {
            throw new RuntimeException('不支持的角色。');
        }
        (new UserRepository())->updateRole($id, $role);
    }
    public function delete(int $id): void
    {
        $user = auth_user();
        if ($user && (int) ($user['id'] ?? 0) === $id) {
            throw new RuntimeException('不能删除当前登录用户。');
        }
        (new UserRepository())->delete($id);
    }
}
