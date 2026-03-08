<section class="card span-12">
  <div class="section-split">
    <div>
      <h2>虚拟机详情：<?= e((string) $vm['name']) ?></h2>
      <p class="muted">模板：<?= e((string) ($template['name'] ?? '-')) ?> / 状态：<?= e((string) ($vm['status'] ?? 'unknown')) ?></p>
    </div>
    <div class="actions">
      <a class="btn secondary" href="/?page=vms">返回虚拟机页</a>
      <?php if (!empty($vm['vnc_display']) && auth_can_write()): ?>
        <a class="btn secondary" target="_blank" href="/novnc/open?id=<?= (int) $vm['id'] ?>">打开 noVNC</a>
      <?php endif; ?>
      <?php if (auth_can_write()): ?>
        <a class="btn secondary" target="_blank" href="/console/open?id=<?= (int) $vm['id'] ?>">打开 Xterm 控制台</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <tbody>
        <tr><th>主 IP</th><td><?= e((string) (($vm['ip_address'] ?? '') ?: '-')) ?></td></tr>
        <tr><th>VNC</th><td><?= e((string) (($vm['vnc_display'] ?? '') ?: '-')) ?> <span class="muted">/ 代理 <?= !empty($noVncStatus['running']) ? '运行中' : '未运行' ?></span></td></tr>
        <tr><th>XML</th><td><code><?= e((string) $vm['xml_path']) ?></code></td></tr>
        <tr><th>cloud-init ISO</th><td><code><?= e((string) (($vm['cloud_init_iso_path'] ?? '') ?: '-')) ?></code></td></tr>
        <tr><th>Xterm 控制台</th><td><?= e((string) (($consoleSnapshot['capabilities']['hint'] ?? '') ?: '-')) ?></td></tr>
      </tbody>
    </table>
  </div>
</section>

<section class="card span-12">
  <h2>磁盘配置</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>名称</th><th>角色</th><th>总线</th><th>格式</th><th>容量</th><th>路径</th></tr></thead>
      <tbody>
        <?php foreach (($vm['normalized_disks'] ?? []) as $disk): ?>
          <tr>
            <td><?= e((string) ($disk['name'] ?? '-')) ?></td>
            <td><?= !empty($disk['is_primary']) ? '主盘' : '数据盘' ?></td>
            <td><?= e((string) ($disk['bus'] ?? 'virtio')) ?></td>
            <td><?= e((string) ($disk['format'] ?? 'qcow2')) ?></td>
            <td><?= e((string) ($disk['size_gb'] ?? '-')) ?> GB</td>
            <td><code><?= e((string) (($disk['path'] ?? '') ?: '-')) ?></code></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($vm['normalized_disks'])): ?><tr><td colspan="6" class="muted">暂无磁盘信息</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card span-12">
  <h2>网卡配置</h2>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>接口</th>
          <th>Bridge / 网络</th>
          <th>模型</th>
          <th>IPv4</th>
          <th>IPv6</th>
          <th>参数</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($vm['normalized_nics'] ?? []) as $nic): ?>
          <tr>
            <td><?= e((string) ($nic['interface_name'] ?? '-')) ?></td>
            <td>
              <?= e((string) ($nic['bridge'] ?? '-')) ?><br>
              <span class="muted"><?= e((string) ($nic['source_type'] ?? 'bridge')) ?> / <?= e((string) ($nic['network_name'] ?? '-')) ?></span>
            </td>
            <td><?= e((string) ($nic['model'] ?? 'virtio')) ?></td>
            <td>
              <?= e((string) ($nic['ipv4_mode'] ?? 'dhcp')) ?>
              <?php if (!empty($nic['ipv4_address'])): ?><br><span class="muted"><?= e((string) $nic['ipv4_address']) ?>/<?= e((string) ($nic['ipv4_prefix_length'] ?? '')) ?></span><?php endif; ?>
              <?php if (!empty($nic['ipv4_gateway'])): ?><br><span class="muted">gw <?= e((string) $nic['ipv4_gateway']) ?></span><?php endif; ?>
            </td>
            <td>
              <?= e((string) ($nic['ipv6_mode'] ?? 'none')) ?>
              <?php if (!empty($nic['ipv6_address'])): ?><br><span class="muted"><?= e((string) $nic['ipv6_address']) ?>/<?= e((string) ($nic['ipv6_prefix_length'] ?? '')) ?></span><?php endif; ?>
              <?php if (!empty($nic['ipv6_gateway'])): ?><br><span class="muted">gw <?= e((string) $nic['ipv6_gateway']) ?></span><?php endif; ?>
            </td>
            <td>
              VLAN: <?= e((string) (($nic['vlan_tag'] ?? '') !== '' && $nic['vlan_tag'] !== null ? $nic['vlan_tag'] : '-')) ?><br>
              MAC: <?= e((string) (($nic['mac'] ?? '') ?: '-')) ?><br>
              <span class="muted">firewall <?= !empty($nic['firewall']) ? 'on' : 'off' ?> / link <?= !empty($nic['link_down']) ? 'down' : 'up' ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($vm['normalized_nics'])): ?><tr><td colspan="6" class="muted">暂无网卡信息</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card span-12">
  <h2>相关快照</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>ID</th><th>名称</th><th>状态</th><th>时间</th></tr></thead>
      <tbody>
        <?php foreach ($snapshots as $snapshot): ?>
          <tr>
            <td><?= (int) $snapshot['id'] ?></td>
            <td><?= e((string) $snapshot['name']) ?></td>
            <td><?= e((string) $snapshot['status']) ?></td>
            <td><?= e((string) $snapshot['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$snapshots): ?><tr><td colspan="4" class="muted">暂无快照</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
