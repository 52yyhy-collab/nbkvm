<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Repositories\NodeNetworkResourceRepository;
use Nbkvm\Support\Shell;
use RuntimeException;

class NodeNetworkResourceService
{
    private const TYPES = ['bridge', 'bond', 'vlan'];
    private const BOND_MODES = [
        'balance-rr',
        'active-backup',
        'balance-xor',
        'broadcast',
        '802.3ad',
        'balance-tlb',
        'balance-alb',
    ];

    public function __construct(
        private readonly ?NodeNetworkResourceRepository $repository = null,
        private readonly ?HostNetworkDiscoveryService $discovery = null,
        private readonly ?Shell $shell = null,
    ) {
    }

    public function capabilities(): array
    {
        $ipExists = $this->commandExists('ip');
        $netplanExists = $this->commandExists('netplan');
        $networkctlExists = $this->commandExists('networkctl');
        $root = function_exists('posix_geteuid') ? posix_geteuid() === 0 : false;

        return [
            'runtime_apply_supported' => $ipExists && $root,
            'ip_command_available' => $ipExists,
            'netplan_available' => $netplanExists,
            'networkctl_available' => $networkctlExists,
            'is_root' => $root,
            'persistence_preview' => $netplanExists || $networkctlExists,
            'runtime_note' => $ipExists
                ? ($root ? '可执行受限 runtime apply（iproute2，仅 root）。' : '可生成 runtime apply 命令，但当前 Web/PHP 进程不是 root，不能直接下发。')
                : '未检测到 ip 命令，无法生成或执行 runtime apply。',
            'persistence_note' => '始终生成 PVE / ifupdown 风格配置预览（更接近 /etc/network/interfaces）；当前版本默认不直接改写宿主持久化文件，仅提供预览与受限 runtime apply。',
        ];
    }

    public function listDetailed(): array
    {
        $capabilities = $this->capabilities();
        $hostMap = $this->hostResourcesByName();
        $items = [];

        foreach ($this->repo()->all() as $resource) {
            $resource = $this->normalizeStoredResource($resource);
            $resource['host_resource'] = $hostMap[$resource['name']] ?? null;
            $resource['plan'] = $this->plan($resource, $resource['host_resource'], $capabilities);
            $items[] = $resource;
        }

        return $items;
    }

    public function save(array $data): int
    {
        $repo = $this->repo();
        $id = (int) ($data['id'] ?? $data['resource_id'] ?? 0);
        $existing = $id > 0 ? $repo->find($id) : null;
        if ($id > 0 && $existing === null) {
            throw new RuntimeException('节点网络资源不存在。');
        }

        $payload = $this->normalizePayload($data, $existing);

        $duplicate = $repo->findByName($payload['name']);
        if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== $id) {
            throw new RuntimeException('节点网络资源名称已存在。');
        }

        if ($existing !== null) {
            $hostMap = $this->hostResourcesByName();
            $existingName = (string) ($existing['name'] ?? '');
            $existingHost = $existingName !== '' ? ($hostMap[$existingName] ?? null) : null;
            if ((int) ($existing['managed_on_host'] ?? 0) === 1 && $existingHost !== null) {
                if ($existingName !== $payload['name']) {
                    throw new RuntimeException('该资源已经在宿主机上由 NBKVM 管理，禁止直接改名；请先“从宿主移除”再改。');
                }
                if ((string) ($existing['type'] ?? '') !== $payload['type']) {
                    throw new RuntimeException('该资源已经在宿主机上由 NBKVM 管理，禁止直接改类型；请先“从宿主移除”再改。');
                }
            }

            $repo->update($id, $payload + [
                'managed_on_host' => (int) ($existing['managed_on_host'] ?? 0),
                'last_apply_status' => $existing['last_apply_status'] ?? 'draft',
                'last_apply_output' => $existing['last_apply_output'] ?? null,
                'updated_at' => date('c'),
            ]);
            return $id;
        }

        return $repo->create($payload + [
            'managed_on_host' => 0,
            'last_apply_status' => 'draft',
            'last_apply_output' => null,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);
    }

    public function deleteDefinition(int $id): void
    {
        $resource = $this->find($id);
        $hostMap = $this->hostResourcesByName();
        if ((int) ($resource['managed_on_host'] ?? 0) === 1 && isset($hostMap[$resource['name']])) {
            throw new RuntimeException('该候选资源仍标记为已应用到宿主，请先执行“从宿主移除”。');
        }
        $this->repo()->delete($id);
    }

    public function apply(int $id): array
    {
        $resource = $this->find($id);
        $capabilities = $this->capabilities();
        $hostMap = $this->hostResourcesByName();
        $hostResource = $hostMap[$resource['name']] ?? null;
        $plan = $this->plan($resource, $hostResource, $capabilities, true);

        if ($plan['errors'] !== []) {
            $message = implode('；', $plan['errors']);
            $this->repo()->updateApplyState($id, [
                'managed_on_host' => (int) ($resource['managed_on_host'] ?? 0),
                'last_apply_status' => 'failed',
                'last_apply_output' => $message,
            ]);
            throw new RuntimeException($message);
        }

        if (!$capabilities['runtime_apply_supported']) {
            throw new RuntimeException('当前宿主不满足 runtime apply 条件：' . $capabilities['runtime_note']);
        }

        $outputs = [];
        foreach ($plan['commands'] as $command) {
            $result = $this->shell()->run($command);
            $outputs[] = '$ ' . implode(' ', array_map('strval', $command));
            if ($result->stdout !== '') {
                $outputs[] = $result->stdout;
            }
            if ($result->stderr !== '') {
                $outputs[] = $result->stderr;
            }
            if (!$result->succeeded()) {
                $message = implode("\n", $outputs);
                $this->repo()->updateApplyState($id, [
                    'managed_on_host' => (int) ($resource['managed_on_host'] ?? 0),
                    'last_apply_status' => 'failed',
                    'last_apply_output' => $message,
                ]);
                throw new RuntimeException($message);
            }
        }

        $output = trim(implode("\n", array_filter($outputs, static fn (string $line): bool => $line !== '')));
        if ($output === '') {
            $output = '无需额外命令，宿主当前状态已与候选配置一致。';
        }

        $this->repo()->updateApplyState($id, [
            'managed_on_host' => 1,
            'last_apply_status' => 'applied',
            'last_apply_output' => $output,
        ]);

        return [
            'message' => $plan['commands'] === [] ? '候选配置已与宿主状态一致。' : '节点网络资源已按受限 runtime 模式应用。',
            'output' => $output,
        ];
    }

    public function removeFromHost(int $id): array
    {
        $resource = $this->find($id);
        $capabilities = $this->capabilities();
        if (!$capabilities['runtime_apply_supported']) {
            throw new RuntimeException('当前宿主不满足 runtime apply 条件：' . $capabilities['runtime_note']);
        }

        $hostMap = $this->hostResourcesByName();
        $existing = $hostMap[$resource['name']] ?? null;
        if ($existing === null) {
            $this->repo()->updateApplyState($id, [
                'managed_on_host' => 0,
                'last_apply_status' => 'removed',
                'last_apply_output' => '宿主上未检测到同名资源，已仅撤销 NBKVM 管理标记。',
            ]);
            return ['message' => '宿主上未检测到同名资源，已撤销管理标记。', 'output' => ''];
        }

        if ((int) ($resource['managed_on_host'] ?? 0) !== 1) {
            throw new RuntimeException('该宿主资源并非由 NBKVM 标记管理，出于安全原因不允许直接删除。');
        }

        $commands = [];
        foreach ((array) ($existing['ports'] ?? []) as $port) {
            $port = trim((string) $port);
            if ($port === '') {
                continue;
            }
            $commands[] = ['ip', 'link', 'set', 'dev', $port, 'nomaster'];
        }
        $commands[] = ['ip', 'link', 'set', 'dev', $resource['name'], 'down'];
        $commands[] = ['ip', 'link', 'delete', $resource['name']];

        $outputs = [];
        foreach ($commands as $command) {
            $result = $this->shell()->run($command);
            $outputs[] = '$ ' . implode(' ', array_map('strval', $command));
            if ($result->stdout !== '') {
                $outputs[] = $result->stdout;
            }
            if ($result->stderr !== '') {
                $outputs[] = $result->stderr;
            }
            if (!$result->succeeded()) {
                $message = implode("\n", $outputs);
                $this->repo()->updateApplyState($id, [
                    'managed_on_host' => 1,
                    'last_apply_status' => 'failed',
                    'last_apply_output' => $message,
                ]);
                throw new RuntimeException($message);
            }
        }

        $output = trim(implode("\n", array_filter($outputs, static fn (string $line): bool => $line !== '')));
        $this->repo()->updateApplyState($id, [
            'managed_on_host' => 0,
            'last_apply_status' => 'removed',
            'last_apply_output' => $output !== '' ? $output : '已从宿主移除。',
        ]);

        return [
            'message' => '节点网络资源已从宿主移除。',
            'output' => $output,
        ];
    }

    public function plan(array $resource, ?array $hostResource = null, ?array $capabilities = null, bool $enforceApplyRules = false): array
    {
        $resource = $this->normalizeStoredResource($resource);
        $capabilities ??= $this->capabilities();
        $hostMap = $this->hostResourcesByName();
        $hostResource ??= $hostMap[$resource['name']] ?? null;

        $warnings = [];
        $errors = [];
        $commands = [];
        $ports = $resource['ports'];
        $managedOnHost = (int) ($resource['managed_on_host'] ?? 0) === 1;

        foreach ($this->riskInterfaces($resource, $hostMap) as $risk) {
            $warnings[] = $risk;
        }

        if (!$capabilities['ip_command_available']) {
            $warnings[] = '宿主未检测到 iproute2（ip 命令），runtime apply 不可用。';
        }
        if (!$capabilities['is_root']) {
            $warnings[] = '当前 Web/PHP 进程不是 root，runtime apply 只能预览不能直接执行。';
        }
        if ((int) ($resource['autostart'] ?? 0) === 1) {
            $warnings[] = 'autostart 当前仅持久保存为 NBKVM 候选属性；若要宿主重启后自动恢复，请参考下方持久化片段自行落地。';
        }

        if ($hostResource !== null && (string) ($hostResource['type'] ?? '') !== $resource['type']) {
            $errors[] = '宿主已存在同名资源，但类型为 ' . (string) ($hostResource['type_label'] ?? $hostResource['type'] ?? 'unknown') . '，不能按 ' . $resource['type'] . ' 继续管理。';
        }

        if ($hostResource !== null && !$managedOnHost && $enforceApplyRules) {
            $errors[] = '宿主已存在同名资源，但尚未被 NBKVM 接管。为避免误改现网，当前只允许预览，不允许直接 apply。';
        }

        if ($errors === []) {
            switch ($resource['type']) {
                case 'bridge':
                    $this->planBridge($resource, $hostResource, $commands, $warnings, $errors, $managedOnHost);
                    break;
                case 'bond':
                    $this->planBond($resource, $hostResource, $commands, $warnings, $errors, $managedOnHost);
                    break;
                case 'vlan':
                    $this->planVlan($resource, $hostResource, $commands, $warnings, $errors, $managedOnHost);
                    break;
                default:
                    $errors[] = '不支持的节点网络资源类型。';
                    break;
            }
        }

        $commandPreview = array_map(
            static fn (array $command): string => '$ ' . implode(' ', array_map('strval', $command)),
            $commands
        );

        return [
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'commands' => $commands,
            'command_preview' => $commandPreview,
            'desired_ports' => $ports,
            'host_exists' => $hostResource !== null,
            'host_matches_type' => $hostResource === null || (string) ($hostResource['type'] ?? '') === $resource['type'],
            'persistence_preview' => $this->persistencePreview($resource),
        ];
    }

    public function find(int $id): array
    {
        $resource = $this->repo()->find($id);
        if ($resource === null) {
            throw new RuntimeException('节点网络资源不存在。');
        }
        return $this->normalizeStoredResource($resource);
    }

    private function planBridge(array $resource, ?array $hostResource, array &$commands, array &$warnings, array &$errors, bool $managedOnHost): void
    {
        $name = $resource['name'];
        $desiredPorts = $resource['ports'];
        $currentPorts = $hostResource !== null ? array_values(array_map('strval', $hostResource['ports'] ?? [])) : [];

        if ($hostResource === null) {
            $commands[] = ['ip', 'link', 'add', 'name', $name, 'type', 'bridge'];
        } elseif ((string) ($hostResource['type'] ?? '') !== 'bridge') {
            $errors[] = '宿主现有同名接口不是 Linux Bridge。';
            return;
        }

        if ($resource['mtu'] !== null && ($hostResource === null || (string) ($hostResource['mtu'] ?? '') !== (string) $resource['mtu'])) {
            $commands[] = ['ip', 'link', 'set', 'dev', $name, 'mtu', (string) $resource['mtu']];
        }

        if ($hostResource === null || $managedOnHost) {
            $commands[] = ['ip', 'link', 'set', 'dev', $name, 'type', 'bridge', 'vlan_filtering', (int) ($resource['bridge_vlan_aware'] ?? 0) === 1 ? '1' : '0'];
        }

        if ($hostResource !== null && !$managedOnHost && $currentPorts !== $desiredPorts) {
            $warnings[] = '宿主已有同名 Linux Bridge，但尚未由 NBKVM 接管；Bridge ports 差异当前只做预览。';
            return;
        }

        foreach (array_values(array_diff($currentPorts, $desiredPorts)) as $port) {
            $commands[] = ['ip', 'link', 'set', 'dev', $port, 'nomaster'];
        }
        foreach (array_values(array_diff($desiredPorts, $currentPorts)) as $port) {
            $commands[] = ['ip', 'link', 'set', 'dev', $port, 'master', $name];
        }

        $commands[] = ['ip', 'link', 'set', 'dev', $name, 'up'];
        $this->planLayer3($resource, $commands);
    }

    private function planBond(array $resource, ?array $hostResource, array &$commands, array &$warnings, array &$errors, bool $managedOnHost): void
    {
        $name = $resource['name'];
        $desiredPorts = $resource['ports'];
        $currentPorts = $hostResource !== null ? array_values(array_map('strval', $hostResource['ports'] ?? [])) : [];
        $bondMode = (string) ($resource['bond_mode'] ?? 'active-backup');

        if ($hostResource === null) {
            $commands[] = ['ip', 'link', 'add', 'name', $name, 'type', 'bond', 'mode', $bondMode];
        } elseif ((string) ($hostResource['type'] ?? '') !== 'bond') {
            $errors[] = '宿主现有同名接口不是 Linux Bond。';
            return;
        }

        if ($hostResource !== null && !$managedOnHost && $currentPorts !== $desiredPorts) {
            $warnings[] = '宿主已有同名 Linux Bond，但尚未由 NBKVM 接管；Bond slaves 差异当前只做预览。';
            return;
        }

        if ($hostResource !== null && $managedOnHost) {
            $commands[] = ['ip', 'link', 'set', 'dev', $name, 'type', 'bond', 'mode', $bondMode];
        }

        if ($resource['mtu'] !== null && ($hostResource === null || (string) ($hostResource['mtu'] ?? '') !== (string) $resource['mtu'])) {
            $commands[] = ['ip', 'link', 'set', 'dev', $name, 'mtu', (string) $resource['mtu']];
        }

        foreach (array_values(array_diff($currentPorts, $desiredPorts)) as $port) {
            $commands[] = ['ip', 'link', 'set', 'dev', $port, 'nomaster'];
        }
        foreach (array_values(array_diff($desiredPorts, $currentPorts)) as $port) {
            $commands[] = ['ip', 'link', 'set', 'dev', $port, 'master', $name];
        }

        $commands[] = ['ip', 'link', 'set', 'dev', $name, 'up'];
        $this->planLayer3($resource, $commands);
    }

    private function planVlan(array $resource, ?array $hostResource, array &$commands, array &$warnings, array &$errors, bool $managedOnHost): void
    {
        $name = $resource['name'];
        $parent = (string) ($resource['parent'] ?? '');
        $vlanId = (int) ($resource['vlan_id'] ?? 0);

        if ($hostResource === null) {
            $commands[] = ['ip', 'link', 'add', 'link', $parent, 'name', $name, 'type', 'vlan', 'id', (string) $vlanId];
        } elseif ((string) ($hostResource['type'] ?? '') !== 'vlan') {
            $errors[] = '宿主现有同名接口不是 Linux VLAN。';
            return;
        } elseif ($managedOnHost) {
            $hostParent = trim((string) ($hostResource['parent'] ?? ''));
            $hostVlanId = (int) ($hostResource['vlan_id'] ?? 0);
            if ($hostParent !== '' && $hostParent !== $parent) {
                $errors[] = '已应用的 VLAN 不能直接改 raw device / parent，请先“从宿主移除”再重建。';
                return;
            }
            if ($hostVlanId > 0 && $hostVlanId !== $vlanId) {
                $errors[] = '已应用的 VLAN 不能直接改 VLAN ID，请先“从宿主移除”再重建。';
                return;
            }
        } else {
            $warnings[] = '宿主已有同名 Linux VLAN，但尚未由 NBKVM 接管；当前仅给出预览。';
            return;
        }

        if ($resource['mtu'] !== null && ($hostResource === null || (string) ($hostResource['mtu'] ?? '') !== (string) $resource['mtu'])) {
            $commands[] = ['ip', 'link', 'set', 'dev', $name, 'mtu', (string) $resource['mtu']];
        }

        $commands[] = ['ip', 'link', 'set', 'dev', $name, 'up'];
        $this->planLayer3($resource, $commands);
    }

    private function planLayer3(array $resource, array &$commands): void
    {
        $name = (string) ($resource['name'] ?? '');
        $cidr = trim((string) ($resource['cidr'] ?? ''));
        $gateway = trim((string) ($resource['gateway'] ?? ''));
        $ipv6Cidr = trim((string) ($resource['ipv6_cidr'] ?? ''));
        $ipv6Gateway = trim((string) ($resource['ipv6_gateway'] ?? ''));

        if ($cidr !== '') {
            $commands[] = ['ip', 'address', 'replace', $cidr, 'dev', $name];
        }
        if ($gateway !== '') {
            $commands[] = ['ip', 'route', 'replace', 'default', 'via', $gateway, 'dev', $name];
        }
        if ($ipv6Cidr !== '') {
            $commands[] = ['ip', '-6', 'address', 'replace', $ipv6Cidr, 'dev', $name];
        }
        if ($ipv6Gateway !== '') {
            $commands[] = ['ip', '-6', 'route', 'replace', 'default', 'via', $ipv6Gateway, 'dev', $name];
        }
    }

    private function persistencePreview(array $resource): string
    {
        $name = (string) $resource['name'];
        $commentLines = [];
        if (trim((string) ($resource['comments'] ?? '')) !== '') {
            foreach (preg_split('/\r?\n/', trim((string) $resource['comments'])) ?: [] as $line) {
                $commentLines[] = '# ' . trim((string) $line);
            }
        }

        $blocks = [];
        $ipv4Static = trim((string) ($resource['cidr'] ?? '')) !== '';
        $ipv6Static = trim((string) ($resource['ipv6_cidr'] ?? '')) !== '';

        $ipv4 = ['auto ' . $name, 'iface ' . $name . ' inet ' . ($ipv4Static ? 'static' : 'manual')];
        if ($resource['type'] === 'bridge') {
            $ipv4[] = '    bridge-ports ' . ($resource['ports'] !== [] ? implode(' ', $resource['ports']) : 'none');
            $ipv4[] = '    bridge-stp off';
            $ipv4[] = '    bridge-fd 0';
            if ((int) ($resource['bridge_vlan_aware'] ?? 0) === 1) {
                $ipv4[] = '    bridge-vlan-aware yes';
            }
        } elseif ($resource['type'] === 'bond') {
            $ipv4[] = '    bond-slaves ' . implode(' ', $resource['ports']);
            $ipv4[] = '    bond-mode ' . (string) ($resource['bond_mode'] ?? 'active-backup');
            $ipv4[] = '    bond-miimon 100';
        } else {
            $ipv4[] = '    vlan-raw-device ' . (string) ($resource['parent'] ?? '');
        }
        if ($resource['mtu'] !== null) {
            $ipv4[] = '    mtu ' . (string) $resource['mtu'];
        }
        if ($ipv4Static) {
            $ipv4[] = '    address ' . (string) $resource['cidr'];
        }
        if (trim((string) ($resource['gateway'] ?? '')) !== '') {
            $ipv4[] = '    gateway ' . (string) $resource['gateway'];
        }
        $blocks[] = implode("\n", $ipv4);

        if ($ipv6Static || trim((string) ($resource['ipv6_gateway'] ?? '')) !== '') {
            $ipv6 = ['iface ' . $name . ' inet6 ' . ($ipv6Static ? 'static' : 'manual')];
            if ($ipv6Static) {
                $ipv6[] = '    address ' . (string) $resource['ipv6_cidr'];
            }
            if (trim((string) ($resource['ipv6_gateway'] ?? '')) !== '') {
                $ipv6[] = '    gateway ' . (string) $resource['ipv6_gateway'];
            }
            $blocks[] = implode("\n", $ipv6);
        }

        return implode("\n", array_merge($commentLines, [implode("\n\n", $blocks)]));
    }

    private function normalizePayload(array $data, ?array $existing = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = strtolower(trim((string) ($data['type'] ?? 'bridge')));
        $parent = trim((string) ($data['parent'] ?? ''));
        $ports = $this->parsePorts((string) ($data['ports'] ?? ''));
        $vlanId = $this->nullableInt($data['vlan_id'] ?? null);
        $bondMode = strtolower(trim((string) ($data['bond_mode'] ?? 'active-backup')));
        $bridgeVlanAware = $this->boolFlag($data['bridge_vlan_aware'] ?? false) ? 1 : 0;
        $cidr = $this->normalizeIpv4Cidr($data['cidr'] ?? null, 'IPv4 CIDR');
        $gateway = $this->normalizeGateway($data['gateway'] ?? null, FILTER_FLAG_IPV4, 'IPv4 Gateway');
        $ipv6Cidr = $this->normalizeIpv6Cidr($data['ipv6_cidr'] ?? null, 'IPv6 CIDR');
        $ipv6Gateway = $this->normalizeGateway($data['ipv6_gateway'] ?? null, FILTER_FLAG_IPV6, 'IPv6 Gateway');
        $mtu = $this->nullableInt($data['mtu'] ?? null);
        $comments = trim((string) ($data['comments'] ?? ''));
        $autostart = $this->boolFlag($data['autostart'] ?? false) ? 1 : 0;

        if ($name === '' || !preg_match('/^[a-zA-Z0-9._:-]+$/', $name)) {
            throw new RuntimeException('节点网络资源名称不合法。');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('节点网络资源类型只支持 bridge / bond / vlan。');
        }
        if ($mtu !== null && ($mtu < 576 || $mtu > 9216)) {
            throw new RuntimeException('MTU 必须在 576-9216 之间。');
        }
        if ($gateway !== null && $cidr === null) {
            throw new RuntimeException('填写 IPv4 Gateway 前必须先填写 IPv4 CIDR。');
        }
        if ($ipv6Gateway !== null && $ipv6Cidr === null) {
            throw new RuntimeException('填写 IPv6 Gateway 前必须先填写 IPv6 CIDR。');
        }

        if ($type === 'bridge') {
            $parent = null;
            $vlanId = null;
            $bondMode = null;
        } elseif ($type === 'bond') {
            if ($ports === []) {
                throw new RuntimeException('Linux Bond 至少需要一个 slaves 成员。');
            }
            if (!in_array($bondMode, self::BOND_MODES, true)) {
                throw new RuntimeException('Bond mode 不受支持。');
            }
            $parent = null;
            $vlanId = null;
            $bridgeVlanAware = 0;
        } elseif ($type === 'vlan') {
            if ($parent === '' || !preg_match('/^[a-zA-Z0-9._:-]+$/', $parent)) {
                throw new RuntimeException('Linux VLAN 必须指定合法的 raw device / parent。');
            }
            if ($vlanId === null || $vlanId < 1 || $vlanId > 4094) {
                throw new RuntimeException('VLAN ID 必须在 1-4094 之间。');
            }
            if ($ports !== []) {
                throw new RuntimeException('Linux VLAN 资源不需要填写 Bridge ports / Bond slaves。');
            }
            $bondMode = null;
            $bridgeVlanAware = 0;
        }

        return [
            'name' => $name,
            'type' => $type,
            'parent' => $parent !== '' ? $parent : null,
            'ports_json' => json_encode($ports, JSON_UNESCAPED_UNICODE),
            'vlan_id' => $vlanId,
            'bond_mode' => $bondMode,
            'bridge_vlan_aware' => $bridgeVlanAware,
            'cidr' => $cidr,
            'gateway' => $gateway,
            'ipv6_cidr' => $ipv6Cidr,
            'ipv6_gateway' => $ipv6Gateway,
            'mtu' => $mtu,
            'autostart' => $autostart,
            'comments' => $comments !== '' ? $comments : null,
        ];
    }

    private function normalizeStoredResource(array $resource): array
    {
        $ports = [];
        $rawPorts = trim((string) ($resource['ports_json'] ?? ''));
        if ($rawPorts !== '') {
            $decoded = json_decode($rawPorts, true);
            if (is_array($decoded)) {
                foreach ($decoded as $port) {
                    $port = trim((string) $port);
                    if ($port !== '') {
                        $ports[] = $port;
                    }
                }
            }
        }
        $resource['ports'] = array_values(array_unique($ports));
        $resource['ports_text'] = implode(', ', $resource['ports']);
        $resource['type'] = strtolower((string) ($resource['type'] ?? 'bridge'));
        $resource['autostart'] = (int) ($resource['autostart'] ?? 0);
        $resource['managed_on_host'] = (int) ($resource['managed_on_host'] ?? 0);
        $resource['bridge_vlan_aware'] = (int) ($resource['bridge_vlan_aware'] ?? 0);
        $resource['mtu'] = $this->nullableInt($resource['mtu'] ?? null);
        $resource['vlan_id'] = $this->nullableInt($resource['vlan_id'] ?? null);
        $resource['bond_mode'] = $resource['bond_mode'] !== null && $resource['bond_mode'] !== '' ? (string) $resource['bond_mode'] : null;
        $resource['parent'] = $resource['parent'] !== null && trim((string) $resource['parent']) !== '' ? trim((string) $resource['parent']) : null;
        $resource['cidr'] = ($resource['cidr'] ?? null) !== null && trim((string) $resource['cidr']) !== '' ? trim((string) $resource['cidr']) : null;
        $resource['gateway'] = ($resource['gateway'] ?? null) !== null && trim((string) $resource['gateway']) !== '' ? trim((string) $resource['gateway']) : null;
        $resource['ipv6_cidr'] = ($resource['ipv6_cidr'] ?? null) !== null && trim((string) $resource['ipv6_cidr']) !== '' ? trim((string) $resource['ipv6_cidr']) : null;
        $resource['ipv6_gateway'] = ($resource['ipv6_gateway'] ?? null) !== null && trim((string) $resource['ipv6_gateway']) !== '' ? trim((string) $resource['ipv6_gateway']) : null;
        $resource['comments'] = $resource['comments'] !== null && trim((string) $resource['comments']) !== '' ? trim((string) $resource['comments']) : null;
        $resource['last_apply_status'] = (string) (($resource['last_apply_status'] ?? '') ?: 'draft');
        return $resource;
    }

    private function parsePorts(string $raw): array
    {
        $items = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ports = [];
        foreach ($items as $port) {
            $port = trim((string) $port);
            if ($port === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $port)) {
                throw new RuntimeException('Bridge ports / Bond slaves 中包含不合法的接口名：' . $port);
            }
            $ports[] = $port;
        }
        return array_values(array_unique($ports));
    }

    private function riskInterfaces(array $resource, array $hostMap): array
    {
        $warnings = [];
        $targets = [(string) ($resource['name'] ?? '')];
        foreach ((array) ($resource['ports'] ?? []) as $port) {
            $targets[] = (string) $port;
        }
        if (!empty($resource['parent'])) {
            $targets[] = (string) $resource['parent'];
        }

        foreach (array_values(array_unique(array_filter($targets, static fn (string $name): bool => $name !== ''))) as $name) {
            $host = $hostMap[$name] ?? null;
            if ($host === null) {
                continue;
            }
            if (!empty($host['default_routes'])) {
                $warnings[] = '接口 ' . $name . ' 上检测到默认路由：' . implode(', ', array_map('strval', (array) $host['default_routes'])) . '。错误调整可能导致宿主失联。';
            }
            if (!empty($host['addresses'])) {
                $warnings[] = '接口 ' . $name . ' 当前已有地址：' . implode(', ', array_map('strval', (array) $host['addresses'])) . '。变更前请确认宿主管理面不会中断。';
            }
        }

        return $warnings;
    }

    private function hostResourcesByName(): array
    {
        $items = [];
        foreach (($this->discovery()->detect()['all'] ?? []) as $resource) {
            $name = trim((string) ($resource['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $items[$name] = $resource;
        }
        return $items;
    }

    private function repo(): NodeNetworkResourceRepository
    {
        return $this->repository ?? new NodeNetworkResourceRepository();
    }

    private function discovery(): HostNetworkDiscoveryService
    {
        return $this->discovery ?? new HostNetworkDiscoveryService();
    }

    private function shell(): Shell
    {
        return $this->shell ?? new Shell();
    }

    private function boolFlag(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) || $value === true || $value === 1;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        return (int) $text;
    }

    private function normalizeIpv4Cidr(mixed $value, string $label): ?string
    {
        $cidr = trim((string) $value);
        if ($cidr === '') {
            return null;
        }
        if (!preg_match('/^(\d+\.\d+\.\d+\.\d+)\/(\d{1,2})$/', $cidr, $matches)) {
            throw new RuntimeException($label . ' 格式不合法。');
        }
        $prefix = (int) $matches[2];
        if ($prefix < 1 || $prefix > 32) {
            throw new RuntimeException($label . ' 前缀必须在 1-32 之间。');
        }
        return $cidr;
    }

    private function normalizeIpv6Cidr(mixed $value, string $label): ?string
    {
        $cidr = trim((string) $value);
        if ($cidr === '') {
            return null;
        }
        if (!preg_match('/^([0-9a-fA-F:]+)\/(\d{1,3})$/', $cidr, $matches)) {
            throw new RuntimeException($label . ' 格式不合法。');
        }
        $prefix = (int) $matches[2];
        if ($prefix < 1 || $prefix > 128) {
            throw new RuntimeException($label . ' 前缀必须在 1-128 之间。');
        }
        return $cidr;
    }

    private function normalizeGateway(mixed $value, int $flag, string $label): ?string
    {
        $gateway = trim((string) $value);
        if ($gateway === '') {
            return null;
        }
        if (!filter_var($gateway, FILTER_VALIDATE_IP, $flag)) {
            throw new RuntimeException($label . ' 不合法。');
        }
        return $gateway;
    }

    private function commandExists(string $command): bool
    {
        return trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null')) !== '';
    }
}
