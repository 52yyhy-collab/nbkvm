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
    <form action="/networks" method="post">
      <?= csrf_field() ?>
      <label>网络名称</label><input type="text" name="name" value="default" required>
      <label>CIDR</label><input type="text" name="cidr" value="192.168.122.0/24" required>
      <label>网关</label><input type="text" name="gateway" value="192.168.122.1" required>
      <label>Bridge</label><input type="text" name="bridge_name" value="virbr0">
      <div class="row">
        <div><label>DHCP 起始</label><input type="text" name="dhcp_start" value="192.168.122.2"></div>
        <div><label>DHCP 结束</label><input type="text" name="dhcp_end" value="192.168.122.254"></div>
      </div>
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">创建网络</button>
    </form>
    <div class="spacer"></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>CIDR</th><th>网关</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($networks as $network): ?>
            <tr>
              <td><?= e((string) $network['name']) ?></td>
              <td><?= e((string) $network['cidr']) ?></td>
              <td><?= e((string) $network['gateway']) ?></td>
              <td>
                <form action="/networks/delete" method="post" onsubmit="return confirm('确认删除该网络？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="name" value="<?= e((string) $network['name']) ?>">
                  <button class="btn danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$networks): ?><tr><td colspan="3" class="muted">暂无网络</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-4">
    <h2>IP 池管理</h2>
    <form action="/ip-pools" method="post">
      <?= csrf_field() ?>
      <label>IP 池名称</label>
      <input type="text" name="name" required placeholder="lan-pool-1">
      <label>绑定网络</label>
      <input type="text" name="network_name" value="default" required>
      <div class="row">
        <div><label>网关</label><input type="text" name="gateway" value="192.168.122.1" required></div>
        <div><label>前缀长度</label><input type="number" name="prefix_length" value="24" required></div>
      </div>
      <div class="row">
        <div><label>起始 IP</label><input type="text" name="start_ip" value="192.168.122.100" required></div>
        <div><label>结束 IP</label><input type="text" name="end_ip" value="192.168.122.150" required></div>
      </div>
      <label>DNS</label><input type="text" name="dns_servers" value="1.1.1.1,8.8.8.8">
      <label>网卡名</label><input type="text" name="interface_name" value="eth0">
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">创建 IP 池</button>
    </form>
    <div class="spacer"></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>网络</th><th>范围</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($ipPools as $pool): ?>
            <tr>
              <td><?= e((string) $pool['name']) ?></td>
              <td><?= e((string) $pool['network_name']) ?></td>
              <td><?= e((string) $pool['start_ip']) ?> - <?= e((string) $pool['end_ip']) ?></td>
              <td>
                <form action="/ip-pools/delete" method="post" onsubmit="return confirm('确认删除该 IP 池？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $pool['id'] ?>">
                  <button class="btn danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ipPools): ?><tr><td colspan="3" class="muted">暂无 IP 池</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card span-4">
    <h2>创建模板</h2>
    <form action="/templates" method="post">
      <?= csrf_field() ?>
      <label>模板名称</label>
      <input type="text" name="name" required placeholder="ubuntu-24-cloud">
      <label>基础镜像</label>
      <select name="image_id" required>
        <option value="">请选择镜像</option>
        <?php foreach ($images as $image): ?>
          <option value="<?= (int) $image['id'] ?>"><?= e((string) $image['name']) ?> (<?= e((string) $image['extension']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="row">
        <div>
          <label>CPU</label>
          <input type="number" name="cpu" min="1" value="2" required>
        </div>
        <div>
          <label>内存(MB)</label>
          <input type="number" name="memory_mb" min="256" value="2048" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>磁盘(GB)</label>
          <input type="number" name="disk_size_gb" min="5" value="20" required>
        </div>
        <div>
          <label>磁盘总线</label>
          <select name="disk_bus">
            <option value="virtio">virtio</option>
            <option value="sata">sata</option>
            <option value="scsi">scsi</option>
          </select>
        </div>
      </div>
      <label>网络</label>
      <input type="text" name="network_name" value="default" required>
      <label>OS Variant</label>
      <input type="text" name="os_variant" value="generic" required>
      <label>备注</label>
      <textarea name="notes" placeholder="比如：cloud image / 安装 ISO / 内网模板"></textarea>
      <label><input class="inline" type="checkbox" name="cloud_init_enabled" value="1"> 启用 cloud-init</label>
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
    <form action="/vms" method="post">
      <?= csrf_field() ?>
      <label>虚拟机名称</label>
      <input type="text" name="name" required placeholder="vm-demo-01">
      <label>模板</label>
      <select name="template_id" required>
        <option value="">请选择模板</option>
        <?php foreach ($templates as $template): ?>
          <option value="<?= (int) $template['id'] ?>"><?= e((string) $template['name']) ?> / <?= e((string) ($template['image_name'] ?? 'unknown')) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="row-3">
        <div>
          <label>CPU</label>
          <input type="number" name="cpu" min="1" value="2">
        </div>
        <div>
          <label>内存(MB)</label>
          <input type="number" name="memory_mb" min="256" value="2048">
        </div>
        <div>
          <label>磁盘(GB)</label>
          <input type="number" name="disk_size_gb" min="5" value="20">
        </div>
      </div>
      <label>网络</label>
      <select name="network_name">
        <?php foreach ($networks as $network): ?>
          <option value="<?= e((string) $network['name']) ?>"><?= e((string) $network['name']) ?> / <?= e((string) $network['cidr']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>IP 池</label>
      <select name="ip_pool_id">
        <option value="">不指定</option>
        <?php foreach ($ipPools as $pool): ?>
          <option value="<?= (int) $pool['id'] ?>"><?= e((string) $pool['name']) ?> / <?= e((string) $pool['network_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="muted">仅对启用 cloud-init 的非 ISO 模板自动下发静态 IP。</p>
      <label><input class="inline" type="checkbox" name="autostart" value="1"> 创建后立即启动</label>
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
            <td><?= (int) $template['cpu'] ?> vCPU / <?= (int) $template['memory_mb'] ?> MB / <?= (int) $template['disk_size_gb'] ?> GB</td>
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
            <td><strong><a href="/vm?id=<?= (int) $vm['id'] ?>"><?= e((string) $vm['name']) ?></a></strong><br><span class="muted">IP: <?= e((string) ($vm['ip_address'] ?: '-')) ?></span></td>
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
