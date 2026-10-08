<?php
/**
 * 公开挂单详情页
 *
 * change: bct-public-order-detail
 *
 * 任何人（含未登录）可访问 bct/order.php?id=N，查看一张挂单的完整信息；
 * 操作按钮按角色渲染（游客 / 登录非参与方 / 挂单人 / 交易对手方）。
 * 仅展示公开字段，绝不输出 email / phone 等私有信息。
 */
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once 'includes/expiry-display.php';
require_once '../classes/BCTOrder.php';
require_once '../classes/CityBCT.php';

$bctOrder = new BCTOrder($pdo);

// 惰性推进：过期订单 / 超时接单（与挂单大厅一致，保证详情页状态新鲜）
try {
    $bctOrder->expireOverdueOrders(null, null, 500);
} catch (Exception $e) {
    error_log('BCT order detail expire error: ' . $e->getMessage());
}
try {
    $bctOrder->releaseStaleClaims(null, 20);
} catch (Exception $e) {
    error_log('BCT order detail release claims error: ' . $e->getMessage());
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

$o = $bctOrder->getClaimPreview($orderId);
if (!$o) {
    header('Location: orders.php');
    exit;
}

// ---- 角色判定（服务端算完再渲染，前端不做权限判断）----
$loggedIn      = isLoggedIn();
$currentUserId = $loggedIn ? (int)$_SESSION['user_id'] : 0;
$loginUrl      = '../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);

$isBuy   = $o['type'] === 'buy';
$claim   = !empty($o['active_claim']) ? $o['active_claim'] : null;
$inTrade = (bool)$claim;
$isMine  = $currentUserId > 0 && (int)$o['user_id'] === $currentUserId;
$isParty = $inTrade && $currentUserId > 0 && (
    $currentUserId === (int)($claim['buyer_side_user_id'] ?? 0)
    || $currentUserId === (int)($claim['seller_side_user_id'] ?? 0)
);
$canClaim = $loggedIn && !$isMine && !$inTrade && $o['trade_type'] === 'direct' && $o['status'] === 'pending';

$tradeTypes = ['direct' => '直接交易', 'platform' => '平台交易', 'mediator' => '中介交易'];
$tradeTypeText = $tradeTypes[$o['trade_type']] ?? $o['trade_type'];

// 状态徽标：交易中优先，其次按订单状态
if ($inTrade) {
    $statusBadge = ['交易中', 'badge-info'];
} else {
    switch ($o['status']) {
        case 'pending':    $statusBadge = ['待成交', 'badge-warning']; break;
        case 'processing': $statusBadge = ['部分成交', 'badge-info']; break;
        case 'completed':  $statusBadge = ['已完成', 'badge-success']; break;
        case 'canceled':   $statusBadge = ['已取消', 'badge-default']; break;
        case 'expired':    $statusBadge = ['已过期', 'badge-default']; break;
        default:           $statusBadge = [$o['status'], 'badge-default'];
    }
}

list($expText, $expCls, $expTitle) = formatRemainingValidity($o['expires_at'], $inTrade);
$total = (int)$o['amount'] * (float)$o['price'];

// 该城市当前市价（供对比参考，失败不影响页面）
$cityPrice = null;
try {
    $cityBCT = new CityBCT($pdo);
    $cityRow = $cityBCT->getCityBCT($o['city']);
    if ($cityRow && isset($cityRow['current_price'])) {
        $cityPrice = (float)$cityRow['current_price'];
    }
} catch (Exception $e) {
    $cityPrice = null;
}

$msgUrl = $loggedIn ? 'messages/index.php?with=' . (int)$o['user_id'] : $loginUrl;

$site_config['title'] = htmlspecialchars($o['city']) . ($isBuy ? ' 求购' : ' 挂售')
    . ' ¥' . number_format((float)$o['price'], 2) . ' | BCT挂单详情';
$site_config['description'] = htmlspecialchars($o['city']) . ($isBuy ? '求购' : '挂售')
    . '挂单：' . number_format((int)$o['amount']) . ' BCT @ ¥' . number_format((float)$o['price'], 2)
    . '，交易方式' . $tradeTypeText . '。查看并接单。';

require_once 'includes/header.php';
?>

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-file-invoice"></i> 挂单详情 <span class="city-symbol">#<?= htmlspecialchars(substr((string)$o['order_no'], 0, 8)) ?></span></h1>
        <div class="subtitle">
            <a href="orders.php" style="color:var(--bct-text-secondary);"><i class="fas fa-arrow-left"></i> 返回挂单大厅</a>
        </div>
    </div>
    <div><span class="badge <?= $statusBadge[1] ?>" style="font-size:13px;padding:5px 10px;"><?= $statusBadge[0] ?></span></div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body" style="padding:20px;">
                <!-- 方向 / 城市 / 价格 -->
                <div class="od-hero">
                    <span class="side <?= $isBuy ? 'buy' : 'sell' ?>" style="font-size:14px;"><?= $isBuy ? '求购' : '挂售' ?></span>
                    <strong class="od-city"><?= htmlspecialchars($o['city']) ?>BCT</strong>
                </div>
                <div class="od-prices">
                    <div class="od-price-block">
                        <div class="od-price-label">单价</div>
                        <div class="od-price-value">¥<?= number_format((float)$o['price'], 2) ?></div>
                    </div>
                    <div class="od-price-op">×</div>
                    <div class="od-price-block">
                        <div class="od-price-label">数量</div>
                        <div class="od-price-value"><?= number_format((int)$o['amount']) ?> <span class="od-unit">BCT</span></div>
                    </div>
                    <div class="od-price-op">=</div>
                    <div class="od-price-block">
                        <div class="od-price-label">总价</div>
                        <div class="od-price-value od-total">¥<?= number_format($total, 2) ?></div>
                    </div>
                </div>

                <!-- 字段区 -->
                <div class="od-rows">
                    <div class="od-row">
                        <span class="od-key">交易方式</span>
                        <span class="od-val"><?= htmlspecialchars($tradeTypeText) ?></span>
                    </div>
                    <div class="od-row">
                        <span class="od-key">剩余有效期</span>
                        <span class="od-val <?= $expCls ?><?= $expTitle ? ' has-tip' : '' ?>"<?= $expTitle ? ' title="' . htmlspecialchars($expTitle) . '"' : '' ?>><?= $expText ?></span>
                    </div>
                    <div class="od-row">
                        <span class="od-key">发布时间</span>
                        <span class="od-val text-muted"><?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></span>
                    </div>
                    <div class="od-row">
                        <span class="od-key">挂单人</span>
                        <span class="od-val">
                            <strong><?= htmlspecialchars($o['username'] ?: ('用户#' . (int)$o['user_id'])) ?></strong>
                            <a href="<?= htmlspecialchars($msgUrl) ?>" class="od-msg" title="站内信联系挂单人"><i class="fas fa-comment-dots"></i></a>
                        </span>
                    </div>
                    <div class="od-row">
                        <span class="od-key">联系方式</span>
                        <span class="od-val">
                            <?php if ($o['trade_type'] !== 'direct'): ?>
                                <span class="text-muted"><?= $o['trade_type'] === 'mediator' ? '由中介协调' : '平台撮合' ?></span>
                            <?php elseif ($inTrade && $isParty): ?>
                                <strong><?= htmlspecialchars($o['contact_info'] ?: '未填写') ?></strong>
                                <div class="od-hint">双方可见 · 私信沟通转账</div>
                            <?php elseif ($inTrade): ?>
                                <span class="text-muted"><i class="fas fa-lock"></i> 交易中 · 暂不可见</span>
                            <?php elseif (empty($o['contact_info'])): ?>
                                <span class="text-muted">未填写 · 站内信联系</span>
                            <?php elseif ($loggedIn): ?>
                                <strong><?= htmlspecialchars($o['contact_info']) ?></strong>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($loginUrl) ?>" style="font-size:12px;"><i class="fas fa-lock"></i> 登录后可见</a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($cityPrice !== null): ?>
                    <div class="od-row">
                        <span class="od-key">该城市当前价</span>
                        <span class="od-val">
                            ¥<?= number_format($cityPrice, 2) ?>
                            <?php
                                $diff = $cityPrice > 0 ? ((float)$o['price'] - $cityPrice) / $cityPrice * 100 : 0;
                            ?>
                            <span class="od-hint-inline <?= $diff >= 0 ? 'up' : 'down' ?>">
                                挂单价 <?= $diff >= 0 ? '+' : '' ?><?= number_format($diff, 1) ?>%
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 操作区：唯一主按钮 -->
                <div class="od-cta">
                    <?php if ($o['status'] === 'completed'): ?>
                        <?php if ($isMine || $isParty): ?>
                            <a href="user/order_detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-primary">查看成交记录</a>
                        <?php else: ?>
                            <span class="od-cta-muted">该挂单已完成</span>
                        <?php endif; ?>
                        <a href="orders.php" class="btn btn-default">看看其他挂单</a>
                    <?php elseif ($o['status'] === 'canceled' || $o['status'] === 'expired'): ?>
                        <span class="od-cta-muted"><?= $o['status'] === 'expired' ? '该挂单已过期' : '该挂单已取消' ?></span>
                        <a href="orders.php" class="btn btn-default">去挂单大厅</a>
                    <?php elseif ($isMine): ?>
                        <?php if ($inTrade): ?>
                            <a href="user/dashboard.php#claims" class="btn btn-primary">交易中 · 去处理</a>
                        <?php else: ?>
                            <a href="user/orders.php" class="btn btn-primary">管理我的挂单</a>
                        <?php endif; ?>
                    <?php elseif ($isParty): ?>
                        <a href="user/dashboard.php#claims" class="btn btn-primary">继续交易</a>
                    <?php elseif ($o['trade_type'] === 'direct'): ?>
                        <?php if ($inTrade): ?>
                            <span class="od-cta-muted">交易中 · 暂时无法接单</span>
                        <?php elseif ($canClaim): ?>
                            <button type="button" class="btn btn-primary btn-claim" data-order-id="<?= (int)$o['id'] ?>">
                                <?= $isBuy ? '接单（卖给TA）' : '接单（买入）' ?>
                            </button>
                        <?php elseif (!$loggedIn): ?>
                            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary">登录后接单</a>
                        <?php else: ?>
                            <span class="od-cta-muted">当前不可接单</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="od-cta-muted"><?= $o['trade_type'] === 'mediator' ? '该单由中介协调' : '该单由平台撮合' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-shield-alt"></i> 交易说明</h3></div>
            <div class="card-body" style="font-size:13px;color:var(--bct-text-secondary);line-height:2;">
                <?php if ($o['trade_type'] === 'direct'): ?>
                接单后该挂单进入「交易中」，其他人将无法再接。请通过站内信/联系方式与对方沟通，
                <strong style="color:var(--bct-text);">线下完成转账后各自在个人中心确认</strong>；
                接单后 24 小时未确认付款将自动释放。
                <?php else: ?>
                <?= $o['trade_type'] === 'mediator' ? '中介交易由所选中介协调成交（手续费 2%）。' : '平台交易（限 500 BCT 以下）由系统自动撮合并结算。' ?>
                该类型挂单不支持直接接单。
                <?php endif; ?>
            </div>
        </div>
        <div class="card" style="margin-top:16px;">
            <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-chart-line"></i> <?= htmlspecialchars($o['city']) ?>行情</h3></div>
            <div class="card-body" style="padding:12px;">
                <a href="city.php?city=<?= urlencode($o['city']) ?>" class="btn btn-default btn-block">查看<?= htmlspecialchars($o['city']) ?>走势与盘口</a>
            </div>
        </div>
    </div>
</div>

<!-- 顶部提示条（接单失败/状态变化时提示，替代 alert） -->
<div class="od-alert" id="odAlert" hidden></div>

<?php if ($canClaim): ?>
<!-- 接单确认弹窗 -->
<div class="claim-modal" id="claimModal" hidden>
    <div class="claim-modal-mask" data-close="1"></div>
    <div class="claim-modal-box">
        <h4><i class="fas fa-handshake"></i> 确认接单</h4>
        <div class="claim-modal-info">
            <div class="claim-modal-row"><span>订单</span><strong id="cmOrderNo"></strong></div>
            <div class="claim-modal-row"><span>方向</span><strong id="cmSide"></strong></div>
            <div class="claim-modal-row"><span>城市</span><strong id="cmCity"></strong></div>
            <div class="claim-modal-row"><span>数量</span><strong id="cmAmount"></strong></div>
            <div class="claim-modal-row"><span>单价</span><strong id="cmPrice"></strong></div>
            <div class="claim-modal-row"><span>总价</span><strong id="cmTotal" style="color:var(--bct-accent);"></strong></div>
            <div class="claim-modal-row"><span>挂单人</span><strong id="cmPoster"></strong></div>
        </div>
        <p class="claim-modal-tip">接单后该挂单进入「交易中」，其他人将无法再接。请通过站内信/联系方式与对方沟通，线下完成转账后各自在个人中心确认。</p>
        <div class="claim-error" id="cmError"></div>
        <div class="claim-modal-btns">
            <button type="button" class="btn btn-default" data-close="1">再想想</button>
            <button type="button" class="btn btn-primary" id="cmConfirm"><i class="fas fa-check"></i> 确认接单</button>
        </div>
    </div>
</div>

<script>
$(function() {
    var CSRF_TOKEN = '<?= generateCsrfToken() ?>';
    var modal = document.getElementById('claimModal');
    var alertBox = document.getElementById('odAlert');
    var pendingOrderId = <?= (int)$o['id'] ?>;

    function fmtMoney(n) { return '¥' + Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function fmtInt(n) { return Number(n).toLocaleString('en-US'); }
    function closeModal() { modal.hidden = true; }

    // 顶部提示：非阻塞，需要刷新时延时刷新页面（替代 alert）
    function notify(msg, reload) {
        if (!alertBox) { if (reload) window.location.reload(); return; }
        alertBox.textContent = msg;
        alertBox.hidden = false;
        if (reload) {
            setTimeout(function() { window.location.reload(); }, 2000);
        } else {
            setTimeout(function() { alertBox.hidden = true; }, 4000);
        }
    }

    modal.addEventListener('click', function(e) {
        if (e.target.getAttribute('data-close')) closeModal();
    });

    document.querySelectorAll('.btn-claim').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = parseInt(this.getAttribute('data-order-id'), 10) || pendingOrderId;
            var trigger = this;
            trigger.disabled = true;

            // 拉取预览（服务端校验可接单状态），再弹窗确认
            fetch('api/trade_flow.php?action=preview&order_id=' + id, {credentials: 'same-origin'})
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    trigger.disabled = false;
                    if (!d.success) { notify(d.message || '订单状态已变化，请刷新页面', true); return; }
                    if (!d.can_claim) {
                        notify(d.in_trade ? '该挂单已被接单，交易中' : '该挂单当前不可接单', true);
                        return;
                    }
                    pendingOrderId = id;
                    document.getElementById('cmOrderNo').textContent = d.order_no;
                    document.getElementById('cmSide').textContent = d.type === 'buy' ? '求购单（你来卖）' : '挂售单（你来买）';
                    document.getElementById('cmCity').textContent = d.city;
                    document.getElementById('cmAmount').textContent = fmtInt(d.amount) + ' BCT';
                    document.getElementById('cmPrice').textContent = fmtMoney(d.price);
                    document.getElementById('cmTotal').textContent = fmtMoney(d.total);
                    document.getElementById('cmPoster').textContent = d.poster_name;
                    var errEl = document.getElementById('cmError');
                    if (errEl) errEl.textContent = '';
                    modal.hidden = false;
                })
                .catch(function() { trigger.disabled = false; notify('网络异常，请重试', false); });
        });
    });

    document.getElementById('cmConfirm').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'claim');
        fd.append('order_id', pendingOrderId);
        fd.append('csrf_token', CSRF_TOKEN);

        fetch('api/trade_flow.php', {method: 'POST', body: fd, credentials: 'same-origin'})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    closeModal();
                    // 接单成功直接进入交易流程（个人中心 - 进行中的交易）
                    window.location.href = 'user/dashboard.php#claims';
                    return;
                }
                btn.disabled = false;
                var errEl = document.getElementById('cmError');
                if (errEl) errEl.textContent = d.message || '接单失败';
                else notify(d.message || '接单失败', false);
            })
            .catch(function() { btn.disabled = false; notify('网络异常，请重试', false); });
    });
});
</script>
<?php endif; ?>

<style>
.od-hero { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.od-city { font-size:20px; }
.od-prices { display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; padding:14px 16px; border-radius:10px; background:var(--bct-bg-tertiary); margin-bottom:18px; }
.od-price-block { min-width:96px; }
.od-price-label { font-size:12px; color:var(--bct-text-secondary); margin-bottom:2px; }
.od-price-value { font-size:18px; font-weight:600; font-family:monospace; }
.od-price-value .od-unit { font-size:12px; font-weight:400; }
.od-total { color:var(--bct-accent); font-size:20px; }
.od-price-op { color:var(--bct-text-secondary); font-size:16px; padding-bottom:4px; }
.od-rows { border-top:1px solid var(--bct-border); }
.od-row { display:flex; gap:12px; padding:10px 2px; border-bottom:1px solid var(--bct-border); font-size:13px; }
.od-key { width:110px; flex:none; color:var(--bct-text-secondary); }
.od-val { flex:1; min-width:0; word-break:break-all; }
.od-hint { font-size:11px; color:var(--bct-text-secondary); margin-top:2px; }
.od-hint-inline { font-size:11px; margin-left:6px; }
.od-hint-inline.up { color:var(--bct-up); }
.od-hint-inline.down { color:var(--bct-down); }
.od-msg { margin-left:6px; color:var(--bct-text-secondary); font-size:13px; }
.od-msg:hover { color:var(--bct-accent); }
.od-cta { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:20px; }
.od-cta .btn { min-width:140px; }
.od-cta-muted { color:var(--bct-text-secondary); font-size:13px; }
.od-alert {
  position:fixed; top:70px; left:50%; transform:translateX(-50%); z-index:99999;
  background:#fff4e0; color:#8a4b00; border:1px solid #ffd591; border-radius:8px;
  padding:10px 18px; font-size:13px; box-shadow:0 6px 20px rgba(0,0,0,.12);
}
.btn-block { display:block; width:100%; }
@media (max-width:575px) {
  .od-key { width:84px; font-size:12px; }
  .od-price-value { font-size:16px; }
  .od-total { font-size:17px; }
  .od-cta .btn { width:100%; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
