<?php
/**
 * AI 全屏问答页（多轮会话，本地保留历史）
 * change: help-center-ai-assistant (task 3.5)
 */

require_once __DIR__ . '/../_init.php';

$aiEnabled = help_setting('ai_assistant_enabled', '1') === '1';
$initQ = isset($_GET['q']) ? mb_substr(trim((string)$_GET['q']), 0, 200) : '';
$isHelpHost = strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'help.') === 0;

require __DIR__ . '/../_layout.php';
help_header([
    'title' => 'AI 助手在线答疑',
    'description' => '向 58区块城市 AI 助手提问，基于官方教程回答，附带引用来源。',
    'active' => 'ask',
]);
?>

<div class="hc-breadcrumb"><a href="<?= e(help_url()) ?>">帮助中心</a> / AI 助手</div>

<style>
.ask-wrap { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; height: calc(100vh - 210px); min-height: 460px; }
.ask-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.ask-head .avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #ff8a3d, #ff6b00); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 19px; }
.ask-head h1 { font-size: 18px; }
.ask-head .sub { font-size: 12px; color: var(--muted); }
.ask-head .actions { margin-left: auto; display: flex; gap: 8px; }
.ask-head button { border: 1px solid var(--line); background: #fff; border-radius: 16px; padding: 6px 14px; font-size: 12px; color: var(--muted); cursor: pointer; }
.ask-head button:hover { border-color: var(--brand); color: var(--brand); }
.ask-log { flex: 1; overflow-y: auto; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; }
.msg { display: flex; gap: 10px; margin-bottom: 16px; }
.msg .who { width: 30px; height: 30px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center; font-size: 13px; }
.msg.user .who { background: #e5edf6; color: #4b6b9b; }
.msg.bot .who { background: #fff3e8; color: var(--brand); }
.msg .bubble { max-width: 86%; font-size: 14px; word-break: break-word; }
.msg.user .bubble { background: #f0f5fa; border-radius: 2px 12px 12px 12px; padding: 10px 14px; }
.msg.bot .bubble { background: #fff; border: 1px solid var(--line); border-radius: 12px 2px 12px 12px; padding: 10px 14px; }
.msg.bot .bubble .copy { margin-top: 8px; font-size: 12px; color: var(--muted); cursor: pointer; user-select: none; }
.msg.bot .bubble .copy:hover { color: var(--brand); }
.msg .sources { margin-top: 8px; font-size: 12px; }
.msg .sources a { display: inline-block; background: #fff7f0; border: 1px solid #ffd9b3; border-radius: 6px; padding: 3px 10px; margin: 2px 4px 2px 0; color: var(--brand-dark); }
.msg .sources a:hover { text-decoration: none; background: #ffedd6; }
.msg .ask-again { margin-top: 8px; font-size: 12px; color: var(--muted); }
.msg .ask-again a { color: var(--brand); cursor: pointer; }
.typing { display: inline-flex; gap: 4px; padding: 4px 0; }
.typing i { width: 6px; height: 6px; border-radius: 50%; background: #d1d5db; animation: blink 1.2s infinite; }
.typing i:nth-child(2) { animation-delay: .2s; } .typing i:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,80%,100% { opacity: .25; } 40% { opacity: 1; } }
.ask-inputbar { display: flex; gap: 10px; margin-top: 12px; }
.ask-inputbar textarea { flex: 1; border: 1px solid var(--line); border-radius: var(--radius); padding: 12px 16px; font-size: 14px; resize: none; height: 52px; outline: none; font-family: inherit; }
.ask-inputbar textarea:focus { border-color: var(--brand); }
.ask-inputbar button { border: none; background: var(--brand-grad); color: #fff; border-radius: var(--radius); padding: 0 26px; font-size: 15px; font-weight: 600; cursor: pointer; }
.ask-inputbar button:disabled { opacity: .5; cursor: default; }
.ask-tips { margin-top: 10px; font-size: 12px; color: var(--muted); text-align: center; }
.ask-empty { text-align: center; color: var(--muted); padding: 46px 10px; }
.ask-empty i { font-size: 46px; color: #ffd9b3; margin-bottom: 10px; display: block; }
.ask-empty .qs a { display: inline-block; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 7px 16px; margin: 4px; font-size: 13px; color: var(--ink); }
.ask-empty .qs a:hover { border-color: var(--brand); color: var(--brand); text-decoration: none; }
.ticket-form { margin-top: 8px; font-size: 12px; color: var(--muted); }
.ticket-form input { border: 1px solid var(--line); border-radius: 6px; padding: 4px 8px; font-size: 12px; width: 160px; margin: 0 4px; }
.ticket-form a { color: var(--brand); cursor: pointer; font-weight: 600; }
</style>

<div class="ask-wrap" id="askApp">
  <div class="ask-head">
    <div class="avatar"><i class="fa-solid fa-robot"></i></div>
    <div>
      <h1>AI 助手「小帮」</h1>
      <div class="sub">基于官方教程回答 · 7×24 在线 · 附引用来源</div>
    </div>
    <div class="actions">
      <button id="btnClear"><i class="fa-solid fa-eraser"></i> 清空会话</button>
    </div>
  </div>

  <div class="ask-log" id="askLog">
    <div class="ask-empty" id="askEmpty">
      <i class="fa-solid fa-comments"></i>
      <div>你好，我是小帮，关于平台的任何问题都可以问我～</div>
      <div class="qs" style="margin-top:14px">
        <a data-q="怎么认领一块虚拟地块？">怎么认领地块？</a>
        <a data-q="BCT 是什么，怎么获得？">BCT 是什么？</a>
        <a data-q="怎么在人气商城开店卖货？">怎么开店？</a>
        <a data-q="人气值和互访是怎么玩的？">互访怎么玩？</a>
      </div>
    </div>
  </div>

  <div class="ask-inputbar">
    <textarea id="askInput" placeholder="输入你的问题，Enter 发送，Shift+Enter 换行" maxlength="500"></textarea>
    <button id="askSend"><i class="fa-solid fa-paper-plane"></i> 发送</button>
  </div>
  <div class="ask-tips">回答由 AI 生成，仅供参考；涉及价格与规则请以官方教程为准。</div>
</div>

<script src="<?= $isHelpHost ? 'https://www.58.tl' : '' ?>/js/ai-client.js"></script>
<script>
(function () {
  <?php if ($isHelpHost): ?>window.AI_API_BASE = 'https://www.58.tl';<?php endif; ?>
  if (!window.AiChat) return; // ai-client 加载失败时静默

  var log = document.getElementById('askLog');
  var input = document.getElementById('askInput');
  var sendBtn = document.getElementById('askSend');
  var history = [];
  var busy = false;
  var STORE_KEY = 'help_ask_history_v1';

  // 恢复本地会话
  try {
    var saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || '[]');
    saved.forEach(function (m) { renderMsg(m.role, m.content, m.sources || null); history.push({ role: m.role, content: m.content }); });
    if (saved.length) { var em = document.getElementById('askEmpty'); if (em) em.remove(); scrollBottom(); }
  } catch (e) {}

  function persist() {
    try { sessionStorage.setItem(STORE_KEY, JSON.stringify(history.slice(-12))); } catch (e) {}
  }

  function scrollBottom() { log.scrollTop = log.scrollHeight; }

  function renderMsg(role, content, sources, chatLogId) {
    var empty = document.getElementById('askEmpty'); if (empty) empty.remove();
    var div = document.createElement('div');
    div.className = 'msg ' + (role === 'user' ? 'user' : 'bot');
    var who = document.createElement('div');
    who.className = 'who';
    who.innerHTML = role === 'user' ? '<i class="fa-solid fa-user"></i>' : '<i class="fa-solid fa-robot"></i>';
    var bub = document.createElement('div');
    bub.className = 'bubble';
    var body = document.createElement('div');
    body.className = 'text';
    body.textContent = content;
    bub.appendChild(body);
    if (role === 'assistant') {
      var cp = document.createElement('div');
      cp.className = 'copy'; cp.textContent = '复制';
      cp.onclick = function () {
        navigator.clipboard && navigator.clipboard.writeText(body.textContent).then(function () { cp.textContent = '已复制 ✓'; setTimeout(function () { cp.textContent = '复制'; }, 1500); });
      };
      bub.appendChild(cp);
      if (sources && sources.length) {
        var s = document.createElement('div');
        s.className = 'sources';
        s.innerHTML = '<i class="fa-solid fa-book-open"></i> 参考：';
        sources.forEach(function (x) {
          var a = document.createElement('a');
          a.href = x.url; a.target = '_blank'; a.textContent = x.title;
          s.appendChild(a);
        });
        bub.appendChild(s);
      }
      if (chatLogId) {
        var tk = document.createElement('div');
        tk.className = 'ask-again';
        tk.innerHTML = '没解决？<a>留言给人工处理</a>';
        tk.querySelector('a').onclick = function () { openTicket(content, chatLogId, tk); };
        bub.appendChild(tk);
      }
    }
    div.appendChild(who); div.appendChild(bub);
    log.appendChild(div);
    scrollBottom();
    return body;
  }

  function renderTyping() {
    var t = document.createElement('div');
    t.className = 'msg bot'; t.id = 'askTyping';
    t.innerHTML = '<div class="who"><i class="fa-solid fa-robot"></i></div><div class="bubble"><span class="typing"><i></i><i></i><i></i></span></div>';
    log.appendChild(t); scrollBottom();
    return t;
  }

  function openTicket(content, chatLogId, anchor) {
    var form = document.createElement('div');
    form.className = 'ticket-form';
    form.innerHTML = '联系方式(选填，方便回复你)：<input type="text" placeholder="微信/邮箱/手机号"> <a>提交留言</a>';
    var submitting = false;
    form.querySelector('a').onclick = function () {
      if (submitting) return; submitting = true;
      var contact = form.querySelector('input').value.trim();
      AiChat.submitTicket({ question: content.slice(0, 1000), contact: contact, chat_log_id: chatLogId }, function (ok, msg) {
        form.textContent = msg || (ok ? '已提交' : '提交失败');
      });
    };
    anchor.appendChild(form);
  }

  function ask() {
    var q = input.value.trim();
    if (!q || busy) return;
    busy = true; sendBtn.disabled = true;
    input.value = '';
    renderMsg('user', q);
    history.push({ role: 'user', content: q });
    persist();

    var typing = renderTyping();
    var bubbleText = null;
    var removed = false;
    function ensureBubble() {
      if (removed || bubbleText) return;
      typing.querySelector('.bubble').innerHTML = '';
      bubbleText = document.createElement('div');
      bubbleText.className = 'text';
      typing.querySelector('.bubble').insertBefore(bubbleText, typing.querySelector('.copy') || null);
    }
    function finish() {
      typing.remove(); removed = true;
      busy = false; sendBtn.disabled = false;
    }

    AiChat.send(q, history.slice(0, -1), location.href, {
      onDelta: function (text) {
        ensureBubble();
        if (!bubbleText) return;
        bubbleText.textContent += text;
        scrollBottom();
      },
      onDone: function (res) {
        var content = bubbleText ? bubbleText.textContent : '';
        finish();
        renderMsg('assistant', content, res.sources || [], res.log_id);
        history.push({ role: 'assistant', content: content });
        persist();
      },
      onLimit: function (msg) {
        finish();
        renderMsg('assistant', msg || '已达今日提问限额');
      },
      onError: function (msg) {
        finish();
        renderMsg('assistant', (msg || '服务异常') + '，请稍后再试');
      }
    });
  }

  sendBtn.onclick = ask;
  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); ask(); }
  });
  document.getElementById('btnClear').onclick = function () {
    history = [];
    try { sessionStorage.removeItem(STORE_KEY); } catch (e) {}
    log.innerHTML = '<div class="ask-empty" id="askEmpty"><i class="fa-solid fa-comments"></i><div>会话已清空，有什么想问的？</div></div>';
  };

  // 初始问题（来自搜索页/空状态引导）
  var initQ = <?= json_encode($initQ, JSON_UNESCAPED_UNICODE) ?>;
  if (initQ) { input.value = initQ; ask(); }
})();
</script>

<?php help_footer(); ?>
