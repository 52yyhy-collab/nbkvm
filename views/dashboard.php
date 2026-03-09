<?php
$pageLabels = [
    'overview' => '总览',
    'networks' => '网络',
    'templates' => '模板',
    'vms' => '虚拟机',
    'images' => '镜像',
    'system' => '系统配置',
];
$pageDescriptions = [
    'overview' => '环境、任务、审计与资源概览',
    'networks' => 'PVE 节点网络对象（Bridge / Bond / VLAN）与兼容层',
    'templates' => '镜像、CPU、内存、磁盘、netX/ipconfigX 模板编排',
    'vms' => '创建虚拟机、编辑 netX/ipconfigX、运行控制台与安全更新',
    'images' => '镜像上传、转换与清理',
    'system' => '系统参数、密码与用户权限',
];
$redirectTo = '/?page=' . $currentPage;
$encodeJson = static fn (mixed $value): string => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$nicSummary = static function (array $nic): string {
    $bridge = (string) ($nic['bridge'] ?? $nic['network_name'] ?? '-');
    $model = (string) ($nic['model'] ?? 'virtio');
    $parts = [$model, 'bridge=' . $bridge];
    if (($nic['vlan_tag'] ?? null) !== null && (string) ($nic['vlan_tag'] ?? '') !== '') {
        $parts[] = 'tag=' . $nic['vlan_tag'];
    }
    if (!empty($nic['mac'])) {
        $parts[] = 'macaddr=' . (string) $nic['mac'];
    }
    $parts[] = 'firewall=' . (!empty($nic['firewall']) ? '1' : '0');
    $parts[] = 'link_down=' . (!empty($nic['link_down']) ? '1' : '0');
    $parts[] = 'ip=' . (string) ($nic['ipv4_mode'] ?? 'dhcp');
    $parts[] = 'ip6=' . (string) ($nic['ipv6_mode'] ?? 'none');
    return implode(', ', $parts);
};
$diskSummary = static function (array $disk): string {
    $primary = !empty($disk['is_primary']) ? '主盘' : '数据盘';
    return $primary . ' / ' . ($disk['bus'] ?? 'virtio') . ' / ' . ($disk['format'] ?? 'qcow2') . ' / ' . ($disk['size_gb'] ?? '?') . ' GB';
};
$dashboardJson = [
    'currentPage' => $currentPage,
    'networks' => array_map(static fn (array $networkConfig): array => [
        'id' => (int) ($networkConfig['network']['id'] ?? 0),
        'name' => (string) ($networkConfig['network']['name'] ?? ''),
        'bridge_name' => (string) ($networkConfig['network']['bridge_name'] ?? ''),
        'cidr' => (string) ($networkConfig['network']['cidr'] ?? ''),
        'gateway' => (string) ($networkConfig['network']['gateway'] ?? ''),
        'dhcp_start' => (string) ($networkConfig['network']['dhcp_start'] ?? ''),
        'dhcp_end' => (string) ($networkConfig['network']['dhcp_end'] ?? ''),
        'ipv6_cidr' => (string) ($networkConfig['network']['ipv6_cidr'] ?? ''),
        'ipv6_gateway' => (string) ($networkConfig['network']['ipv6_gateway'] ?? ''),
        'libvirt_managed' => (int) ($networkConfig['network']['libvirt_managed'] ?? 0),
        'ipv4_pool' => $networkConfig['ipv4_pool'] ? [
            'id' => (int) ($networkConfig['ipv4_pool']['id'] ?? 0),
            'start_ip' => (string) ($networkConfig['ipv4_pool']['start_ip'] ?? ''),
            'end_ip' => (string) ($networkConfig['ipv4_pool']['end_ip'] ?? ''),
            'dns_servers' => (string) ($networkConfig['ipv4_pool']['dns_servers'] ?? ''),
        ] : null,
        'ipv6_pool' => $networkConfig['ipv6_pool'] ? [
            'id' => (int) ($networkConfig['ipv6_pool']['id'] ?? 0),
            'start_ip' => (string) ($networkConfig['ipv6_pool']['start_ip'] ?? ''),
            'end_ip' => (string) ($networkConfig['ipv6_pool']['end_ip'] ?? ''),
            'dns_servers' => (string) ($networkConfig['ipv6_pool']['dns_servers'] ?? ''),
        ] : null,
    ], $networkConfigs),
    'templates' => array_map(static fn (array $template): array => [
        'id' => (int) ($template['id'] ?? 0),
        'name' => (string) ($template['name'] ?? ''),
        'image_id' => (int) ($template['image_id'] ?? 0),
        'os_variant' => (string) ($template['os_variant'] ?? ''),
        'virtualization_mode' => (string) ($template['virtualization_mode'] ?? 'kvm'),
        'machine_type' => (string) ($template['machine_type'] ?? 'pc'),
        'firmware_type' => (string) ($template['firmware_type'] ?? 'bios'),
        'gpu_type' => (string) ($template['gpu_type'] ?? 'cirrus'),
        'cpu_sockets' => (int) ($template['cpu_sockets'] ?? 1),
        'cpu_cores' => (int) ($template['cpu_cores'] ?? 1),
        'cpu_threads' => (int) ($template['cpu_threads'] ?? 1),
        'memory_mb' => (int) ($template['memory_mb'] ?? 2048),
        'memory_max_mb' => (int) ($template['memory_max_mb'] ?? 0),
        'memory_overcommit_percent' => (int) ($template['memory_overcommit_percent'] ?? 100),
        'disk_size_gb' => (int) ($template['disk_size_gb'] ?? 20),
        'disk_bus' => (string) ($template['disk_bus'] ?? 'virtio'),
        'autostart_default' => (int) ($template['autostart_default'] ?? 0),
        'disk_overcommit_enabled' => (int) ($template['disk_overcommit_enabled'] ?? 0),
        'cloud_init_enabled' => (int) ($template['cloud_init_enabled'] ?? 0),
        'cloud_init_user' => (string) ($template['cloud_init_user'] ?? 'ubuntu'),
        'cloud_init_password' => (string) ($template['cloud_init_password'] ?? ''),
        'cloud_init_ssh_key' => (string) ($template['cloud_init_ssh_key'] ?? ''),
        'network_name' => (string) ($template['network_name'] ?? 'default'),
        'notes' => (string) ($template['notes'] ?? ''),
        'linked_vm_count' => (int) ($template['linked_vm_count'] ?? 0),
        'disks' => array_values($template['normalized_disks'] ?? []),
        'nics' => array_values($template['normalized_nics'] ?? []),
    ], $templates),
    'vms' => array_map(static fn (array $vm): array => [
        'id' => (int) ($vm['id'] ?? 0),
        'name' => (string) ($vm['name'] ?? ''),
        'template_id' => (int) ($vm['template_id'] ?? 0),
        'template_name' => (string) ($vm['template_name'] ?? ''),
        'status' => (string) ($vm['status'] ?? 'unknown'),
        'cpu' => (int) ($vm['cpu'] ?? 1),
        'cpu_sockets' => (int) ($vm['cpu_sockets'] ?? 1),
        'cpu_cores' => (int) ($vm['cpu_cores'] ?? 1),
        'cpu_threads' => (int) ($vm['cpu_threads'] ?? 1),
        'memory_mb' => (int) ($vm['memory_mb'] ?? 2048),
        'expires_at' => (string) ($vm['expires_at'] ?? ''),
        'expire_grace_days' => (int) ($vm['expire_grace_days'] ?? 3),
        'ip_address' => (string) ($vm['ip_address'] ?? ''),
        'vnc_display' => (string) ($vm['vnc_display'] ?? ''),
        'disks' => array_values($vm['normalized_disks'] ?? []),
        'nics' => array_values($vm['normalized_nics'] ?? []),
    ], $vms),
    'bridgeCandidates' => $bridgeCandidates,
    'nodeNetworkResources' => array_map(static fn (array $resource): array => [
        'id' => (int) ($resource['id'] ?? 0),
        'name' => (string) ($resource['name'] ?? ''),
        'type' => (string) ($resource['type'] ?? 'bridge'),
        'parent' => (string) ($resource['parent'] ?? ''),
        'ports_text' => (string) ($resource['ports_text'] ?? ''),
        'vlan_id' => $resource['vlan_id'] ?? null,
        'bond_mode' => (string) ($resource['bond_mode'] ?? ''),
        'bridge_vlan_aware' => (int) ($resource['bridge_vlan_aware'] ?? 0),
        'cidr' => (string) ($resource['cidr'] ?? ''),
        'gateway' => (string) ($resource['gateway'] ?? ''),
        'ipv6_cidr' => (string) ($resource['ipv6_cidr'] ?? ''),
        'ipv6_gateway' => (string) ($resource['ipv6_gateway'] ?? ''),
        'mtu' => $resource['mtu'] ?? null,
        'autostart' => (int) ($resource['autostart'] ?? 0),
        'comments' => (string) ($resource['comments'] ?? ''),
        'managed_on_host' => (int) ($resource['managed_on_host'] ?? 0),
    ], $nodeNetworkResources ?? []),
    'nodeNetworkCapabilities' => $nodeNetworkCapabilities ?? [],
];
?>
<div class="dashboard-shell">
  <section class="section-header card">
    <div>
      <div class="eyebrow">PVE 风格控制台</div>
      <h2><?= e($pageLabels[$currentPage] ?? '总览') ?></h2>
      <p class="section-desc"><?= e($pageDescriptions[$currentPage] ?? '') ?></p>
    </div>
    <div class="muted">当前共有 <?= count($vms) ?> 台 VM / <?= count($templates) ?> 个模板 / <?= count($networks) ?> 个网络 / <?= count($images) ?> 个镜像</div>
  </section>

  <section class="page-tabs">
    <?php foreach ($pageLabels as $pageKey => $pageLabel): ?>
      <a class="page-tab <?= $currentPage === $pageKey ? 'active' : '' ?>" href="/?page=<?= e($pageKey) ?>">
        <span><?= e($pageLabel) ?></span>
        <small><?= e($pageDescriptions[$pageKey] ?? '') ?></small>
      </a>
    <?php endforeach; ?>
  </section>

  <script id="dashboard-data" type="application/json"><?= $encodeJson($dashboardJson) ?></script>
  <?php require __DIR__ . '/dashboard/' . $currentPage . '.php'; ?>
</div>
<script src="/assets/dashboard.js" defer></script>
