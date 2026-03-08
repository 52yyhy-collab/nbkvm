<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\ImageConvertService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class ImageConvertController extends BaseController
{
    public function qcow2(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            (new ImageConvertService())->convertToQcow2((int) $request->input('id'));
            (new AuditService())->log('转换镜像为 qcow2', 'image', (string) $request->input('id'));
            $this->back('镜像已转换为 qcow2。');
        } catch (\Throwable $e) {
            $this->back('镜像转换失败：' . $e->getMessage(), 'error');
        }
    }
}
