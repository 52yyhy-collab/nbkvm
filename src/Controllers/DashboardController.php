<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Repositories\AuditLogRepository;
use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\SnapshotRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\UserRepository;
use Nbkvm\Repositories\VmRepository;
use Nbkvm\Services\EnvironmentCheckService;
use Nbkvm\Services\VmService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        (new VmService())->refreshStates();
        $this->view('dashboard', [
            'images' => (new ImageRepository())->all(),
            'templates' => (new TemplateRepository())->all(),
            'users' => (new UserRepository())->all(),
            'vms' => (new VmRepository())->all(),
            'snapshots' => (new SnapshotRepository())->all(),
            'auditLogs' => (new AuditLogRepository())->latest(20),
            'envChecks' => (new EnvironmentCheckService())->report(),
            'libvirtAvailable' => function_exists('libvirt_connect'),
            'authUser' => auth_user(),
            'novncBaseUrl' => (string) config('novnc.base_url'),
        ]);
    }
}
