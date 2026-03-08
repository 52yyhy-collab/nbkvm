<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\TemplateRepository;
use RuntimeException;
class ImageService
{
    public function __construct(private readonly ?ImageRepository $images = null)
    {
    }
    public function upload(array $file): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('镜像上传失败。');
        }
        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, config('allowed_extensions', []), true)) {
            throw new RuntimeException('不支持的镜像格式。');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $name = $safeName . '_' . date('Ymd_His') . '.' . $extension;
        $destination = rtrim((string) config('upload_path'), '/') . '/' . $name;
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0775, true);
        }
        if (!move_uploaded_file($tmp, $destination)) {
            if (!rename($tmp, $destination)) {
                throw new RuntimeException('无法保存上传文件。');
            }
        }
        @chmod($destination, 0644);
        return ($this->images ?? new ImageRepository())->create([
            'name' => $name,
            'original_name' => $originalName,
            'path' => $destination,
            'extension' => $extension,
            'size_bytes' => (int) ($file['size'] ?? filesize($destination)),
            'created_at' => date('c'),
        ]);
    }
    public function delete(int $id): void
    {
        foreach ((new TemplateRepository())->all() as $template) {
            if ((int) $template['image_id'] === $id) {
                throw new RuntimeException('该镜像仍被模板使用，不能删除。');
            }
        }
        $image = (new ImageRepository())->find($id);
        if (!$image) {
            throw new RuntimeException('镜像不存在。');
        }
        @unlink((string) $image['path']);
        (new ImageRepository())->delete($id);
    }
}
