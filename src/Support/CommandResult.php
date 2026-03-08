<?php

declare(strict_types=1);
namespace Nbkvm\Support;
class CommandResult
{
    public function __construct(
        public readonly string $command,
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {}
    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
