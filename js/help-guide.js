/**
 * 场景化帮助引导（锚点驱动，无依赖）
 * change: help-center-ai-assistant (task 4.4)
 *
 * 页面埋点约定：
 *   data-help-complex="slug"   复杂页面：首次访问顶部教学横幅（关闭后 localStorage 不再出现）
 *   data-help-empty="slug"     空状态：容器内渲染"入门指南"引导卡
 *   data-help-hint="slug"      关键操作：元素旁渲染"先看指南"小提示
 *   （slug 为帮助中心文章 slug，链接到 https://help.58.tl/article/{slug}）
 */
(function () {
  if (window.__HELP_GUIDE_LOADED) return;
  window.__HELP_GUIDE_LOADED = true;

  var HELP_BASE = (typeof window.HELP_GUIDE_BASE !== 'undefined')
    ? window.HELP_GUIDE_BASE
    : (location.hostname.indexOf('help.') === 0 ? location.origin + '/' : 'https://help.58.tl/');

  function artUrl(slug) { return HELP_BASE + 'article/' + slug; }
  function askUrl() { return HELP_BASE + 'ask'; }

  function addStyle() {
    var css = [
      '.hgc-banner{position:relative;background:linear-gradient(90deg,#fff7f0,#fff);border:1px solid #ffd9b3;border-radius:12px;padding:12px 44px 12px 16px;margin:14px 0;display:flex;align-items:center;gap:12px;font-size:14px;color:#4b3a2f}',
      '.hgc-banner .ic{font-size:22px}',
      '.hgc-banner a{color:#ff6b00;font-weight:600;white-space:nowrap}',
      '.hgc-banner .x{position:absolute;top:8px;right:10px;border:none;background:none;font-size:16px;color:#b0a79f;cursor:pointer;padding:4px}',
      '.hgc-banner .x:hover{color:#666}',
      '.hgc-empty-card{margin-top:14px;padding:14px 16px;border:1px dashed #ffd9b3;border-radius:12px;background:#fffaf5;text-align:center;font-size:13px;color:#9c8877}',
      '.hgc-empty-card a{display:inline-block;margin-top:8px;background:linear-gradient(135deg,#ff8a3d,#ff6b00);color:#fff;border-radius:16px;padding:6px 18px;font-weight:600;text-decoration:none}',
      '.hgc-hint{display:inline-block;margin-left:8px;font-size:12px;color:#ff6b00;white-space:nowrap;text-decoration:none;border-bottom:1px dashed #ff6b00}'
    ].join('');
    var s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
  }

  function init() {
    addStyle();

    // 1) 复杂页面横幅
    document.querySelectorAll('[data-help-complex]').forEach(function (el) {
      var slug = el.getAttribute('data-help-complex');
      if (!slug) return;
      var seenKey = 'hgc_seen_' + slug;
      try { if (localStorage.getItem(seenKey) === '1') return; } catch (e) {}
      var banner = document.createElement('div');
      banner.className = 'hgc-banner';
      banner.innerHTML = '<span class="ic">📖</span><span>第一次来这里？花 3 分钟看篇教程，马上就能上手。</span>' +
        '<a href="' + artUrl(slug) + '" target="_blank">查看入门教程 →</a>' +
        '<button class="x" title="不再提示" aria-label="关闭">✕</button>';
      banner.querySelector('.x').onclick = function () {
        banner.remove();
        try { localStorage.setItem(seenKey, '1'); } catch (e) {}
      };
      // 插到锚点元素前（或父容器顶部）
      el.parentNode ? el.parentNode.insertBefore(banner, el) : document.body.insertBefore(banner, document.body.firstChild);
    });

    // 2) 空状态引导卡
    document.querySelectorAll('[data-help-empty]').forEach(function (el) {
      var slug = el.getAttribute('data-help-empty');
      if (!slug) return;
      var card = document.createElement('div');
      card.className = 'hgc-empty-card';
      card.innerHTML = '这里还空空的～先看看<span style="color:#ff6b00">「入门指南」</span>了解这个功能怎么用<br>' +
        '<a href="' + artUrl(slug) + '" target="_blank">阅读入门指南</a>　<a href="' + askUrl() + '" target="_blank" style="background:none;color:#ff6b00;border:1px solid #ffd9b3">问 AI 助手</a>';
      el.appendChild(card);
    });

    // 3) 关键操作提示
    document.querySelectorAll('[data-help-hint]').forEach(function (el) {
      var slug = el.getAttribute('data-help-hint');
      if (!slug) return;
      var a = document.createElement('a');
      a.className = 'hgc-hint';
      a.href = artUrl(slug);
      a.target = '_blank';
      a.textContent = '❓ 先看交易指南';
      el.appendChild(a);
    });
  }

  if (document.readyState !== 'loading') init();
  else document.addEventListener('DOMContentLoaded', init);
})();
