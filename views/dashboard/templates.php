<div class="grid dashboard-grid">
  <section class="card span-5">
    <div class="section-split">
      <div>
        <h3>模板编辑器</h3>
        <p class="muted">按 CPU / 内存 / 磁盘 / 网络 / GPU / 镜像模块拆开，避免一大坨表单堆在一起。</p>
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
          <h4>镜像模块</h4>
          <label>模板名称</label>
          <input type="text" name="name" id="template-name" required placeholder="ubuntu-24-cloud">
          <label>基础镜像</label>
          <select name="image_id" id="template-image-id" required>
            <option value="">请选择镜像</option>
            <?php foreach ($images as $image): ?>
              <option value="<?= (int) $image['id'] ?>"><?= e((string) $image['name']) ?> (<?= e((string) $image['extension']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <label>OS Variant</label>
          <input type="text" name="os_variant" id="template-os-variant" value="generic" required>
          <label>备注</label>
          <textarea name="notes" id="template-notes" placeholder="比如：cloud image / 安装 ISO / 内网模板"></textarea>
        </section>

        <section class="module-card">
          <h4>CPU 模块</h4>
          <div class="row-3">
            <div><label>虚拟化</label><select name="virtualization_mode" id="template-virtualization-mode"><option value="kvm">硬件虚拟化 (KVM)</option><option value="qemu">软件虚拟化 (QEMU)</option></select></div>
            <div><label>主板类型</label><select name="machine_type" id="template-machine-type"><option value="pc">pc</option><option value="q35">q35</option></select></div>
            <div><label>固件</label><select name="firmware_type" id="template-firmware-type"><option value="bios">bios</option><option value="uefi">uefi</option></select></div>
          </div>
          <div class="row-3">
            <div><label>Sockets</label><input type="number" min="1" name="cpu_sockets" id="template-cpu-sockets" value="1"></div>
            <div><label>Cores</label><input type="number" min="1" name="cpu_cores" id="template-cpu-cores" value="2"></div>
            <div><label>Threads</label><input type="number" min="1" name="cpu_threads" id="template-cpu-threads" value="1"></div>
          </div>
          <p class="muted">总 vCPU = sockets × cores × threads。模板已派生出 VM 后，基础镜像 / 虚拟化模式 / 固件等危险项会锁定。</p>
        </section>

        <section class="module-card">
          <h4>内存模块</h4>
          <div class="row-3">
            <div><label>启动内存(MB)</label><input type="number" min="256" name="memory_mb" id="template-memory-mb" value="2048" required></div>
            <div><label>最大内存(MB)</label><input type="number" min="256" name="memory_max_mb" id="template-memory-max-mb" value="4096"></div>
            <div><label>内存超开(%)</label><input type="number" min="100" name="memory_overcommit_percent" id="template-memory-overcommit" value="100"></div>
          </div>
        </section>

        <section class="module-card">
          <div class="section-split">
            <h4>硬盘模块</h4>
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
            <textarea name="disks_json_override" id="template-disks-json-override" placeholder="需要兼容旧数据时再手填；日常请优先用上面的磁盘卡片。"></textarea>
            <textarea id="template-disks-preview" readonly></textarea>
          </details>
        </section>

        <section class="module-card">
          <div class="section-split">
            <h4>网络模块（netX / ipconfigX）</h4>
            <button class="btn secondary js-add-nic" type="button" data-editor="template">新增 netX</button>
          </div>
          <div class="nic-editor" id="template-nic-editor"></div>
          <p class="muted">每张卡片就是一块 PVE 风格 netX：bridge / tag / model / firewall / link_down / macaddr；卡片下半部分对应 ipconfigX，主流程是 DHCP / static / auto，pool 已降级为高级兼容选项。</p>
          <details>
            <summary>查看生成后的 nics_json</summary>
            <textarea id="template-nics-preview" readonly></textarea>
          </details>
        </section>

        <section class="module-card">
          <h4>显卡 / GPU 模块</h4>
          <label>GPU 类型</label>
          <select name="gpu_type" id="template-gpu-type"><option value="cirrus">cirrus</option><option value="qxl">qxl</option><option value="virtio">virtio</option><option value="vga">vga</option><option value="none">none</option></select>
        </section>

        <section class="module-card">
          <h4>cloud-init 模块</h4>
          <label><input class="inline" type="checkbox" name="cloud_init_enabled" id="template-cloud-init-enabled" value="1"> 启用 cloud-init</label>
          <label>默认用户</label>
          <input type="text" name="cloud_init_user" id="template-cloud-init-user" value="ubuntu">
          <label>密码</label>
          <input type="text" name="cloud_init_password" id="template-cloud-init-password" placeholder="可选">
          <label>SSH 公钥</label>
          <textarea name="cloud_init_ssh_key" id="template-cloud-init-ssh-key" placeholder="ssh-ed25519 ..."></textarea>
          <label><input class="inline" type="checkbox" name="autostart_default" id="template-autostart-default" value="1"> 模板默认自启动</label>
        </section>
      </div>

      <div class="actions top-gap">
        <button class="btn" type="submit">保存模板</button>
        <span class="muted">模板已被 VM 使用后，修改只影响后续新建的 VM，不会回写现有实例。</span>
      </div>
    </form>
  </section>

  <section class="card span-7">
    <div class="section-split">
      <div>
        <h3>模板列表</h3>
        <p class="muted">支持创建 / 编辑 / 删除；危险字段会在编辑阶段显式锁定。</p>
      </div>
      <span class="muted">共 <?= count($templates) ?> 条</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>模板</th><th>镜像</th><th>CPU / 内存</th><th>磁盘 / 网络</th><th>cloud-init</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($templates as $template): ?>
            <tr>
              <td>
                <strong><?= e((string) $template['name']) ?></strong>
                <div class="muted">已派生 VM：<?= (int) ($template['linked_vm_count'] ?? 0) ?></div>
                <div class="muted"><?= e((string) ($template['virtualization_mode'] ?? 'kvm')) ?> / <?= e((string) ($template['machine_type'] ?? 'pc')) ?> / <?= e((string) ($template['firmware_type'] ?? 'bios')) ?></div>
              </td>
              <td><?= e((string) ($template['image_name'] ?? 'unknown')) ?></td>
              <td>
                <?= (int) $template['cpu'] ?> vCPU<br>
                <span class="muted"><?= (int) ($template['cpu_sockets'] ?? 1) ?>S / <?= (int) ($template['cpu_cores'] ?? 1) ?>C / <?= (int) ($template['cpu_threads'] ?? 1) ?>T</span><br>
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
              <td><?= (int) ($template['cloud_init_enabled'] ?? 0) === 1 ? '启用' : '关闭' ?></td>
              <td>
                <div class="actions vertical-actions">
                  <button class="btn secondary js-edit-template" type="button" data-template='<?= e((string) json_encode([
                    'id' => (int) $template['id'],
                    'name' => (string) $template['name'],
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
                    'notes' => (string) ($template['notes'] ?? ''),
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
