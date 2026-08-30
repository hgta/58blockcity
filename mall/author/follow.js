// 粉丝数格式化：整数 → "5.4万"（与后端 Author::formatFollower 保持一致）
function formatAuthorFollower(n) {
    n = parseInt(n, 10) || 0;
    if (n >= 10000) {
        var s = (n / 10000).toFixed(1).replace(/\.0$/, '');
        return s + '万';
    }
    return String(n);
}

// 作者关注按钮（列表卡片 / 详情页 / 我的关注 复用）
// 与模特版 follow.js 选择器不同（data-author-id），可同时引入互不干扰
function bindAuthorFollowButtons(root) {
    var scope = root || document;
    scope.querySelectorAll('.author-follow-btn[data-author-id]').forEach(function (btn) {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!btn.dataset.loggedIn) {
                window.location.href = btn.dataset.loginUrl;
                return;
            }
            var authorId = btn.dataset.authorId;
            var fd = new FormData();
            fd.append('author_id', authorId);
            fetch('/author/follow.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.error) { alert('操作失败，请重试'); return; }
                if (res.action === 'followed') {
                    btn.textContent = '已关注';
                    btn.classList.add('followed');
                } else {
                    btn.textContent = '+ 关注';
                    btn.classList.remove('followed');
                }
                var cnt = btn.closest('.author-card, .author-detail-head');
                if (cnt) {
                    var fc = cnt.querySelector('.follower-count');
                    if (fc && typeof res.follower_count !== 'undefined') fc.textContent = formatAuthorFollower(res.follower_count);
                }
            })
            .catch(function () { alert('操作失败，请重试'); });
        });
    });
}

document.addEventListener('DOMContentLoaded', function () { bindAuthorFollowButtons(document); });
