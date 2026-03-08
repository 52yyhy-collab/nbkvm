<?php

declare(strict_types=1);
namespace Nbkvm\Support;
use RuntimeException;
class Shell
{
    public function run(array $parts): CommandResult
    {
        $command = implode(' ', array_map('escapeshellarg', $parts));
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptor, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to open process');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return new CommandResult($command, $exitCode, trim((string) $stdout), trim((string) $stderr));
    }
}
