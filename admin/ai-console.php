<?php
/**
 * AI 训练台（管理员与 Hermes 智能体对话、调教与沉淀）
 * change: admin-ai-training-console (task 4.1-4.4)
 *
 * - 左栏：Hermes 原生会话（新建/切换/fork/删除）
 * - 主区：流式对话（api/ai/console.php 代理 SSE 透传）
 * - 每条 AI 回答旁沉淀按钮：存为FAQ / 存为文章草稿 / 写入记忆 / 发布到小帮
 * - 卡片：小帮人设编辑（system_settings） / 未命中问题看板
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
checkAdmin();

$admin_site_config = ['site' => 'main', 'page_title' => 'AI 训练台'];
require_once '../shared/admin/admin-header.php';
?>
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-graduation-cap"></i> AI 训练台（调教 Hermes 智能体，沉淀训练成果）</span>
    </div>
    <div class="admin-card-body" style="padding:10px 16px;font-size:12px;color:#94a3b8;">
        训练对话使用 Hermes 原生会话与独立记忆区（web:58tl:admin），不影响前台小帮；「发布到小帮」才会写入前台共享记忆区（web:58tl:assistant）。
        重要事实类内容建议优先「存为FAQ/文章」（进入知识库，任何渠道可用），记忆区仅存人设微调级内容（容量约2200字符）。
    </div>
</div>

<div style="display:grid;grid-template-columns:260px 1fr;gap:16px;align-items:start;">
    <!-- 左栏：会话列表 -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title" style="font-size:14px;"><i class="fas fa-comments"></i> 训练会话</span>
            <button id="btnNewSession" class="admin-btn admin-btn-success admin-btn-sm">+ 新建</button>
        </div>
        <div class="admin-card-body" style="padding:8px;">
            <div id="sessionList" style="max-height:420px;overflow-y:auto;">
                <div style="color:#64748b;padding:12px;text-align:center;font-size:12px;">加载中…</div>
            </div>
        </div>
    </div>

    <!-- 主区：对话 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title" style="font-size:14px;"><i class="fas fa-robot"></i> 对话区
                <span id="curSessionLabel" style="color:#64748b;font-weight:normal;"></span></span>
            <span>
                <button id="btnFork" class="admin-btn admin-btn-secondary admin-btn-sm" title="基于当前会话分叉实验，母会话不受影响">⑂ 分叉实验</button>
            </span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <div id="chatBox" style="height:420px;overflow-y:auto;padding:14px;background:#0b1220;font-size:13px;line-height:1.7;">
                <div style="color:#64748b;text-align:center;padding-top:120px;">选择或新建一个训练会话开始对话</div>
            </div>
            <div style="display:flex;gap:8px;padding:10px;border-top:1px solid #1e293b;">
                <textarea id="chatInput" rows="2" placeholder="输入训练指令…（Ctrl+Enter 发送）"
                    style="flex:1;resize:none;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></textarea>
                <button id="btnSend" class="admin-btn admin-btn-primary">发送</button>
            </div>
        </div>
    </div>
</div>

<!-- 沉淀弹层 -->
<div id="distillModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:10px;width:640px;max-width:94vw;max-height:88vh;overflow-y:auto;padding:20px;">
        <h3 id="distillTitle" style="margin:0 0 12px;font-size:16px;color:#f1f5f9;"></h3>
        <div id="distillForm" style="display:grid;gap:10px;"></div>
        <div style="margin-top:14px;display:flex;gap:10px;justify-content:flex-end;">
            <button class="admin-btn admin-btn-secondary" onclick="closeDistill()">取消</button>
            <button id="distillSubmit" class="admin-btn admin-btn-primary">提交</button>
        </div>
        <div id="distillResult" style="margin-top:10px;font-size:13px;"></div>
    </div>
</div>

<!-- 小帮人设 + 未命中看板 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title" style="font-size:14px;"><i class="fas fa-user-pen"></i> 小帮人设
            <span id="promptSource" style="font-size:11px;color:#64748b;font-weight:normal;"></span></span></div>
        <div class="admin-card-body">
            <textarea id="promptText" rows="8" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:12px;padding:10px;"></textarea>
            <div style="margin-top:8px;display:flex;gap:10px;align-items:center;">
                <button id="btnSavePrompt" class="admin-btn admin-btn-primary admin-btn-sm">保存（前台立即生效）</button>
                <button id="btnResetPrompt" class="admin-btn admin-btn-secondary admin-btn-sm">恢复默认</button>
                <span style="font-size:11px;color:#64748b;">留空保存=恢复默认；RAG知识注入等结构性逻辑不受此影响</span>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title" style="font-size:14px;"><i class="fas fa-chart-simple"></i> 未命中问题看板（近30天）</span></div>
        <div class="admin-card-body" style="padding:0;">
            <div id="unmatchedBox" style="max-height:300px;overflow-y:auto;">
                <div style="color:#64748b;padding:16px;text-align:center;font-size:12px;">加载中…</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CONSOLE_API = '../api/ai/console.php';
    var curSession = null;   // {id, title}
    var lastAnswer = '';     // 最近一条 AI 回答（沉淀用）
    var lastQuestion = '';

    // ---------- 工具 ----------
    function $(id) { return document.getElementById(id); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    async function api(action, body) {
        var opts = body ? {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(Object.assign({action: action}, body))}
                        : {};
        var r = await fetch(CONSOLE_API + (body ? '' : '?action=' + action), opts);
        return r.json();
    }
    function scrollChat() { var b = $('chatBox'); b.scrollTop = b.scrollHeight; }
    function addMsg(role, html) {
        var d = document.createElement('div');
        d.style.cssText = 'margin-bottom:12px;' + (role === 'user' ? 'text-align:right;' : '');
        d.innerHTML = role === 'user'
            ? '<div style="display:inline-block;max-width:85%;background:#1e3a5f;border-radius:10px;padding:8px 12px;text-align:left;">' + html + '</div>'
            : '<div style="display:inline-block;max-width:92%;background:#111c30;border:1px solid #1e293b;border-radius:10px;padding:10px 12px;white-space:pre-wrap;">' + html + '</div>';
        $('chatBox').appendChild(d);
        scrollChat();
        return d;
    }

    // ---------- 会话列表 ----------
    function sessionId(s) { return s.id || s.session_id || (s.session && s.session.id); }
    function sessionTitle(s) { return s.title || s.name || (s.session && s.session.title) || ('会话 ' + sessionId(s)); }
    async function loadSessions() {
        try {
            var res = await api('sessions');
            var list = (res.data && (res.data.sessions || res.data.items || res.data)) || [];
            if (!Array.isArray(list)) list = [];
            $('sessionList').innerHTML = list.length ? '' : '<div style="color:#64748b;padding:12px;text-align:center;font-size:12px;">暂无会话，点右上「+ 新建」</div>';
            list.forEach(function (s) {
                var id = sessionId(s); if (!id) return;
                var row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:center;gap:6px;padding:8px;border-radius:6px;cursor:pointer;border:1px solid transparent;';
                row.onmouseenter = function(){ row.style.background = '#111c30'; };
                row.onmouseleave = function(){ row.style.background = ''; };
                row.innerHTML = '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;">' + esc(sessionTitle(s)) + '</span>'
                    + '<button title="分叉" class="admin-btn admin-btn-sm admin-btn-secondary" data-fork="' + esc(id) + '">⑂</button>'
                    + '<button title="删除" class="admin-btn admin-btn-sm admin-btn-danger" data-del="' + esc(id) + '">✕</button>';
                row.onclick = function (e) {
                    if (e.target.dataset.fork !== undefined) return forkSession(e.target.dataset.fork);
                    if (e.target.dataset.del  !== undefined) return delSession(e.target.dataset.del);
                    openSession(id, sessionTitle(s));
                };
                $('sessionList').appendChild(row);
            });
        } catch (e) {
            $('sessionList').innerHTML = '<div style="color:#ef4444;padding:12px;font-size:12px;">加载失败：' + esc(e.message) + '</div>';
        }
    }

    async function newSession() {
        var title = prompt('会话标题（用于区分调教主题）', '训练 ' + new Date().toLocaleDateString());
        if (title === null) return;
        var res = await api('create_session', {title: title});
        if (!res.ok) return alert('创建失败：' + res.msg);
        var s = res.data || {};
        await loadSessions();
        openSession(sessionId(s) || '', s.title || title);
    }
    async function forkSession(id) {
        if (!confirm('基于该会话分叉出新实验会话？')) return;
        var res = await api('fork_session', {id: id});
        if (!res.ok) return alert('分叉失败：' + res.msg);
        await loadSessions();
        var s = res.data || {};
        openSession(sessionId(s) || '', (sessionTitle(s)) + '（分叉）');
    }
    async function delSession(id) {
        if (!confirm('确认删除该会话？')) return;
        var res = await api('delete_session', {id: id});
        if (!res.ok) return alert('删除失败：' + res.msg);
        if (curSession && curSession.id === id) { curSession = null; $('curSessionLabel').textContent = ''; $('chatBox').innerHTML = ''; }
        loadSessions();
    }

    async function openSession(id, title) {
        curSession = {id: id, title: title};
        $('curSessionLabel').textContent = ' — ' + title;
        $('chatBox').innerHTML = '<div style="color:#64748b;padding:12px;">加载历史消息…</div>';
        try {
            var res = await fetch(CONSOLE_API + '?action=messages&id=' + encodeURIComponent(id));
            var j = await res.json();
            var msgs = (j.data && (j.data.messages || j.data.items || j.data)) || [];
            $('chatBox').innerHTML = '';
            if (!Array.isArray(msgs) || !msgs.length) {
                $('chatBox').innerHTML = '<div style="color:#64748b;text-align:center;padding-top:40px;">（新会话）发送第一条训练指令</div>';
            } else {
                msgs.forEach(function (m) {
                    var role = m.role === 'user' || m.role === 'human' ? 'user' : 'ai';
                    var text = m.content || m.text || m.message || '';
                    addMsg(role, esc(text));
                });
            }
        } catch (e) {
            $('chatBox').innerHTML = '<div style="color:#ef4444;padding:12px;">历史加载失败：' + esc(e.message) + '</div>';
        }
    }

    // ---------- 对话（SSE 透传消费） ----------
    var sending = false;
    async function send() {
        var text = $('chatInput').value.trim();
        if (!text || sending) return;
        if (!curSession) return alert('请先选择或新建会话');
        sending = true; $('btnSend').disabled = true;
        $('chatInput').value = '';
        addMsg('user', esc(text));
        lastQuestion = text;
        var aiDiv = addMsg('ai', '');
        var bubble = aiDiv.firstChild;
        var raw = '';

        try {
            var resp = await fetch(CONSOLE_API, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'chat', session_id: curSession.id, message: text})
            });
            var reader = resp.body.getReader();
            var dec = new TextDecoder();
            var buf = '';
            while (true) {
                var r = await reader.read();
                if (r.done) break;
                buf += dec.decode(r.value, {stream: true});
                var lines = buf.split('\n'); buf = lines.pop();
                lines.forEach(function (ln) {
                    ln = ln.trim();
                    if (ln.indexOf('data:') !== 0) return;
                    var payload = ln.slice(5).trim();
                    if (payload === '' || payload === '[DONE]') return;
                    try {
                        var o = JSON.parse(payload);
                        if (o.type === 'error') { bubble.innerHTML += '<span style="color:#ef4444">' + esc(o.msg) + '</span>'; return; }
                        var t = o.content || o.delta || o.text
                              || (o.choices && o.choices[0] && ((o.choices[0].delta && o.choices[0].delta.content) || (o.choices[0].message && o.choices[0].message.content)))
                              || '';
                        if (t) { raw += t; bubble.textContent = raw; scrollChat(); }
                    } catch (e) { /* 非 JSON 行忽略 */ }
                });
            }
            lastAnswer = raw;
            if (raw) addDistillBar(aiDiv);
        } catch (e) {
            bubble.innerHTML = '<span style="color:#ef4444">请求失败：' + esc(e.message) + '</span>';
        }
        sending = false; $('btnSend').disabled = false;
    }

    function addDistillBar(aiDiv) {
        var bar = document.createElement('div');
        bar.style.cssText = 'margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;';
        bar.innerHTML =
            '<button class="admin-btn admin-btn-sm admin-btn-secondary" data-a="faq">存为FAQ</button>' +
            '<button class="admin-btn admin-btn-sm admin-btn-secondary" data-a="article">存为文章草稿</button>' +
            '<button class="admin-btn admin-btn-sm admin-btn-secondary" data-a="mem">写入记忆(试验)</button>' +
            '<button class="admin-btn admin-btn-sm admin-btn-success" data-a="pub">发布到小帮</button>';
        bar.onclick = function (e) {
            var a = e.target.dataset.a; if (!a) return;
            if (a === 'faq') openDistillFaq();
            else if (a === 'article') openDistillArticle();
            else openDistillMemory(a === 'pub');
        };
        aiDiv.appendChild(bar);
    }

    // ---------- 沉淀弹层 ----------
    function closeDistill() { $('distillModal').style.display = 'none'; }
    function field(label, name, val, ta) {
        return '<div><label style="display:block;font-size:12px;margin-bottom:4px;color:#94a3b8;">' + label + '</label>'
            + (ta
                ? '<textarea name="' + name + '" rows="' + ta + '" style="width:100%;background:#0b1220;border:1px solid #334155;border-radius:6px;color:#f1f5f9;padding:8px;font-size:12px;">' + esc(val) + '</textarea>'
                : '<input name="' + name + '" value="' + esc(val) + '" style="width:100%;background:#0b1220;border:1px solid #334155;border-radius:6px;color:#f1f5f9;padding:8px;font-size:12px;">')
            + '</div>';
    }
    function openModal(title, formHtml, onSubmit) {
        $('distillTitle').textContent = title;
        $('distillForm').innerHTML = formHtml;
        $('distillResult').innerHTML = '';
        $('distillModal').style.display = 'flex';
        $('distillSubmit').onclick = async function () {
            var data = {};
            $('distillForm').querySelectorAll('[name]').forEach(function (el) { data[el.name] = el.value; });
            $('distillSubmit').disabled = true;
            try { await onSubmit(data); } finally { $('distillSubmit').disabled = false; }
        };
    }

    function openDistillFaq() {
        openModal('沉淀为 FAQ 草稿（发布后进入小帮 RAG 知识库）',
            field('分类ID（11=规则与FAQ）', 'category_id', '11')
            + field('问题 *', 'question', lastQuestion)
            + field('回答 *', 'answer', lastAnswer, 6),
            async function (d) {
                var res = await api('distill_faq', d);
                $('distillResult').innerHTML = res.ok
                    ? '<span style="color:#22c55e">已创建 FAQ 草稿 #' + res.data.faq_id + '，请到「FAQ管理」完善发布</span>'
                    : '<span style="color:#ef4444">' + esc(res.msg) + '</span>';
            });
    }
    function openDistillArticle() {
        openModal('沉淀为帮助文章草稿',
            field('分类ID（1=新手上路…见帮助分类页）', 'category_id', '1')
            + field('标题 *', 'title', lastQuestion)
            + field('摘要', 'summary', '')
            + field('正文（支持HTML）*', 'content', lastAnswer, 8),
            async function (d) {
                var res = await api('distill_article', d);
                $('distillResult').innerHTML = res.ok
                    ? '<span style="color:#22c55e">已创建文章草稿 #' + res.data.article_id + '，请到「帮助文章」编辑发布</span>'
                    : '<span style="color:#ef4444">' + esc(res.msg) + '</span>';
            });
    }
    function openDistillMemory(isPublish) {
        openModal(isPublish ? '发布到小帮（写入前台共享记忆区）' : '写入记忆（训练试验区）',
            field('记忆内容 *（人设微调级，≤500字；事实类知识请改用FAQ/文章沉淀）', 'content', '', 5)
            + '<div style="font-size:11px;color:#64748b;">写入后将自动发送验证问题确认 Hermes 已记住；Hermes 记忆区容量约2200字符，由其自行整理淘汰。</div>',
            async function (d) {
                d.session_id = curSession ? curSession.id : '';
                var res = await api(isPublish ? 'memory_publish' : 'memory_write', d);
                if (!res.ok) { $('distillResult').innerHTML = '<span style="color:#ef4444">' + esc(res.msg) + '</span>'; return; }
                var v = res.data;
                $('distillResult').innerHTML =
                    (v.verified ? '<span style="color:#22c55e">✔ 写入并验证通过</span>' : '<span style="color:#f59e0b">⚠ 写入已发送，但验证未确认，请人工抽查</span>')
                    + '<div style="margin-top:6px;color:#94a3b8;">验证回复：' + esc(v.verify_reply) + '</div>';
            });
    }

    // ---------- 人设编辑 ----------
    var DEFAULT_PROMPT = ''; // 留空：占位展示见下方 placeholder
    async function loadPrompt() {
        var res = await api('prompt');
        if (!res.ok) return;
        $('promptText').value = res.data.prompt;
        $('promptText').placeholder = '（当前使用内置默认人设，输入自定义文案后保存即覆盖）';
        $('promptSource').textContent = res.data.source === 'custom' ? '（当前：自定义）' : '（当前：内置默认）';
    }
    async function savePrompt(prompt) {
        var res = await api('save_prompt', {prompt: prompt});
        if (!res.ok) return alert(res.msg);
        $('promptSource').textContent = prompt === '' ? '（当前：内置默认）' : '（当前：自定义）';
        alert('已保存，前台小帮下次对话即生效');
    }

    // ---------- 未命中看板 ----------
    async function loadUnmatched() {
        try {
            var res = await api('unmatched');
            var rows = res.data || [];
            $('unmatchedBox').innerHTML = rows.length ? '' : '<div style="color:#64748b;padding:16px;text-align:center;font-size:12px;">近30天没有未命中记录 🎉</div>';
            rows.slice(0, 20).forEach(function (r) {
                var d = document.createElement('div');
                d.style.cssText = 'display:flex;gap:10px;align-items:center;padding:8px 14px;border-bottom:1px solid #1e293b;cursor:pointer;';
                d.title = '点击带入对话区回答并沉淀';
                d.innerHTML = '<span style="min-width:34px;text-align:center;background:#7c2d12;border-radius:6px;color:#fdba74;font-size:11px;padding:2px 6px;">' + r.cnt + '次</span>'
                    + '<span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(r.question) + '</span>';
                d.onclick = function () {
                    if (!$('chatInput').value) $('chatInput').value = '用户高频未命中问题（近30天' + r.cnt + '次）：' + r.question + '\n请给出准确的官方解答：';
                    $('chatInput').focus();
                    window.scrollTo({top: 0, behavior: 'smooth'});
                };
                $('unmatchedBox').appendChild(d);
            });
        } catch (e) {
            $('unmatchedBox').innerHTML = '<div style="color:#ef4444;padding:12px;font-size:12px;">加载失败：' + esc(e.message) + '</div>';
        }
    }

    // ---------- 绑定 ----------
    $('btnNewSession').onclick = newSession;
    $('btnFork').onclick = function () { curSession ? forkSession(curSession.id) : alert('请先选择会话'); };
    $('btnSend').onclick = send;
    $('chatInput').onkeydown = function (e) { if (e.ctrlKey && e.key === 'Enter') send(); };
    $('btnSavePrompt').onclick = function () { savePrompt($('promptText').value.trim()); };
    $('btnResetPrompt').onclick = function () { if (confirm('确认恢复内置默认人设？')) { $('promptText').value = ''; savePrompt(''); } };

    loadSessions(); loadPrompt(); loadUnmatched();
})();
</script>

<?php require_once '../shared/admin/admin-footer.php'; ?>
