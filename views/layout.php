<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e((string) config('app_name')) ?></title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="container">
  <div class="hero">
    <div>
      <h1><?= e((string) config('app_name')) ?></h1>
      <p>基于 PHP + libvirt 扩展的 KVM 控制面板</p>
    </div>
    <div class="muted">
      <?php if (auth_check()): ?>
        当前用户：<code><?= e((string) (auth_user()['username'] ?? 'unknown')) ?></code>
        / 角色：<code><?= e(auth_role()) ?></code>
        <form class="inline" action="/logout" method="post" style="display:inline-block;margin-left:12px;">
          <?= csrf_field() ?>
          <button class="btn secondary" type="submit">退出</button>
        </form>
      <?php else: ?>
        未登录
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($flash['success'])): ?>
    <div class="flash success"><?= e((string) $flash['success']) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash['error'])): ?>
    <div class="flash error"><?= e((string) $flash['error']) ?></div>
  <?php endif; ?>
  <?= $content ?>
</div>
</body>
</html>
