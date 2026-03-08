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
        try {
            (new ImageService())->upload($request->file('image') ?? []);
            $this->back('镜像上传成功。');
        } catch (\Throwable $e) {
            $this->back('镜像上传失败：' . $e->getMessage(), 'error');
        }
    }
}
