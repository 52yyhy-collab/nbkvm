<?php
$dashboardJson = [
    'networks' => array_map(static fn (array $network): array => [
        'id' => (int) ($network['id'] ?? 0),
        'name' => (string) ($network['name'] ?? ''),
        'bridge_name' => (string) ($network['bridge_name'] ?? ''),
        'cidr' => (string) ($network['cidr'] ?? ''),
        'gateway' => (string) ($network['gateway'] ?? ''),
        'dhcp_start' => (string) ($network['dhcp_start'] ?? ''),
        'dhcp_end' => (string) ($network['dhcp_end'] ?? ''),
        'ipv6_cidr' => (string) ($network['ipv6_cidr'] ?? ''),
        'ipv6_gateway' => (string) ($network['ipv6_gateway'] ?? ''),
        'libvirt_managed' => (int) ($network['libvirt_managed'] ?? 0),
    ], $networks),
    'pools' => array_map(static fn (array $pool): array => [
        'id' => (int) ($pool['id'] ?? 0),
        'name' => (string) ($pool['name'] ?? ''),
        'network_id' => (int) ($pool['network_id'] ?? 0),
        'network_name' => (string) ($pool['network_name'] ?? ''),
        'family' => (string) ($pool['family'] ?? 'ipv4'),
        'dns_servers' => (string) ($pool['dns_servers'] ?? ''),
        'start_ip' => (string) ($pool['start_ip'] ?? ''),
        'end_ip' => (string) ($pool['end_ip'] ?? ''),
    ], $ipPools),
    'templates' => array_map(static fn (array $template): array => [
        'id' => (int) ($template['id'] ?? 0),
        'name' => (string) ($template['name'] ?? ''),
        'nics' => array_values($template['normalized_nics'] ?? []),
    ], $templates),
];
$encodeAttr = static fn (array $value): string => e((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$nicSummary = static function (array $nic): string {
    $bridge = (string) ($nic['bridge'] ?? $nic['network_name'] ?? '-');
    $model = (string) ($nic['model'] ?? 'virtio');
    $vlan = ($nic['vlan_tag'] ?? null) !== null && (string) ($nic['vlan_tag'] ?? '') !== '' ? (' vlan ' . $nic['vlan_tag']) : '';
    return $bridge . ' / ' . $model . $vlan . ' / IPv4 ' . ($nic['ipv4_mode'] ?? 'dhcp') . ' / IPv6 ' . ($nic['ipv6_mode'] ?? 'none');
};
?>
<div class="grid">
  <section class="card span-12">
    <h2>环境自检</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>项目</th><th>状态</th><th>详情</th></tr></thead>
        <tbody>
          <?php foreach ($envChecks as $check): ?>
            <tr>
              <td><?= e((string) $check['name']) ?></td>
              <td><span class="badge <?= !empty($check['ok']) ? 'running' : 'shut' ?>"><?= !empty($check['ok']) ? '正常' : '待处理' ?></span></td>
              <td><?= e((string) $check['value']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-12">
    <h2>系统配置</h2>
    <form action="/settings" method="post">
      <?= csrf_field() ?>
      <div class="row-3">
        <div><label>上传大小(MB)</label><input type="number" min="1" name="upload_max_size_mb" value="<?= e((string) ($settingsMap['upload_max_size_mb'] ?? '51200')) ?>"></div>
        <div><label>到期后暂停几天删除</label><input type="number" min="0" name="expire_grace_days" value="<?= e((string) ($settingsMap['expire_grace_days'] ?? '3')) ?>"></div>
      </div>
      <p class="muted">虚拟机到期后统一先暂停，再按这里设置的天数自动删除。</p>
      <label>系统变量(JSON)</label>
      <textarea name="system_variables_json" placeholder='{"UPLOAD_TMP_DIR":"/data/tmp","DEFAULT_BRIDGE":"vmbr0"}'><?= e((string) ($settingsMap['system_variables_json'] ?? '{}')) ?></textarea>
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">保存系统配置</button>
    </form>
  </section>
  <section class="card span-4">
    <h2>修改密码</h2>
    <form action="/password" method="post">
      <?= csrf_field() ?>
      <label>新密码</label>
      <input type="password" name="password" required>
      <label>确认新密码</label>
      <input type="password" name="password_confirm" required>
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">更新密码</button>
    </form>
  </section>
  <?php if (auth_is_admin()): ?>
  <section class="card span-4">
    <h2>用户管理</h2>
    <form action="/users" method="post">
      <?= csrf_field() ?>
      <label>用户名</label>
      <input type="text" name="username" required>
      <label>密码</label>
      <input type="password" name="password" required>
      <label>角色</label>
      <select name="role">
        <option value="admin">admin</option>
        <option value="operator">operator</option>
        <option value="readonly">readonly</option>
      </select>
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">创建用户</button>
    </form>
    <p class="muted">只有 admin 可以管理用户；operator 可执行资源写操作；readonly 仅查看。</p>
    <div class="spacer"></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>用户名</th><th>角色</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($users as $userItem): ?>
            <tr>
              <td><?= (int) $userItem['id'] ?></td>
              <td><?= e((string) $userItem['username']) ?></td>
              <td>
                <form action="/users/role" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $userItem['id'] ?>">
                  <select name="role">
                    <option value="admin" <?= ($userItem['role'] === 'admin') ? 'selected' : '' ?>>admin</option>
                    <option value="operator" <?= ($userItem['role'] === 'operator') ? 'selected' : '' ?>>operator</option>
                    <option value="readonly" <?= ($userItem['role'] === 'readonly') ? 'selected' : '' ?>>readonly</option>
                  </select>
                  <button class="btn secondary" type="submit">更新角色</button>
                </form>
              </td>
              <td>
                <form action="/users/delete" method="post" onsubmit="return confirm('确认删除该用户？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $userItem['id'] ?>">
                  <button class="btn danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
  <section class="card span-4">
    <h2>上传镜像</h2>
    <form action="/images" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label for="image">镜像文件</label>
      <input id="image" type="file" name="image" required>
      <p class="muted">支持：ISO / QCOW2 / RAW / IMG</p>
      <button class="btn" type="submit">上传</button>
    </form>
  </section>
  <section class="card span-4">
    <h2>网络管理</h2>
    <form action="/networks" method="post" id="network-form">
      <?= csrf_field() ?>
      <input type="hidden" name="network_id" id="network-id" value="">
      <label>网络名称</label><input type="text" id="network-name" name="name" value="default" required>
      <div class="row-3">
        <div><label>Bridge</label><input type="text" id="network-bridge" name="bridge_name" value="vmbr0" required></div>
        <div>
          <label>接入方式</label>
          <select id="network-libvirt-managed" name="libvirt_managed">
            <option value="0">bridge 直连（PVE 风格）</option>
            <option value="1">managed libvirt network（兼容模式）</option>
          </select>
        </div>
        <div><label>说明</label><div class="muted form-note">bridge 直连会生成 &lt;interface type='bridge'&gt;。</div></div>
      </div>
      <div class="row">
        <div><label>IPv4 子网</label><input type="text" id="network-cidr" name="cidr" value="192.168.122.0/24" placeholder="10.0.10.0/24"></div>
        <div><label>IPv4 网关</label><input type="text" id="network-gateway" name="gateway" value="192.168.122.1" placeholder="10.0.10.1"></div>
      </div>
      <div class="row">
        <div><label>DHCP 起始</label><input type="text" id="network-dhcp-start" name="dhcp_start" value="192.168.122.2"></div>
        <div><label>DHCP 结束</label><input type="text" id="network-dhcp-end" name="dhcp_end" value="192.168.122.254"></div>
      </div>
      <div class="row">
        <div><label>IPv6 子网</label><input type="text" id="network-ipv6-cidr" name="ipv6_cidr" placeholder="fd00:10::/64"></div>
        <div><label>IPv6 网关</label><input type="text" id="network-ipv6-gateway" name="ipv6_gateway" placeholder="fd00:10::1"></div>
      </div>
      <p class="muted">子网 / 网关定义在网络对象上；IP 池只描述区间范围，更接近 PVE SDN/IPAM。</p>
      <div class="actions">
        <button class="btn secondary" type="submit">保存网络</button>
        <button class="btn secondary" type="button" id="network-form-reset">新建</button>
      </div>
    </form>
    <div class="spacer"></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>Bridge / 模式</th><th>IPv4</th><th>IPv6</th><th>地址池</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($networkConfigs as $networkConfig): ?>
            <?php $network = $networkConfig['network']; ?>
            <?php $networkPools = $networkConfig['pools']; ?>
            <tr>
              <td><?= e((string) $network['name']) ?></td>
              <td><?= e((string) ($network['bridge_name'] ?: '-')) ?><br><span class="muted"><?= (int) ($network['libvirt_managed'] ?? 0) === 1 ? 'managed libvirt network' : 'bridge 直连' ?></span></td>
              <td><?= e((string) (($network['cidr'] ?? '') ?: '-')) ?><br><span class="muted"><?= e((string) (($network['gateway'] ?? '') ?: '-')) ?></span></td>
              <td><?= e((string) (($network['ipv6_cidr'] ?? '') ?: '-')) ?><br><span class="muted"><?= e((string) (($network['ipv6_gateway'] ?? '') ?: '-')) ?></span></td>
              <td>
                <?php foreach ($networkPools as $pool): ?>
                  <div><strong><?= e((string) $pool['name']) ?></strong> <span class="muted">(<?= e((string) ($pool['family'] ?? 'ipv4')) ?>)</span><br><span class="muted"><?= e((string) $pool['start_ip']) ?> - <?= e((string) $pool['end_ip']) ?></span></div>
                <?php endforeach; ?>
                <?php if (!$networkPools): ?><span class="muted">暂无池</span><?php endif; ?>
              </td>
              <td>
                <div class="actions">
                  <button class="btn secondary js-edit-network" type="button" data-network='<?= $encodeAttr([
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
                  ]) ?>'>编辑</button>
                  <form action="/networks/delete" method="post" onsubmit="return confirm('确认删除该网络？');">
                    <?= csrf_field() ?>
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
  <section class="card span-4">
    <h2>IP 池管理</h2>
    <form action="/ip-pools" method="post" id="pool-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="pool-id" value="">
      <label>IP 池名称</label>
      <input type="text" id="pool-name" name="name" required placeholder="vmbr0-ipv4-main">
      <label>绑定网络</label>
      <select id="pool-network-id" name="network_id" required>
        <option value="">请选择网络</option>
        <?php foreach ($networks as $network): ?>
          <option value="<?= (int) $network['id'] ?>"><?= e((string) $network['name']) ?> / <?= e((string) (($network['bridge_name'] ?? '') ?: '-')) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="row">
        <div><label>地址族</label><select id="pool-family" name="family"><option value="ipv4">IPv4</option><option value="ipv6">IPv6</option></select></div>
        <div><label>DNS</label><input type="text" id="pool-dns" name="dns_servers" value="1.1.1.1,8.8.8.8" placeholder="1.1.1.1,8.8.8.8"></div>
      </div>
      <div class="row">
        <div><label>起始 IP</label><input type="text" id="pool-start-ip" name="start_ip" value="192.168.122.100" required></div>
        <div><label>结束 IP</label><input type="text" id="pool-end-ip" name="end_ip" value="192.168.122.150" required></div>
      </div>
      <p class="muted">池只表示某个子网里的可分配区间；网关和前缀会自动继承自网络对象。</p>
      <div class="actions">
        <button class="btn secondary" type="submit">保存 IP 池</button>
        <button class="btn secondary" type="button" id="pool-form-reset">新建</button>
      </div>
    </form>
    <div class="spacer"></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>地址族</th><th>网络</th><th>范围</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($ipPools as $pool): ?>
            <tr>
              <td><?= e((string) $pool['name']) ?></td>
              <td><?= e((string) ($pool['family'] ?? 'ipv4')) ?></td>
              <td><?= e((string) (($pool['network_name'] ?? '') ?: ('#' . (int) ($pool['network_id'] ?? 0)))) ?></td>
              <td><?= e((string) $pool['start_ip']) ?> - <?= e((string) $pool['end_ip']) ?></td>
              <td>
                <div class="actions">
                  <button class="btn secondary js-edit-pool" type="button" data-pool='<?= $encodeAttr([
                    'id' => (int) $pool['id'],
                    'name' => (string) $pool['name'],
                    'network_id' => (int) ($pool['network_id'] ?? 0),
                    'network_name' => (string) ($pool['network_name'] ?? ''),
                    'family' => (string) ($pool['family'] ?? 'ipv4'),
                    'dns_servers' => (string) ($pool['dns_servers'] ?? ''),
                    'start_ip' => (string) ($pool['start_ip'] ?? ''),
                    'end_ip' => (string) ($pool['end_ip'] ?? ''),
                  ]) ?>'>编辑</button>
                  <form action="/ip-pools/delete" method="post" onsubmit="return confirm('确认删除该 IP 池？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $pool['id'] ?>">
                    <button class="btn danger" type="submit">删除</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ipPools): ?><tr><td colspan="5" class="muted">暂无 IP 池</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-4">
    <h2>创建模板</h2>
    <form action="/templates" method="post" id="template-form">
      <?= csrf_field() ?>
      <input type="hidden" name="network_name" id="template-fallback-network" value="default">
      <input type="hidden" name="nics_json" id="template-nics-json" value="[]">
      <label>模板名称</label>
      <input type="text" name="name" required placeholder="ubuntu-24-cloud">
      <label>基础镜像</label>
      <select name="image_id" required>
        <option value="">请选择镜像</option>
        <?php foreach ($images as $image): ?>
          <option value="<?= (int) $image['id'] ?>"><?= e((string) $image['name']) ?> (<?= e((string) $image['extension']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="row-3">
        <div><label>CPU 虚拟化</label><select name="virtualization_mode"><option value="kvm">硬件虚拟化 (KVM)</option><option value="qemu">软件虚拟化 (QEMU)</option></select></div>
        <div><label>主板类型</label><select name="machine_type"><option value="pc">pc</option><option value="q35">q35</option></select></div>
        <div><label>固件</label><select name="firmware_type"><option value="bios">bios</option><option value="uefi">uefi</option></select></div>
      </div>
      <div class="row-3">
        <div><label>CPU 核数组 / sockets</label><input type="number" min="1" name="cpu_sockets" value="1"></div>
        <div><label>CPU 核心数 / cores</label><input type="number" min="1" name="cpu_cores" value="2"></div>
        <div><label>CPU 线程数 / threads</label><input type="number" min="1" name="cpu_threads" value="1"></div>
      </div>
      <p class="muted">总 vCPU = sockets × cores × threads</p>
      <div class="row-3">
        <div><label>内存(MB)</label><input type="number" min="256" name="memory_mb" value="2048" required></div>
        <div><label>内存上限(MB)</label><input type="number" min="256" name="memory_max_mb" value="4096"></div>
        <div><label>内存超开(%)</label><input type="number" min="100" name="memory_overcommit_percent" value="100"></div>
      </div>
      <div class="row-3">
        <div><label>默认系统盘(GB)</label><input type="number" min="5" name="disk_size_gb" value="20" required></div>
        <div><label>磁盘总线</label><select name="disk_bus"><option value="virtio">virtio</option><option value="sata">sata</option><option value="scsi">scsi</option></select></div>
        <div><label>GPU 类型</label><select name="gpu_type"><option value="cirrus">cirrus</option><option value="qxl">qxl</option><option value="virtio">virtio</option><option value="vga">vga</option><option value="none">none</option></select></div>
      </div>
      <label>OS Variant</label>
      <input type="text" name="os_variant" value="generic" required>
      <label>备注</label>
      <textarea name="notes" placeholder="比如：cloud image / 安装 ISO / 内网模板"></textarea>
      <label><input class="inline" type="checkbox" name="autostart_default" value="1"> 模板默认自启动</label>
      <label><input class="inline" type="checkbox" name="disk_overcommit_enabled" value="1"> 允许磁盘超开</label>
      <label>多硬盘配置(JSON)</label>
      <textarea name="disks_json">[{"name":"disk0","size_gb":20,"bus":"virtio","format":"qcow2"},{"name":"disk1","size_gb":50,"bus":"virtio","format":"qcow2"}]</textarea>
      <p class="muted">磁盘还保留高级 JSON 入口；日常网络配置改为可视化表单。</p>

      <div class="editor-header">
        <label>网卡配置（PVE 风格）</label>
        <button class="btn secondary js-add-nic" type="button" data-editor="template">新增网卡</button>
      </div>
      <div class="nic-editor" id="template-nic-editor"></div>
      <p class="muted">每块网卡独立设置 bridge / VLAN / model / IPv4 / IPv6；IPv4 与 IPv6 地址池可以同时绑定在同一块网卡上。</p>
      <details>
        <summary>查看生成后的 nics_json</summary>
        <textarea id="template-nics-preview" readonly></textarea>
      </details>

      <label><input class="inline" type="checkbox" name="cloud_init_enabled" value="1"> 启用 cloud-init</label>
      <p class="muted">静态 IP / IP 池模式依赖 cloud-init；纯 DHCP / IPv6 auto 可不依赖。</p>
      <label>cloud-init 用户</label>
      <input type="text" name="cloud_init_user" value="ubuntu">
      <label>cloud-init 密码</label>
      <input type="text" name="cloud_init_password" placeholder="可选">
      <label>SSH 公钥</label>
      <textarea name="cloud_init_ssh_key" placeholder="ssh-ed25519 ..."></textarea>
      <button class="btn" type="submit">创建模板</button>
    </form>
  </section>
  <section class="card span-4">
    <h2>创建虚拟机</h2>
    <form action="/vms" method="post" id="vm-form">
      <?= csrf_field() ?>
      <input type="hidden" name="vm_nics_json" id="vm-nics-json" value="">
      <label>虚拟机名称</label>
      <input type="text" name="name" required placeholder="vm-demo-01">
      <label>模板</label>
      <select name="template_id" id="vm-template-id" required>
        <option value="">请选择模板</option>
        <?php foreach ($templates as $template): ?>
          <option value="<?= (int) $template['id'] ?>"><?= e((string) $template['name']) ?> / <?= e((string) ($template['image_name'] ?? 'unknown')) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="vm-template-nics" id="vm-template-nic-summary">
        <div class="muted">默认会继承模板里的网卡拓扑。</div>
      </div>
      <label><input class="inline" type="checkbox" id="vm-custom-nics-enabled" value="1"> 创建时自定义网卡（可选）</label>
      <div id="vm-custom-nics-wrap" class="hidden-block">
        <div class="editor-header">
          <label>自定义网卡</label>
          <button class="btn secondary js-add-nic" type="button" data-editor="vm">新增网卡</button>
        </div>
        <div class="nic-editor" id="vm-nic-editor"></div>
        <details>
          <summary>查看生成后的 vm_nics_json</summary>
          <textarea id="vm-nics-preview" readonly></textarea>
        </details>
      </div>
      <label><input class="inline" type="checkbox" name="autostart" value="1"> 创建后立即启动</label>
      <label>到期时间</label>
      <input type="datetime-local" name="expires_at">
      <p class="muted">默认继承模板网卡；如果勾选“自定义网卡”，会按当前表单覆写模板网络配置。</p>
      <div class="spacer"></div>
      <button class="btn success" type="submit">创建虚拟机</button>
    </form>
  </section>
  <section class="card span-6">
    <h2>镜像列表</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>大小</th><th>路径</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($images as $image): ?>
          <tr>
            <td><?= (int) $image['id'] ?></td>
            <td><?= e((string) $image['name']) ?></td>
            <td><?= e((string) $image['extension']) ?></td>
            <td><?= number_format(((int) $image['size_bytes']) / 1024 / 1024, 2) ?> MB</td>
            <td><code><?= e((string) $image['path']) ?></code></td>
            <td>
              <?php if (auth_can_write()): ?>
                <div class="actions">
                  <form action="/images/convert" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
                    <select name="target_extension">
                      <option value="qcow2">qcow2</option>
                      <option value="raw">raw</option>
                      <option value="img">img</option>
                    </select>
                    <button class="btn secondary" type="submit">转换</button>
                  </form>
                  <form action="/images/delete" method="post" onsubmit="return confirm('确认删除该镜像？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
                    <button class="btn danger" type="submit">删除</button>
                  </form>
                </div>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$images): ?><tr><td colspan="5" class="muted">暂无镜像</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-6">
    <h2>模板列表</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>模板</th><th>镜像</th><th>规格</th><th>cloud-init</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($templates as $template): ?>
          <tr>
            <td><?= (int) $template['id'] ?></td>
            <td><?= e((string) $template['name']) ?></td>
            <td><?= e((string) ($template['image_name'] ?? 'unknown')) ?></td>
            <?php $templateDisks = json_decode((string) ($template['disks_json'] ?? '[]'), true) ?: []; ?>
            <?php $templateNics = $template['normalized_nics'] ?? []; ?>
            <td>
              <?= (int) $template['cpu'] ?> vCPU / <?= (int) $template['memory_mb'] ?> MB / <?= (int) $template['disk_size_gb'] ?> GB<br>
              <span class="muted"><?= e((string) ($template['virtualization_mode'] ?? 'kvm')) ?> / <?= e((string) ($template['machine_type'] ?? 'pc')) ?> / <?= e((string) ($template['firmware_type'] ?? 'bios')) ?> / 磁盘 <?= count($templateDisks) ?> / 网卡 <?= count($templateNics) ?></span>
              <?php if ($templateNics): ?>
                <div class="muted nic-summary-list">
                  <?php foreach ($templateNics as $index => $nic): ?>
                    <div>net<?= (int) $index ?>: <?= e($nicSummary($nic)) ?></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <td><?= (int) ($template['cloud_init_enabled'] ?? 0) === 1 ? '启用' : '关闭' ?></td>
            <td>
              <form action="/templates/delete" method="post" onsubmit="return confirm('确认删除该模板？');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                <button class="btn danger" type="submit">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$templates): ?><tr><td colspan="5" class="muted">暂无模板</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-12">
    <h2>虚拟机列表</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>模板</th><th>状态</th><th>规格</th><th>VNC/noVNC</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($vms as $vm): ?>
          <?php $status = strtolower((string) ($vm['status'] ?? 'unknown')); ?>
          <tr>
            <td>
              <strong><a href="/vm?id=<?= (int) $vm['id'] ?>"><?= e((string) $vm['name']) ?></a></strong><br>
              <span class="muted">IP: <?= e((string) ($vm['ip_address'] ?: '-')) ?></span>
              <?php if (!empty($vm['normalized_nics'])): ?>
                <div class="muted nic-summary-list">
                  <?php foreach (($vm['normalized_nics'] ?? []) as $index => $nic): ?>
                    <div>net<?= (int) $index ?>: <?= e($nicSummary($nic)) ?></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <td><?= e((string) ($vm['template_name'] ?? 'unknown')) ?></td>
            <td><span class="badge <?= str_contains($status, 'running') ? 'running' : (str_contains($status, 'shut') ? 'shut' : 'unknown') ?>"><?= e($status) ?></span></td>
            <td><?= (int) $vm['cpu'] ?> vCPU / <?= (int) $vm['memory_mb'] ?> MB / <?= (int) $vm['disk_size_gb'] ?> GB</td>
            <td>
              <div><?= e((string) ($vm['vnc_display'] ?: '-')) ?></div>
              <div class="muted">代理状态：<?= !empty($noVncStatus[$vm['name']]['running']) ? '运行中' : '未运行' ?></div>
              <?php if (!empty($vm['vnc_display']) && auth_can_write()): ?>
                <a class="btn secondary" target="_blank" href="/novnc/open?id=<?= (int) $vm['id'] ?>">打开 noVNC</a>
              <?php endif; ?>
              <?php if (auth_can_write()): ?>
                <div class="actions">
                  <form action="/novnc/start" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vm_name" value="<?= e((string) $vm['name']) ?>">
                    <input type="hidden" name="port" value="6080">
                    <button class="btn secondary" type="submit">启动代理</button>
                  </form>
                  <form action="/novnc/stop" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vm_name" value="<?= e((string) $vm['name']) ?>">
                    <button class="btn secondary" type="submit">停止代理</button>
                  </form>
                </div>
              <?php endif; ?>
              <div class="muted">代理命令：<code><?= e((new \Nbkvm\Services\NoVncService())->helperCommand((string) $vm['name'])) ?></code></div>
            </td>
            <td>
              <div class="actions">
                <?php if (auth_can_write()): ?>
                <form action="/vms/start" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn success" type="submit">启动</button></form>
                <form action="/vms/shutdown" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn warn" type="submit">关机</button></form>
                <form action="/vms/destroy" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn danger" type="submit">强停</button></form>
                <form action="/snapshots" method="post"><?= csrf_field() ?><input type="hidden" name="vm_id" value="<?= (int) $vm['id'] ?>"><input type="text" name="name" placeholder="snapshot-1" style="width:140px"><button class="btn secondary" type="submit">快照</button></form>
                <form action="/vms/delete" method="post" onsubmit="return confirm('确认删除虚拟机定义？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $vm['id'] ?>">
                  <label class="muted"><input type="checkbox" name="remove_storage" value="1"> 同时删磁盘</label>
                  <button class="btn secondary" type="submit">删除</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$vms): ?><tr><td colspan="6" class="muted">暂无虚拟机</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-6">
    <h2>快照记录</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>虚拟机</th><th>快照名</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($snapshots as $snapshot): ?>
            <tr>
              <td><?= (int) $snapshot['id'] ?></td>
              <td><?= e((string) ($snapshot['vm_name'] ?? '-')) ?></td>
              <td><?= e((string) $snapshot['name']) ?></td>
              <td><?= e((string) $snapshot['status']) ?></td>
              <td><?= e((string) $snapshot['created_at']) ?></td>
              <td>
                <div class="actions">
                  <?php if (auth_can_write()): ?>
                  <form action="/snapshots/revert" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vm_name" value="<?= e((string) ($snapshot['vm_name'] ?? '')) ?>">
                    <input type="hidden" name="snapshot_name" value="<?= e((string) $snapshot['name']) ?>">
                    <button class="btn warn" type="submit">回滚</button>
                  </form>
                  <form action="/snapshots/delete" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vm_name" value="<?= e((string) ($snapshot['vm_name'] ?? '')) ?>">
                    <input type="hidden" name="snapshot_name" value="<?= e((string) $snapshot['name']) ?>">
                    <button class="btn secondary" type="submit">删除</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$snapshots): ?><tr><td colspan="6" class="muted">暂无快照</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-6">
    <h2>任务历史</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>时间</th><th>任务</th><th>目标</th><th>状态</th><th>输出</th></tr></thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
            <tr>
              <td><?= e((string) $job['created_at']) ?></td>
              <td><?= e((string) $job['name']) ?></td>
              <td><?= e((string) (($job['target_type'] ?: '-') . ' / ' . ($job['target_name'] ?: '-'))) ?></td>
              <td><?= e((string) $job['status']) ?></td>
              <td><?= e((string) ($job['output'] ?: '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$jobs): ?><tr><td colspan="5" class="muted">暂无任务</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-6">
    <h2>审计日志</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>时间</th><th>用户</th><th>动作</th><th>目标</th></tr></thead>
        <tbody>
          <?php foreach ($auditLogs as $log): ?>
            <tr>
              <td><?= e((string) $log['created_at']) ?></td>
              <td><?= e((string) ($log['username'] ?: '-')) ?></td>
              <td><?= e((string) $log['action']) ?></td>
              <td><?= e((string) (($log['target_type'] ?: '-') . ' / ' . ($log['target_name'] ?: '-'))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$auditLogs): ?><tr><td colspan="4" class="muted">暂无日志</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<script>
(() => {
  const data = <?= json_encode($dashboardJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const networks = Array.isArray(data.networks) ? data.networks : [];
  const pools = Array.isArray(data.pools) ? data.pools : [];
  const templateMap = Object.fromEntries((Array.isArray(data.templates) ? data.templates : []).map((tpl) => [String(tpl.id), tpl]));
  const modelOptions = ['virtio', 'e1000', 'rtl8139', 'vmxnet3'];

  const preferredNetwork = () => networks.find((item) => item.name === 'default') || networks[0] || {id: '', name: 'default', bridge_name: 'vmbr0'};
  const findNetwork = (id) => networks.find((item) => String(item.id) === String(id)) || preferredNetwork();
  const poolsFor = (networkId, family) => pools.filter((pool) => String(pool.network_id) === String(networkId) && String(pool.family) === String(family));

  const defaultNic = (network) => ({
    network_id: network?.id ?? '',
    model: 'virtio',
    vlan_tag: '',
    mac: '',
    firewall: false,
    link_down: false,
    ipv4_mode: 'dhcp',
    ipv4_pool_id: '',
    ipv4_address: '',
    ipv4_prefix_length: '',
    ipv4_gateway: '',
    ipv4_dns_servers: '',
    ipv6_mode: 'none',
    ipv6_pool_id: '',
    ipv6_address: '',
    ipv6_prefix_length: '',
    ipv6_gateway: '',
    ipv6_dns_servers: '',
  });

  const normalizeNic = (nic = {}) => ({...defaultNic(findNetwork(nic.network_id || preferredNetwork().id)), ...nic});
  const networkOptionsHtml = (selectedId) => ['<option value="">请选择网络</option>']
    .concat(networks.map((network) => `<option value="${network.id}" ${String(network.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(network.name)} / ${escapeHtml(network.bridge_name || '-')}</option>`))
    .join('');
  const modelOptionsHtml = (selected) => modelOptions.map((model) => `<option value="${model}" ${String(model) === String(selected) ? 'selected' : ''}>${model}</option>`).join('');
  const modeOptionsHtml = (options, selected) => options.map((value) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${value}</option>`).join('');
  const poolOptionsHtml = (networkId, family, selectedId) => ['<option value="">不使用</option>']
    .concat(poolsFor(networkId, family).map((pool) => `<option value="${pool.id}" ${String(pool.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(pool.name)} / ${escapeHtml(pool.start_ip)} - ${escapeHtml(pool.end_ip)}</option>`))
    .join('');

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));
  }

  function renderNicCard(nic, index) {
    const network = findNetwork(nic.network_id || preferredNetwork().id);
    return `
      <div class="nic-card" data-index="${index}">
        <div class="editor-header">
          <strong>net${index}</strong>
          <button class="btn danger js-remove-nic" type="button">删除</button>
        </div>
        <div class="row-3">
          <div>
            <label>网络 / Bridge</label>
            <select data-field="network_id">${networkOptionsHtml(nic.network_id || network.id)}</select>
            <div class="muted nic-network-hint">${escapeHtml((network.bridge_name || '-') + ' / ' + ((Number(network.libvirt_managed) === 1) ? 'managed' : 'bridge'))}</div>
          </div>
          <div><label>model</label><select data-field="model">${modelOptionsHtml(nic.model || 'virtio')}</select></div>
          <div><label>VLAN Tag</label><input type="number" min="1" max="4094" data-field="vlan_tag" value="${escapeHtml(nic.vlan_tag || '')}" placeholder="留空表示不打 tag"></div>
        </div>
        <div class="row-3">
          <div><label>MAC</label><input type="text" data-field="mac" value="${escapeHtml(nic.mac || '')}" placeholder="52:54:00:12:34:56"></div>
          <div><label><input class="inline" type="checkbox" data-field="firewall" ${nic.firewall ? 'checked' : ''}> firewall</label></div>
          <div><label><input class="inline" type="checkbox" data-field="link_down" ${nic.link_down ? 'checked' : ''}> link_down</label></div>
        </div>
        <div class="row-3">
          <div><label>IPv4 模式</label><select data-field="ipv4_mode">${modeOptionsHtml(['dhcp', 'static', 'pool', 'none'], nic.ipv4_mode || 'dhcp')}</select></div>
          <div><label>IPv4 池</label><select data-field="ipv4_pool_id">${poolOptionsHtml(nic.network_id || network.id, 'ipv4', nic.ipv4_pool_id)}</select></div>
          <div><label>IPv4 地址</label><input type="text" data-field="ipv4_address" value="${escapeHtml(nic.ipv4_address || '')}" placeholder="10.0.10.20"></div>
        </div>
        <div class="row-3">
          <div><label>IPv4 前缀</label><input type="number" min="1" max="32" data-field="ipv4_prefix_length" value="${escapeHtml(nic.ipv4_prefix_length || '')}" placeholder="24"></div>
          <div><label>IPv4 网关</label><input type="text" data-field="ipv4_gateway" value="${escapeHtml(nic.ipv4_gateway || '')}" placeholder="10.0.10.1"></div>
          <div><label>IPv4 DNS</label><input type="text" data-field="ipv4_dns_servers" value="${escapeHtml(nic.ipv4_dns_servers || '')}" placeholder="1.1.1.1,8.8.8.8"></div>
        </div>
        <div class="row-3">
          <div><label>IPv6 模式</label><select data-field="ipv6_mode">${modeOptionsHtml(['auto', 'static', 'pool', 'none'], nic.ipv6_mode || 'none')}</select></div>
          <div><label>IPv6 池</label><select data-field="ipv6_pool_id">${poolOptionsHtml(nic.network_id || network.id, 'ipv6', nic.ipv6_pool_id)}</select></div>
          <div><label>IPv6 地址</label><input type="text" data-field="ipv6_address" value="${escapeHtml(nic.ipv6_address || '')}" placeholder="fd00:10::20"></div>
        </div>
        <div class="row-3">
          <div><label>IPv6 前缀</label><input type="number" min="1" max="128" data-field="ipv6_prefix_length" value="${escapeHtml(nic.ipv6_prefix_length || '')}" placeholder="64"></div>
          <div><label>IPv6 网关</label><input type="text" data-field="ipv6_gateway" value="${escapeHtml(nic.ipv6_gateway || '')}" placeholder="fd00:10::1"></div>
          <div><label>IPv6 DNS</label><input type="text" data-field="ipv6_dns_servers" value="${escapeHtml(nic.ipv6_dns_servers || '')}" placeholder="2606:4700:4700::1111"></div>
        </div>
      </div>`;
  }

  function collectEditorNics(container) {
    return Array.from(container.querySelectorAll('.nic-card')).map((card) => ({
      network_id: card.querySelector('[data-field="network_id"]').value || '',
      model: card.querySelector('[data-field="model"]').value || 'virtio',
      vlan_tag: card.querySelector('[data-field="vlan_tag"]').value || '',
      mac: card.querySelector('[data-field="mac"]').value.trim(),
      firewall: card.querySelector('[data-field="firewall"]').checked,
      link_down: card.querySelector('[data-field="link_down"]').checked,
      ipv4_mode: card.querySelector('[data-field="ipv4_mode"]').value || 'dhcp',
      ipv4_pool_id: card.querySelector('[data-field="ipv4_pool_id"]').value || '',
      ipv4_address: card.querySelector('[data-field="ipv4_address"]').value.trim(),
      ipv4_prefix_length: card.querySelector('[data-field="ipv4_prefix_length"]').value || '',
      ipv4_gateway: card.querySelector('[data-field="ipv4_gateway"]').value.trim(),
      ipv4_dns_servers: card.querySelector('[data-field="ipv4_dns_servers"]').value.trim(),
      ipv6_mode: card.querySelector('[data-field="ipv6_mode"]').value || 'none',
      ipv6_pool_id: card.querySelector('[data-field="ipv6_pool_id"]').value || '',
      ipv6_address: card.querySelector('[data-field="ipv6_address"]').value.trim(),
      ipv6_prefix_length: card.querySelector('[data-field="ipv6_prefix_length"]').value || '',
      ipv6_gateway: card.querySelector('[data-field="ipv6_gateway"]').value.trim(),
      ipv6_dns_servers: card.querySelector('[data-field="ipv6_dns_servers"]').value.trim(),
    }));
  }

  function refreshCardNetwork(card) {
    const network = findNetwork(card.querySelector('[data-field="network_id"]').value || preferredNetwork().id);
    card.querySelector('.nic-network-hint').textContent = `${network.bridge_name || '-'} / ${Number(network.libvirt_managed) === 1 ? 'managed' : 'bridge'}`;
    card.querySelector('[data-field="ipv4_pool_id"]').innerHTML = poolOptionsHtml(network.id, 'ipv4', card.querySelector('[data-field="ipv4_pool_id"]').value);
    card.querySelector('[data-field="ipv6_pool_id"]').innerHTML = poolOptionsHtml(network.id, 'ipv6', card.querySelector('[data-field="ipv6_pool_id"]').value);
  }

  function reindexEditor(container) {
    Array.from(container.querySelectorAll('.nic-card')).forEach((card, index) => {
      card.dataset.index = index;
      const title = card.querySelector('.editor-header strong');
      if (title) title.textContent = `net${index}`;
    });
  }

  function attachCardHandlers(container, hiddenInput, preview, options, card) {
    const ensurePreview = () => {
      const nics = collectEditorNics(container);
      hiddenInput.value = JSON.stringify(nics);
      if (preview) preview.value = JSON.stringify(nics, null, 2);
      if (options.onChange) options.onChange(nics);
    };
    card.querySelectorAll('input,select').forEach((node) => {
      node.addEventListener('change', () => {
        if (node.dataset.field === 'network_id') refreshCardNetwork(card);
        ensurePreview();
      });
      node.addEventListener('input', ensurePreview);
    });
    card.querySelector('.js-remove-nic').addEventListener('click', () => {
      card.remove();
      if (!container.querySelector('.nic-card')) {
        addNic(container, hiddenInput, preview, defaultNic(preferredNetwork()), options);
        return;
      }
      reindexEditor(container);
      ensurePreview();
    });
    refreshCardNetwork(card);
  }

  function addNic(container, hiddenInput, preview, nic, options = {}) {
    const nextIndex = container.querySelectorAll('.nic-card').length;
    container.insertAdjacentHTML('beforeend', renderNicCard(normalizeNic(nic), nextIndex));
    attachCardHandlers(container, hiddenInput, preview, options, container.lastElementChild);
    container._ensurePreview();
  }

  function renderEditor(container, hiddenInput, preview, nics, options = {}) {
    const list = Array.isArray(nics) && nics.length ? nics.map(normalizeNic) : [defaultNic(preferredNetwork())];
    container.innerHTML = list.map((nic, index) => renderNicCard(nic, index)).join('');
    container._ensurePreview = () => {
      const current = collectEditorNics(container);
      hiddenInput.value = JSON.stringify(current);
      if (preview) preview.value = JSON.stringify(current, null, 2);
      if (options.onChange) options.onChange(current);
    };
    Array.from(container.querySelectorAll('.nic-card')).forEach((card) => attachCardHandlers(container, hiddenInput, preview, options, card));
    container._ensurePreview();
  }

  function initNetworkForm() {
    const reset = () => {
      document.getElementById('network-id').value = '';
      document.getElementById('network-name').value = 'default';
      document.getElementById('network-bridge').value = 'vmbr0';
      document.getElementById('network-cidr').value = '192.168.122.0/24';
      document.getElementById('network-gateway').value = '192.168.122.1';
      document.getElementById('network-dhcp-start').value = '192.168.122.2';
      document.getElementById('network-dhcp-end').value = '192.168.122.254';
      document.getElementById('network-ipv6-cidr').value = '';
      document.getElementById('network-ipv6-gateway').value = '';
      document.getElementById('network-libvirt-managed').value = '0';
    };
    document.getElementById('network-form-reset')?.addEventListener('click', reset);
    document.querySelectorAll('.js-edit-network').forEach((button) => {
      button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.network || '{}');
        document.getElementById('network-id').value = item.id || '';
        document.getElementById('network-name').value = item.name || '';
        document.getElementById('network-bridge').value = item.bridge_name || '';
        document.getElementById('network-cidr').value = item.cidr || '';
        document.getElementById('network-gateway').value = item.gateway || '';
        document.getElementById('network-dhcp-start').value = item.dhcp_start || '';
        document.getElementById('network-dhcp-end').value = item.dhcp_end || '';
        document.getElementById('network-ipv6-cidr').value = item.ipv6_cidr || '';
        document.getElementById('network-ipv6-gateway').value = item.ipv6_gateway || '';
        document.getElementById('network-libvirt-managed').value = String(item.libvirt_managed || 0);
        document.getElementById('network-form').scrollIntoView({behavior: 'smooth', block: 'center'});
      });
    });
  }

  function initPoolForm() {
    const reset = () => {
      document.getElementById('pool-id').value = '';
      document.getElementById('pool-name').value = '';
      document.getElementById('pool-network-id').value = '';
      document.getElementById('pool-family').value = 'ipv4';
      document.getElementById('pool-dns').value = '1.1.1.1,8.8.8.8';
      document.getElementById('pool-start-ip').value = '192.168.122.100';
      document.getElementById('pool-end-ip').value = '192.168.122.150';
    };
    document.getElementById('pool-form-reset')?.addEventListener('click', reset);
    document.getElementById('pool-family')?.addEventListener('change', (event) => {
      const family = event.target.value;
      document.getElementById('pool-start-ip').placeholder = family === 'ipv6' ? 'fd00:10::100' : '10.0.10.100';
      document.getElementById('pool-end-ip').placeholder = family === 'ipv6' ? 'fd00:10::1ff' : '10.0.10.150';
    });
    document.querySelectorAll('.js-edit-pool').forEach((button) => {
      button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.pool || '{}');
        document.getElementById('pool-id').value = item.id || '';
        document.getElementById('pool-name').value = item.name || '';
        document.getElementById('pool-network-id').value = item.network_id || '';
        document.getElementById('pool-family').value = item.family || 'ipv4';
        document.getElementById('pool-dns').value = item.dns_servers || '';
        document.getElementById('pool-start-ip').value = item.start_ip || '';
        document.getElementById('pool-end-ip').value = item.end_ip || '';
        document.getElementById('pool-form').scrollIntoView({behavior: 'smooth', block: 'center'});
      });
    });
  }

  function renderTemplateSummary(templateId) {
    const target = document.getElementById('vm-template-nic-summary');
    const template = templateMap[String(templateId)];
    if (!target) return;
    if (!template || !Array.isArray(template.nics) || !template.nics.length) {
      target.innerHTML = '<div class="muted">默认会继承模板里的网卡拓扑。</div>';
      return;
    }
    target.innerHTML = template.nics.map((nic, index) => {
      const network = findNetwork(nic.network_id || preferredNetwork().id);
      return `<div class="muted">net${index}: ${escapeHtml(network.bridge_name || network.name || '-')} / ${escapeHtml(nic.model || 'virtio')} / IPv4 ${escapeHtml(nic.ipv4_mode || 'dhcp')} / IPv6 ${escapeHtml(nic.ipv6_mode || 'none')}</div>`;
    }).join('');
  }

  function initNicEditors() {
    const templateContainer = document.getElementById('template-nic-editor');
    const templateHidden = document.getElementById('template-nics-json');
    const templatePreview = document.getElementById('template-nics-preview');
    renderEditor(templateContainer, templateHidden, templatePreview, [defaultNic(preferredNetwork())], {
      onChange: (nics) => {
        const first = nics[0] || {};
        document.getElementById('template-fallback-network').value = String(findNetwork(first.network_id || preferredNetwork().id).name || 'default');
      },
    });

    const vmContainer = document.getElementById('vm-nic-editor');
    const vmHidden = document.getElementById('vm-nics-json');
    const vmPreview = document.getElementById('vm-nics-preview');
    renderEditor(vmContainer, vmHidden, vmPreview, [defaultNic(preferredNetwork())]);

    document.querySelectorAll('.js-add-nic').forEach((button) => {
      button.addEventListener('click', () => {
        const target = button.dataset.editor === 'vm' ? vmContainer : templateContainer;
        const hidden = button.dataset.editor === 'vm' ? vmHidden : templateHidden;
        const preview = button.dataset.editor === 'vm' ? vmPreview : templatePreview;
        addNic(target, hidden, preview, defaultNic(preferredNetwork()), button.dataset.editor === 'vm' ? {} : {
          onChange: (nics) => {
            const first = nics[0] || {};
            document.getElementById('template-fallback-network').value = String(findNetwork(first.network_id || preferredNetwork().id).name || 'default');
          },
        });
        reindexEditor(target);
      });
    });

    const templateSelect = document.getElementById('vm-template-id');
    const customToggle = document.getElementById('vm-custom-nics-enabled');
    const customWrap = document.getElementById('vm-custom-nics-wrap');
    const syncVmEditor = () => {
      renderTemplateSummary(templateSelect?.value || '');
      if (!customToggle?.checked) {
        vmHidden.value = '';
        customWrap?.classList.add('hidden-block');
        return;
      }
      const template = templateMap[String(templateSelect?.value || '')];
      const nics = Array.isArray(template?.nics) && template.nics.length ? template.nics : [defaultNic(preferredNetwork())];
      renderEditor(vmContainer, vmHidden, vmPreview, nics.map(normalizeNic));
      customWrap?.classList.remove('hidden-block');
    };
    templateSelect?.addEventListener('change', syncVmEditor);
    customToggle?.addEventListener('change', syncVmEditor);
    document.getElementById('vm-form')?.addEventListener('submit', () => {
      if (!customToggle?.checked) {
        vmHidden.value = '';
      } else {
        vmContainer._ensurePreview();
      }
    });
    document.getElementById('template-form')?.addEventListener('submit', () => templateContainer._ensurePreview());
    syncVmEditor();
  }

  initNetworkForm();
  initPoolForm();
  initNicEditors();
})();
</script>
