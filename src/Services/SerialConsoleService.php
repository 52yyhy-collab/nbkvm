<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Support\Shell;
use RuntimeException;

class SerialConsoleService
{
    public function __construct(private readonly ?Shell $shell = null)
    {
    }

    public function capabilities(array $vm): array
    {
        $virshAvailable = $this->commandAvailable((string) config('libvirt.virsh', 'virsh'));
        $tmuxAvailable = $this->commandAvailable('tmux');
        $serialConfigured = $this->serialConfigured($vm);
        $running = $this->sessionExists((string) ($vm['name'] ?? ''));

        $hint = '可通过 virsh console 提供终端式控制台。';
        if (!$virshAvailable) {
            $hint = '宿主机未检测到 virsh，无法启用串口控制台。';
        } elseif (!$tmuxAvailable) {
            $hint = '宿主机未检测到 tmux，暂时无法提供网页交互会话。';
        } elseif (!$serialConfigured) {
            $hint = '当前虚拟机 XML 尚未明确配置 serial/console target；新建 VM 已自动带上，旧 VM 可能需要重新定义。';
        }

        return [
            'virsh_available' => $virshAvailable,
            'tmux_available' => $tmuxAvailable,
            'serial_configured' => $serialConfigured,
            'running' => $running,
            'session_name' => $this->sessionName((string) ($vm['name'] ?? 'vm')),
            'hint' => $hint,
        ];
    }

    public function start(array $vm): array
    {
        $name = (string) ($vm['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('虚拟机名称不能为空。');
        }

        $capabilities = $this->capabilities($vm);
        if (!$capabilities['virsh_available']) {
            throw new RuntimeException('宿主机未安装 virsh。');
        }
        if (!$capabilities['tmux_available']) {
            throw new RuntimeException('宿主机未安装 tmux。');
        }

        if (!$this->sessionExists($name)) {
            $command = sprintf(
                '%s -c %s console %s',
                escapeshellcmd((string) config('libvirt.virsh', 'virsh')),
                escapeshellarg((string) config('libvirt.uri')),
                escapeshellarg($name)
            );
            $result = $this->shell()->run(['tmux', 'new-session', '-d', '-s', $this->sessionName($name), $command]);
            if (!$result->succeeded()) {
                throw new RuntimeException('启动串口控制台失败：' . ($result->stderr ?: $result->stdout));
            }
            usleep(400000);
        }

        return $this->snapshot($vm);
    }

    public function stop(array $vm): array
    {
        $name = (string) ($vm['name'] ?? '');
        if ($name !== '' && $this->sessionExists($name)) {
            $result = $this->shell()->run(['tmux', 'kill-session', '-t', $this->sessionName($name)]);
            if (!$result->succeeded()) {
                throw new RuntimeException('停止串口控制台失败：' . ($result->stderr ?: $result->stdout));
            }
        }

        return $this->snapshot($vm);
    }

    public function send(array $vm, string $input, bool $appendEnter = true): array
    {
        $name = (string) ($vm['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('虚拟机名称不能为空。');
        }
        if (!$this->sessionExists($name)) {
            $this->start($vm);
        }

        if ($input !== '') {
            $result = $this->shell()->run(['tmux', 'send-keys', '-t', $this->sessionName($name), '-l', $input]);
            if (!$result->succeeded()) {
                throw new RuntimeException('发送控制台输入失败：' . ($result->stderr ?: $result->stdout));
            }
        }

        if ($appendEnter) {
            $result = $this->shell()->run(['tmux', 'send-keys', '-t', $this->sessionName($name), 'Enter']);
            if (!$result->succeeded()) {
                throw new RuntimeException('发送回车失败：' . ($result->stderr ?: $result->stdout));
            }
        }

        usleep(150000);
        return $this->snapshot($vm);
    }

    public function snapshot(array $vm): array
    {
        $name = (string) ($vm['name'] ?? '');
        $capabilities = $this->capabilities($vm);
        $output = '';

        if ($name !== '' && $capabilities['tmux_available'] && $this->sessionExists($name)) {
            $capture = $this->shell()->run(['tmux', 'capture-pane', '-pt', $this->sessionName($name), '-S', '-200']);
            if ($capture->succeeded()) {
                $output = $capture->stdout;
            }
        }

        return [
            'capabilities' => $capabilities,
            'output' => $output,
        ];
    }

    private function shell(): Shell
    {
        return $this->shell ?? new Shell();
    }

    private function sessionExists(string $vmName): bool
    {
        if ($vmName === '' || !$this->commandAvailable('tmux')) {
            return false;
        }

        $result = $this->shell()->run(['tmux', 'has-session', '-t', $this->sessionName($vmName)]);
        return $result->succeeded();
    }

    private function sessionName(string $vmName): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $vmName) ?: 'vm';
        return 'nbkvm-console-' . substr($sanitized, 0, 40);
    }

    private function commandAvailable(string $command): bool
    {
        $result = $this->shell()->run(['/bin/sh', '-lc', 'command -v ' . escapeshellarg($command)]);
        return $result->succeeded() && trim($result->stdout) !== '';
    }

    private function serialConfigured(array $vm): bool
    {
        $xmlPath = trim((string) ($vm['xml_path'] ?? ''));
        $xml = '';
        if ($xmlPath !== '' && is_file($xmlPath)) {
            $xml = (string) @file_get_contents($xmlPath);
        }
        if ($xml === '') {
            $result = $this->shell()->run([
                (string) config('libvirt.virsh', 'virsh'),
                '-c',
                (string) config('libvirt.uri'),
                'dumpxml',
                (string) ($vm['name'] ?? ''),
            ]);
            if ($result->succeeded()) {
                $xml = $result->stdout;
            }
        }

        if ($xml === '') {
            return false;
        }

        return str_contains($xml, '<serial') && str_contains($xml, '<console');
    }
}
