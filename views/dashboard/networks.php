<div class="grid dashboard-grid">
  <section class="card span-5">
    <div class="section-split">
      <div>
        <h3>网络配置</h3>
        <p class="muted">地址池已经和网络对象绑定；前台不再单独暴露 IP 池页面。</p>
      </div>
      <button class="btn secondary" type="button" id="network-form-reset">新建网络</button>
    </div>

    <div id="network-edit-state" class="inline-banner hidden-block"></div>

    <form action="/networks" method="post" id="network-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <input type="hidden" name="network_id" id="network-id" value="">

      <div class="module-grid single">
        <section class="module-card">
          <h4>基础</h4>
          <label>网络名称</label>
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
              <label>Bridge 选择</label>
              <select id="network-bridge-select">
                <?php foreach (($bridgeCandidates['bridges'] ?? []) as $bridge): ?>
                  <option value="<?= e((string) $bridge['name']) ?>"><?= e((string) $bridge['name']) ?> / <?= e((string) ($bridge['state'] ?? 'unknown')) ?></option>
                <?php endforeach; ?>
                <option value="__custom__">自定义输入…</option>
              </select>
            </div>
          </div>
          <div id="network-bridge-custom-wrap" class="hidden-block">
            <label>自定义 Bridge</label>
            <input type="text" id="network-bridge" name="bridge_name" value="vmbr0" required>
          </div>
          <div class="muted host-interfaces">
            <strong>宿主检测到的 bridge：</strong>
            <?php if (!empty($bridgeCandidates['bridges'])): ?>
              <?= e(implode(' / ', array_map(static fn (array $item): string => (string) $item['name'], $bridgeCandidates['bridges']))) ?>
            <?php else: ?>
              未检测到 bridge，请使用高级自定义输入。
            <?php endif; ?>
            <br>
            <strong>物理/其他接口参考：</strong>
            <?php if (!empty($bridgeCandidates['interfaces'])): ?>
              <?= e(implode(' / ', array_map(static fn (array $item): string => (string) $item['name'], $bridgeCandidates['interfaces']))) ?>
            <?php else: ?>
              无
            <?php endif; ?>
          </div>
        </section>

        <section class="module-card">
          <h4>IPv4</h4>
          <div class="row-2">
            <div><label>IPv4 子网</label><input type="text" id="network-cidr" name="cidr" value="192.168.122.0/24" placeholder="10.0.10.0/24"></div>
            <div><label>IPv4 网关</label><input type="text" id="network-gateway" name="gateway" value="192.168.122.1" placeholder="10.0.10.1"></div>
          </div>
          <div class="row-2">
            <div><label>DHCP 起始</label><input type="text" id="network-dhcp-start" name="dhcp_start" value="192.168.122.2"></div>
            <div><label>DHCP 结束</label><input type="text" id="network-dhcp-end" name="dhcp_end" value="192.168.122.254"></div>
          </div>
          <div class="row-2">
            <div><label>IPv4 地址池起始</label><input type="text" id="network-ipv4-pool-start" name="ipv4_pool_start_ip" placeholder="192.168.122.100"></div>
            <div><label>IPv4 地址池结束</label><input type="text" id="network-ipv4-pool-end" name="ipv4_pool_end_ip" placeholder="192.168.122.150"></div>
          </div>
          <label>IPv4 地址池 DNS</label>
          <input type="text" id="network-ipv4-pool-dns" name="ipv4_pool_dns_servers" value="1.1.1.1,8.8.8.8" placeholder="1.1.1.1,8.8.8.8">
        </section>

        <section class="module-card">
          <h4>IPv6</h4>
          <div class="row-2">
            <div><label>IPv6 子网</label><input type="text" id="network-ipv6-cidr" name="ipv6_cidr" placeholder="fd00:10::/64"></div>
            <div><label>IPv6 网关</label><input type="text" id="network-ipv6-gateway" name="ipv6_gateway" placeholder="fd00:10::1"></div>
          </div>
          <div class="row-2">
            <div><label>IPv6 地址池起始</label><input type="text" id="network-ipv6-pool-start" name="ipv6_pool_start_ip" placeholder="fd00:10::100"></div>
            <div><label>IPv6 地址池结束</label><input type="text" id="network-ipv6-pool-end" name="ipv6_pool_end_ip" placeholder="fd00:10::1ff"></div>
          </div>
          <label>IPv6 地址池 DNS</label>
          <input type="text" id="network-ipv6-pool-dns" name="ipv6_pool_dns_servers" value="2606:4700:4700::1111" placeholder="2606:4700:4700::1111">
          <p class="muted">留空起始/结束地址即可关闭某个地址族的池；模板/VM 网卡仅选择网络即可自动继承地址池。</p>
        </section>
      </div>

      <div class="actions top-gap">
        <button class="btn" type="submit">保存网络</button>
        <span class="muted">已被模板或 VM 引用的网络，不允许直接修改 bridge / 模式 / 子网等危险字段。</span>
      </div>
    </form>
  </section>

  <section class="card span-7">
    <div class="section-split">
      <div>
        <h3>网络列表</h3>
        <p class="muted">地址池直接显示在网络行里，不再拆成单独的管理对象。</p>
      </div>
      <span class="muted">共 <?= count($networkConfigs) ?> 条</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>网络</th><th>Bridge / 模式</th><th>IPv4</th><th>IPv6</th><th>地址池</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($networkConfigs as $networkConfig): ?>
            <?php $network = $networkConfig['network']; ?>
            <tr>
              <td>
                <strong><?= e((string) $network['name']) ?></strong>
                <div class="muted">ID #<?= (int) $network['id'] ?></div>
              </td>
              <td>
                <?= e((string) (($network['bridge_name'] ?? '') ?: '-')) ?><br>
                <span class="muted"><?= (int) ($network['libvirt_managed'] ?? 0) === 1 ? 'managed libvirt network' : 'bridge 直连' ?></span>
              </td>
              <td>
                <?= e((string) (($network['cidr'] ?? '') ?: '-')) ?><br>
                <span class="muted">gw <?= e((string) (($network['gateway'] ?? '') ?: '-')) ?></span><br>
                <span class="muted">dhcp <?= e((string) (($network['dhcp_start'] ?? '') ?: '-')) ?> → <?= e((string) (($network['dhcp_end'] ?? '') ?: '-')) ?></span>
              </td>
              <td>
                <?= e((string) (($network['ipv6_cidr'] ?? '') ?: '-')) ?><br>
                <span class="muted">gw <?= e((string) (($network['ipv6_gateway'] ?? '') ?: '-')) ?></span>
              </td>
              <td>
                <div class="resource-stack">
                  <div>
                    <strong>IPv4</strong>
                    <?php if (!empty($networkConfig['ipv4_pool'])): ?>
                      <div class="muted"><?= e((string) $networkConfig['ipv4_pool']['start_ip']) ?> - <?= e((string) $networkConfig['ipv4_pool']['end_ip']) ?></div>
                      <div class="muted">DNS <?= e((string) (($networkConfig['ipv4_pool']['dns_servers'] ?? '') ?: '-')) ?></div>
                    <?php else: ?>
                      <div class="muted">未启用</div>
                    <?php endif; ?>
                  </div>
                  <div>
                    <strong>IPv6</strong>
                    <?php if (!empty($networkConfig['ipv6_pool'])): ?>
                      <div class="muted"><?= e((string) $networkConfig['ipv6_pool']['start_ip']) ?> - <?= e((string) $networkConfig['ipv6_pool']['end_ip']) ?></div>
                      <div class="muted">DNS <?= e((string) (($networkConfig['ipv6_pool']['dns_servers'] ?? '') ?: '-')) ?></div>
                    <?php else: ?>
                      <div class="muted">未启用</div>
                    <?php endif; ?>
                  </div>
                </div>
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
                  <form action="/networks/delete" method="post" onsubmit="return confirm('确认删除该网络？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $network['id'] ?>">
                    <button class="btn danger" type="submit">删除</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$networkConfigs): ?><tr><td colspan="6" class="muted">暂无网络</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
