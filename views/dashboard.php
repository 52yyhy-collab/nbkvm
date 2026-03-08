<div class="grid">
  <?php if (empty($libvirtAvailable)): ?>
    <section class="card span-12">
      <h2>环境提醒</h2>
      <div class="flash error">当前环境未检测到 <code>php-libvirt</code> 扩展。项目代码已按扩展方式实现，但你需要在目标机器安装并启用该扩展后才能真正控制 libvirt。</div>
    </section>
  <?php endif; ?>
  <section class="card span-4">
    <h2>上传镜像</h2>
    <form action="/images" method="post" enctype="multipart/form-data">
      <label for="image">镜像文件</label>
      <input id="image" type="file" name="image" required>
      <p class="muted">支持：ISO / QCOW2 / RAW / IMG</p>
      <button class="btn" type="submit">上传</button>
    </form>
  </section>
  <section class="card span-4">
    <h2>创建模板</h2>
    <form action="/templates" method="post">
      <label>模板名称</label>
      <input type="text" name="name" required placeholder="ubuntu-24-cloud">
      <label>基础镜像</label>
      <select name="image_id" required>
        <option value="">请选择镜像</option>
        <?php foreach ($images as $image): ?>
          <option value="<?= (int) $image['id'] ?>"><?= e($image['name']) ?> (<?= e($image['extension']) ?>)</option>
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
      <button class="btn" type="submit">创建模板</button>
    </form>
  </section>
  <section class="card span-4">
    <h2>创建虚拟机</h2>
    <form action="/vms" method="post">
      <label>虚拟机名称</label>
      <input type="text" name="name" required placeholder="vm-demo-01">
      <label>模板</label>
      <select name="template_id" required>
        <option value="">请选择模板</option>
        <?php foreach ($templates as $template): ?>
          <option value="<?= (int) $template['id'] ?>"><?= e($template['name']) ?> / <?= e($template['image_name'] ?? 'unknown') ?></option>
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
      <input type="text" name="network_name" value="default">
      <label><input class="inline" type="checkbox" name="autostart" value="1"> 创建后立即启动</label>
      <div class="spacer"></div>
      <button class="btn success" type="submit">创建虚拟机</button>
    </form>
  </section>
  <section class="card span-6">
    <h2>镜像列表</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>大小</th><th>路径</th></tr></thead>
        <tbody>
        <?php foreach ($images as $image): ?>
          <tr>
            <td><?= (int) $image['id'] ?></td>
            <td><?= e($image['name']) ?></td>
            <td><?= e($image['extension']) ?></td>
            <td><?= number_format(((int) $image['size_bytes']) / 1024 / 1024, 2) ?> MB</td>
            <td><code><?= e($image['path']) ?></code></td>
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
        <thead><tr><th>ID</th><th>模板</th><th>镜像</th><th>规格</th><th>网络</th></tr></thead>
        <tbody>
        <?php foreach ($templates as $template): ?>
          <tr>
            <td><?= (int) $template['id'] ?></td>
            <td><?= e($template['name']) ?></td>
            <td><?= e($template['image_name'] ?? 'unknown') ?></td>
            <td><?= (int) $template['cpu'] ?> vCPU / <?= (int) $template['memory_mb'] ?> MB / <?= (int) $template['disk_size_gb'] ?> GB</td>
            <td><?= e($template['network_name']) ?></td>
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
        <thead><tr><th>名称</th><th>模板</th><th>状态</th><th>规格</th><th>磁盘</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($vms as $vm): ?>
          <?php $status = strtolower((string) ($vm['status'] ?? 'unknown')); ?>
          <tr>
            <td><strong><?= e($vm['name']) ?></strong><br><span class="muted">IP: <?= e((string) ($vm['ip_address'] ?: '-')) ?></span></td>
            <td><?= e($vm['template_name'] ?? 'unknown') ?></td>
            <td><span class="badge <?= str_contains($status, 'running') ? 'running' : (str_contains($status, 'shut') ? 'shut' : 'unknown') ?>"><?= e($status) ?></span></td>
            <td><?= (int) $vm['cpu'] ?> vCPU / <?= (int) $vm['memory_mb'] ?> MB / <?= (int) $vm['disk_size_gb'] ?> GB</td>
            <td><code><?= e($vm['disk_path']) ?></code></td>
            <td>
              <div class="actions">
                <form action="/vms/start" method="post"><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn success" type="submit">启动</button></form>
                <form action="/vms/shutdown" method="post"><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn warn" type="submit">关机</button></form>
                <form action="/vms/destroy" method="post"><input type="hidden" name="id" value="<?= (int) $vm['id'] ?>"><button class="btn danger" type="submit">强停</button></form>
                <form action="/vms/delete" method="post" onsubmit="return confirm('确认删除虚拟机定义？');">
                  <input type="hidden" name="id" value="<?= (int) $vm['id'] ?>">
                  <label class="muted"><input type="checkbox" name="remove_storage" value="1"> 同时删磁盘</label>
                  <button class="btn secondary" type="submit">删除</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$vms): ?><tr><td colspan="6" class="muted">暂无虚拟机</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
