<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\AuditLogRepository;
class AuditService
{
    public function log(string $action, ?string $targetType = null, ?string $targetName = null, ?string $detail = null): void
    {
        $user = auth_user();
        (new AuditLogRepository())->create([
            'username' => $user['username'] ?? null,
            'action' => $action,
            'target_type' => $targetType,
            'target_name' => $targetName,
            'detail' => $detail,
            'created_at' => date('c'),
        ]);
    }
}
