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
    public function convertToQcow2(int $imageId): int
    {
        $image = (new ImageRepository())->find($imageId);
        if (!$image) {
            throw new RuntimeException('镜像不存在。');
        }
        $extension = strtolower((string) $image['extension']);
        if ($extension === 'qcow2') {
            throw new RuntimeException('该镜像已经是 qcow2。');
        }
        if ($extension === 'iso') {
            throw new RuntimeException('ISO 不能直接转换成系统盘 qcow2，请把它当安装介质使用。');
        }
        $targetName = pathinfo((string) $image['name'], PATHINFO_FILENAME) . '_converted.qcow2';
        $targetPath = rtrim((string) config('upload_path'), '/') . '/' . $targetName;
        $cmd = [
            (string) config('libvirt.qemu_img'),
            'convert',
            '-O',
            'qcow2',
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
            'extension' => 'qcow2',
            'size_bytes' => (int) filesize($targetPath),
            'created_at' => date('c'),
        ]);
    }
}
