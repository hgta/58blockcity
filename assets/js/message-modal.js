/**
 * 统一站内信弹窗
 * 用法: <a href="javascript:void(0)" onclick="openMessage(userId, 'username')">@username</a>
 */
(function(){
    if (window._messageModalInit) return;
    window._messageModalInit = true;

    // 注入模态框 HTML
    var modal = document.createElement('div');
    modal.id = 'msg-modal';
    modal.innerHTML = '<div class="msg-overlay" onclick="closeMsgModal(event)"></div>' +
        '<div class="msg-dialog">' +
            '<div class="msg-header"><span class="msg-title"></span><span class="msg-close" onclick="closeMsgModal()">&times;</span></div>' +
            '<div class="msg-body" id="msg-body"><p class="msg-loading">加载中...</p></div>' +
            '<form class="msg-form" onsubmit="sendMsg(event)">' +
                '<input type="text" name="msg_text" maxlength="500" placeholder="输入消息..." autocomplete="off">' +
                '<button type="submit">发送</button>' +
            '</form>' +
        '</div>';
    modal.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:10000;';

    // 样式（先创建 style，等 DOM ready 后统一注入）
    var style = document.createElement('style');
    style.textContent = '.msg-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5)}' +
        '.msg-dialog{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;max-width:90%;max-height:80%;background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3)}' +
        '.msg-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #eee;font-size:15px;font-weight:bold}' +
        '.msg-close{font-size:24px;cursor:pointer;color:#999;line-height:1}' +
        '.msg-body{flex:1;overflow-y:auto;padding:12px 18px;min-height:120px;max-height:300px}' +
        '.msg-item{display:flex;gap:10px;margin-bottom:12px}' +
        '.msg-avatar{width:32px;height:32px;border-radius:50%;background:#f0f0f0;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center}' +
        '.msg-avatar img{width:100%;height:100%;object-fit:cover}' +
        '.msg-avatar i{font-size:16px;color:#ccc}' +
        '.msg-content{flex:1;min-width:0}' +
        '.msg-name{font-size:12px;color:#999;margin-bottom:2px}' +
        '.msg-text{font-size:14px;color:#333;word-break:break-all;line-height:1.5}' +
        '.msg-form{display:flex;gap:8px;padding:12px 18px;border-top:1px solid #eee}' +
        '.msg-form input{flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:20px;font-size:14px;outline:none}' +
        '.msg-form button{padding:10px 18px;background:#ff6b00;color:#fff;border:none;border-radius:20px;cursor:pointer;font-size:14px;white-space:nowrap}' +
        '.msg-loading{text-align:center;color:#999;padding:20px}' +
        '.msg-empty{text-align:center;color:#ccc;padding:30px;font-size:14px}';
    // 等 body 可用后再注入
    function injectDOM() {
        document.body.appendChild(modal);
        document.head.appendChild(style);
    }
    if (document.body) {
        injectDOM();
    } else {
        document.addEventListener('DOMContentLoaded', injectDOM);
    }
})();

var _msgToId = 0, _msgToName = '';

function openMessage(userId, username) {
    _msgToId = parseInt(userId);
    _msgToName = username;
    var modal = document.getElementById('msg-modal');
    var body = document.getElementById('msg-body');
    var title = modal.querySelector('.msg-title');
    title.textContent = '给 @' + username + ' 发消息';
    body.innerHTML = '<p class="msg-loading">加载中...</p>';
    modal.style.display = 'block';
    modal.querySelector('.msg-form input').value = '';
    modal.querySelector('.msg-form input').focus();

    // 加载最近消息
    fetch('/messages/ajax.php?action=recent&with=' + _msgToId)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data || !data.length) {
                body.innerHTML = '<p class="msg-empty">暂无对话，发送第一条消息吧~</p>';
                return;
            }
            body.innerHTML = '';
            data.forEach(function(m){
                var isMe = m.from_user_id != _msgToId;
                var div = document.createElement('div');
                div.className = 'msg-item';
                div.innerHTML = '<div class="msg-avatar">' + (m.user_avatar ? '<img src="/assets/images/' + m.user_avatar + '">' : '<i class="fas fa-user"></i>') + '</div>' +
                    '<div class="msg-content"><div class="msg-name">' + (isMe ? '我' : m.username) + '</div><div class="msg-text">' + escHtml(m.message) + '</div></div>';
                body.appendChild(div);
            });
            body.scrollTop = body.scrollHeight;
        }).catch(function(){ body.innerHTML = '<p class="msg-empty">加载失败</p>'; });
}

function sendMsg(e) {
    e.preventDefault();
    var input = document.querySelector('#msg-modal .msg-form input');
    var text = input.value.trim();
    if (!text || !_msgToId) return;
    var body = document.getElementById('msg-body');
    var btn = document.querySelector('#msg-modal .msg-form button');
    btn.disabled = true; btn.textContent = '发送中...';

    fetch('/messages/ajax.php?action=send', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'to_user_id=' + _msgToId + '&message=' + encodeURIComponent(text)
    }).then(function(r){ return r.json(); })
      .then(function(data){
          if (data.success) {
              input.value = '';
              // 追加到列表
              var div = document.createElement('div');
              div.className = 'msg-item';
              div.innerHTML = '<div class="msg-avatar"><i class="fas fa-user"></i></div>' +
                  '<div class="msg-content"><div class="msg-name">我</div><div class="msg-text">' + escHtml(text) + '</div></div>';
              body.appendChild(div);
              body.scrollTop = body.scrollHeight;
          }
      }).finally(function(){ btn.disabled = false; btn.textContent = '发送'; });
}

function closeMsgModal(e) {
    if (e && e.target !== document.querySelector('.msg-overlay')) return;
    document.getElementById('msg-modal').style.display = 'none';
    _msgToId = 0;
}

function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
