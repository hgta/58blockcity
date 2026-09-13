/* ============================================================
   58 模特库 · 子站公共脚本
   - 关注按钮（列表 / 首页 / 详情共用）
   - 图片灯箱（图集 / 日常照片共用）
   ============================================================ */

/* ---------- 粉丝数格式化（与后端 Model::formatFollower 一致） ---------- */
function mFormatFollower(n) {
    n = parseInt(n, 10) || 0;
    if (n >= 10000) {
        return (n / 10000).toFixed(1).replace(/\.0$/, '') + '万';
    }
    return String(n);
}

/* ---------- 网格工具：读取实际列数 ----------
   .m-grid 为固定列数网格，通过计算后的 grid-template-columns
   统计轨道数，供「加载更多」按整行补齐使用。 */
function mGridColumns(gridEl) {
    if (!gridEl) return 1;
    var tpl = window.getComputedStyle(gridEl).gridTemplateColumns || '';
    var cols = tpl.split(' ').filter(function (s) { return s.trim() !== ''; }).length;
    return cols > 0 ? cols : 1;
}

/* ---------- 首屏自动补齐整行 ----------
   服务端首屏条数是固定的（首页 15 / 列表 20），在窄屏下可能不是
   当前列数的整数倍。页面加载后检查首屏余数，若不满一行则自动补一次，
   使用户第一眼就看到完整行。
   （宽屏下若已是整数倍则不发请求） */
function autoFillFirstRow(opts) {
    var btn  = document.getElementById(opts.buttonId || 'load-more');
    var grid = document.getElementById(opts.gridId || 'model-grid');
    if (!btn || !grid) return;

    function fill() {
        var cols   = mGridColumns(grid);
        var loaded = grid.querySelectorAll('.model-card').length;
        var total  = parseInt(btn.dataset.total || '0', 10);
        if (loaded === 0 || loaded % cols === 0) return;      // 已满行
        if (total > 0 && loaded >= total) return;             // 已全部加载

        var params = new URLSearchParams();
        params.set('ajax', '1');
        params.set('offset', loaded);
        params.set('limit', cols - (loaded % cols));          // 补足这一行
        Object.keys(opts.args || {}).forEach(function (k) {
            var v = opts.args[k];
            if (v !== '' && v !== null) params.set(k, v);
        });

        fetch((opts.url || '/list.php') + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            grid.insertAdjacentHTML('beforeend', res.html);
            if (opts.onLoaded) opts.onLoaded(grid, res);
        })
        .catch(function () {});
    }

    // 首屏渲染后执行；视口尺寸变化（如旋转屏幕）时重新检查
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        fill();
    } else {
        document.addEventListener('DOMContentLoaded', fill);
    }
    var rTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(rTimer);
        rTimer = setTimeout(fill, 300);
    });
}

/* ---------- 按整行补齐加载更多 ----------
   以「已加载条数」为游标请求下一行：每次请求 limit = 当前网格列数，
   保证追加后不产生半行空位。

   注意：不能简单用 page +1 递增，因为首屏条数与后续每行条数往往不同
   （如首屏 15 个、每次补一行 5 个），用 page 计算 OFFSET 会导致重复数据。
   因此这里统一使用 offset = 当前已渲染卡片数。 */
function bindLoadMore(opts) {
    var btn  = document.getElementById(opts.buttonId || 'load-more');
    var grid = document.getElementById(opts.gridId || 'model-grid');
    if (!btn || !grid) return;

    var baseUrl   = opts.url || '/list.php';
    var extraArgs = opts.args || {};
    var onLoaded  = opts.onLoaded || function () {};

    btn.addEventListener('click', function () {
        var cols   = mGridColumns(grid);
        var loaded = grid.querySelectorAll('.model-card').length;
        var total  = parseInt(btn.dataset.total || '0', 10);

        // 已加载数量达到总数则直接收尾
        if (total > 0 && loaded >= total) {
            btn.textContent = '已加载全部';
            btn.disabled = true;
            setTimeout(function () { btn.style.display = 'none'; }, 600);
            return;
        }

        btn.disabled = true;
        btn.textContent = '加载中…';

        var params = new URLSearchParams();
        params.set('ajax', '1');
        params.set('offset', loaded);      // 从已加载处继续
        params.set('limit', cols);         // 恰好补满一行
        Object.keys(extraArgs).forEach(function (k) {
            if (extraArgs[k] !== '' && extraArgs[k] !== null) params.set(k, extraArgs[k]);
        });

        fetch(baseUrl + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            grid.insertAdjacentHTML('beforeend', res.html);
            onLoaded(grid, res);

            var got = parseInt(res.count || 0, 10);
            if (got < cols) {
                btn.textContent = '已加载全部';
                btn.disabled = true;
                setTimeout(function () { btn.style.display = 'none'; }, 600);
            } else {
                btn.disabled = false;
                btn.textContent = '加载更多';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = '加载失败，点击重试';
        });
    });
}

/* ---------- 关注按钮 ---------- */
function bindFollowButtons(root) {
    var scope = root || document;
    scope.querySelectorAll('.model-follow-btn[data-model-id]').forEach(function (btn) {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!btn.dataset.loggedIn) {
                window.location.href = btn.dataset.loginUrl;
                return;
            }
            var fd = new FormData();
            fd.append('model_id', btn.dataset.modelId);
            fetch('/follow.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.error) {
                    if (res.error === 'unauthorized') { window.location.href = btn.dataset.loginUrl; return; }
                    alert('操作失败，请重试');
                    return;
                }
                if (res.action === 'followed') {
                    btn.textContent = '已关注';
                    btn.classList.add('followed');
                } else {
                    btn.textContent = '+ 关注';
                    btn.classList.remove('followed');
                }
                var card = btn.closest('.model-card, .m-profile-head, .m-rail-card');
                if (card) {
                    var fc = card.querySelector('.follower-count');
                    if (fc && typeof res.follower_count !== 'undefined') {
                        fc.textContent = mFormatFollower(res.follower_count);
                    }
                }
            })
            .catch(function () { alert('操作失败，请重试'); });
        });
    });
}

/* ---------- 灯箱 ---------- */
var mLightboxImages = [];
var mLightboxIndex = 0;

function mInitLightbox(selector) {
    mLightboxImages = [];
    var els = document.querySelectorAll(selector);
    els.forEach(function (el) {
        var src = el.dataset.src;
        if (!src) return;
        var idx = mLightboxImages.length;
        mLightboxImages.push(src);
        el.addEventListener('click', function () { openLightbox(idx); });
    });
}

function openLightbox(idx) {
    if (!mLightboxImages.length) return;
    mLightboxIndex = idx;
    var lb = document.getElementById('lightbox');
    if (!lb) return;
    lb.querySelector('#lightbox-img').src = mLightboxImages[idx];
    var c = lb.querySelector('#lightbox-count');
    if (c) c.textContent = (idx + 1) + ' / ' + mLightboxImages.length;
    lb.classList.add('on');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    var lb = document.getElementById('lightbox');
    if (!lb) return;
    lb.classList.remove('on');
    document.body.style.overflow = '';
}

function lbStep(delta) {
    if (!mLightboxImages.length) return;
    mLightboxIndex = (mLightboxIndex + delta + mLightboxImages.length) % mLightboxImages.length;
    openLightbox(mLightboxIndex);
}

/* 键盘与手势 */
document.addEventListener('keydown', function (e) {
    var lb = document.getElementById('lightbox');
    if (!lb || !lb.classList.contains('on')) return;
    if (e.key === 'ArrowLeft')  lbStep(-1);
    if (e.key === 'ArrowRight') lbStep(1);
    if (e.key === 'Escape')     closeLightbox();
});

document.addEventListener('DOMContentLoaded', function () {
    var lb = document.getElementById('lightbox');
    if (lb) {
        lb.addEventListener('click', function (e) { if (e.target === lb) closeLightbox(); });
        // 移动端左右滑动
        var startX = 0;
        lb.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
        lb.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 50) lbStep(dx < 0 ? 1 : -1);
        }, { passive: true });
    }
    bindFollowButtons(document);
});
