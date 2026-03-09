<?php
$joinList = static fn (array $items): string => $items !== [] ? implode(' / ', array_map(static fn ($item): string => (string) $item, $items)) : '-';
$usageSummary = static function (array $usage): string {
    $templateCount = (int) ($usage['template_count'] ?? 0);
    $templateNics = (int) ($usage['template_nics'] ?? 0);
    $vmCount = (int) ($usage['vm_count'] ?? 0);
    $vmNics = (int) ($usage['vm_nics'] ?? 0);
    return sprintf('模板 %d 个（%d 张网卡） / VM %d 台（%d 张网卡）', $templateCount, $templateNics, $vmCount, $vmNics);
};
?>
<div class="grid dashboard-grid">
  <section class="card span-7">
    <div class="section-split">
      <div>
        <h3>节点网络资源</h3>
        <p class="muted">先看宿主 bridge / bond / vlan / physical iface，再决定给 VM 的 netX 接到哪里；Bridge 资源是这里的第一心智。</p>
      </div>
      <span class="muted">优先 Bridge：<?= e((string) (($bridgeCandidates['preferred_bridge'] ?? null) ?: '-')) ?></span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>接口</th><th>类型 / 状态</th><th>关系</th><th>地址 / 路由</th><th>备注</th></tr></thead>
        <tbody>
          <?php foreach (($bridgeCandidates['all'] ?? []) as $resource): ?>
            <tr>
              <td>
                <strong><?= e((string) $resource['name']) ?></strong>
                <?php if (!empty($resource['is_vmbr'])): ?><div class="muted">vmbr 风格 Bridge</div><?php endif; ?>
              </td>
              <td>
                <?= e((string) ($resource['type_label'] ?? ($resource['type'] ?? '-'))) ?><br>
                <span class="muted">state <?= e((string) (($resource['state'] ?? '') ?: 'unknown')) ?></span><br>
                <span class="muted">mtu <?= e((string) (($resource['mtu'] ?? '') ?: '-')) ?><?php if (!empty($resource['speed'])): ?> / <?= e((string) $resource['speed']) ?> Mb/s<?php endif; ?></span>
                <?php if (!empty($resource['vlan_id'])): ?><br><span class="muted">VID <?= (int) $resource['vlan_id'] ?></span><?php endif; ?>
              </td>
              <td>
                <div class="resource-stack">
                  <?php if (!empty($resource['master'])): ?><div class="muted">master → <?= e((string) $resource['master']) ?></div><?php endif; ?>
                  <?php if (!empty($resource['parent'])): ?><div class="muted">parent → <?= e((string) $resource['parent']) ?></div><?php endif; ?>
                  <?php if (!empty($resource['ports'])): ?><div class="muted">ports: <?= e($joinList(array_values($resource['ports']))) ?></div><?php endif; ?>
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
            </tr>
          <?php endforeach; ?>
          <?php if (empty($bridgeCandidates['all'])): ?><tr><td colspan="5" class="muted">当前没有探测到可展示的宿主网络资源。</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card span-5">
    <div class="section-split">
      <div>
        <h3>Bridge Profile / 网络资源映射</h3>
        <p class="muted">这里维护“可给 VM netX 复用的 Bridge + L3 描述”。DHCP 只留在兼容模式；默认 IP 池作为 ipconfig 的可选后备，不再和基础网络属性堆成一坨。</p>
      </div>
      <button class="btn secondary" type="button" id="network-form-reset">新建 Profile</button>
    </div>

    <div id="network-edit-state" class="inline-banner hidden-block"></div>

    <form action="/networks" method="post" id="network-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <input type="hidden" name="network_id" id="network-id" value="">

      <div class="module-grid single">
        <section class="module-card">
          <h4>Bridge 绑定</h4>
          <label>Profile 名称</label>
          <input type="text" id="network-name" name="name" value="default" required>
          <div class="row-2">
            <div>
              <label>接入方式</label>
              <select id="network-libvirt-managed" name="libvirt_managed">
                <option value="0">bridge 直连（PVE 风格）</option>
                <option value="1">managed libvirt network（兼容模式）</option>
              </select>
            </div>
            <div>
              <label>Bridge 资源</label>
              <select id="network-bridge-select">
                <?php foreach (($bridgeCandidates['bridges'] ?? []) as $bridge): ?>
                  <option value="<?= e((string) $bridge['name']) ?>"><?= e((string) $bridge['name']) ?> / <?= e((string) ($bridge['state'] ?? 'unknown')) ?> / mtu <?= e((string) (($bridge['mtu'] ?? '') ?: '-')) ?></option>
                <?php endforeach; ?>
                <option value="__custom__">自定义输入…</option>
              </select>
            </div>
          </div>
          <div id="network-bridge-custom-wrap" class="hidden-block top-gap-mini">
            <label>自定义 Bridge</label>
            <input type="text" id="network-bridge" name="bridge_name" value="vmbr0" required>
          </div>
          <div class="host-interfaces top-gap-mini" id="network-bridge-resource-summary">
            请选择 Bridge 资源。
          </div>
        </section>

        <section class="module-card">
          <h4>L3 基础属性</h4>
          <div class="row-2">
            <div><label>IPv4 子网</label><input type="text" id="network-cidr" name="cidr" value="192.168.122.0/24" placeholder="10.0.10.0/24"></div>
            <div><label>IPv4 网关</label><input type="text" id="network-gateway" name="gateway" value="192.168.122.1" placeholder="10.0.10.1"></div>
          </div>
          <div class="row-2">
            <div><label>IPv6 子网</label><input type="text" id="network-ipv6-cidr" name="ipv6_cidr" placeholder="fd00:10::/64"></div>
            <div><label>IPv6 网关</label><input type="text" id="network-ipv6-gateway" name="ipv6_gateway" placeholder="fd00:10::1"></div>
          </div>
          <p class="muted top-gap-mini">Bridge Profile 只描述基础 L2/L3；真正给 VM 用 DHCP / static / auto / pool，是在模板或 VM 的 ipconfig 层选择。</p>
        </section>

        <details class="module-card" id="network-managed-dhcp-panel">
          <summary>兼容模式：managed libvirt DHCP</summary>
          <div class="row-2 top-gap-mini">
            <div><label>DHCP 起始</label><input type="text" id="network-dhcp-start" name="dhcp_start" value="192.168.122.2"></div>
            <div><label>DHCP 结束</label><input type="text" id="network-dhcp-end" name="dhcp_end" value="192.168.122.254"></div>
          </div>
          <p class="muted top-gap-mini">只有在 managed libvirt network 兼容模式下才建议填写。bridge 直连 / PVE 风格通常不在这里管 DHCP。</p>
        </details>

        <details class="module-card" id="network-pool-panel">
          <summary>默认 IP 池（供 netX → ipconfigX 的 pool 模式继承，可选）</summary>
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
          <p class="muted top-gap-mini">留空起止地址即可关闭对应地址族的池。前台不再把 pool 当成主对象，而是把它当作 ipconfig 的可选后备来源。</p>
        </details>
      </div>

      <div class="actions top-gap">
        <button class="btn" type="submit">保存 Profile</button>
        <span class="muted">已被模板或 VM 引用的 Profile，不允许直接修改 bridge / 模式 / 子网等危险字段。</span>
      </div>
    </form>
  </section>

  <section class="card span-12">
    <div class="section-split">
      <div>
        <h3>Bridge Profile 列表</h3>
        <p class="muted">每条 Profile 都围绕一个 Bridge 资源组织；默认 IP 池是可选附属信息，不再抢前台主位。</p>
      </div>
      <span class="muted">共 <?= count($networkConfigs) ?> 条</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Bridge Resource</th><th>Profile</th><th>L3 / 兼容项</th><th>默认 IP 池</th><th>引用</th><th>操作</th></tr></thead>
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
                  <div class="muted"><?= e((string) (($resource['type_label'] ?? '') ?: ($resource['type'] ?? 'bridge'))) ?> / <?= e((string) (($resource['state'] ?? '') ?: 'unknown')) ?> / mtu <?= e((string) (($resource['mtu'] ?? '') ?: '-')) ?></div>
                  <?php if (!empty($resource['ports'])): ?><div class="muted">ports: <?= e($joinList(array_values($resource['ports']))) ?></div><?php endif; ?>
                <?php else: ?>
                  <div class="muted">宿主当前未检测到该 Bridge 资源</div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e((string) $network['name']) ?></strong>
                <div class="muted">ID #<?= (int) $network['id'] ?></div>
                <div class="muted"><?= (int) ($network['libvirt_managed'] ?? 0) === 1 ? 'managed libvirt network（兼容）' : 'bridge 直连（PVE 风格）' ?></div>
              </td>
              <td>
                <div class="resource-stack">
                  <div>
                    <strong>IPv4</strong>
                    <div class="muted"><?= e((string) (($network['cidr'] ?? '') ?: '-')) ?> / gw <?= e((string) (($network['gateway'] ?? '') ?: '-')) ?></div>
                  </div>
                  <div>
                    <strong>IPv6</strong>
                    <div class="muted"><?= e((string) (($network['ipv6_cidr'] ?? '') ?: '-')) ?> / gw <?= e((string) (($network['ipv6_gateway'] ?? '') ?: '-')) ?></div>
                  </div>
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
                <?php if ($inUse): ?><div class="muted">危险字段已锁定；如需变更请新建 Profile 并迁移 netX。</div><?php endif; ?>
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
                  <form action="/networks/delete" method="post" onsubmit="return confirm('确认删除该 Bridge Profile？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $network['id'] ?>">
                    <button class="btn danger" type="submit" <?= $inUse ? 'disabled' : '' ?>>删除</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$networkConfigs): ?><tr><td colspan="6" class="muted">暂无 Bridge Profile</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
