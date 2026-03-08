<div class="grid dashboard-grid">
  <section class="card span-3 stat-card">
    <div class="eyebrow">虚拟机</div>
    <strong><?= count($vms) ?></strong>
    <span class="muted"><?= count(array_filter($vms, static fn (array $vm): bool => str_contains(strtolower((string) ($vm['status'] ?? '')), 'running'))) ?> 台运行中</span>
  </section>
  <section class="card span-3 stat-card">
    <div class="eyebrow">模板</div>
    <strong><?= count($templates) ?></strong>
    <span class="muted"><?= count(array_filter($templates, static fn (array $template): bool => (int) ($template['linked_vm_count'] ?? 0) > 0)) ?> 个已被使用</span>
  </section>
  <section class="card span-3 stat-card">
    <div class="eyebrow">网络</div>
    <strong><?= count($networks) ?></strong>
    <span class="muted"><?= count(array_filter($networkConfigs, static fn (array $item): bool => !empty($item['ipv4_pool']) || !empty($item['ipv6_pool']))) ?> 个已带地址池</span>
  </section>
  <section class="card span-3 stat-card">
    <div class="eyebrow">镜像</div>
    <strong><?= count($images) ?></strong>
    <span class="muted">上传与转换入口已独立到镜像页面</span>
  </section>

  <section class="card span-12">
    <div class="section-split">
      <div>
        <h3>环境自检</h3>
        <p class="muted">优先看 libvirt / qemu-img / noVNC / cloud-init 这些底层依赖。</p>
      </div>
      <a class="btn secondary" href="/?page=system">去系统配置</a>
    </div>
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

  <section class="card span-6">
    <div class="section-split">
      <div>
        <h3>最近任务</h3>
        <p class="muted">创建 / 启停 / 快照等后台任务会记录在这里。</p>
      </div>
      <a class="btn secondary" href="/?page=vms">去虚拟机页面</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>时间</th><th>任务</th><th>目标</th><th>状态</th></tr></thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
            <tr>
              <td><?= e((string) $job['created_at']) ?></td>
              <td><?= e((string) $job['name']) ?></td>
              <td><?= e((string) (($job['target_type'] ?: '-') . ' / ' . ($job['target_name'] ?: '-'))) ?></td>
              <td><?= e((string) $job['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$jobs): ?><tr><td colspan="4" class="muted">暂无任务</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card span-6">
    <div class="section-split">
      <div>
        <h3>最近审计</h3>
        <p class="muted">适合快速回看谁改了什么。</p>
      </div>
      <a class="btn secondary" href="/?page=networks">去网络页面</a>
    </div>
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

  <section class="card span-6">
    <div class="section-split">
      <div>
        <h3>最新模板</h3>
        <p class="muted">模块化模板配置已拆到独立页面里。</p>
      </div>
      <a class="btn secondary" href="/?page=templates">去模板页面</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>模板</th><th>镜像</th><th>规格</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($templates, 0, 5) as $template): ?>
            <tr>
              <td><?= e((string) $template['name']) ?></td>
              <td><?= e((string) ($template['image_name'] ?? 'unknown')) ?></td>
              <td><?= (int) $template['cpu'] ?> vCPU / <?= (int) $template['memory_mb'] ?> MB / <?= count($template['normalized_disks'] ?? []) ?> 盘 / <?= count($template['normalized_nics'] ?? []) ?> 网卡</td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$templates): ?><tr><td colspan="3" class="muted">暂无模板</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card span-6">
    <div class="section-split">
      <div>
        <h3>最新虚拟机</h3>
        <p class="muted">noVNC 与 Xterm 控制台入口都在 VM 页面里。</p>
      </div>
      <a class="btn secondary" href="/?page=vms">去虚拟机页面</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>名称</th><th>状态</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($vms, 0, 5) as $vm): ?>
            <?php $status = strtolower((string) ($vm['status'] ?? 'unknown')); ?>
            <tr>
              <td><a href="/vm?id=<?= (int) $vm['id'] ?>"><?= e((string) $vm['name']) ?></a></td>
              <td><span class="badge <?= str_contains($status, 'running') ? 'running' : (str_contains($status, 'shut') ? 'shut' : 'unknown') ?>"><?= e($status) ?></span></td>
              <td><?= e((string) (($vm['ip_address'] ?? '') ?: '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$vms): ?><tr><td colspan="3" class="muted">暂无虚拟机</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
