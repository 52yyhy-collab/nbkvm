<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Services\AuditService;
use Nbkvm\Services\ImageConvertService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class ImageConvertController extends BaseController
{
    public function convert(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new ImageConvertService())->convert((int) $request->input('id'), (string) $request->input('target_extension'));
            (new AuditService())->log('转换镜像格式', 'image', (string) $request->input('id'));
            $this->back('镜像转换成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('镜像转换失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
