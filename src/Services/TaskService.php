<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\JobRepository;
class TaskService
{
    public function run(string $name, ?string $targetType, ?string $targetName, callable $callback): mixed
    {
        $repo = new JobRepository();
        $jobId = $repo->create([
            'name' => $name,
            'target_type' => $targetType,
            'target_name' => $targetName,
            'status' => 'running',
            'output' => null,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);
        try {
            $result = $callback();
            $repo->update($jobId, 'success', '执行成功');
            return $result;
        } catch (\Throwable $e) {
            $repo->update($jobId, 'failed', $e->getMessage());
            throw $e;
        }
    }
}
