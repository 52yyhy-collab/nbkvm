<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use RuntimeException;
class NoVncService
{
    private function stateDir(): string
    {
        $dir = sys_get_temp_dir() . '/nbkvm-novnc';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }
    private function pidFile(string $vmName): string
    {
        return $this->stateDir() . '/' . $vmName . '.pid';
    }
    private function logFile(string $vmName): string
    {
        return $this->stateDir() . '/' . $vmName . '.log';
    }
    public function helperCommand(string $vmName, int $port = 6080): string
    {
        return sprintf('bash bin/start_novnc_proxy.sh %s %d', escapeshellarg($vmName), $port);
    }
    public function start(string $vmName, int $port = 6080): void
    {
        if ($this->isRunning($vmName)) {
            return;
        }
        $cmd = sprintf('nohup bash %s %s %d >> %s 2>&1 & echo $! > %s',
            escapeshellarg(base_path('bin/start_novnc_proxy.sh')),
            escapeshellarg($vmName),
            $port,
            escapeshellarg($this->logFile($vmName)),
            escapeshellarg($this->pidFile($vmName))
        );
        exec($cmd);
        usleep(500000);
        if (!$this->isRunning($vmName)) {
            throw new RuntimeException('noVNC 代理启动失败。');
        }
    }
    public function stop(string $vmName): void
    {
        $pidFile = $this->pidFile($vmName);
        if (!is_file($pidFile)) {
            return;
        }
        $pid = (int) trim((string) file_get_contents($pidFile));
        if ($pid > 0) {
            @posix_kill($pid, SIGTERM);
            usleep(300000);
            if (@posix_kill($pid, 0)) {
                @posix_kill($pid, SIGKILL);
            }
        }
        @unlink($pidFile);
    }
    public function isRunning(string $vmName): bool
    {
        $pidFile = $this->pidFile($vmName);
        if (!is_file($pidFile)) {
            return false;
        }
        $pid = (int) trim((string) file_get_contents($pidFile));
        return $pid > 0 && @posix_kill($pid, 0);
    }
    public function status(string $vmName): array
    {
        return [
            'running' => $this->isRunning($vmName),
            'pidFile' => $this->pidFile($vmName),
            'logFile' => $this->logFile($vmName),
        ];
    }
}
