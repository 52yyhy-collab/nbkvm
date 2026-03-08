<?php

declare(strict_types=1);
namespace Nbkvm\Services;
class CleanupService
{
    public function removeVmFiles(array $vm): void
    {
        @unlink((string) $vm['disk_path']);
        @unlink((string) $vm['xml_path']);
        if (!empty($vm['cloud_init_iso_path'])) {
            @unlink((string) $vm['cloud_init_iso_path']);
        }
        $vmDir = dirname((string) $vm['disk_path']);
        @unlink($vmDir . '/user-data');
        @unlink($vmDir . '/meta-data');
        @rmdir($vmDir);
    }
}
