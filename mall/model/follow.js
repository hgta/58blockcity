// 粉丝数格式化：整数 → "5.4万"（与后端 Model::formatFollower 保持一致）
function formatFollower(n) {
    n = parseInt(n, 10) || 0;
    if (n >= 10000) {
        var s = (n / 10000).toFixed(1).replace(/\.0$/, '');
        return s + '万';
    }
    return String(n);
}

// 模特关注按钮（列表卡片 / 详情页 / 我的关注 复用）
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
            var modelId = btn.dataset.modelId;
            var fd = new FormData();
            fd.append('model_id', modelId);
            fetch('/model/follow.php', {
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
                var cnt = btn.closest('.model-card, .model-detail-head');
                if (cnt) {
                    var fc = cnt.querySelector('.follower-count');
                    if (fc && typeof res.follower_count !== 'undefined') fc.textContent = formatFollower(res.follower_count);
                }
            })
            .catch(function () { alert('操作失败，请重试'); });
        });
    });
}

document.addEventListener('DOMContentLoaded', function () { bindFollowButtons(document); });
