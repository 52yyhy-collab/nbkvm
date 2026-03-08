<?php

declare(strict_types=1);
namespace Nbkvm\Services;
class NoVncService
{
    public function helperCommand(string $vmName, int $port = 6080): string
    {
        return sprintf('bash bin/start_novnc_proxy.sh %s %d', escapeshellarg($vmName), $port);
    }
}
