<?php
$joinList = static fn (array $items): string => $items !== [] ? implode(' / ', array_map(static fn ($item): string => (string) $item, $items)) : '-';
$usageSummary = static function (array $usage): string {
    $templateCount = (int) ($usage['template_count'] ?? 0);
    $templateNics = (int) ($usage['template_nics'] ?? 0);
    $vmCount = (int) ($usage['vm_count'] ?? 0);
    $vmNics = (int) ($usage['vm_nics'] ?? 0);
    return sprintf('模板 %d 个（%d 张网卡） / VM %d 台（%d 张网卡）', $templateCount, $templateNics, $vmCount, $vmNics);
};
$typeLabel = static fn (string $type): string => match (strtolower($type)) {
    'bridge' => 'Linux Bridge',
    'bond' => 'Linux Bond',
    'vlan' => 'Linux VLAN',
    default => strtoupper($type),
};
$firstAddressByFamily = static function (array $resource, string $family): ?string {
    foreach ((array) ($resource['addresses'] ?? []) as $label) {
        $label = (string) $label;
        if ($family === 'inet' && !str_contains($label, '(inet,')) {
            continue;
        }
        if ($family === 'inet6' && !str_contains($label, '(inet6,')) {
            continue;
        }
        if (preg_match('/^([^\s]+)/', $label, $matches)) {
            return $matches[1];
        }
    }
    return null;
};
$firstGatewayByFamily = static function (array $resource, string $family): ?string {
    foreach ((array) ($resource['default_routes'] ?? []) as $gateway) {
        $gateway = trim((string) $gateway);
        if ($gateway === '' || $gateway === 'on-link') {
            continue;
        }
        $isIpv6 = str_contains($gateway, ':');
        if (($family === 'inet6' && $isIpv6) || ($family === 'inet' && !$isIpv6)) {
            return $gateway;
        }
    }
    return null;
};
$nodeNetworkCapabilities = $nodeNetworkCapabilities ?? [];
$nodeNetworkResources = $nodeNetworkResources ?? [];
$runtimeApplySupported = (bool) ($nodeNetworkCapabilities['runtime_apply_supported'] ?? false);
?>
<div class="grid dashboard-grid">
  <section class="card span-7">
    <div class="section-split">
      <div>
        <h3>PVE 风格节点网络（宿主实况）</h3>
        <p class="muted">先看宿主当前有哪些 Linux Bridge / Linux Bond / Linux VLAN / Physical iface，再决定 VM 的 netX 可以接到哪里。这里是“像 PVE Node → Network 那样先看节点对象”的入口。</p>
      </div>
      <span class="muted">优先 Bridge：<?= e((string) (($bridgeCandidates['preferred_bridge'] ?? null) ?: '-')) ?></span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>接口</th><th>类型 / 状态</th><th>关系</th><th>地址 / 路由</th><th>备注</th><th>候选操作</th></tr></thead>
        <tbody>
          <?php foreach (($bridgeCandidates['all'] ?? []) as $resource): ?>
            <?php
              $resourcePayload = [
                  'name' => (string) ($resource['name'] ?? ''),
                  'type' => (string) ($resource['type'] ?? 'bridge'),
                  'parent' => (string) ($resource['parent'] ?? ''),
                  'ports_text' => implode(', ', array_map(static fn ($item): string => (string) $item, (array) ($resource['ports'] ?? []))),
                  'vlan_id' => $resource['vlan_id'] ?? null,
                  'bond_mode' => '',
                  'bridge_vlan_aware' => 0,
                  'cidr' => $firstAddressByFamily($resource, 'inet') ?? '',
                  'gateway' => $firstGatewayByFamily($resource, 'inet') ?? '',
                  'ipv6_cidr' => $firstAddressByFamily($resource, 'inet6') ?? '',
                  'ipv6_gateway' => $firstGatewayByFamily($resource, 'inet6') ?? '',
                  'mtu' => ($resource['mtu'] ?? '') !== '' ? (int) $resource['mtu'] : null,
                  'autostart' => 0,
                  'comments' => '从宿主资源导入：' . (string) ($resource['name'] ?? ''),
                  'managed_on_host' => 0,
              ];
            ?>
            <tr>
              <td>
                <strong><?= e((string) $resource['name']) ?></strong>
                <?php if (!empty($resource['is_vmbr'])): ?><div class="muted">vmbr 风格 Bridge</div><?php endif; ?>
              </td>
              <td>
                <?= e($typeLabel((string) ($resource['type'] ?? ''))) ?><br>
                <span class="muted">state <?= e((string) (($resource['state'] ?? '') ?: 'unknown')) ?></span><br>
                <span class="muted">mtu <?= e((string) (($resource['mtu'] ?? '') ?: '-')) ?><?php if (!empty($resource['speed'])): ?> / <?= e((string) $resource['speed']) ?> Mb/s<?php endif; ?></span>
                <?php if (!empty($resource['vlan_id'])): ?><br><span class="muted">VID <?= (int) $resource['vlan_id'] ?></span><?php endif; ?>
              </td>
              <td>
                <div class="resource-stack">
                  <?php if (!empty($resource['master'])): ?><div class="muted">master → <?= e((string) $resource['master']) ?></div><?php endif; ?>
                  <?php if (!empty($resource['parent'])): ?><div class="muted">raw device → <?= e((string) $resource['parent']) ?></div><?php endif; ?>
                  <?php if (!empty($resource['ports'])): ?><div class="muted">ports/slaves: <?= e($joinList(array_values($resource['ports']))) ?></div><?php endif; ?>
                  <?php if (!empty($resource['uppers'])): ?><div class="muted">uppers: <?= e($joinList(array_values($resource['uppers']))) ?></div><?php endif; ?>
                  <?php if (empty($resource['master']) && empty($resource['parent']) && empty($resource['ports']) && empty($resource['uppers'])): ?><span class="muted">-</span><?php endif; ?>
                </div>
              </td>
              <td>
                <div class="resource-stack">
                  <?php if (!empty($resource['addresses'])): ?>
                    <?php foreach ((array) $resource['addresses'] as $address): ?>
                      <div class="muted"><?= e((string) $address) ?></div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="muted">无地址</span>
                  <?php endif; ?>
                  <?php if (!empty($resource['default_routes'])): ?><div class="muted">default via <?= e($joinList(array_values($resource['default_routes']))) ?></div><?php endif; ?>
                </div>
              </td>
              <td>
                <div class="resource-stack">
                  <div class="muted">MAC <?= e((string) (($resource['mac'] ?? '') ?: '-')) ?></div>
                  <div class="muted">kind <?= e((string) (($resource['kind'] ?? '') ?: '-')) ?></div>
                  <div class="muted">carrier <?= e((string) (($resource['carrier'] ?? '') ?: '-')) ?> / duplex <?= e((string) (($resource['duplex'] ?? '') ?: '-')) ?></div>
                </div>
              </td>
              <td>
                <?php if (in_array((string) ($resource['type'] ?? ''), ['bridge', 'bond', 'vlan'], true)): ?>
                  <button class="btn secondary js-import-node-resource" type="button" data-resource='<?= e((string) json_encode($resourcePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>导入到候选配置</button>
                  <div class="muted top-gap-mini">导入后可继续补齐 CIDR / Gateway / VLAN aware，再做受限 apply。</div>
                <?php else: ?>
                  <span class="muted">Physical iface 只展示，不直接纳入候选对象。</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($bridgeCandidates['all'])): ?><tr><td colspan="6" class="muted">当前没有探测到可展示的宿主网络资源。</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card span-5">
    <div class="section-split">
      <div>
        <h3>节点网络配置（候选层）</h3>
        <p class="muted">前台心智改成 PVE 原生对象：Linux Bridge / Linux Bond / Linux VLAN。这里负责对象定义、编辑、校验、预览，以及受限 runtime apply。</p>
      </div>
      <button class="btn secondary" type="button" id="node-resource-form-reset">新建对象</button>
    </div>

    <div class="inline-banner warn">
      <strong>安全边界：</strong>
      <div class="muted">对宿主现网对象默认先预览，不直接接管。只有在新建对象或已由 NBKVM 标记为 managed_on_host=1 的对象上，才允许继续做 runtime apply。持久化部分只给 PVE / ifupdown 风格预览，不直接改宿主配置文件。</div>
    </div>

    <div class="host-interfaces top-gap-mini">
      <div><strong>能力探测</strong></div>
      <div class="muted"><?= e((string) ($nodeNetworkCapabilities['runtime_note'] ?? '')) ?></div>
      <div class="muted top-gap-mini"><?= e((string) ($nodeNetworkCapabilities['persistence_note'] ?? '')) ?></div>
    </div>

    <div id="node-resource-edit-state" class="inline-banner hidden-block"></div>

    <form action="/node-network-resources" method="post" id="node-resource-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <input type="hidden" name="id" id="node-resource-id" value="">

      <div class="module-grid single">
        <section class="module-card">
          <h4>对象定义</h4>
          <div class="row-2">
            <div>
              <label>名称 / iface</label>
              <input type="text" name="name" id="node-resource-name" placeholder="vmbr10 / bond0 / vmbr0.100" required>
            </div>
            <div>
              <label>类型</label>
              <select name="type" id="node-resource-type">
                <option value="bridge">Linux Bridge</option>
                <option value="bond">Linux Bond</option>
                <option value="vlan">Linux VLAN</option>
              </select>
            </div>
          </div>

          <div class="row-2">
            <div data-node-field="ports">
              <label id="node-resource-ports-label">Bridge Ports</label>
              <input type="text" name="ports" id="node-resource-ports" placeholder="enp4s0, enp5s0">
              <div class="muted top-gap-mini">Bridge 用 bridge-ports，Bond 用 bond-slaves；逗号或空格分隔。</div>
            </div>
            <div data-node-field="bond-mode">
              <label>Bond Mode</label>
              <select name="bond_mode" id="node-resource-bond-mode">
                <option value="active-backup">active-backup</option>
                <option value="802.3ad">802.3ad</option>
                <option value="balance-rr">balance-rr</option>
                <option value="balance-xor">balance-xor</option>
                <option value="broadcast">broadcast</option>
                <option value="balance-tlb">balance-tlb</option>
                <option value="balance-alb">balance-alb</option>
              </select>
            </div>
          </div>

          <div class="row-2">
            <div data-node-field="parent">
              <label>VLAN Raw Device</label>
              <input type="text" name="parent" id="node-resource-parent" placeholder="vmbr0 / bond0 / enp4s0">
            </div>
            <div data-node-field="vlan-id">
              <label>VLAN ID</label>
              <input type="number" min="1" max="4094" name="vlan_id" id="node-resource-vlan-id" placeholder="100">
            </div>
          </div>

          <div class="row-2">
            <div data-node-field="bridge-vlan-aware">
              <label><input class="inline" type="checkbox" name="bridge_vlan_aware" id="node-resource-bridge-vlan-aware" value="1"> Bridge VLAN Aware</label>
              <div class="muted top-gap-mini">仅 Linux Bridge 使用，对应 PVE 的 VLAN aware。</div>
            </div>
            <div>
              <label><input class="inline" type="checkbox" name="autostart" id="node-resource-autostart" value="1"> Autostart</label>
              <div class="muted top-gap-mini">当前仅保存在 NBKVM，并反映到下方的 PVE / ifupdown 预览。</div>
            </div>
          </div>
        </section>

        <section class="module-card">
          <h4>L3 地址</h4>
          <div class="row-2">
            <div>
              <label>IPv4 CIDR</label>
              <input type="text" name="cidr" id="node-resource-cidr" placeholder="10.0.10.2/24">
            </div>
            <div>
              <label>IPv4 Gateway</label>
              <input type="text" name="gateway" id="node-resource-gateway" placeholder="10.0.10.1">
            </div>
          </div>
          <div class="row-2">
            <div>
              <label>IPv6 CIDR</label>
              <input type="text" name="ipv6_cidr" id="node-resource-ipv6-cidr" placeholder="fd00:10::2/64">
            </div>
            <div>
              <label>IPv6 Gateway</label>
              <input type="text" name="ipv6_gateway" id="node-resource-ipv6-gateway" placeholder="fd00:10::1">
            </div>
          </div>
        </section>

        <section class="module-card">
          <h4>其他</h4>
          <div class="row-2">
            <div>
              <label>MTU</label>
              <input type="number" min="576" max="9216" name="mtu" id="node-resource-mtu" placeholder="1500">
            </div>
            <div></div>
          </div>
          <label>Comments</label>
          <textarea name="comments" id="node-resource-comments" rows="4" placeholder="例如：业务网桥 / trunk bridge / bond 上联 / 仅测试环境使用"></textarea>
        </section>
      </div>

      <div class="actions top-gap">
        <button class="btn" type="submit">保存节点网络对象</button>
        <span class="muted">对象层以 PVE 原生术语为主；libvirt network / pool 相关兼容能力已经下沉到页面底部。</span>
      </div>
    </form>
  </section>

  <section class="card span-12">
    <div class="section-split">
      <div>
        <h3>节点网络对象列表</h3>
        <p class="muted">这里形成闭环：定义 → 编辑 → 预览 → 受限 apply。持久化预览改成更接近 PVE 的 <code>/etc/network/interfaces</code> 风格。</p>
      </div>
      <span class="muted">共 <?= count($nodeNetworkResources) ?> 条</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>对象</th><th>配置</th><th>宿主状态</th><th>预览 / 风险</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($nodeNetworkResources as $candidate): ?>
            <?php
              $host = $candidate['host_resource'] ?? null;
              $plan = $candidate['plan'] ?? ['warnings' => [], 'errors' => [], 'command_preview' => [], 'persistence_preview' => ''];
              $previewText = trim(implode("\n", array_map(static fn ($line): string => (string) $line, (array) ($plan['command_preview'] ?? []))));
              $applyBlocked = !$runtimeApplySupported || ($host !== null && (int) ($candidate['managed_on_host'] ?? 0) !== 1);
              $deleteBlocked = $host !== null && (int) ($candidate['managed_on_host'] ?? 0) === 1;
            ?>
            <tr>
              <td>
                <strong><?= e((string) $candidate['name']) ?></strong>
                <div class="muted"><?= e($typeLabel((string) $candidate['type'])) ?> / ID #<?= (int) $candidate['id'] ?></div>
                <div class="muted">autostart <?= (int) ($candidate['autostart'] ?? 0) === 1 ? 'on' : 'off' ?> / mtu <?= e((string) (($candidate['mtu'] ?? null) !== null ? $candidate['mtu'] : '-')) ?></div>
                <div class="muted">状态 <?= e((string) (($candidate['last_apply_status'] ?? '') ?: 'draft')) ?><?= (int) ($candidate['managed_on_host'] ?? 0) === 1 ? ' / managed_on_host=1' : '' ?></div>
              </td>
              <td>
                <div class="resource-stack">
                  <?php if ((string) $candidate['type'] === 'bridge'): ?>
                    <div class="muted">Bridge Ports: <?= e((string) (($candidate['ports_text'] ?? '') ?: 'none')) ?></div>
                    <div class="muted">Bridge VLAN Aware: <?= (int) ($candidate['bridge_vlan_aware'] ?? 0) === 1 ? 'yes' : 'no' ?></div>
                  <?php elseif ((string) $candidate['type'] === 'bond'): ?>
                    <div class="muted">Bond Slaves: <?= e((string) (($candidate['ports_text'] ?? '') ?: '-')) ?></div>
                    <div class="muted">Bond Mode: <?= e((string) (($candidate['bond_mode'] ?? '') ?: 'active-backup')) ?></div>
                  <?php else: ?>
                    <div class="muted">VLAN Raw Device: <?= e((string) (($candidate['parent'] ?? '') ?: '-')) ?></div>
                    <div class="muted">VLAN ID: <?= e((string) (($candidate['vlan_id'] ?? '') ?: '-')) ?></div>
                  <?php endif; ?>
                  <div class="muted">IPv4: <?= e((string) (($candidate['cidr'] ?? '') ?: '-')) ?> / gw <?= e((string) (($candidate['gateway'] ?? '') ?: '-')) ?></div>
                  <div class="muted">IPv6: <?= e((string) (($candidate['ipv6_cidr'] ?? '') ?: '-')) ?> / gw <?= e((string) (($candidate['ipv6_gateway'] ?? '') ?: '-')) ?></div>
                  <div class="muted">Comments: <?= e((string) (($candidate['comments'] ?? '') ?: '-')) ?></div>
                </div>
              </td>
              <td>
                <?php if ($host): ?>
                  <div class="resource-stack">
                    <div><strong>宿主已存在</strong></div>
                    <div class="muted"><?= e($typeLabel((string) ($host['type'] ?? ''))) ?> / state <?= e((string) (($host['state'] ?? '') ?: 'unknown')) ?></div>
                    <div class="muted">mtu <?= e((string) (($host['mtu'] ?? '') ?: '-')) ?></div>
                    <?php if (!empty($host['ports'])): ?><div class="muted">ports/slaves <?= e($joinList(array_values($host['ports']))) ?></div><?php endif; ?>
                    <?php if (!empty($host['parent'])): ?><div class="muted">raw device <?= e((string) $host['parent']) ?></div><?php endif; ?>
                    <?php if (!empty($host['vlan_id'])): ?><div class="muted">VID <?= (int) $host['vlan_id'] ?></div><?php endif; ?>
                    <?php if (!empty($host['addresses'])): ?><div class="muted">addr <?= e($joinList(array_values($host['addresses']))) ?></div><?php endif; ?>
                    <?php if (!empty($host['default_routes'])): ?><div class="muted">default via <?= e($joinList(array_values($host['default_routes']))) ?></div><?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="muted">宿主当前未检测到同名对象。</div>
                <?php endif; ?>
                <?php if (!empty($candidate['last_apply_output'])): ?>
                  <details class="top-gap-mini">
                    <summary>最近 apply / remove 输出</summary>
                    <pre><?= e((string) $candidate['last_apply_output']) ?></pre>
                  </details>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($plan['errors'])): ?>
                  <div class="resource-stack">
                    <?php foreach ((array) $plan['errors'] as $error): ?>
                      <div class="muted">错误：<?= e((string) $error) ?></div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($plan['warnings'])): ?>
                  <div class="resource-stack top-gap-mini">
                    <?php foreach ((array) $plan['warnings'] as $warning): ?>
                      <div class="muted">提示：<?= e((string) $warning) ?></div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <details class="top-gap-mini">
                  <summary>runtime apply 预览</summary>
                  <pre><?= e($previewText !== '' ? $previewText : '当前宿主状态已与候选配置一致，或该对象现阶段仅预览不下发。') ?></pre>
                </details>
                <details class="top-gap-mini">
                  <summary>PVE / ifupdown 风格持久化预览</summary>
                  <pre><?= e((string) (($plan['persistence_preview'] ?? '') ?: '暂无')) ?></pre>
                </details>
              </td>
              <td>
                <div class="actions vertical-actions">
                  <button class="btn secondary js-edit-node-resource" type="button" data-resource='<?= e((string) json_encode([
                    'id' => (int) $candidate['id'],
                    'name' => (string) $candidate['name'],
                    'type' => (string) $candidate['type'],
                    'parent' => (string) ($candidate['parent'] ?? ''),
                    'ports_text' => (string) ($candidate['ports_text'] ?? ''),
                    'vlan_id' => $candidate['vlan_id'],
                    'bond_mode' => (string) ($candidate['bond_mode'] ?? ''),
                    'bridge_vlan_aware' => (int) ($candidate['bridge_vlan_aware'] ?? 0),
                    'cidr' => (string) ($candidate['cidr'] ?? ''),
                    'gateway' => (string) ($candidate['gateway'] ?? ''),
                    'ipv6_cidr' => (string) ($candidate['ipv6_cidr'] ?? ''),
                    'ipv6_gateway' => (string) ($candidate['ipv6_gateway'] ?? ''),
                    'mtu' => $candidate['mtu'],
                    'autostart' => (int) ($candidate['autostart'] ?? 0),
                    'comments' => (string) ($candidate['comments'] ?? ''),
                    'managed_on_host' => (int) ($candidate['managed_on_host'] ?? 0),
                  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>编辑</button>
                  <form action="/node-network-resources/apply" method="post" onsubmit="return confirm('确认按受限 runtime 模式应用到宿主？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $candidate['id'] ?>">
                    <button class="btn success" type="submit" <?= $applyBlocked ? 'disabled' : '' ?>>安全应用</button>
                  </form>
                  <form action="/node-network-resources/remove-host" method="post" onsubmit="return confirm('确认从宿主移除该节点网络对象？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $candidate['id'] ?>">
                    <button class="btn warn" type="submit" <?= ((int) ($candidate['managed_on_host'] ?? 0) !== 1 || !$runtimeApplySupported) ? 'disabled' : '' ?>>从宿主移除</button>
                  </form>
                  <form action="/node-network-resources/delete" method="post" onsubmit="return confirm('确认删除该候选定义？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $candidate['id'] ?>">
                    <button class="btn danger" type="submit" <?= $deleteBlocked ? 'disabled' : '' ?>>删除候选</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$nodeNetworkResources): ?><tr><td colspan="5" class="muted">暂无节点网络对象候选。</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card span-12">
    <details>
      <summary><strong>兼容层（低优先级）</strong>：legacy libvirt network / 默认 IP 池</summary>
      <div class="top-gap">
        <div class="inline-banner warn">
          <strong>说明：</strong>
          <div class="muted">这一层只为兼容旧数据、libvirt managed network 和 pool 继承。模板 / VM 的主心智已经改成直接围绕 Bridge / netX / ipconfigX；这里不再作为前台主入口。</div>
        </div>

        <div class="grid dashboard-grid top-gap-mini">
          <section class="card span-5">
            <div class="section-split">
              <div>
                <h3>兼容网络配置</h3>
                <p class="muted">仅在需要继续复用 legacy libvirt network 或默认 IP 池时使用。</p>
              </div>
              <button class="btn secondary" type="button" id="network-form-reset">新建兼容项</button>
            </div>

            <div id="network-edit-state" class="inline-banner hidden-block"></div>

            <form action="/networks" method="post" id="network-form">
              <?= csrf_field() ?>
              <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
              <input type="hidden" name="network_id" id="network-id" value="">

              <div class="module-grid single">
                <section class="module-card">
                  <h4>libvirt / Bridge 绑定</h4>
                  <label>兼容项名称</label>
                  <input type="text" id="network-name" name="name" value="default" required>
                  <div class="row-2">
                    <div>
                      <label>接入方式</label>
                      <select id="network-libvirt-managed" name="libvirt_managed">
                        <option value="0">bridge 直连（兼容记录）</option>
                        <option value="1">managed libvirt network</option>
                      </select>
                    </div>
                    <div>
                      <label>Bridge 资源</label>
                      <select id="network-bridge-select">
                        <?php foreach (($bridgeCandidates['bridges'] ?? []) as $bridge): ?>
                          <option value="<?= e((string) $bridge['name']) ?>"><?= e((string) $bridge['name']) ?> / <?= e((string) (($bridge['state'] ?? '') ?: 'unknown')) ?> / mtu <?= e((string) (($bridge['mtu'] ?? '') ?: '-')) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">自定义输入…</option>
                      </select>
                    </div>
                  </div>
                  <div id="network-bridge-custom-wrap" class="hidden-block top-gap-mini">
                    <label>自定义 Bridge</label>
                    <input type="text" id="network-bridge" name="bridge_name" value="vmbr0" required>
                  </div>
                  <div class="host-interfaces top-gap-mini" id="network-bridge-resource-summary">请选择 Bridge 资源。</div>
                </section>

                <section class="module-card">
                  <h4>L3 / 默认子网</h4>
                  <div class="row-2">
                    <div><label>IPv4 子网</label><input type="text" id="network-cidr" name="cidr" value="192.168.122.0/24" placeholder="10.0.10.0/24"></div>
                    <div><label>IPv4 网关</label><input type="text" id="network-gateway" name="gateway" value="192.168.122.1" placeholder="10.0.10.1"></div>
                  </div>
                  <div class="row-2">
                    <div><label>IPv6 子网</label><input type="text" id="network-ipv6-cidr" name="ipv6_cidr" placeholder="fd00:10::/64"></div>
                    <div><label>IPv6 网关</label><input type="text" id="network-ipv6-gateway" name="ipv6_gateway" placeholder="fd00:10::1"></div>
                  </div>
                </section>

                <details class="module-card" id="network-managed-dhcp-panel">
                  <summary>仅 managed libvirt network 使用：DHCP</summary>
                  <div class="row-2 top-gap-mini">
                    <div><label>DHCP 起始</label><input type="text" id="network-dhcp-start" name="dhcp_start" value="192.168.122.2"></div>
                    <div><label>DHCP 结束</label><input type="text" id="network-dhcp-end" name="dhcp_end" value="192.168.122.254"></div>
                  </div>
                </details>

                <details class="module-card" id="network-pool-panel">
                  <summary>高级：默认 IP 池</summary>
                  <div class="row-2 top-gap-mini">
                    <div><label>IPv4 池起始</label><input type="text" id="network-ipv4-pool-start" name="ipv4_pool_start_ip" placeholder="192.168.122.100"></div>
                    <div><label>IPv4 池结束</label><input type="text" id="network-ipv4-pool-end" name="ipv4_pool_end_ip" placeholder="192.168.122.150"></div>
                  </div>
                  <label>IPv4 池 DNS</label>
                  <input type="text" id="network-ipv4-pool-dns" name="ipv4_pool_dns_servers" value="1.1.1.1,8.8.8.8" placeholder="1.1.1.1,8.8.8.8">
                  <div class="row-2 top-gap-mini">
                    <div><label>IPv6 池起始</label><input type="text" id="network-ipv6-pool-start" name="ipv6_pool_start_ip" placeholder="fd00:10::100"></div>
                    <div><label>IPv6 池结束</label><input type="text" id="network-ipv6-pool-end" name="ipv6_pool_end_ip" placeholder="fd00:10::1ff"></div>
                  </div>
                  <label>IPv6 池 DNS</label>
                  <input type="text" id="network-ipv6-pool-dns" name="ipv6_pool_dns_servers" value="2606:4700:4700::1111" placeholder="2606:4700:4700::1111">
                </details>
              </div>

              <div class="actions top-gap">
                <button class="btn" type="submit">保存兼容项</button>
                <span class="muted">被模板 / VM 引用后，危险字段依然锁定。</span>
              </div>
            </form>
          </section>

          <section class="card span-7">
            <div class="section-split">
              <div>
                <h3>兼容项列表</h3>
                <p class="muted">仅供 legacy libvirt network / pool 兼容，不再主导前台网络心智。</p>
              </div>
              <span class="muted">共 <?= count($networkConfigs) ?> 条</span>
            </div>

            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Bridge</th><th>兼容项</th><th>L3 / DHCP</th><th>默认池</th><th>引用</th><th>操作</th></tr></thead>
                <tbody>
                  <?php foreach ($networkConfigs as $networkConfig): ?>
                    <?php
                      $network = $networkConfig['network'];
                      $resource = $networkConfig['bridge_resource'] ?? null;
                      $usage = $networkConfig['usage'] ?? [];
                      $inUse = (int) ($usage['template_count'] ?? 0) > 0 || (int) ($usage['vm_count'] ?? 0) > 0;
                    ?>
                    <tr>
                      <td>
                        <strong><?= e((string) (($network['bridge_name'] ?? '') ?: '-')) ?></strong>
                        <?php if ($resource): ?>
                          <div class="muted"><?= e($typeLabel((string) ($resource['type'] ?? 'bridge'))) ?> / <?= e((string) (($resource['state'] ?? '') ?: 'unknown')) ?> / mtu <?= e((string) (($resource['mtu'] ?? '') ?: '-')) ?></div>
                        <?php else: ?>
                          <div class="muted">宿主当前未检测到该 Bridge</div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <strong><?= e((string) $network['name']) ?></strong>
                        <div class="muted">ID #<?= (int) $network['id'] ?></div>
                        <div class="muted"><?= (int) ($network['libvirt_managed'] ?? 0) === 1 ? 'managed libvirt network' : 'bridge 直连兼容记录' ?></div>
                      </td>
                      <td>
                        <div class="resource-stack">
                          <div class="muted">IPv4 <?= e((string) (($network['cidr'] ?? '') ?: '-')) ?> / gw <?= e((string) (($network['gateway'] ?? '') ?: '-')) ?></div>
                          <div class="muted">IPv6 <?= e((string) (($network['ipv6_cidr'] ?? '') ?: '-')) ?> / gw <?= e((string) (($network['ipv6_gateway'] ?? '') ?: '-')) ?></div>
                          <?php if ((int) ($network['libvirt_managed'] ?? 0) === 1): ?>
                            <div class="muted">DHCP <?= e((string) (($network['dhcp_start'] ?? '') ?: '-')) ?> → <?= e((string) (($network['dhcp_end'] ?? '') ?: '-')) ?></div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <div class="resource-stack">
                          <div>
                            <strong>IPv4</strong>
                            <?php if (!empty($networkConfig['ipv4_pool'])): ?>
                              <div class="muted"><?= e((string) $networkConfig['ipv4_pool']['start_ip']) ?> - <?= e((string) $networkConfig['ipv4_pool']['end_ip']) ?></div>
                              <div class="muted">DNS <?= e((string) (($networkConfig['ipv4_pool']['dns_servers'] ?? '') ?: '-')) ?></div>
                            <?php else: ?>
                              <div class="muted">未配置</div>
                            <?php endif; ?>
                          </div>
                          <div>
                            <strong>IPv6</strong>
                            <?php if (!empty($networkConfig['ipv6_pool'])): ?>
                              <div class="muted"><?= e((string) $networkConfig['ipv6_pool']['start_ip']) ?> - <?= e((string) $networkConfig['ipv6_pool']['end_ip']) ?></div>
                              <div class="muted">DNS <?= e((string) (($networkConfig['ipv6_pool']['dns_servers'] ?? '') ?: '-')) ?></div>
                            <?php else: ?>
                              <div class="muted">未配置</div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="muted"><?= e($usageSummary($usage)) ?></div>
                        <?php if ($inUse): ?><div class="muted">危险字段已锁定；如需大改，请迁移到上方节点网络对象。</div><?php endif; ?>
                      </td>
                      <td>
                        <div class="actions vertical-actions">
                          <button class="btn secondary js-edit-network" type="button" data-network='<?= e((string) json_encode([
                            'id' => (int) $network['id'],
                            'name' => (string) $network['name'],
                            'bridge_name' => (string) ($network['bridge_name'] ?? ''),
                            'cidr' => (string) ($network['cidr'] ?? ''),
                            'gateway' => (string) ($network['gateway'] ?? ''),
                            'dhcp_start' => (string) ($network['dhcp_start'] ?? ''),
                            'dhcp_end' => (string) ($network['dhcp_end'] ?? ''),
                            'ipv6_cidr' => (string) ($network['ipv6_cidr'] ?? ''),
                            'ipv6_gateway' => (string) ($network['ipv6_gateway'] ?? ''),
                            'libvirt_managed' => (int) ($network['libvirt_managed'] ?? 0),
                            'ipv4_pool' => $networkConfig['ipv4_pool'],
                            'ipv6_pool' => $networkConfig['ipv6_pool'],
                          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>编辑</button>
                          <form action="/networks/delete" method="post" onsubmit="return confirm('确认删除该兼容项？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                            <input type="hidden" name="id" value="<?= (int) $network['id'] ?>">
                            <button class="btn danger" type="submit" <?= $inUse ? 'disabled' : '' ?>>删除</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$networkConfigs): ?><tr><td colspan="6" class="muted">暂无兼容项</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
    </details>
  </section>
</div>
