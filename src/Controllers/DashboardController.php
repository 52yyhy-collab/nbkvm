<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Repositories\AuditLogRepository;
use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\IpAddressRepository;
use Nbkvm\Repositories\IpPoolRepository;
use Nbkvm\Repositories\JobRepository;
use Nbkvm\Repositories\NetworkRepository;
use Nbkvm\Repositories\SettingRepository;
use Nbkvm\Repositories\SnapshotRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\UserRepository;
use Nbkvm\Repositories\VmRepository;
use Nbkvm\Services\EnvironmentCheckService;
use Nbkvm\Services\NicConfigService;
use Nbkvm\Services\NoVncService;
use Nbkvm\Services\VmService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        (new VmService())->refreshStates();

        $templateRepo = new TemplateRepository();
        $vmRepo = new VmRepository();
        $networkRepo = new NetworkRepository();
        $poolRepo = new IpPoolRepository();
        $nicService = new NicConfigService();

        $templates = $templateRepo->all();
        foreach ($templates as &$template) {
            $template['normalized_nics'] = $nicService->hydrateTemplateNics($template);
        }
        unset($template);

        $vms = $vmRepo->all();
        foreach ($vms as &$vm) {
            $vm['normalized_nics'] = $nicService->hydrateVmNics($vm);
        }
        unset($vm);

        $noVnc = new NoVncService();
        $noVncStatus = [];
        foreach ($vms as $vm) {
            $noVncStatus[$vm['name']] = $noVnc->status((string) $vm['name']);
        }

        $settingsRows = (new SettingRepository())->all();
        $settingsMap = [];
        foreach ($settingsRows as $row) {
            $settingsMap[$row['key_name']] = $row['value_text'];
        }

        $networks = $networkRepo->all();
        $pools = $poolRepo->all();
        $poolsByNetwork = [];
        foreach ($pools as $pool) {
            $key = (int) ($pool['network_id'] ?? 0) > 0 ? 'id:' . (int) $pool['network_id'] : 'name:' . (string) ($pool['network_name'] ?? '');
            $poolsByNetwork[$key][] = $pool;
        }

        $networkConfigs = [];
        foreach ($networks as $network) {
            $key = 'id:' . (int) $network['id'];
            $networkConfigs[] = [
                'network' => $network,
                'pools' => $poolsByNetwork[$key] ?? ($poolsByNetwork['name:' . (string) $network['name']] ?? []),
            ];
        }

        $this->view('dashboard', [
            'images' => (new ImageRepository())->all(),
            'networks' => $networks,
            'networkConfigs' => $networkConfigs,
            'ipPools' => $pools,
            'ipAddresses' => (new IpAddressRepository())->all(),
            'templates' => $templates,
            'users' => (new UserRepository())->all(),
            'vms' => $vms,
            'snapshots' => (new SnapshotRepository())->all(),
            'settings' => $settingsRows,
            'settingsMap' => $settingsMap,
            'auditLogs' => (new AuditLogRepository())->latest(20),
            'jobs' => (new JobRepository())->latest(20),
            'envChecks' => (new EnvironmentCheckService())->report(),
            'libvirtAvailable' => function_exists('libvirt_connect'),
            'authUser' => auth_user(),
            'novncBaseUrl' => (string) config('novnc.base_url'),
            'noVncStatus' => $noVncStatus,
        ]);
    }
}
