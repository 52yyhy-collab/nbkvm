<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Services\ImageService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class ImageController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new ImageService())->upload($request->file('image') ?? []);
            (new \Nbkvm\Services\AuditService())->log('上传镜像', 'image', (string) (($request->file('image') ?? [])['name'] ?? 'unknown'));
            $this->back('镜像上传成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('镜像上传失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new ImageService())->delete((int) $request->input('id'));
            (new \Nbkvm\Services\AuditService())->log('删除镜像', 'image', (string) $request->input('id'));
            $this->back('镜像删除成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('镜像删除失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
