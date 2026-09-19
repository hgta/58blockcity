/**
 * 全站 AI 助手悬浮窗（无依赖原生 JS）
 * change: help-center-ai-assistant (task 4.1)
 *
 * - 右下角气泡 → 迷你聊天窗（复用 AiChat 客户端）
 * - 气泡关闭后 localStorage 记忆 7 天不再打扰
 * - 会话窗口状态记忆（打开/收起）
 * - 依赖: /js/ai-client.js 需先于本文件加载
 * - 可覆盖: window.AI_WIDGET_HELP_URL（帮助中心地址）
 */
(function () {
  if (window.__AI_WIDGET_LOADED) return;
  window.__AI_WIDGET_LOADED = true;

  var DISMISS_KEY = 'aiw_dismissed_until';
  var OPEN_KEY = 'aiw_panel_open';
  var HIST_KEY = 'aiw_history_v1';

  function helpUrl() {
    if (typeof window.AI_WIDGET_HELP_URL !== 'undefined') return window.AI_WIDGET_HELP_URL;
    var h = location.hostname;
    if (h.indexOf('help.') === 0) return location.origin + '/';
    if (h === 'www.58.tl' || h === '58.tl') return location.origin + '/help/';
    return 'https://help.58.tl/';
  }

  // 一周内主动关闭过则不显示
  try {
    var until = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
    if (until && Date.now() < until) return;
  } catch (e) {}

  var css = [
    '.aiw-bubble{position:fixed;right:18px;bottom:18px;z-index:99990;width:56px;height:56px;border-radius:50%;cursor:pointer;box-shadow:0 6px 20px rgba(255,107,0,.45);border:none;background:linear-gradient(135deg,#ff8a3d,#ff6b00);color:#fff;display:flex;align-items:center;justify-content:center;transition:transform .15s}',
    '.aiw-bubble:hover{transform:scale(1.08)}',
    '.aiw-bubble svg{width:26px;height:26px}',
    '.aiw-bubble .aiw-dot{position:absolute;top:-2px;right:-2px;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #fff}',
    '.aiw-panel{position:fixed;right:18px;bottom:86px;z-index:99991;width:min(360px,calc(100vw - 32px));height:min(520px,calc(100vh - 130px));background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.18);display:none;flex-direction:column;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;border:1px solid #eceef1}',
    '.aiw-panel.open{display:flex}',
    '.aiw-head{background:linear-gradient(135deg,#ff8a3d,#ff6b00);color:#fff;padding:12px 14px;display:flex;align-items:center;gap:8px;flex:none}',
    '.aiw-head .t{font-size:14px;font-weight:700}.aiw-head .s{font-size:11px;opacity:.9}',
    '.aiw-head a{color:#fff;font-size:11px;text-decoration:none;border:1px solid rgba(255,255,255,.6);border-radius:12px;padding:3px 10px;margin-left:auto;white-space:nowrap}',
    '.aiw-head a:hover{background:rgba(255,255,255,.15)}',
    '.aiw-log{flex:1;overflow-y:auto;padding:12px;background:#f7f8fa;font-size:13px;line-height:1.6}',
    '.aiw-m{display:flex;gap:8px;margin-bottom:10px}',
    '.aiw-m .b{max-width:82%;padding:8px 12px;border-radius:12px;word-break:break-word;white-space:pre-wrap}',
    '.aiw-m.u{justify-content:flex-end}.aiw-m.u .b{background:#ff6b00;color:#fff;border-radius:12px 2px 12px 12px}',
    '.aiw-m.a .b{background:#fff;border:1px solid #eceef1;border-radius:2px 12px 12px 12px}',
    '.aiw-m .src{margin-top:6px;font-size:11px}.aiw-m .src a{color:#e05e00;margin-right:6px}',
    '.aiw-m .tip{font-size:11px;color:#9ca3af;margin-top:4px}',
    '.aiw-empty{color:#9ca3af;text-align:center;padding:26px 8px;font-size:12px}',
    '.aiw-qs{margin-top:8px}.aiw-qs a{display:block;background:#fff;border:1px solid #eceef1;border-radius:8px;padding:7px 10px;margin:6px 0;color:#374151;font-size:12px;text-decoration:none;cursor:pointer}',
    '.aiw-qs a:hover{border-color:#ff6b00;color:#ff6b00}',
    '.aiw-inputbar{display:flex;gap:8px;padding:10px;border-top:1px solid #eceef1;background:#fff;flex:none}',
    '.aiw-inputbar textarea{flex:1;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;font-size:13px;resize:none;height:40px;outline:none;font-family:inherit}',
    '.aiw-inputbar textarea:focus{border-color:#ff6b00}',
    '.aiw-inputbar button{border:none;background:linear-gradient(135deg,#ff8a3d,#ff6b00);color:#fff;border-radius:10px;width:56px;cursor:pointer;font-size:16px}',
    '.aiw-inputbar button:disabled{opacity:.5}',
    '.aiw-typing i{display:inline-block;width:5px;height:5px;border-radius:50%;background:#c0c4cc;margin:0 1px;animation:aiwb 1.2s infinite}',
    '.aiw-typing i:nth-child(2){animation-delay:.2s}.aiw-typing i:nth-child(3){animation-delay:.4s}',
    '@keyframes aiwb{0%,80%,100%{opacity:.25}40%{opacity:1}}',
    '@media(max-width:480px){.aiw-panel{right:8px;bottom:78px}.aiw-bubble{right:12px;bottom:12px}}'
  ].join('');

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var SVG_ROBOT = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h3a4 4 0 0 1 4 4v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-1H3a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1v-1a4 4 0 0 1 4-4h3V5.73A2 2 0 0 1 12 2zm-4 11a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>';

  var bubble = document.createElement('button');
  bubble.className = 'aiw-bubble';
  bubble.setAttribute('aria-label', 'AI 助手');
  bubble.innerHTML = SVG_ROBOT + '<span class="aiw-dot"></span>';

  var panel = document.createElement('div');
  panel.className = 'aiw-panel';
  panel.innerHTML =
    '<div class="aiw-head"><div><div class="t">AI 助手「小帮」</div><div class="s">基于官方教程 · 在线秒回</div></div>' +
    '<a href="' + helpUrl() + '" target="_blank">帮助中心 ↗</a></div>' +
    '<div class="aiw-log" id="aiwLog"><div class="aiw-empty">你好，我是小帮～<div class="aiw-qs">' +
    '<a data-q="怎么认领虚拟地块？">怎么认领地块？</a><a data-q="BCT 是什么？">BCT 是什么？</a><a data-q="怎么开店卖货？">怎么开店？</a></div></div></div>' +
    '<div class="aiw-inputbar"><textarea id="aiwInput" placeholder="有问题尽管问…" maxlength="500"></textarea><button id="aiwSend">➤</button></div>';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    document.body.appendChild(bubble);
    document.body.appendChild(panel);

    var log = panel.querySelector('.aiw-log');
    var input = panel.querySelector('#aiwInput');
    var sendBtn = panel.querySelector('#aiwSend');
    var history = [];
    var busy = false;

    function isOpen() { return panel.classList.contains('open'); }
    function toggle(open) {
      panel.classList.toggle('open', open);
      try { localStorage.setItem(OPEN_KEY, open ? '1' : '0'); } catch (e) {}
      if (open) { input.focus(); scrollBottom(); } else { dismissBubble(); }
    }
    bubble.onclick = function () { toggle(!isOpen()); };

    function dismissBubble() {
      // 收起面板不打扰气泡；长按/右键才隐藏气泡（简单方案：关闭面板时气泡保留）
    }
    // 双击气泡 = 本周不再显示
    bubble.ondblclick = function () {
      try { localStorage.setItem(DISMISS_KEY, String(Date.now() + 7 * 86400 * 1000)); } catch (e) {}
      panel.classList.remove('open');
      bubble.remove(); panel.remove();
    };

    function scrollBottom() { log.scrollTop = log.scrollHeight; }

    function renderMsg(role, text, sources) {
      var empty = log.querySelector('.aiw-empty'); if (empty) empty.remove();
      var m = document.createElement('div');
      m.className = 'aiw-m ' + (role === 'user' ? 'u' : 'a');
      var b = document.createElement('div');
      b.className = 'b';
      b.textContent = text;
      if (sources && sources.length) {
        var s = document.createElement('div');
        s.className = 'src';
        s.textContent = '参考：';
        sources.forEach(function (x) {
          var a = document.createElement('a');
          a.href = x.url; a.target = '_blank'; a.textContent = x.title;
          s.appendChild(a);
        });
        b.appendChild(s);
      }
      m.appendChild(b);
      log.appendChild(m);
      scrollBottom();
      return b;
    }

    function ask() {
      var q = input.value.trim();
      if (!q || busy || !window.AiChat) return;
      busy = true; sendBtn.disabled = true;
      input.value = '';
      renderMsg('user', q);
      history.push({ role: 'user', content: q });
      try { localStorage.setItem(HIST_KEY, JSON.stringify(history.slice(-8))); } catch (e) {}

      var t = document.createElement('div');
      t.className = 'aiw-m a';
      t.innerHTML = '<div class="b"><span class="aiw-typing"><i></i><i></i><i></i></span></div>';
      log.appendChild(t); scrollBottom();
      var bubbleEl = t.querySelector('.b');
      var textNode = null;

      function finish() { t.remove(); busy = false; sendBtn.disabled = false; }

      AiChat.send(q, history.slice(0, -1), location.href, {
        onDelta: function (x) {
          if (!textNode) { bubbleEl.innerHTML = ''; textNode = document.createElement('span'); bubbleEl.appendChild(textNode); }
          textNode.textContent += x;
          scrollBottom();
        },
        onDone: function (res) {
          var content = textNode ? textNode.textContent : '';
          finish();
          renderMsg('assistant', content, res.sources || []);
          history.push({ role: 'assistant', content: content });
          try { localStorage.setItem(HIST_KEY, JSON.stringify(history.slice(-8))); } catch (e) {}
        },
        onLimit: function (msg) { finish(); renderMsg('assistant', msg || '已达限额，明天再来吧'); },
        onError: function (msg) { finish(); renderMsg('assistant', (msg || '服务异常') + '。也可到帮助中心查找教程'); }
      });
    }

    sendBtn.onclick = ask;
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); ask(); }
    });
    log.addEventListener('click', function (ev) {
      var a = ev.target.closest('.aiw-qs a');
      if (a) { input.value = a.getAttribute('data-q'); ask(); }
    });

    // 恢复上次会话与打开状态
    try {
      var saved = JSON.parse(localStorage.getItem(HIST_KEY) || '[]');
      saved.forEach(function (m) {
        renderMsg(m.role, m.content);
        history.push({ role: m.role, content: m.content });
      });
      if (localStorage.getItem(OPEN_KEY) === '1' && saved.length) panel.classList.add('open');
    } catch (e) {}
  });
})();
