<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Services\AuditService;
use Nbkvm\Services\NodeNetworkResourceService;
use Nbkvm\Services\TaskService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class NodeNetworkResourceController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            $id = (new NodeNetworkResourceService())->save($request->all());
            (new AuditService())->log('保存节点网络对象', 'node_network_resource', (string) $id);
            $this->back('节点网络对象已保存。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('节点网络对象保存失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function apply(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');
        $id = (int) $request->input('id');

        try {
            $result = (new TaskService())->run('应用节点网络对象', 'node_network_resource', (string) $id, fn () => (new NodeNetworkResourceService())->apply($id));
            (new AuditService())->log('应用节点网络对象', 'node_network_resource', (string) $id, (string) ($result['output'] ?? ''));
            $this->back((string) ($result['message'] ?? '节点网络对象已应用。'), 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('节点网络对象应用失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function removeHost(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');
        $id = (int) $request->input('id');

        try {
            $result = (new TaskService())->run('移除宿主节点网络对象', 'node_network_resource', (string) $id, fn () => (new NodeNetworkResourceService())->removeFromHost($id));
            (new AuditService())->log('移除宿主节点网络对象', 'node_network_resource', (string) $id, (string) ($result['output'] ?? ''));
            $this->back((string) ($result['message'] ?? '节点网络对象已从宿主移除。'), 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('移除宿主节点网络对象失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');
        $id = (int) $request->input('id');

        try {
            (new NodeNetworkResourceService())->deleteDefinition($id);
            (new AuditService())->log('删除节点网络对象候选', 'node_network_resource', (string) $id);
            $this->back('节点网络对象候选已删除。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('删除节点网络对象候选失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
