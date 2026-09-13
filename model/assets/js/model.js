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
