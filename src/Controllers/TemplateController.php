<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\TemplateService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class TemplateController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            (new TemplateService())->create($request->all());
            (new \Nbkvm\Services\AuditService())->log('创建模板', 'template', (string) $request->input('name'));
            $this->back('模板创建成功。');
        } catch (\Throwable $e) {
            $this->back('模板创建失败：' . $e->getMessage(), 'error');
        }
    }
}
