<?php

declare(strict_types=1);
namespace Nbkvm\Services;
class CleanupService
{
    public function removeVmFiles(array $vm): void
    {
        if (!empty($vm['disk_path'])) {
            @unlink((string) $vm['disk_path']);
        }
        if (!empty($vm['xml_path'])) {
            @unlink((string) $vm['xml_path']);
        }
        if (!empty($vm['cloud_init_iso_path'])) {
            @unlink((string) $vm['cloud_init_iso_path']);
        }
        $vmDir = !empty($vm['disk_path']) ? dirname((string) $vm['disk_path']) : '';
        if ($vmDir !== '' && is_dir($vmDir)) {
            foreach (glob($vmDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($vmDir);
        }
    }
}
