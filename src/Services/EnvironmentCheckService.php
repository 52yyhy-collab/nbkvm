<?php

declare(strict_types=1);
namespace Nbkvm\Services;
class EnvironmentCheckService
{
    public function report(): array
    {
        $checks = [];
        $checks[] = $this->item('PHP', PHP_VERSION, true);
        $checks[] = $this->item('SQLite 扩展', extension_loaded('sqlite3') ? '已加载' : '未加载', extension_loaded('sqlite3'));
        $checks[] = $this->item('libvirt 扩展', function_exists('libvirt_connect') ? '已加载' : '未加载', function_exists('libvirt_connect'));
        $checks[] = $this->item('qemu-img', $this->cmdVersion((string) config('libvirt.qemu_img')), $this->commandExists((string) config('libvirt.qemu_img')));
        $checks[] = $this->item('virsh', $this->cmdVersion((string) config('libvirt.virsh')), $this->commandExists((string) config('libvirt.virsh')));
        $checks[] = $this->item('libvirt socket', is_file('/var/run/libvirt/libvirt-sock') ? '/var/run/libvirt/libvirt-sock' : '未找到', is_file('/var/run/libvirt/libvirt-sock'));
        $checks[] = $this->item('/dev/kvm', file_exists('/dev/kvm') ? '/dev/kvm' : '未找到', file_exists('/dev/kvm'));
        $checks[] = $this->item('存储根目录', (string) config('storage_root'), is_dir((string) config('storage_root')) || @mkdir((string) config('storage_root'), 0755, true));
        $checks[] = $this->item('cloud-localds', $this->cmdVersion((string) config('cloud_init.cloud_localds')), $this->commandExists((string) config('cloud_init.cloud_localds')));
        $checks[] = $this->item('noVNC 安装', is_dir('/usr/share/novnc') ? '/usr/share/novnc' : '未安装', is_dir('/usr/share/novnc'));
        $checks[] = $this->item('websockify', $this->cmdVersion('websockify'), $this->commandExists('websockify'));
        $checks[] = $this->item('noVNC 地址', (string) config('novnc.base_url') ?: '未配置', !empty(config('novnc.base_url')));
        return $checks;
    }
    private function item(string $name, string $value, bool $ok): array
    {
        return ['name' => $name, 'value' => $value, 'ok' => $ok];
    }
    private function commandExists(string $command): bool
    {
        $output = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
        return $output !== '';
    }
    private function cmdVersion(string $command): string
    {
        if (!$this->commandExists($command)) {
            return '未安装';
        }
        $output = shell_exec(escapeshellarg($command) . ' --version 2>&1');
        return trim((string) preg_split('/\r?\n/', (string) $output)[0]);
    }
}
