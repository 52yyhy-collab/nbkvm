<section class="card span-12">
  <h2>虚拟机详情：<?= e((string) $vm['name']) ?></h2>
  <div class="table-wrap">
    <table class="table">
      <tbody>
        <tr><th>状态</th><td><?= e((string) $vm['status']) ?></td></tr>
        <tr><th>IP</th><td><?= e((string) ($vm['ip_address'] ?: '-')) ?></td></tr>
        <tr><th>VNC</th><td><?= e((string) ($vm['vnc_display'] ?: '-')) ?></td></tr>
        <tr><th>磁盘</th><td><code><?= e((string) $vm['disk_path']) ?></code></td></tr>
        <tr><th>XML</th><td><code><?= e((string) $vm['xml_path']) ?></code></td></tr>
        <tr><th>cloud-init ISO</th><td><code><?= e((string) ($vm['cloud_init_iso_path'] ?: '-')) ?></code></td></tr>
      </tbody>
    </table>
  </div>
  <?php if (!empty($vm['vnc_display']) && auth_can_write()): ?>
    <a class="btn secondary" target="_blank" href="/novnc/open?id=<?= (int) $vm['id'] ?>">打开 noVNC</a>
  <?php endif; ?>
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
