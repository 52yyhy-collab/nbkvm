<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\SettingService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class SettingController extends BaseController
{
    public function update(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireAdmin();
        try {
            (new SettingService())->update($request->all());
            (new AuditService())->log('更新系统配置', 'setting', 'system');
            $this->back('系统配置更新成功。');
        } catch (\Throwable $e) {
            $this->back('系统配置更新失败：' . $e->getMessage(), 'error');
        }
    }
}
