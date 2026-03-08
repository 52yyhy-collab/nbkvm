<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\SettingRepository;
use RuntimeException;
class SettingService
{
    public function update(array $data): void
    {
        $repo = new SettingRepository();
        if (array_key_exists('upload_max_size_mb', $data)) {
            $repo->set('upload_max_size_mb', (string) max(1, (int) $data['upload_max_size_mb']));
        }
        if (array_key_exists('expire_grace_days', $data)) {
            $repo->set('expire_grace_days', (string) max(0, (int) $data['expire_grace_days']));
        }
        if (array_key_exists('system_variables_json', $data)) {
            $json = trim((string) $data['system_variables_json']);
            if ($json === '') {
                $json = '{}';
            }
            json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('系统变量必须是合法 JSON。');
            }
            $repo->set('system_variables_json', $json);
        }
    }
}
