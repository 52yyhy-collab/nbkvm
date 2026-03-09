<div class="grid dashboard-grid">
  <section class="card span-6">
    <div class="section-split">
      <div>
        <h3>模板编辑器（PVE 向导风格）</h3>
        <p class="muted">按 General / OS / System / Disks / CPU / Memory / Network / Cloud-Init 拆分，尽量贴近 PVE 新建虚拟机心智。</p>
      </div>
      <button class="btn secondary" type="button" id="template-form-reset">新建模板</button>
    </div>

    <div id="template-edit-state" class="inline-banner hidden-block"></div>
    <div id="template-edit-locks" class="inline-banner warn hidden-block"></div>

    <form action="/templates" method="post" id="template-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <input type="hidden" name="template_id" id="template-id" value="">
      <input type="hidden" name="network_name" id="template-fallback-network" value="default">
      <input type="hidden" name="nics_json" id="template-nics-json" value="[]">
      <input type="hidden" name="disks_json" id="template-disks-json" value="[]">

      <div class="module-grid single">
        <section class="module-card">
          <h4>General / 基本信息</h4>
          <div class="row-3">
            <div>
              <label>名称</label>
              <input type="text" name="name" id="template-name" required placeholder="ubuntu-24-cloud">
            </div>
            <div>
              <label>VM ID（建议）</label>
              <input type="number" min="1" name="vmid_hint" id="template-vmid-hint" placeholder="比如 9001">
            </div>
            <div>
              <label>OS Type</label>
              <select name="os_type" id="template-os-type">
                <option value="l26">Linux (l26)</option>
                <option value="win">Windows</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>
          <label>说明 / 备注</label>
          <textarea name="notes" id="template-notes" placeholder="比如：cloud image 模板 / 对外业务模板 / 内网测试模板"></textarea>
        </section>

        <section class="module-card">
          <h4>OS / 镜像</h4>
          <div class="row-3">
            <div>
              <label>镜像来源（ISO / 镜像）</label>
              <select name="os_source" id="template-os-source">
                <option value="image">镜像文件（qcow2/raw）</option>
                <option value="iso">ISO 安装介质</option>
                <option value="clone">已有磁盘克隆</option>
              </select>
            </div>
            <div>
              <label>基础镜像</label>
              <select name="image_id" id="template-image-id" required>
                <option value="">请选择镜像</option>
                <?php foreach ($images as $image): ?>
                  <option value="<?= (int) $image['id'] ?>"><?= e((string) $image['name']) ?> (<?= e((string) $image['extension']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Guest OS Variant</label>
              <input type="text" name="os_variant" id="template-os-variant" value="generic" required>
            </div>
          </div>
          <div class="muted">如果模板里 netX/ipconfigX 使用 static/pool，必须启用 Cloud-Init 才能正确把网络参数注入来宾系统。</div>
        </section>

        <section class="module-card">
          <h4>System</h4>
          <div class="row-3">
            <div><label>虚拟化</label><select name="virtualization_mode" id="template-virtualization-mode"><option value="kvm">硬件虚拟化 (KVM)</option><option value="qemu">软件虚拟化 (QEMU)</option></select></div>
            <div><label>Machine</label><select name="machine_type" id="template-machine-type"><option value="pc">i440fx (pc)</option><option value="q35">q35</option></select></div>
            <div><label>BIOS/UEFI</label><select name="firmware_type" id="template-firmware-type"><option value="bios">bios</option><option value="uefi">uefi</option></select></div>
          </div>
          <div class="row-3">
            <div>
              <label>SCSI Controller</label>
              <select name="scsi_controller" id="template-scsi-controller">
                <option value="virtio-scsi-single">virtio-scsi-single</option>
                <option value="virtio-scsi-pci">virtio-scsi-pci</option>
                <option value="lsi">lsi</option>
                <option value="megasas">megasas</option>
              </select>
            </div>
            <div>
              <label>Display</label>
              <select name="display_type" id="template-display-type">
                <option value="vnc">vnc</option>
                <option value="spice">spice</option>
                <option value="none">none</option>
              </select>
            </div>
            <div>
              <label>GPU 类型</label>
              <select name="gpu_type" id="template-gpu-type"><option value="cirrus">cirrus</option><option value="qxl">qxl</option><option value="virtio">virtio</option><option value="vga">vga</option><option value="none">none</option></select>
            </div>
          </div>
          <div class="row-2 compact-fields">
            <div><label><input class="inline" type="checkbox" name="qemu_agent_enabled" id="template-qemu-agent-enabled" value="1" checked> 启用 QEMU Agent 通道</label></div>
            <div><label><input class="inline" type="checkbox" name="serial_console_enabled" id="template-serial-console-enabled" value="1" checked> 启用 Serial Console</label></div>
          </div>
        </section>

        <section class="module-card">
          <h4>CPU</h4>
          <div class="row-4">
            <div><label>Sockets</label><input type="number" min="1" name="cpu_sockets" id="template-cpu-sockets" value="1"></div>
            <div><label>Cores</label><input type="number" min="1" name="cpu_cores" id="template-cpu-cores" value="2"></div>
            <div><label>Threads</label><input type="number" min="1" name="cpu_threads" id="template-cpu-threads" value="1"></div>
            <div>
              <label>Type</label>
              <select name="cpu_type" id="template-cpu-type">
                <option value="host">host</option>
                <option value="default">default(host-model)</option>
                <option value="qemu64">qemu64</option>
                <option value="kvm64">kvm64</option>
              </select>
            </div>
          </div>
          <div class="row-3 compact-fields">
            <div><label><input class="inline" type="checkbox" name="cpu_numa" id="template-cpu-numa" value="1"> NUMA</label></div>
            <div><label>CPU Limit(%)</label><input type="number" min="1" name="cpu_limit_percent" id="template-cpu-limit-percent" placeholder="留空=不限"></div>
            <div><label>CPU Units</label><input type="number" min="1" name="cpu_units" id="template-cpu-units" placeholder="留空=默认"></div>
          </div>
          <p class="muted">总 vCPU = sockets × cores × threads。模板已派生出 VM 后，基础镜像 / 虚拟化模式 / 主板 / 固件会被锁定。</p>
        </section>

        <section class="module-card">
          <h4>Memory</h4>
          <div class="row-4">
            <div><label>Memory(MB)</label><input type="number" min="256" name="memory_mb" id="template-memory-mb" value="2048" required></div>
            <div><label>Balloon Min(MB)</label><input type="number" min="128" name="memory_min_mb" id="template-memory-min-mb" placeholder="留空=不设"></div>
            <div><label>Memory Max(MB)</label><input type="number" min="256" name="memory_max_mb" id="template-memory-max-mb" value="4096"></div>
            <div><label>内存超开(%)</label><input type="number" min="100" name="memory_overcommit_percent" id="template-memory-overcommit" value="100"></div>
          </div>
          <label><input class="inline" type="checkbox" name="balloon_enabled" id="template-balloon-enabled" value="1" checked> 启用 Balloon</label>
        </section>

        <section class="module-card">
          <div class="section-split">
            <h4>Disks</h4>
            <button class="btn secondary js-add-disk" type="button" data-editor="template">新增磁盘</button>
          </div>
          <div class="row-3 compact-fields">
            <div><label>默认系统盘(GB)</label><input type="number" min="5" name="disk_size_gb" id="template-disk-size-gb" value="20"></div>
            <div><label>默认总线</label><select name="disk_bus" id="template-disk-bus"><option value="virtio">virtio</option><option value="sata">sata</option><option value="scsi">scsi</option><option value="ide">ide</option></select></div>
            <div><label><input class="inline" type="checkbox" name="disk_overcommit_enabled" id="template-disk-overcommit" value="1"> 允许磁盘超开</label></div>
          </div>
          <div class="disk-editor" id="template-disk-editor"></div>
          <details>
            <summary>高级 JSON 兼容入口</summary>
            <textarea name="disks_json_override" id="template-disks-json-override" placeholder="主流程请使用可视化磁盘卡片；仅在兼容旧数据时手填 JSON。"></textarea>
            <textarea id="template-disks-preview" readonly></textarea>
          </details>
        </section>

        <section class="module-card">
          <div class="section-split">
            <h4>Network（netX / ipconfigX）</h4>
            <button class="btn secondary js-add-nic" type="button" data-editor="template">新增 netX</button>
          </div>
          <div class="nic-editor" id="template-nic-editor"></div>
          <p class="muted">每张卡片对应一张 PVE 风格网卡（model/bridge/tag/firewall/link_down/macaddr）+ ipconfigX（dhcp/static/auto/pool）。</p>
          <details>
            <summary>查看生成后的 nics_json</summary>
            <textarea id="template-nics-preview" readonly></textarea>
          </details>
        </section>

        <section class="module-card">
          <h4>Cloud-Init</h4>
          <label><input class="inline" type="checkbox" name="cloud_init_enabled" id="template-cloud-init-enabled" value="1"> 启用 cloud-init</label>
          <div class="row-2">
            <div>
              <label>用户名</label>
              <input type="text" name="cloud_init_user" id="template-cloud-init-user" value="ubuntu">
            </div>
            <div>
              <label>密码（留空则不改）</label>
              <input type="text" name="cloud_init_password" id="template-cloud-init-password" placeholder="可填明文或 hash">
            </div>
          </div>
          <label>SSH 公钥</label>
          <textarea name="cloud_init_ssh_key" id="template-cloud-init-ssh-key" placeholder="一行一个 key"></textarea>
          <div class="row-3">
            <div>
              <label>Hostname</label>
              <input type="text" name="cloud_init_hostname" id="template-cloud-init-hostname" placeholder="留空默认 VM 名称">
            </div>
            <div>
              <label>DNS</label>
              <input type="text" name="cloud_init_dns_servers" id="template-cloud-init-dns-servers" placeholder="1.1.1.1,8.8.8.8">
            </div>
            <div>
              <label>Search Domain</label>
              <input type="text" name="cloud_init_search_domain" id="template-cloud-init-search-domain" placeholder="example.local">
            </div>
          </div>
          <details>
            <summary>高级 cloud-init（额外 user-data）</summary>
            <textarea name="cloud_init_extra_user_data" id="template-cloud-init-extra-user-data" placeholder="可填写 packages / runcmd / write_files 等 cloud-config 片段（无需再写 #cloud-config）"></textarea>
          </details>
          <label><input class="inline" type="checkbox" name="autostart_default" id="template-autostart-default" value="1"> 模板默认自启动</label>
          <p class="muted">IP 配置主要来自 netX/ipconfigX；Cloud-Init 会把网卡地址策略（dhcp/static/pool）写入来宾系统网络配置。</p>
        </section>
      </div>

      <div class="actions top-gap">
        <button class="btn" type="submit">保存模板</button>
        <span class="muted">模板已被 VM 使用后，修改只影响后续新建的 VM，不会回写现有实例。</span>
      </div>
    </form>
  </section>

  <section class="card span-6">
    <div class="section-split">
      <div>
        <h3>模板列表</h3>
        <p class="muted">支持创建 / 编辑 / 删除；危险字段会在编辑阶段显式锁定。</p>
      </div>
      <span class="muted">共 <?= count($templates) ?> 条</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>模板</th><th>OS</th><th>CPU / 内存</th><th>磁盘 / 网络</th><th>cloud-init</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($templates as $template): ?>
            <tr>
              <td>
                <strong><?= e((string) $template['name']) ?></strong>
                <div class="muted">VMID 建议：<?= e((string) (($template['vmid_hint'] ?? '') ?: '-')) ?></div>
                <div class="muted">已派生 VM：<?= (int) ($template['linked_vm_count'] ?? 0) ?></div>
              </td>
              <td>
                <?= e((string) ($template['image_name'] ?? 'unknown')) ?><br>
                <span class="muted"><?= e((string) ($template['os_type'] ?? 'l26')) ?> / <?= e((string) ($template['os_variant'] ?? 'generic')) ?></span><br>
                <span class="muted"><?= e((string) ($template['virtualization_mode'] ?? 'kvm')) ?> / <?= e((string) ($template['machine_type'] ?? 'pc')) ?> / <?= e((string) ($template['firmware_type'] ?? 'bios')) ?></span>
              </td>
              <td>
                <?= (int) $template['cpu'] ?> vCPU<br>
                <span class="muted"><?= (int) ($template['cpu_sockets'] ?? 1) ?>S / <?= (int) ($template['cpu_cores'] ?? 1) ?>C / <?= (int) ($template['cpu_threads'] ?? 1) ?>T / <?= e((string) ($template['cpu_type'] ?? 'host')) ?></span><br>
                <span class="muted"><?= (int) $template['memory_mb'] ?> MB<?php if (!empty($template['memory_max_mb'])): ?> / max <?= (int) $template['memory_max_mb'] ?> MB<?php endif; ?></span>
              </td>
              <td>
                <div class="resource-stack">
                  <?php foreach (($template['normalized_disks'] ?? []) as $disk): ?>
                    <div class="muted"><?= e($diskSummary($disk)) ?></div>
                  <?php endforeach; ?>
                </div>
                <div class="resource-stack top-gap-mini">
                  <?php foreach (($template['normalized_nics'] ?? []) as $index => $nic): ?>
                    <div class="muted">net<?= (int) $index ?>: <?= e($nicSummary($nic)) ?></div>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <?= (int) ($template['cloud_init_enabled'] ?? 0) === 1 ? '启用' : '关闭' ?>
                <?php if ((int) ($template['cloud_init_enabled'] ?? 0) === 1): ?>
                  <div class="muted">user: <?= e((string) (($template['cloud_init_user'] ?? '') ?: 'ubuntu')) ?></div>
                  <div class="muted">host: <?= e((string) (($template['cloud_init_hostname'] ?? '') ?: '-')) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions vertical-actions">
                  <button class="btn secondary js-edit-template" type="button" data-template='<?= e((string) json_encode([
                    'id' => (int) $template['id'],
                    'name' => (string) $template['name'],
                    'vmid_hint' => ($template['vmid_hint'] ?? null) !== null && (string) ($template['vmid_hint'] ?? '') !== '' ? (int) $template['vmid_hint'] : null,
                    'image_id' => (int) ($template['image_id'] ?? 0),
                    'os_type' => (string) ($template['os_type'] ?? 'l26'),
                    'os_source' => (string) ($template['os_source'] ?? 'image'),
                    'os_variant' => (string) ($template['os_variant'] ?? ''),
                    'virtualization_mode' => (string) ($template['virtualization_mode'] ?? 'kvm'),
                    'machine_type' => (string) ($template['machine_type'] ?? 'pc'),
                    'firmware_type' => (string) ($template['firmware_type'] ?? 'bios'),
                    'scsi_controller' => (string) ($template['scsi_controller'] ?? 'virtio-scsi-single'),
                    'qemu_agent_enabled' => (int) ($template['qemu_agent_enabled'] ?? 1),
                    'display_type' => (string) ($template['display_type'] ?? 'vnc'),
                    'serial_console_enabled' => (int) ($template['serial_console_enabled'] ?? 1),
                    'gpu_type' => (string) ($template['gpu_type'] ?? 'cirrus'),
                    'cpu_sockets' => (int) ($template['cpu_sockets'] ?? 1),
                    'cpu_cores' => (int) ($template['cpu_cores'] ?? 1),
                    'cpu_threads' => (int) ($template['cpu_threads'] ?? 1),
                    'cpu_type' => (string) ($template['cpu_type'] ?? 'host'),
                    'cpu_numa' => (int) ($template['cpu_numa'] ?? 0),
                    'cpu_limit_percent' => ($template['cpu_limit_percent'] ?? null) !== null && (string) ($template['cpu_limit_percent'] ?? '') !== '' ? (int) $template['cpu_limit_percent'] : null,
                    'cpu_units' => ($template['cpu_units'] ?? null) !== null && (string) ($template['cpu_units'] ?? '') !== '' ? (int) $template['cpu_units'] : null,
                    'memory_mb' => (int) ($template['memory_mb'] ?? 2048),
                    'memory_min_mb' => ($template['memory_min_mb'] ?? null) !== null && (string) ($template['memory_min_mb'] ?? '') !== '' ? (int) $template['memory_min_mb'] : null,
                    'memory_max_mb' => (int) ($template['memory_max_mb'] ?? 0),
                    'balloon_enabled' => (int) ($template['balloon_enabled'] ?? 1),
                    'memory_overcommit_percent' => (int) ($template['memory_overcommit_percent'] ?? 100),
                    'disk_size_gb' => (int) ($template['disk_size_gb'] ?? 20),
                    'disk_bus' => (string) ($template['disk_bus'] ?? 'virtio'),
                    'autostart_default' => (int) ($template['autostart_default'] ?? 0),
                    'disk_overcommit_enabled' => (int) ($template['disk_overcommit_enabled'] ?? 0),
                    'cloud_init_enabled' => (int) ($template['cloud_init_enabled'] ?? 0),
                    'cloud_init_user' => (string) ($template['cloud_init_user'] ?? 'ubuntu'),
                    'cloud_init_password' => (string) ($template['cloud_init_password'] ?? ''),
                    'cloud_init_ssh_key' => (string) ($template['cloud_init_ssh_key'] ?? ''),
                    'cloud_init_hostname' => (string) ($template['cloud_init_hostname'] ?? ''),
                    'cloud_init_dns_servers' => (string) ($template['cloud_init_dns_servers'] ?? ''),
                    'cloud_init_search_domain' => (string) ($template['cloud_init_search_domain'] ?? ''),
                    'cloud_init_extra_user_data' => (string) ($template['cloud_init_extra_user_data'] ?? ''),
                    'notes' => (string) ($template['notes'] ?? ''),
                    'network_name' => (string) ($template['network_name'] ?? 'default'),
                    'linked_vm_count' => (int) ($template['linked_vm_count'] ?? 0),
                    'disks' => array_values($template['normalized_disks'] ?? []),
                    'nics' => array_values($template['normalized_nics'] ?? []),
                  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>编辑</button>
                  <form action="/templates/delete" method="post" onsubmit="return confirm('确认删除该模板？');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                    <button class="btn danger" type="submit">删除</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$templates): ?><tr><td colspan="6" class="muted">暂无模板</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
