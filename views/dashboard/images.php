<div class="grid dashboard-grid">
  <section class="card span-4">
    <h3>上传镜像</h3>
    <p class="muted">支持 ISO / QCOW2 / RAW / IMG。</p>
    <form action="/images" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <label for="image">镜像文件</label>
      <input id="image" type="file" name="image" required>
      <div class="spacer"></div>
      <button class="btn" type="submit">上传</button>
    </form>
  </section>

  <section class="card span-8">
    <div class="section-split">
      <div>
        <h3>镜像列表</h3>
        <p class="muted">镜像 CRUD 以上传 / 转换 / 删除为主，模板引用中的镜像会被保护。</p>
      </div>
      <span class="muted">共 <?= count($images) ?> 条</span>
    </div>
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
                <div class="actions vertical-actions">
                  <form action="/images/convert" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
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
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
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
        <?php if (!$images): ?><tr><td colspan="6" class="muted">暂无镜像</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
