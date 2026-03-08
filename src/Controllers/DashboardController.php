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
use Nbkvm\Services\DiskConfigService;
use Nbkvm\Services\EnvironmentCheckService;
use Nbkvm\Services\HostNetworkDiscoveryService;
use Nbkvm\Services\NicConfigService;
use Nbkvm\Services\NoVncService;
use Nbkvm\Services\SerialConsoleService;
use Nbkvm\Services\VmService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        (new VmService())->refreshStates();

        $currentPage = (string) $request->input('page', 'overview');
        $allowedPages = ['overview', 'networks', 'templates', 'vms', 'images', 'system'];
        if (!in_array($currentPage, $allowedPages, true)) {
            $currentPage = 'overview';
        }

        $templateRepo = new TemplateRepository();
        $vmRepo = new VmRepository();
        $networkRepo = new NetworkRepository();
        $poolRepo = new IpPoolRepository();
        $nicService = new NicConfigService();
        $diskService = new DiskConfigService();

        $templates = $templateRepo->all();
        foreach ($templates as &$template) {
            $template['normalized_nics'] = $nicService->hydrateTemplateNics($template);
            $template['normalized_disks'] = $diskService->hydrateTemplateDisks($template);
        }
        unset($template);

        $vms = $vmRepo->all();
        $noVnc = new NoVncService();
        $serialConsole = new SerialConsoleService();
        $noVncStatus = [];
        $consoleCapabilities = [];
        foreach ($vms as &$vm) {
            $vm['normalized_nics'] = $nicService->hydrateVmNics($vm);
            $vm['normalized_disks'] = $diskService->hydrateVmDisks($vm);
            $noVncStatus[$vm['name']] = $noVnc->status((string) $vm['name']);
            $consoleCapabilities[$vm['name']] = $serialConsole->capabilities($vm);
        }
        unset($vm);

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
            $networkPools = $poolsByNetwork[$key] ?? ($poolsByNetwork['name:' . (string) $network['name']] ?? []);
            $network['ipv4_pool'] = $this->firstPoolByFamily($networkPools, 'ipv4');
            $network['ipv6_pool'] = $this->firstPoolByFamily($networkPools, 'ipv6');
            $networkConfigs[] = [
                'network' => $network,
                'pools' => $networkPools,
                'ipv4_pool' => $network['ipv4_pool'],
                'ipv6_pool' => $network['ipv6_pool'],
            ];
        }

        $bridgeCandidates = (new HostNetworkDiscoveryService())->detect();

        $this->view('dashboard', [
            'currentPage' => $currentPage,
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
            'bridgeCandidates' => $bridgeCandidates,
            'libvirtAvailable' => function_exists('libvirt_connect'),
            'authUser' => auth_user(),
            'novncBaseUrl' => (string) config('novnc.base_url'),
            'noVncStatus' => $noVncStatus,
            'consoleCapabilities' => $consoleCapabilities,
        ]);
    }

    private function firstPoolByFamily(array $pools, string $family): ?array
    {
        foreach ($pools as $pool) {
            if (strtolower((string) ($pool['family'] ?? 'ipv4')) === strtolower($family)) {
                return $pool;
            }
        }
        return null;
    }
}
