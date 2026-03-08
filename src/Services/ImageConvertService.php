<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Support\Shell;
use RuntimeException;
class ImageConvertService
{
    public function __construct(private readonly ?Shell $shell = null)
    {
    }
    private function shell(): Shell
    {
        return $this->shell ?? new Shell();
    }
    public function convert(int $imageId, string $targetExtension): int
    {
        $image = (new ImageRepository())->find($imageId);
        if (!$image) {
            throw new RuntimeException('镜像不存在。');
        }
        $targetExtension = strtolower(trim($targetExtension));
        if (!in_array($targetExtension, ['qcow2', 'raw', 'img'], true)) {
            throw new RuntimeException('当前只支持转换到 qcow2 / raw / img。');
        }
        $sourceExtension = strtolower((string) $image['extension']);
        $normalizedSource = $sourceExtension === 'img' ? 'raw' : $sourceExtension;
        $normalizedTarget = $targetExtension === 'img' ? 'raw' : $targetExtension;
        if ($normalizedSource === $normalizedTarget && $sourceExtension === $targetExtension) {
            throw new RuntimeException('源镜像已经是目标格式。');
        }
        $targetName = pathinfo((string) $image['name'], PATHINFO_FILENAME) . '_converted.' . $targetExtension;
        $targetPath = rtrim((string) config('upload_path'), '/') . '/' . $targetName;
        $cmd = [
            (string) config('libvirt.qemu_img'),
            'convert',
            '-O',
            $normalizedTarget,
            (string) $image['path'],
            $targetPath,
        ];
        $result = $this->shell()->run($cmd);
        if (!$result->succeeded()) {
            throw new RuntimeException('镜像转换失败：' . ($result->stderr ?: $result->stdout));
        }
        @chmod($targetPath, 0644);
        return (new ImageRepository())->create([
            'name' => $targetName,
            'original_name' => $targetName,
            'path' => $targetPath,
            'extension' => $targetExtension,
            'size_bytes' => (int) filesize($targetPath),
            'created_at' => date('c'),
        ]);
    }
}
