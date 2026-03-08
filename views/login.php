<section class="card span-4" style="max-width:420px;margin:80px auto;">
  <h2>登录 NBKVM</h2>
  <p class="muted">默认账号：<code><?= e((string) config('auth.default_username')) ?></code></p>
  <form action="/login" method="post">
    <?= csrf_field() ?>
    <label>用户名</label>
    <input type="text" name="username" required>
    <label>密码</label>
    <input type="password" name="password" required>
    <div class="spacer"></div>
    <button class="btn" type="submit">登录</button>
  </form>
</section>
