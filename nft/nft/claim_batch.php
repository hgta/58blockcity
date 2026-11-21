<?php
/**
 * 批量认领页（openspec: nft-batch-claim）
 * 选城市+区块 → 粘贴含编号文本 → 解析预览 → 批量认领
 */
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';
require_once '../includes/auth.php';

checkLogin();

$nft = new NFT($pdo);
$city = new City($pdo);
$cities = $city->getAllCities();
$dailyUsed = $nft->getDailyClaimCount((int)$_SESSION['user_id']);
$dailyLimit = NFT::BATCH_CLAIM_DAILY_LIMIT;
?>
<?php require_once '../includes/header.php'; ?>

<style>
.batch-wrap {
    max-width: 760px;
    margin: 0 auto;
    padding: 0 12px 40px;
}
.batch-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 22px;
    font-weight: 800;
    color: #1f2937;
    margin: 18px 0 6px;
}
.batch-title i { color: #ff6b00; }
.batch-sub {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 16px;
    line-height: 1.6;
}
.batch-card {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    margin-bottom: 16px;
}
.batch-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.batch-field {
    flex: 1;
    min-width: 220px;
}
.batch-field label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}
.batch-input {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 14px;
    font-size: 0.92rem;
    color: #1f2937;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    transition: border-color .2s, box-shadow .2s;
}
.batch-input:focus {
    border-color: #ff8a3d;
    box-shadow: 0 0 0 3px rgba(255,107,0,.12);
}
.batch-input:disabled { background: #f8fafc; cursor: not-allowed; }
.batch-textarea {
    display: block;
    width: 100%;
    box-sizing: border-box;
    min-height: 120px;
    padding: 12px 14px;
    font-size: 0.92rem;
    line-height: 1.6;
    color: #1f2937;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    resize: vertical;
    outline: none;
    font-family: inherit;
    transition: border-color .2s, box-shadow .2s;
}
.batch-textarea:focus {
    border-color: #ff8a3d;
    box-shadow: 0 0 0 3px rgba(255,107,0,.12);
}
.batch-hint {
    font-size: 0.78rem;
    color: #9ca3af;
    margin-top: 6px;
    line-height: 1.5;
}
.batch-actions {
    display: flex;
    gap: 10px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.batch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 24px;
    border: none;
    border-radius: 999px;
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s, opacity .2s;
}
.batch-btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }
.batch-btn-preview {
    background: #fff;
    color: #ff6b00;
    border: 1.5px solid #ff8a3d;
}
.batch-btn-preview:hover:not(:disabled) { background: #fff7f0; }
.batch-btn-submit {
    margin-left: auto;
    background: linear-gradient(135deg, #ff8a3d, #e55a00);
    color: #fff;
    box-shadow: 0 4px 12px rgba(255,107,0,.25);
}
.batch-btn-submit:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(255,107,0,.32);
}
.batch-alert {
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 0.88rem;
    line-height: 1.6;
    margin-bottom: 12px;
}
.batch-alert-err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.batch-alert-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.batch-alert-ok { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

.batch-summary {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.batch-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
}
.chip-ok   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.chip-warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.chip-err  { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.chip-mute { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

.batch-list { display: flex; flex-direction: column; gap: 6px; }
.batch-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 0.88rem;
    line-height: 1.5;
    border: 1px solid #eef2f7;
    background: #fafbfc;
}
.batch-item .code {
    font-weight: 700;
    color: #1f2937;
    min-width: 56px;
    letter-spacing: .5px;
}
.batch-item .detail { color: #6b7280; word-break: break-all; }
.batch-item.ok     { background: #f0fdf4; border-color: #bbf7d0; }
.batch-item.ok .code::before { content: "✅ "; }
.batch-item.warn   { background: #fffbeb; border-color: #fde68a; }
.batch-item.warn .code::before { content: "⚠️ "; }
.batch-item.err    { background: #fef2f2; border-color: #fecaca; }
.batch-item.err .code::before { content: "❌ "; }
.batch-item.mute   { background: #f8fafc; }
.batch-item.mute .code::before { content: "⏭ "; }
.batch-item.self   { background: #eff6ff; border-color: #bfdbfe; }
.batch-item.self .code::before { content: "🔵 "; }

.batch-loading {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 0.88rem;
    padding: 10px 2px;
}
.batch-spinner {
    width: 16px; height: 16px;
    border: 2px solid #e5e7eb;
    border-top-color: #ff6b00;
    border-radius: 50%;
    animation: batch-spin .7s linear infinite;
}
@keyframes batch-spin { to { transform: rotate(360deg); } }

.batch-quota {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: #9ca3af;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: 999px;
    padding: 4px 12px;
    margin-bottom: 14px;
}

@media (max-width: 640px) {
    .batch-field { min-width: 100%; }
    .batch-btn-submit { margin-left: 0; width: 100%; }
    .batch-btn-preview { width: 100%; }
}
</style>

<div class="batch-wrap">
    <div class="batch-title">
        <i class="fas fa-layer-group"></i> 批量认领
    </div>
    <p class="batch-sub">
        选择一个城市和您的区块，粘贴含编号的文本（如 <b>ML24 FX24 YP86...</b>，自动忽略"使用/出售"等无关文字），预览确认后一键认领。
        单批最多 <?= NFT::BATCH_CLAIM_MAX_CODES ?> 个编号。
    </p>

    <div class="batch-quota">
        <i class="fas fa-gauge-high"></i> 今日已认领 <b><?= $dailyUsed ?></b> / <?= $dailyLimit ?> 个
    </div>

    <div class="batch-card">
        <div class="batch-row">
            <div class="batch-field">
                <label for="citySelect"><i class="fas fa-city"></i> 选择城市</label>
                <select class="batch-input" id="citySelect">
                    <option value="">-- 请选择城市 --</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'] . ' ' . $c['rank']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="batch-field">
                <label for="blockId"><i class="fas fa-map-marker-alt"></i> 您的区块ID</label>
                <input type="text" class="batch-input" id="blockId" placeholder="例如: 0101" list="userBlocks">
                <datalist id="userBlocks"></datalist>
                <div class="batch-hint">输入或选择您在该城市的区块ID，不存在将自动创建</div>
            </div>
        </div>

        <div style="margin-top: 14px;">
            <label for="codeInput"><i class="fas fa-keyboard"></i> 编号文本</label>
            <textarea class="batch-textarea" id="codeInput"
                      placeholder="粘贴编号文本，例如：&#10;ML24 使用 出售 FX24 使用 出售 YP86 UK74 QR86 HK53 VU84 KQ63"></textarea>
            <div class="batch-hint">支持任意格式文本，自动提取"两字母+两数字"的编号，大小写不限，自动去重；单批上限 <?= NFT::BATCH_CLAIM_MAX_CODES ?> 个</div>
        </div>

        <div class="batch-actions">
            <button type="button" class="batch-btn batch-btn-preview" id="btnPreview" disabled>
                <i class="fas fa-magnifying-glass"></i> 解析预览
            </button>
            <button type="button" class="batch-btn batch-btn-submit" id="btnSubmit" disabled>
                <i class="fas fa-gavel"></i> 确认认领
            </button>
        </div>
    </div>

    <div id="resultArea"></div>
</div>

<script>
(function () {
    var citySelect = document.getElementById('citySelect');
    var blockInput = document.getElementById('blockId');
    var codeInput  = document.getElementById('codeInput');
    var btnPreview = document.getElementById('btnPreview');
    var btnSubmit  = document.getElementById('btnSubmit');
    var resultArea = document.getElementById('resultArea');
    var lastPreview = null;

    function refreshStates() {
        var hasCity = !!citySelect.value;
        var hasCode = codeInput.value.replace(/\s/g, '') !== '';
        btnPreview.disabled = !(hasCity && hasCode);
        btnSubmit.disabled = !(hasCity && hasCode && blockInput.value.trim() !== '' && lastPreview);
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function alertBox(type, html) {
        return '<div class="batch-alert batch-alert-' + type + '">' + html + '</div>';
    }

    function resetBtn(btn) {
        if (btn.dataset.orig) { btn.innerHTML = btn.dataset.orig; delete btn.dataset.orig; }
        refreshStates();
    }

    function post(url, data, onDone) {
        var body = new URLSearchParams(data);
        fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { throw new Error('服务器响应异常，请稍后重试'); });
        }).then(onDone).catch(function (e) {
            resultArea.innerHTML = alertBox('err', esc(e.message || '请求失败，请重试'));
            resetBtn(btnPreview);
            resetBtn(btnSubmit);
        });
    }

    function itemClass(status) {
        return {available: 'ok', self: 'self', claimed: 'warn', missing: 'err',
                ambiguous: 'err', fail: 'err', quota: 'mute'}[status] || 'mute';
    }

    function chip(n, label, cls) {
        if (!n) return '';
        return '<span class="batch-chip chip-' + cls + '">' + esc(label) + ' ' + n + '</span>';
    }

    function renderList(data, isResult) {
        var html = '';
        if (data.ignored > 0) {
            html += alertBox('info', '编号超过单批上限，已忽略后 ' + data.ignored + ' 个');
        }
        var s = data.summary || {};
        var chips = '';
        if (isResult) {
            chips = chip(s.ok, '成功', 'ok') + chip(s.self, '更新区块', 'ok')
                  + chip(s.claimed, '已被认领', 'warn') + chip(s.missing, '不存在', 'err')
                  + chip(s.ambiguous, '歧义', 'err') + chip(s.fail, '失败', 'err')
                  + chip(s.quota, '超上限未执行', 'mute');
        } else {
            chips = chip(s.available, '可认领', 'ok') + chip(s.self, '已认领', 'mute')
                  + chip(s.claimed, '已被认领', 'warn') + chip(s.missing, '不存在', 'err')
                  + chip(s.ambiguous, '歧义', 'err');
        }
        html += '<div class="batch-summary">' + chips + '</div>';
        html += '<div class="batch-list">';
        data.items.forEach(function (it) {
            html += '<div class="batch-item ' + itemClass(it.status) + '">'
                  + '<span class="code">' + esc(it.code) + '</span>'
                  + '<span class="detail">' + esc(it.detail) + '</span>'
                  + '</div>';
        });
        html += '</div>';
        if (isResult && s.ok > 0) {
            html += '<div style="margin-top:14px;"><a href="/user/collection.php" '
                  + 'style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;'
                  + 'border-radius:999px;background:linear-gradient(135deg,#ff8a3d,#e55a00);'
                  + 'color:#fff;font-size:.9rem;font-weight:600;text-decoration:none;">'
                  + '<i class="fas fa-arrow-right"></i> 查看我的收藏</a></div>';
        }
        return html;
    }

    // 城市联动：加载用户区块 + 状态刷新
    citySelect.addEventListener('change', function () {
        lastPreview = null;
        refreshStates();
        resultArea.innerHTML = '';
        var cityId = citySelect.value;
        if (!cityId) return;
        fetch('/api/get_user_blocks.php?city_id=' + encodeURIComponent(cityId), {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (blocks) {
                var dl = document.getElementById('userBlocks');
                dl.innerHTML = '';
                (blocks || []).forEach(function (b) {
                    var opt = document.createElement('option');
                    opt.value = b.block_number;
                    dl.appendChild(opt);
                });
                if ((blocks || []).length > 0 && !blockInput.value) {
                    blockInput.value = blocks[0].block_number;
                    refreshStates();
                }
            }).catch(function () {});
    });

    blockInput.addEventListener('input', refreshStates);
    codeInput.addEventListener('input', refreshStates);

    // 解析预览（dry-run）
    btnPreview.addEventListener('click', function () {
        if (btnPreview.disabled) return;
        resultArea.innerHTML = '<div class="batch-loading"><span class="batch-spinner"></span> 解析与校验中...</div>';
        btnPreview.dataset.orig = btnPreview.innerHTML;
        btnPreview.innerHTML = '<span class="batch-spinner"></span> 解析中';
        btnPreview.disabled = true;
        post('/api/nft_batch_claim_preview.php', {
            city_id: citySelect.value,
            input: codeInput.value
        }, function (data) {
            resetBtn(btnPreview);
            if (!data.success) {
                resultArea.innerHTML = alertBox('err', esc(data.error || '校验失败'));
                refreshStates();
                return;
            }
            lastPreview = data;
            refreshStates();
            var s = data.summary || {};
            resultArea.innerHTML = renderList(data, false)
                + alertBox('info', '预览完成：可认领 <b>' + (s.available || 0) + '</b> 个，确认无误后点击「确认认领」');
        });
    });

    // 确认认领
    btnSubmit.addEventListener('click', function () {
        if (btnSubmit.disabled) return;
        if (!confirm('确定批量认领吗？不可认领的编号将自动跳过。')) return;
        resultArea.innerHTML = '<div class="batch-loading"><span class="batch-spinner"></span> 批量认领执行中...</div>';
        btnSubmit.dataset.orig = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<span class="batch-spinner"></span> 执行中';
        btnSubmit.disabled = true;
        post('/api/nft_batch_claim_submit.php', {
            city_id: citySelect.value,
            block_id: blockInput.value.trim(),
            input: codeInput.value
        }, function (data) {
            resetBtn(btnSubmit);
            if (!data.success) {
                resultArea.innerHTML = alertBox('err', esc(data.error || '执行失败'));
                refreshStates();
                return;
            }
            resultArea.innerHTML = alertBox('ok', '批量认领执行完成') + renderList(data, true);
        });
    });

    refreshStates();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
