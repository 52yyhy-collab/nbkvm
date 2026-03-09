<div class="grid dashboard-grid">
  <section class="card span-4">
    <h3>创建虚拟机</h3>
    <form action="/vms" method="post" id="vm-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
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
        <div class="muted">默认继承模板里的 net0 / net1 / ipconfig0 / ipconfig1 拓扑。</div>
      </div>
      <label><input class="inline" type="checkbox" id="vm-custom-nics-enabled" value="1"> 创建时自定义 netX / ipconfigX（可选）</label>
      <div id="vm-custom-nics-wrap" class="hidden-block">
        <div class="section-split">
          <label>自定义 netX / ipconfigX</label>
          <button class="btn secondary js-add-nic" type="button" data-editor="vm">新增 netX</button>
        </div>
        <div class="nic-editor" id="vm-nic-editor"></div>
        <details>
          <summary>查看生成后的 vm_nics_json</summary>
          <textarea id="vm-nics-preview" readonly></textarea>
        </details>
      </div>
      <details class="top-gap">
        <summary>Cloud-Init 覆盖（可选，高级）</summary>
        <p class="muted">用于创建时覆盖模板默认 cloud-init：可改密码、注入 SSH Key、设主机名、DNS、附加高级 user-data 片段。</p>
        <div class="row-2">
          <div>
            <label>用户名覆盖</label>
            <input type="text" name="cloud_init_user_override" id="vm-cloud-init-user-override" placeholder="留空=继承模板">
          </div>
          <div>
            <label>密码覆盖（重设）</label>
            <input type="text" name="cloud_init_password_override" id="vm-cloud-init-password-override" placeholder="留空=继承模板">
          </div>
        </div>
        <label>SSH Key 覆盖</label>
        <textarea name="cloud_init_ssh_key_override" id="vm-cloud-init-ssh-key-override" placeholder="一行一个 key"></textarea>
        <div class="row-3">
          <div><label>Hostname 覆盖</label><input type="text" name="cloud_init_hostname_override" id="vm-cloud-init-hostname-override" placeholder="留空=继承模板"></div>
          <div><label>DNS 覆盖</label><input type="text" name="cloud_init_dns_override" id="vm-cloud-init-dns-override" placeholder="1.1.1.1,8.8.8.8"></div>
          <div><label>Search Domain 覆盖</label><input type="text" name="cloud_init_search_domain_override" id="vm-cloud-init-search-domain-override" placeholder="example.local"></div>
        </div>
        <label>高级 user-data 片段</label>
        <textarea name="cloud_init_extra_user_data_override" id="vm-cloud-init-extra-user-data-override" placeholder="可填 packages/runcmd/write_files 片段（无需 #cloud-config）"></textarea>
      </details>
      <label><input class="inline" type="checkbox" name="autostart" value="1"> 创建后立即启动</label>
      <label>到期时间</label>
      <input type="datetime-local" name="expires_at">
      <div class="spacer"></div>
      <button class="btn success" type="submit">创建虚拟机</button>
    </form>
  </section>

  <section class="card span-4">
    <div class="section-split">
      <div>
        <h3>编辑 VM（安全模式）</h3>
        <p class="muted">CPU / 内存 / 到期策略可改；netX / ipconfigX 仅在 VM 已关机时允许编辑。运行中的 VM 会只读展示当前网卡结构。</p>
      </div>
      <button class="btn secondary" type="button" id="vm-edit-reset">取消编辑</button>
    </div>
    <div id="vm-edit-state" class="inline-banner hidden-block"></div>
    <div id="vm-edit-locks" class="inline-banner warn hidden-block"></div>
    <form action="/vms/update" method="post" id="vm-edit-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <input type="hidden" name="id" id="vm-edit-id" value="">
      <input type="hidden" name="vm_nics_json" id="vm-edit-nics-json" value="[]">
      <label>虚拟机</label>
      <input type="text" id="vm-edit-name" readonly placeholder="请从右侧列表点“编辑配置”">
      <label>模板</label>
      <input type="text" id="vm-edit-template" readonly>
      <div class="row-3">
        <div><label>Sockets</label><input type="number" min="1" name="cpu_sockets" id="vm-edit-cpu-sockets" value="1"></div>
        <div><label>Cores</label><input type="number" min="1" name="cpu_cores" id="vm-edit-cpu-cores" value="1"></div>
        <div><label>Threads</label><input type="number" min="1" name="cpu_threads" id="vm-edit-cpu-threads" value="1"></div>
      </div>
      <label>内存(MB)</label>
      <input type="number" min="256" name="memory_mb" id="vm-edit-memory-mb" value="2048">
      <label>到期时间</label>
      <input type="datetime-local" name="expires_at" id="vm-edit-expires-at">
      <label>到期后保留天数</label>
      <input type="number" min="0" name="expire_grace_days" id="vm-edit-expire-grace-days" value="3">

      <div class="section-split top-gap">
        <label>netX / ipconfigX</label>
        <button class="btn secondary js-add-nic" type="button" data-editor="vm-edit">新增 netX</button>
      </div>
      <div class="nic-editor" id="vm-edit-nic-editor"></div>
      <details>
        <summary>查看生成后的 vm_nics_json</summary>
        <textarea id="vm-edit-nics-preview" readonly></textarea>
      </details>

      <details class="top-gap" open>
        <summary>Cloud-Init 覆盖（密码重设 / 高级操作）</summary>
        <p class="muted">保存后会重生成 cloud-init seed ISO 并重新 define 域配置；参数通常在下次重启实例时生效。</p>
        <div class="row-2">
          <div>
            <label>用户名覆盖</label>
            <input type="text" name="cloud_init_user_override" id="vm-edit-cloud-init-user-override" placeholder="留空=继承模板">
          </div>
          <div>
            <label>密码覆盖（重设）</label>
            <input type="text" name="cloud_init_password_override" id="vm-edit-cloud-init-password-override" placeholder="留空=继承模板">
          </div>
        </div>
        <label>SSH Key 覆盖</label>
        <textarea name="cloud_init_ssh_key_override" id="vm-edit-cloud-init-ssh-key-override" placeholder="一行一个 key"></textarea>
        <div class="row-3">
          <div><label>Hostname 覆盖</label><input type="text" name="cloud_init_hostname_override" id="vm-edit-cloud-init-hostname-override" placeholder="留空=继承模板"></div>
          <div><label>DNS 覆盖</label><input type="text" name="cloud_init_dns_override" id="vm-edit-cloud-init-dns-override" placeholder="1.1.1.1,8.8.8.8"></div>
          <div><label>Search Domain 覆盖</label><input type="text" name="cloud_init_search_domain_override" id="vm-edit-cloud-init-search-domain-override" placeholder="example.local"></div>
        </div>
        <label>高级 user-data 片段</label>
        <textarea name="cloud_init_extra_user_data_override" id="vm-edit-cloud-init-extra-user-data-override" placeholder="可填 packages/runcmd/write_files 片段（无需 #cloud-config）"></textarea>
      </details>

      <div class="spacer"></div>
      <button class="btn" type="submit">保存配置</button>
    </form>
  </section>

  <section class="card span-4">
    <h3>控制台能力</h3>
    <p class="muted">noVNC 负责图形控制台；Xterm 控制台走 serial / virsh console 风格后端。</p>
    <div class="resource-stack">
      <div class="muted">- noVNC：现有代理保留</div>
      <div class="muted">- Xterm：新增 /console/open 入口，后端基于 tmux + virsh console 封装</div>
      <div class="muted">- 若宿主缺少 tmux/virsh，页面会给出能力探测提示并保留占位</div>
    </div>
  </section>

  <section class="card span-12">
    <div class="section-split">
      <div>
        <h3>虚拟机列表</h3>
        <p class="muted">支持创建 / 更新 / 启停 / 删除 / 快照 / noVNC / Xterm 控制台。</p>
      </div>
      <span class="muted">共 <?= count($vms) ?> 台</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>模板</th><th>状态</th><th>规格</th><th>网络 / 磁盘</th><th>控制台</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($vms as $vm): ?>
          <?php $status = strtolower((string) ($vm['status'] ?? 'unknown')); ?>
          <?php $console = $consoleCapabilities[$vm['name']] ?? ['tmux_available' => false, 'virsh_available' => false, 'serial_configured' => false, 'running' => false, 'hint' => '']; ?>
          <tr>
            <td>
              <strong><a href="/vm?id=<?= (int) $vm['id'] ?>"><?= e((string) $vm['name']) ?></a></strong><br>
              <span class="muted">IP: <?= e((string) (($vm['ip_address'] ?? '') ?: '-')) ?></span>
            </td>
            <td><?= e((string) ($vm['template_name'] ?? 'unknown')) ?></td>
            <td><span class="badge <?= str_contains($status, 'running') ? 'running' : (str_contains($status, 'shut') ? 'shut' : 'unknown') ?>"><?= e($status) ?></span></td>
            <td>
              <?= (int) $vm['cpu'] ?> vCPU / <?= (int) $vm['memory_mb'] ?> MB<br>
              <span class="muted"><?= (int) ($vm['cpu_sockets'] ?? 1) ?>S / <?= (int) ($vm['cpu_cores'] ?? 1) ?>C / <?= (int) ($vm['cpu_threads'] ?? 1) ?>T</span>
            </td>
            <td>
              <div class="resource-stack">
                <?php foreach (($vm['normalized_nics'] ?? []) as $index => $nic): ?>
                  <div class="muted">net<?= (int) $index ?>: <?= e($nicSummary($nic)) ?></div>
                <?php endforeach; ?>
              </div>
              <div class="resource-stack top-gap-mini">
                <?php foreach (($vm['normalized_disks'] ?? []) as $disk): ?>
                  <div class="muted"><?= e($diskSummary($disk)) ?></div>
                <?php endforeach; ?>
              </div>
            </td>
            <td>
              <div><?= e((string) (($vm['vnc_display'] ?? '') ?: '-')) ?></div>
              <div class="muted">noVNC：<?= !empty($noVncStatus[$vm['name']]['running']) ? '运行中' : '未运行' ?></div>
              <div class="muted">Xterm：<?= !empty($console['tmux_available']) && !empty($console['virsh_available']) ? (!empty($console['serial_configured']) ? '可用' : '待补 serial') : '宿主能力不足' ?></div>
              <div class="muted"><?= e((string) ($console['hint'] ?? '')) ?></div>
              <div class="actions top-gap-mini">
                <?php if (!empty($vm['vnc_display']) && auth_can_write()): ?>
                  <a class="btn secondary" target="_blank" href="/novnc/open?id=<?= (int) $vm['id'] ?>">noVNC</a>
                <?php endif; ?>
                <?php if (auth_can_write()): ?>
                  <a class="btn secondary" target="_blank" href="/console/open?id=<?= (int) $vm['id'] ?>">Xterm 控制台</a>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="actions vertical-actions">
                <button class="btn secondary js-edit-vm" type="button" data-vm='<?= e((string) json_encode([
                  'id' => (int) $vm['id'],
                  'name' => (string) $vm['name'],
                  'template_name' => (string) ($vm['template_name'] ?? 'unknown'),
                  'status' => (string) ($vm['status'] ?? 'unknown'),
                  'cpu_sockets' => (int) ($vm['cpu_sockets'] ?? 1),
                  'cpu_cores' => (int) ($vm['cpu_cores'] ?? 1),
                  'cpu_threads' => (int) ($vm['cpu_threads'] ?? 1),
                  'memory_mb' => (int) ($vm['memory_mb'] ?? 2048),
                  'expires_at' => (string) ($vm['expires_at'] ?? ''),
                  'expire_grace_days' => (int) ($vm['expire_grace_days'] ?? 3),
                  'cloud_init_user_override' => (string) ($vm['cloud_init_user_override'] ?? ''),
                  'cloud_init_password_override' => (string) ($vm['cloud_init_password_override'] ?? ''),
                  'cloud_init_ssh_key_override' => (string) ($vm['cloud_init_ssh_key_override'] ?? ''),
                  'cloud_init_hostname_override' => (string) ($vm['cloud_init_hostname_override'] ?? ''),
                  'cloud_init_dns_override' => (string) ($vm['cloud_init_dns_override'] ?? ''),
                  'cloud_init_search_domain_override' => (string) ($vm['cloud_init_search_domain_override'] ?? ''),
                  'cloud_init_extra_user_data_override' => (string) ($vm['cloud_init_extra_user_data_override'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>编辑配置</button>
                <?php if (auth_can_write()): ?>
                <form action="/vms/start" method="post"><?= csrf_field() ?><input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>"><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn success" type="submit">启动</button></form>
                <form action="/vms/shutdown" method="post"><?= csrf_field() ?><input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>"><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn warn" type="submit">关机</button></form>
                <form action="/vms/destroy" method="post"><?= csrf_field() ?><input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>"><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn danger" type="submit">强停</button></form>
                <form action="/novnc/start" method="post"><?= csrf_field() ?><input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>"><input type="hidden" name="vm_name" value="<?= e((string) $vm['name']) ?>"><input type="hidden" name="port" value="6080"><button class="btn secondary" type="submit">启动 noVNC 代理</button></form>
                <form action="/novnc/stop" method="post"><?= csrf_field() ?><input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>"><input type="hidden" name="vm_name" value="<?= e((string) $vm['name']) ?>"><button class="btn secondary" type="submit">停止 noVNC 代理</button></form>
                <form action="/snapshots" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                  <input type="hidden" name="vm_id" value="<?= (int) $vm['id'] ?>">
                  <input type="text" name="name" placeholder="snapshot-1" style="width:140px">
                  <button class="btn secondary" type="submit">快照</button>
                </form>
                <form action="/vms/delete" method="post" onsubmit="return confirm('确认删除虚拟机定义？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                  <input type="hidden" name="id" value="<?= (int) $vm['id'] ?>">
                  <label class="muted"><input type="checkbox" name="remove_storage" value="1"> 同时删磁盘</label>
                  <button class="btn danger" type="submit">删除</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$vms): ?><tr><td colspan="7" class="muted">暂无虚拟机</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card span-12">
    <h3>快照记录</h3>
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
                <div class="actions vertical-actions">
                  <?php if (auth_can_write()): ?>
                  <form action="/snapshots/revert" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="vm_name" value="<?= e((string) ($snapshot['vm_name'] ?? '')) ?>">
                    <input type="hidden" name="snapshot_name" value="<?= e((string) $snapshot['name']) ?>">
                    <button class="btn warn" type="submit">回滚</button>
                  </form>
                  <form action="/snapshots/delete" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
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
</div>
