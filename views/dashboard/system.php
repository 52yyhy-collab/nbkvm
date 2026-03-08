<div class="grid dashboard-grid">
  <section class="card span-6">
    <h3>系统配置</h3>
    <form action="/settings" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <div class="row-2">
        <div><label>上传大小(MB)</label><input type="number" min="1" name="upload_max_size_mb" value="<?= e((string) ($settingsMap['upload_max_size_mb'] ?? '51200')) ?>"></div>
        <div><label>到期后暂停几天删除</label><input type="number" min="0" name="expire_grace_days" value="<?= e((string) ($settingsMap['expire_grace_days'] ?? '3')) ?>"></div>
      </div>
      <label>系统变量(JSON)</label>
      <textarea name="system_variables_json" placeholder='{"UPLOAD_TMP_DIR":"/data/tmp","DEFAULT_BRIDGE":"vmbr0"}'><?= e((string) ($settingsMap['system_variables_json'] ?? '{}')) ?></textarea>
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">保存系统配置</button>
    </form>
  </section>

  <section class="card span-6">
    <h3>修改密码</h3>
    <form action="/password" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <label>新密码</label>
      <input type="password" name="password" required>
      <label>确认新密码</label>
      <input type="password" name="password_confirm" required>
      <div class="spacer"></div>
      <button class="btn secondary" type="submit">更新密码</button>
    </form>
  </section>

  <?php if (auth_is_admin()): ?>
  <section class="card span-12">
    <div class="section-split">
      <div>
        <h3>用户管理</h3>
        <p class="muted">admin 可管理用户；operator 可执行写操作；readonly 仅查看。</p>
      </div>
    </div>
    <div class="two-column-stack">
      <form action="/users" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
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
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>ID</th><th>用户名</th><th>角色</th><th>操作</th></tr></thead>
          <tbody>
            <?php foreach ($users as $userItem): ?>
              <tr>
                <td><?= (int) $userItem['id'] ?></td>
                <td><?= e((string) $userItem['username']) ?></td>
                <td>
                  <form action="/users/role" method="post" class="inline-form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
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
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $userItem['id'] ?>">
                    <button class="btn danger" type="submit">删除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif; ?>
</div>
