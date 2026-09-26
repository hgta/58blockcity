/**
 * AI 助手共享客户端（问答页与悬浮窗共用）
 * change: help-center-ai-assistant (task 3.5)
 *
 * 用法:
 *   AiChat.send(question, history, page, {
 *     onDelta: function(text){},          // 增量文本
 *     onDone: function(res){},            // {matched, sources:[{title,url}], log_id}
 *     onError: function(msg){},
 *     onLimit: function(msg){}
 *   });
 *   AiChat.submitTicket({question, contact, chat_log_id}, function(ok, msg){});
 *
 * 跨子域自动选择 API 地址；如需强制指定，先设置 window.AI_API_BASE。
 */
window.AiChat = (function () {
  var API_BASE;
  if (typeof window.AI_API_BASE !== 'undefined') {
    API_BASE = window.AI_API_BASE;
  } else {
    var h = location.hostname;
    // www 主站同源直连；其他子域（help.58.tl 等）走主站 API
    API_BASE = (h === 'www.58.tl' || h === '58.tl' || h === 'localhost' || h === '127.0.0.1')
      ? '' : 'https://www.58.tl';
  }

  function readSse(res, cb) {
    var ct = (res.headers.get('content-type') || '').toLowerCase();
    // 错误/降级响应：非 SSE（JSON 错误体，含被网关包装的 5xx）同样按错误处理
    if (!res.ok || (ct.indexOf('text/event-stream') === -1 && ct.indexOf('application/json') !== -1)) {
      res.text().then(function (t) {
        var m = '';
        try { m = JSON.parse(t).msg || ''; } catch (e) {
          var mt = t.match(/data:\s*(\{[\s\S]*?\})/);
          if (mt) { try { m = JSON.parse(mt[1]).msg || ''; } catch (e2) {} }
        }
        if (!m) m = '请求失败(' + res.status + ')';
        if (res.status === 429) { cb.onLimit && cb.onLimit(m); }
        else { cb.onError && cb.onError(m); }
      }).catch(function () { cb.onError && cb.onError('网络异常'); });
      return;
    }
    var reader = res.body.getReader();
    var decoder = new TextDecoder('utf-8');
    var buf = '';
    function pump() {
      return reader.read().then(function (r) {
        if (r.done) return;
        buf += decoder.decode(r.value, { stream: true });
        var lines = buf.split('\n');
        buf = lines.pop();
        for (var i = 0; i < lines.length; i++) {
          var line = lines[i].trim();
          if (line.indexOf('data:') !== 0) continue;
          var data;
          try { data = JSON.parse(line.slice(5).trim()); } catch (e) { continue; }
          if (data.type === 'delta' && cb.onDelta) cb.onDelta(data.text || '');
          else if (data.type === 'done' && cb.onDone) cb.onDone(data);
          else if (data.type === 'limit' && cb.onLimit) cb.onLimit(data.msg || '已达限额');
          else if (data.type === 'error' && cb.onError) cb.onError(data.msg || '服务异常');
        }
        return pump();
      });
    }
    pump().catch(function () { cb.onError && cb.onError('连接中断'); });
  }

  function send(question, history, page, cb) {
    fetch(API_BASE + '/api/ai/chat.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question: question, history: history || [], page: page || location.href })
    }).then(function (res) {
      readSse(res, cb);
    }).catch(function () {
      cb.onError && cb.onError('网络异常，请稍后再试');
    });
  }

  function submitTicket(payload, cb) {
    fetch(API_BASE + '/api/ai/ticket.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (res) { return res.json(); })
      .then(function (j) { cb && cb(!!j.ok, j.msg || ''); })
      .catch(function () { cb && cb(false, '网络异常'); });
  }

  return { send: send, submitTicket: submitTicket, apiBase: function () { return API_BASE; } };
})();
