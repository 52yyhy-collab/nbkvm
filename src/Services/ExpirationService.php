<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\SettingRepository;
use Nbkvm\Repositories\VmRepository;
class ExpirationService
{
    public function process(): array
    {
        $repo = new VmRepository();
        $settings = new SettingRepository();
        $graceDays = (int) ($settings->get('expire_grace_days', '3') ?: 3);
        $now = time();
        $done = [];
        foreach ($repo->all() as $vm) {
            if (empty($vm['expires_at'])) {
                continue;
            }
            $expiresAt = strtotime((string) $vm['expires_at']);
            if ($expiresAt === false || $expiresAt > $now) {
                continue;
            }
            if (empty($vm['expired_at'])) {
                try {
                    (new LibvirtService())->suspend((string) $vm['name']);
                    $repo->markExpired((int) $vm['id'], 'paused');
                    $done[] = 'paused:' . $vm['name'];
                } catch (\Throwable) {
                }
                continue;
            }
            $expiredAt = strtotime((string) $vm['expired_at']);
            if ($expiredAt !== false && $expiredAt + ($graceDays * 86400) <= $now) {
                try {
                    (new VmService())->delete((int) $vm['id'], true);
                    $done[] = 'deleted:' . $vm['name'];
                } catch (\Throwable) {
                }
            }
        }
        return $done;
    }
}
