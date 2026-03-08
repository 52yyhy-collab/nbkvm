<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Services\AuditService;
use Nbkvm\Services\TemplateService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class TemplateController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            $id = (int) ($request->input('template_id') ?: $request->input('id'));
            if ($id > 0) {
                (new TemplateService())->update($id, $request->all());
                (new AuditService())->log('更新模板', 'template', (string) $id);
                $this->back('模板更新成功。', 'success', $redirectTo);
            }

            (new TemplateService())->create($request->all());
            (new AuditService())->log('创建模板', 'template', (string) $request->input('name'));
            $this->back('模板创建成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('模板保存失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new TemplateService())->delete((int) $request->input('id'));
            (new AuditService())->log('删除模板', 'template', (string) $request->input('id'));
            $this->back('模板删除成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('模板删除失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
