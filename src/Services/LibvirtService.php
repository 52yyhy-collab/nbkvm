<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Support\Shell;
use RuntimeException;
class LibvirtService
{
    public function __construct(private readonly ?Shell $shell = null)
    {
    }
    private function shell(): Shell
    {
        return $this->shell ?? new Shell();
    }
    private function ensureExtension(): void
    {
        if (!function_exists('libvirt_connect')) {
            throw new RuntimeException('未检测到 PHP libvirt 扩展，请先安装并启用 php-libvirt。');
        }
    }
    private function connection(bool $readonly = false)
    {
        $this->ensureExtension();
        $connection = @libvirt_connect((string) config('libvirt.uri'), $readonly);
        if ($connection === false) {
            throw new RuntimeException('连接 libvirt 失败：' . $this->lastError());
        }
        return $connection;
    }
    private function lastError(): string
    {
        return function_exists('libvirt_get_last_error') ? (string) libvirt_get_last_error() : 'unknown error';
    }
    public function define(string $xml): void
    {
        $connection = $this->connection(false);
        $domain = libvirt_domain_define_xml($connection, $xml);
        if ($domain === false) {
            throw new RuntimeException('定义虚拟机失败：' . $this->lastError());
        }
    }
    private function lookupWithConnection(string $name): array
    {
        $connection = $this->connection(false);
        $domain = @libvirt_domain_lookup_by_name($connection, $name);
        if ($domain === false) {
            throw new RuntimeException('查找虚拟机失败：' . $this->lastError());
        }
        return [$connection, $domain];
    }
    public function start(string $name): void
    {
        [$connection, $domain] = $this->lookupWithConnection($name);
        if (!libvirt_domain_create($domain)) {
            throw new RuntimeException('启动虚拟机失败：' . $this->lastError());
        }
        unset($connection);
    }
    public function shutdown(string $name): void
    {
        [$connection, $domain] = $this->lookupWithConnection($name);
        if (!libvirt_domain_shutdown($domain)) {
            throw new RuntimeException('关机失败：' . $this->lastError());
        }
        unset($connection);
    }
    public function suspend(string $name): void
    {
        [$connection, $domain] = $this->lookupWithConnection($name);
        if (!libvirt_domain_suspend($domain)) {
            throw new RuntimeException('暂停失败：' . $this->lastError());
        }
        unset($connection);
    }
    public function destroy(string $name): void
    {
        [$connection, $domain] = $this->lookupWithConnection($name);
        if (!libvirt_domain_destroy($domain)) {
            throw new RuntimeException('强制停止失败：' . $this->lastError());
        }
        unset($connection);
    }
    public function undefine(string $name): void
    {
        [$connection, $domain] = $this->lookupWithConnection($name);
        if (!libvirt_domain_undefine($domain)) {
            throw new RuntimeException('取消定义失败：' . $this->lastError());
        }
        unset($connection);
    }
    public function domainExists(string $name): bool
    {
        try {
            [$connection, $domain] = $this->lookupWithConnection($name);
            unset($domain, $connection);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    public function domInfo(string $name): array
    {
        try {
            [$connection, $domain] = $this->lookupWithConnection($name);
            $info = libvirt_domain_get_info($domain);
            unset($connection);
            if ($info === false || !is_array($info)) {
                return ['state' => 'unknown'];
            }
            return $info;
        } catch (\Throwable) {
            return ['state' => 'unknown'];
        }
    }
    public function createDiskFromImage(string $sourceImage, string $outputDisk, int $sizeGb, ?string $sourceExtension = null): void
    {
        if (!is_dir(dirname($outputDisk))) {
            mkdir(dirname($outputDisk), 0755, true);
        }
        $qemuImg = (string) config('libvirt.qemu_img');
        $format = $this->detectImageFormat($sourceImage, $sourceExtension);
        if ($format === 'qcow2') {
            $result = $this->shell()->run([$qemuImg, 'create', '-f', 'qcow2', '-F', 'qcow2', '-b', $sourceImage, $outputDisk, $sizeGb . 'G']);
        } elseif ($format === 'raw') {
            $result = $this->shell()->run([$qemuImg, 'convert', '-f', 'raw', '-O', 'qcow2', $sourceImage, $outputDisk]);
            if ($result->succeeded()) {
                $result = $this->shell()->run([$qemuImg, 'resize', $outputDisk, $sizeGb . 'G']);
            }
        } elseif ($format === 'iso') {
            $result = $this->shell()->run([$qemuImg, 'create', '-f', 'qcow2', $outputDisk, $sizeGb . 'G']);
        } else {
            $result = $this->shell()->run([$qemuImg, 'convert', '-O', 'qcow2', $sourceImage, $outputDisk]);
            if ($result->succeeded()) {
                $result = $this->shell()->run([$qemuImg, 'resize', $outputDisk, $sizeGb . 'G']);
            }
        }
        if (!$result->succeeded()) {
            throw new RuntimeException('创建磁盘失败：' . ($result->stderr ?: $result->stdout));
        }
    }
    public function createEmptyDisk(string $outputDisk, int $sizeGb, string $format = 'qcow2'): void
    {
        if (!is_dir(dirname($outputDisk))) {
            mkdir(dirname($outputDisk), 0755, true);
        }
        $result = $this->shell()->run([(string) config('libvirt.qemu_img'), 'create', '-f', $format, $outputDisk, $sizeGb . 'G']);
        if (!$result->succeeded()) {
            throw new RuntimeException('创建空磁盘失败：' . ($result->stderr ?: $result->stdout));
        }
    }
    private function detectImageFormat(string $sourceImage, ?string $fallbackExtension = null): string
    {
        $fallback = strtolower((string) ($fallbackExtension ?: pathinfo($sourceImage, PATHINFO_EXTENSION)));
        if ($fallback === 'iso') {
            return 'iso';
        }
        $qemuImg = (string) config('libvirt.qemu_img');
        $result = $this->shell()->run([$qemuImg, 'info', '--output=json', $sourceImage]);
        if ($result->succeeded()) {
            $data = json_decode($result->stdout, true);
            if (is_array($data) && !empty($data['format'])) {
                return strtolower((string) $data['format']);
            }
        }
        return $fallback;
    }
    public function stateLabel(mixed $state): string
    {
        return match ((int) $state) {
            1 => 'running',
            2 => 'blocked',
            3 => 'paused',
            4 => 'shutdown',
            5 => 'shut off',
            6 => 'crashed',
            default => 'unknown',
        };
    }
    public function vncDisplay(string $name): ?string
    {
        $result = $this->shell()->run([(string) config('libvirt.virsh'), '-c', (string) config('libvirt.uri'), 'vncdisplay', $name]);
        if (!$result->succeeded() || $result->stdout === '') {
            return null;
        }
        return trim($result->stdout);
    }
    public function domIp(string $name): ?string
    {
        foreach ([['domifaddr', $name, '--source', 'lease'], ['domifaddr', $name]] as $args) {
            $result = $this->shell()->run(array_merge([(string) config('libvirt.virsh'), '-c', (string) config('libvirt.uri')], $args));
            if ($result->succeeded() && preg_match('/([0-9a-fA-F:.]+)\/\d+/', $result->stdout, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
