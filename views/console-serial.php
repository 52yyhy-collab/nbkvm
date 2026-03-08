<?php $cap = $consoleSnapshot['capabilities'] ?? []; ?>
<section class="card span-12 console-page" id="console-app"
  data-vm-id="<?= (int) $vm['id'] ?>"
  data-csrf="<?= e(csrf_token()) ?>"
  data-status-url="/console/status?id=<?= (int) $vm['id'] ?>"
  data-start-url="/console/start"
  data-send-url="/console/send"
  data-stop-url="/console/stop">
  <div class="section-split">
    <div>
      <div class="eyebrow">Xterm / Serial Console</div>
      <h2><?= e((string) $vm['name']) ?></h2>
      <p class="muted">后端通过 tmux + virsh console 维持会话；如果 xterm.js CDN 不可达，会自动回退为只读终端视图。</p>
    </div>
    <div class="actions">
      <a class="btn secondary" href="/vm?id=<?= (int) $vm['id'] ?>">返回 VM 详情</a>
      <a class="btn secondary" href="/?page=vms">返回虚拟机页</a>
    </div>
  </div>

  <div class="inline-banner <?= !empty($cap['virsh_available']) && !empty($cap['tmux_available']) ? '' : 'warn' ?>">
    <?= e((string) ($cap['hint'] ?? '')) ?>
  </div>

  <div class="console-layout">
    <div class="console-terminal-wrap">
      <div id="console-terminal" class="terminal-surface"></div>
      <pre id="console-fallback" class="terminal-fallback"><?= e((string) ($consoleSnapshot['output'] ?? '')) ?></pre>
    </div>
    <div class="console-sidepanel">
      <div class="resource-stack">
        <div><strong>virsh</strong>：<?= !empty($cap['virsh_available']) ? '可用' : '缺失' ?></div>
        <div><strong>tmux</strong>：<?= !empty($cap['tmux_available']) ? '可用' : '缺失' ?></div>
        <div><strong>serial</strong>：<?= !empty($cap['serial_configured']) ? '已检测' : '未显式检测到' ?></div>
        <div><strong>会话</strong>：<span id="console-session-state"><?= !empty($cap['running']) ? '运行中' : '未启动' ?></span></div>
      </div>
      <div class="actions top-gap">
        <button class="btn" type="button" id="console-start">启动会话</button>
        <button class="btn secondary" type="button" id="console-refresh">刷新输出</button>
        <button class="btn danger" type="button" id="console-stop">停止会话</button>
      </div>
      <label>发送输入</label>
      <textarea id="console-input" placeholder="例如：回车、用户名、密码、systemctl status"></textarea>
      <div class="actions">
        <button class="btn secondary" type="button" id="console-send">发送并回车</button>
        <button class="btn secondary" type="button" id="console-send-raw">仅发送原文</button>
      </div>
      <p class="muted">提示：这是串口/终端式控制台，不是图形 noVNC。更适合 cloud image、救援模式、virsh serial console 等场景。</p>
    </div>
  </div>
</section>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.5.0/css/xterm.min.css">
<script src="https://cdn.jsdelivr.net/npm/xterm@5.5.0/lib/xterm.min.js"></script>
<script>
(() => {
  const app = document.getElementById('console-app');
  if (!app) return;
  const vmId = app.dataset.vmId;
  const csrf = app.dataset.csrf;
  const statusUrl = app.dataset.statusUrl;
  const startUrl = app.dataset.startUrl;
  const sendUrl = app.dataset.sendUrl;
  const stopUrl = app.dataset.stopUrl;
  const fallback = document.getElementById('console-fallback');
  const sessionState = document.getElementById('console-session-state');
  const input = document.getElementById('console-input');
  let terminal = null;

  if (window.Terminal) {
    terminal = new window.Terminal({convertEol: true, disableStdin: true, theme: {background: '#0d1427', foreground: '#eef4ff'}});
    terminal.open(document.getElementById('console-terminal'));
    fallback.style.display = 'none';
  }

  function render(output) {
    const text = String(output || '');
    if (terminal) {
      terminal.reset();
      terminal.write(text.replace(/\n/g, '\r\n'));
    } else {
      fallback.textContent = text;
    }
  }

  async function refresh() {
    const response = await fetch(statusUrl, {credentials: 'same-origin'});
    const data = await response.json();
    render(data.output || '');
    sessionState.textContent = data.capabilities && data.capabilities.running ? '运行中' : '未启动';
  }

  async function post(url, payload) {
    const body = new URLSearchParams({_csrf: csrf, id: vmId, ...payload});
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: body.toString(),
    });
    const data = await response.json();
    if (!response.ok || data.error) {
      throw new Error(data.error || '请求失败');
    }
    render(data.output || '');
    sessionState.textContent = data.capabilities && data.capabilities.running ? '运行中' : '未启动';
  }

  document.getElementById('console-start').addEventListener('click', async () => {
    try { await post(startUrl, {}); } catch (error) { alert(error.message); }
  });
  document.getElementById('console-stop').addEventListener('click', async () => {
    try { await post(stopUrl, {}); } catch (error) { alert(error.message); }
  });
  document.getElementById('console-refresh').addEventListener('click', async () => {
    try { await refresh(); } catch (error) { alert(error.message); }
  });
  document.getElementById('console-send').addEventListener('click', async () => {
    try { await post(sendUrl, {input: input.value, append_enter: '1'}); input.value = ''; } catch (error) { alert(error.message); }
  });
  document.getElementById('console-send-raw').addEventListener('click', async () => {
    try { await post(sendUrl, {input: input.value, append_enter: '0'}); input.value = ''; } catch (error) { alert(error.message); }
  });
  input.addEventListener('keydown', async (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
      event.preventDefault();
      try { await post(sendUrl, {input: input.value, append_enter: '1'}); input.value = ''; } catch (error) { alert(error.message); }
    }
  });

  refresh().catch(() => {});
  setInterval(() => refresh().catch(() => {}), 2000);
})();
</script>
