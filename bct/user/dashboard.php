<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/expiry-display.php';
require_once '../../classes/BCTOrder.php';
require_once '../../classes/UserHoldings.php';

checkLogin();
$userId = $_SESSION['user_id'];

$order = new BCTOrder($pdo);
$holdings = new UserHoldings($pdo);

// 惰性推进超时接单（买方 24h 未确认付款的释放回 pending）
try {
    $order->releaseStaleClaims((int)$userId, 50);
} catch (Exception $e) {
    error_log('BCT dashboard release claims error: ' . $e->getMessage());
}

$tab = $_GET['tab'] ?? 'buy';
$tab = in_array($tab, ['buy','sell','completed']) ? $tab : 'buy';

// 三个列表一次取齐，tab 切换由前端完成（不再整页刷新）；每类最多 100 条
$ordersByTab = [];
foreach (['buy', 'sell', 'completed'] as $t) {
    try {
        $ordersByTab[$t] = $t === 'completed'
            ? $order->getUserOrders($userId, 'all', 'completed', 1, 100)
            : $order->getUserOrders($userId, $t, 'active', 1, 100);
    } catch (Exception $e) {
        error_log('BCT dashboard orders[' . $t . '] error: ' . $e->getMessage());
        $ordersByTab[$t] = [];
    }
}

/**
 * 渲染「我的挂单」单个 tab 的表格（含空态），供三个 pane 复用
 */
function renderOrdersPane(array $orders, string $paneTab, BCTOrder $order): void {
    if (empty($orders)): ?>
        <div class="text-center" style="padding:40px;color:var(--bct-text-secondary);">
            <i class="fas fa-inbox" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
            <p><?= $paneTab=='completed' ? '暂无成交记录' : ($paneTab=='sell' ? '暂无卖出订单（可去发布一笔出售）' : '暂无买入订单（可去发布一笔求购）') ?></p>
            <a href="../market.php" class="btn btn-primary">去交易</a>
        </div>
        <?php return; endif; ?>
        <div class="table-responsive">
            <table class="table mob-cards">
                <thead>
                    <tr>
                        <th>订单号</th><th>类型</th><th>城市</th><th>数量</th><th>价格</th><th>总金额</th>
                        <th>交易方式</th><th>状态</th><th>剩余有效期</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        $statusMap = [
                            'pending'    => ['待成交', 'badge-warning'],
                            'processing' => ['部分成交', 'badge-info'],
                            'completed'  => ['已完成', 'badge-success'],
                            'canceled'   => ['已取消', 'badge-default'],
                            'expired'    => ['已过期', 'badge-default'],
                        ];
                        $s = $statusMap[$o['status']] ?? [$o['status'], ''];
                        $tradeTypes = ['direct' => '直接交易', 'platform' => '平台交易', 'mediator' => '中介交易'];
                        $isBuy = $o['type'] === 'buy';

                        // 直接交易单：存在活跃接单时展示"交易中"
                        $myClaim = null;
                        if ($o['trade_type'] === 'direct' && in_array($o['status'], ['pending','processing'], true)) {
                            try { $myClaim = $order->getActiveClaim((int)$o['id']); } catch (Exception $e) {}
                        }
                    ?>
                    <tr>
                        <td data-label="订单号" style="font-size:12px;color:var(--bct-text-muted);"><?= substr($o['order_no'], 0, 8) ?></td>
                        <td data-label="类型"><span class="side <?= $isBuy ? 'buy' : 'sell' ?>"><?= $isBuy ? '求购' : '挂售' ?></span></td>
                        <td data-label="城市"><?= htmlspecialchars($o['city']) ?></td>
                        <td data-label="数量"><?= number_format($o['amount']) ?> BCT</td>
                        <td data-label="价格">¥<?= number_format($o['price'], 2) ?></td>
                        <td data-label="总金额">¥<?= number_format($o['total_amount'] ?? ($o['amount']*$o['price']), 2) ?></td>
                        <td data-label="交易方式" style="font-size:12px;"><?= $tradeTypes[$o['trade_type']] ?? $o['trade_type'] ?></td>
                        <td data-label="状态">
                            <?php if ($myClaim): ?><span class="badge badge-info">交易中</span>
                            <?php else: ?><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span><?php endif; ?>
                        </td>
                        <?php if ($paneTab === 'completed'): ?>
                        <td data-label="剩余有效期" class="exp-ok">已结束</td>
                        <?php else: list($expText, $expCls, $expTitle) = formatRemainingValidity($o['expires_at'], (bool)$myClaim); ?>
                        <td data-label="剩余有效期" class="<?= $expCls ?><?= $expTitle ? ' has-tip' : '' ?>"<?= $expTitle ? ' title="' . htmlspecialchars($expTitle) . '"' : '' ?>><?= $expText ?></td>
                        <?php endif; ?>
                        <td data-label="操作" class="mob-actions" style="white-space:nowrap;">
                            <?php if ($myClaim): ?>
                            <a href="dashboard.php#claims" class="btn btn-sm btn-primary">对方已接单 · 去处理</a>
                            <?php elseif (in_array($o['status'], ['pending','processing'])): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确定取消该订单?')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <button class="btn btn-sm btn-danger">取消</button>
                            </form>
                            <?php elseif ($o['status'] === 'completed'): ?>
                            <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-default">查看</a>
                            <?php else: ?>
                            <span style="color:var(--bct-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php }

$msg = '';
if (isset($_SESSION['holdings_msg'])) {
    $msg = $_SESSION['holdings_msg'];
    unset($_SESSION['holdings_msg']);
}

// 用户持有区块的城市（持仓可编辑范围）
$cityList = $holdings->getUserCities($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCsrfToken();
    $oid = intval($_POST['order_id'] ?? 0);

    if ($_POST['action'] === 'save_holdings') {
        // 表单以 city_id 为键，映射回城市名并交给数据层做白名单校验
        $idToName = [];
        foreach ($cityList as $c) {
            $idToName[(string)$c['city_id']] = $c['name'];
        }
        $posted = $_POST['qty'] ?? [];
        $input = [];
        if (is_array($posted)) {
            foreach ($posted as $cid => $val) {
                $cid = (string)$cid;
                if (!isset($idToName[$cid])) {
                    continue; // 非持有城市：忽略（越权防护）
                }
                $input[$idToName[$cid]] = $val;
            }
        }
        $res = $holdings->saveUserPopularities($userId, $input);
        $errors = $res['errors'] ?? [];
        if (!empty($errors)) {
            $msg = '<div class="alert alert-danger">部分城市未保存：' . htmlspecialchars(implode('；', $errors)) . '</div>';
        } else {
            $msg = '<div class="alert alert-success">持仓已保存</div>';
        }
        $_SESSION['holdings_msg'] = $msg;
        header('Location: dashboard.php?tab=' . urlencode($tab));
        exit;
    }

    if ($_POST['action'] === 'cancel' && $oid) {
        if ($order->cancelOrder($oid, $userId)) {
            $msg = '<div class="alert alert-success">订单已取消</div>';
            header("Location: dashboard.php?tab={$tab}");
            exit;
        } else {
            $msg = '<div class="alert alert-danger">取消失败（交易中的订单需先在下方"进行中的交易"里放弃）</div>';
        }
    }

    // ---- 直接交易接单流程动作 ----
    if (in_array($_POST['action'], ['claim_buyer_confirm', 'claim_seller_confirm', 'claim_abandon'], true) && $oid) {
        if ($_POST['action'] === 'claim_buyer_confirm') {
            $res = $order->buyerConfirmClaim($oid, $userId);
        } elseif ($_POST['action'] === 'claim_seller_confirm') {
            $res = $order->sellerConfirmClaim($oid, $userId);
        } else {
            $res = $order->abandonClaim($oid, $userId, trim((string)($_POST['reason'] ?? '')) ?: null);
        }
        $msg = ($res['success'] ?? false)
            ? '<div class="alert alert-success">' . htmlspecialchars($res['message']) . '</div>'
            : '<div class="alert alert-danger">' . htmlspecialchars($res['message'] ?? '操作失败') . '</div>';
        header('Location: dashboard.php?tab=' . urlencode($tab) . '#claims');
        exit;
    }
}

// 我参与中的直接交易接单
$activeClaims = [];
try {
    $activeClaims = $order->getUserActiveClaims((int)$userId);
} catch (Exception $e) {
    error_log('BCT dashboard claims error: ' . $e->getMessage());
}

// 我参与过的已完成直接交易（双方确认收款后留档，买卖双方均可见）
$completedClaims = [];
try {
    $completedClaims = $order->getUserCompletedClaims((int)$userId);
} catch (Exception $e) {
    error_log('BCT dashboard completed claims error: ' . $e->getMessage());
}

// 人气值持仓模型（数量 × 城市单价）
$popularityMap = $holdings->getUserPopularityMap($userId);
$holdingRows = [];
$totalAmount = 0.0;
$totalPopularity = 0;
foreach ($cityList as $c) {
    $name = (string)$c['name'];
    $price = (float)$c['bct_current_price'];
    $rank = (int)($c['rank'] ?? 0);
    $qty = isset($popularityMap[$name]) ? (int)$popularityMap[$name] : 0;
    $amount = $qty * $price;
    $totalAmount += $amount;
    $totalPopularity += $qty;
    $holdingRows[] = [
        'city_id' => (int)$c['city_id'],
        'name'    => $name,
        'price'   => $price,
        'rank'    => $rank,
        'qty'     => $qty,
        'amount'  => $amount,
    ];
}
$cityCount = count($holdingRows);
$showPie = $totalAmount > 0; // 无金额时不渲染饼图容器，避免脚本对空节点初始化

require_once '../includes/header.php';
?>

<style>
/* 我的持仓：排名 / 步进输入 / 实时计算 的页面级样式 */
.holdings-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.holdings-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.holdings-dirty { font-size:12px; color:var(--bct-accent); display:inline-flex; align-items:center; gap:5px; }
.holdings-dirty i { font-size:8px; }
.holdings-search { position:relative; }
.holdings-search i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--bct-text-muted); font-size:12px; pointer-events:none; }
.holdings-search input {
  background:var(--bct-bg-tertiary); border:1px solid var(--bct-border); color:var(--bct-text);
  border-radius:var(--bct-radius); padding:6px 10px 6px 28px; font-size:13px; width:170px;
}
.holdings-search input:focus { outline:none; border-color:var(--bct-accent); box-shadow:0 0 0 2px rgba(240,185,11,.2); }
.holdings-table-wrap { max-height:560px; overflow:auto; }
.holdings-table thead th { position:sticky; top:0; z-index:2; }
.holdings-table tbody tr.is-hidden { display:none; }
.holdings-rank {
  display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:22px; padding:0 6px;
  border-radius:6px; background:var(--bct-bg-tertiary); border:1px solid var(--bct-border);
  font-family:monospace; font-size:12px; color:var(--bct-text-secondary);
}
.holdings-rank.top { background:rgba(240,185,11,.15); border-color:rgba(240,185,11,.45); color:var(--bct-accent); font-weight:700; }
.qty-stepper { display:inline-flex; align-items:stretch; border:1px solid var(--bct-border); border-radius:var(--bct-radius); overflow:hidden; background:var(--bct-bg-tertiary); }
.qty-stepper:focus-within { border-color:var(--bct-accent); box-shadow:0 0 0 2px rgba(240,185,11,.2); }
.qty-stepper .qty-btn { width:30px; border:none; background:transparent; color:var(--bct-text-secondary); cursor:pointer; font-size:15px; line-height:1; user-select:none; }
.qty-stepper .qty-btn:hover { background:var(--bct-bg-hover); color:var(--bct-accent); }
.qty-stepper .qty-input {
  border:none; background:transparent; color:var(--bct-text); width:70px; text-align:center;
  font-family:'Roboto Mono',monospace; font-size:14px; padding:6px 2px;
}
.qty-stepper .qty-input:focus { outline:none; }
.qty-stepper .qty-input::-webkit-outer-spin-button,
.qty-stepper .qty-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.holdings-table tfoot td { background:var(--bct-bg-tertiary); border-top:1px solid var(--bct-border); }
.holdings-actions { padding:14px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; border-top:1px solid var(--bct-border); }
.holdings-actions .holdings-note { font-size:12px; color:var(--bct-text-secondary); }
.holdings-actions .holdings-btns { display:flex; gap:8px; }
@media (max-width:576px) { .holdings-search input { width:120px; } }

/* 我的挂单：自绘 tab（不依赖 Bootstrap 浮动，避免与其他样式冲突叠层） */
.order-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    border-bottom: 1px solid var(--bct-border);
}
.order-tab {
    display: inline-block;
    padding: 10px 16px;
    margin-bottom: -1px;
    color: var(--bct-text-secondary);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.4;
    text-decoration: none;
    white-space: nowrap;
}
.order-tab:hover { color: var(--bct-text); text-decoration: none; }
.order-tab.active {
    color: var(--bct-accent);
    border-bottom-color: var(--bct-accent);
}
/* 订单类型与说明条 */
.dash-wrap .side.buy { color: var(--bct-up); font-weight: 600; }
.dash-wrap .side.sell { color: var(--bct-down); font-weight: 600; }
.hall-hint {
    padding: 12px 16px;
    font-size: 13px;
    line-height: 1.8;
    color: var(--bct-text-secondary);
    border-top: 1px solid var(--bct-border);
}
.hall-hint a { color: var(--bct-accent); }
.hall-hint strong { color: var(--bct-text); }
.hall-contact-value { color: var(--bct-accent); word-break: break-all; font-size: 13px; }

/* 剩余有效期分级（与挂单大厅同口径，bct-hall-expiry-display） */
.dash-wrap td.exp-ok       { color: var(--bct-text-secondary); }
.dash-wrap td.exp-warn     { color: var(--bct-accent); font-weight: 600; }
.dash-wrap td.exp-danger   { color: #e67e22; font-weight: 600; }
.dash-wrap td.exp-critical { color: #e05656; font-weight: 600; }
.dash-wrap td.exp-expired  { color: #e05656; text-decoration: line-through; }
.dash-wrap td.exp-trade    { color: var(--bct-text-secondary); font-style: italic; cursor: help; }
.dash-wrap td.has-tip      { cursor: help; }

/* 移动端：表格转卡片堆叠，操作按钮始终可见 */
@media (max-width: 767px) {
    .mob-cards { display: block; border: none; }
    .mob-cards thead { display: none; }
    .mob-cards tbody { display: block; }
    .mob-cards tr {
        display: block;
        border: 1px solid var(--bct-border);
        border-radius: 10px;
        margin: 0 12px 12px;
        padding: 10px 12px;
        background: var(--bct-bg-tertiary);
    }
    .mob-cards td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        border-top: none;
        padding: 5px 0;
        text-align: right;
        word-break: break-all;
    }
    .mob-cards td::before {
        content: attr(data-label);
        flex-shrink: 0;
        color: var(--bct-text-secondary);
        font-size: 12px;
    }
    .mob-cards td.mob-actions {
        display: block;
        border-top: 1px dashed var(--bct-border);
        margin-top: 6px;
        padding: 10px 0 2px;
        text-align: right;
    }
    .mob-cards td.mob-actions::before { content: none; }
}
</style>

<div class="dash-wrap" style="padding-top:20px;">
    <?= $msg ?>

    <div class="bct-page-title">
        <div>
            <h1><i class="fas fa-user-circle"></i> 欢迎回来，<?= htmlspecialchars($_SESSION['username']) ?></h1>
            <div class="subtitle">管理您的 BCT 资产与订单</div>
        </div>
        <div>
            <a href="../trade.php" class="btn btn-primary"><i class="fas fa-plus"></i> 发布交易</a>
            <a href="profile.php" class="btn btn-default" style="margin-left:8px;"><i class="fas fa-cog"></i> 账户设置</a>
        </div>
    </div>

    <div class="bct-portfolio-summary">
        <div class="bct-portfolio-card">
            <div class="label">总资产额</div>
            <div class="value" id="sumTotalAmount">¥<?= number_format($totalAmount, 2) ?></div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">持有城市</div>
            <div class="value"><?= (int)$cityCount ?></div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">总人气值</div>
            <div class="value" id="sumTotalPop">Ⓟ <?= number_format($totalPopularity) ?></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header holdings-header">
                    <h3 style="margin:0;font-size:16px;"><i class="fas fa-wallet"></i> 我的持仓</h3>
                    <?php if (!empty($holdingRows)): ?>
                    <div class="holdings-toolbar">
                        <span class="holdings-dirty" id="holdingsDirty" hidden><i class="fas fa-circle"></i> 有未保存的修改</span>
                        <div class="holdings-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="holdingsSearch" placeholder="搜索城市" autocomplete="off">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (empty($holdingRows)): ?>
                <div class="text-center" style="padding:40px;color:var(--bct-text-secondary);">
                    <i class="fas fa-wallet" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
                    <p>暂无可管理的城市</p>
                    <p style="font-size:13px;">持有区块后即可在此登记该城市的人气值</p>
                    <a href="../market.php" class="btn btn-primary">去交易</a>
                </div>
                <?php else: ?>
                <form method="post" id="holdingsForm" autocomplete="off">
                    <input type="hidden" name="action" value="save_holdings">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="holdings-table-wrap">
                        <table class="table holdings-table">
                            <thead>
                                <tr>
                                    <th style="width:72px;">排名</th>
                                    <th>城市</th>
                                    <th class="text-right">城市单价</th>
                                    <th class="text-right" style="width:190px;">资产数量（人气值）</th>
                                    <th class="text-right">资产金额</th>
                                    <th class="text-right">占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($holdingRows as $row):
                                    $ratio = $totalAmount > 0 ? $row['amount'] / $totalAmount * 100 : 0;
                                    $rankCls = ($row['rank'] > 0 && $row['rank'] <= 10) ? ' top' : '';
                                ?>
                                <tr class="holding-row" data-price="<?= (float)$row['price'] ?>" data-city="<?= htmlspecialchars($row['name']) ?>">
                                    <td>
                                        <span class="holdings-rank<?= $rankCls ?>">
                                            <?= $row['rank'] > 0 ? (int)$row['rank'] : '—' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                    <td class="text-right num">¥<?= number_format($row['price'], 2) ?></td>
                                    <td class="text-right">
                                        <div class="qty-stepper">
                                            <button type="button" class="qty-btn" data-step="-1" tabindex="-1" aria-label="减少">−</button>
                                            <input type="text" inputmode="numeric" pattern="[0-9]*"
                                                   name="qty[<?= (int)$row['city_id'] ?>]"
                                                   value="<?= (int)$row['qty'] ?>"
                                                   class="qty-input" aria-label="人气值数量">
                                            <button type="button" class="qty-btn" data-step="1" tabindex="-1" aria-label="增加">+</button>
                                        </div>
                                    </td>
                                    <td class="text-right num row-amount">¥<?= number_format($row['amount'], 2) ?></td>
                                    <td class="text-right num row-ratio"><?= number_format($ratio, 2) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>—</td>
                                    <td><strong>合计</strong></td>
                                    <td class="text-right">—</td>
                                    <td class="text-right"><strong>Ⓟ <span id="totalPop"><?= number_format($totalPopularity) ?></span></strong></td>
                                    <td class="text-right"><strong>¥<span id="totalAmount"><?= number_format($totalAmount, 2) ?></span></strong></td>
                                    <td class="text-right num">100.00%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="holdings-actions">
                        <span class="holdings-note">
                            <i class="fas fa-info-circle"></i> 人气值为你的自管记录，真实交易以 blockcity.vip 为准
                        </span>
                        <div class="holdings-btns">
                            <button type="button" class="btn btn-default" id="holdingsReset"><i class="fas fa-undo"></i> 重置</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存持仓</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-chart-pie"></i> 持仓分布</h3></div>
                <div class="card-body">
                    <div id="portfolioPie" style="width:100%;height:300px;<?= $showPie ? '' : 'display:none;' ?>"></div>
                    <div id="portfolioEmpty" class="text-center" style="padding:30px;color:var(--bct-text-secondary);" <?= $showPie ? 'hidden' : '' ?>>暂无数据</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:24px;" id="claims">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;font-size:16px;">
                <i class="fas fa-handshake"></i> 进行中的交易
                <span style="font-size:12px;font-weight:400;color:var(--bct-text-secondary);margin-left:8px;">线下转账后请各自确认；卖方线下已收款可直接确认完成 · 接单 24 小时未确认付款将自动释放</span>
            </h3>
        </div>
        <?php if (empty($activeClaims)): ?>
        <div class="text-center" style="padding:32px;color:var(--bct-text-secondary);">
            <i class="fas fa-mug-hot" style="font-size:36px;display:block;margin-bottom:12px;opacity:.3;"></i>
            <p style="margin:0;font-size:13px;">暂无进行中的交易 · 可去 <a href="../orders.php" style="color:var(--bct-accent);">挂单大厅</a> 接一单</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mob-cards">
                <thead>
                    <tr>
                        <th>城市</th><th>我的角色</th><th>数量</th><th>单价</th><th>总价</th>
                        <th>对方</th><th>联系方式（挂单人登记）</th><th>当前阶段</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeClaims as $c):
                        $amBuyer = (int)$c['buyer_side_user_id'] === (int)$userId;
                        $counterName = $amBuyer ? ($c['seller_side_name'] ?: '用户#'.$c['seller_side_user_id']) : ($c['buyer_side_name'] ?: '用户#'.$c['buyer_side_user_id']);
                        $counterId = $amBuyer ? (int)$c['seller_side_user_id'] : (int)$c['buyer_side_user_id'];
                        $stageText = $c['status'] === 'buyer_confirmed'
                            ? ($amBuyer ? '已确认付款 · 待对方确认收款' : '对方已确认付款 · 待你确认收款')
                            : ($amBuyer ? '待你确认付款' : '待对方确认付款 · 你也可直接确认收款');
                    ?>
                    <tr>
                        <td data-label="城市"><strong><?= htmlspecialchars($c['city']) ?></strong></td>
                        <td data-label="我的角色"><span class="side <?= $amBuyer ? 'buy' : 'sell' ?>"><?= $amBuyer ? '买方（付款）' : '卖方（收款）' ?></span></td>
                        <td data-label="数量"><?= number_format((int)$c['amount']) ?> BCT</td>
                        <td data-label="单价">¥<?= number_format((float)$c['price'], 2) ?></td>
                        <td data-label="总价">¥<?= number_format((int)$c['amount'] * (float)$c['price'], 2) ?></td>
                        <td data-label="对方">
                            <a href="../messages/index.php?with=<?= $counterId ?>" title="站内信联系">
                                <strong><?= htmlspecialchars($counterName) ?></strong>
                            </a>
                        </td>
                        <td data-label="联系方式" class="hall-contact-value"><?= htmlspecialchars($c['contact_info'] ?: '未填写 · 站内信联系') ?></td>
                        <td data-label="当前阶段" style="font-size:12px;"><?= $stageText ?></td>
                        <td data-label="操作" class="mob-actions" style="white-space:nowrap;">
                            <?php if ($amBuyer && $c['status'] === 'matched'): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确认你已线下付款给对方？')">
                                <input type="hidden" name="action" value="claim_buyer_confirm">
                                <input type="hidden" name="order_id" value="<?= (int)$c['order_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <button class="btn btn-xs btn-primary">确认已付款</button>
                            </form>
                            <?php elseif (!$amBuyer && in_array($c['status'], ['matched','buyer_confirmed'], true)): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确认你已线下收到对方款项？确认后交易立即完成（无需等对方确认付款）。')">
                                <input type="hidden" name="action" value="claim_seller_confirm">
                                <input type="hidden" name="order_id" value="<?= (int)$c['order_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <button class="btn btn-xs btn-primary">确认已收款</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确定放弃本次交易？挂单将重新开放给其他人。')">
                                <input type="hidden" name="action" value="claim_abandon">
                                <input type="hidden" name="order_id" value="<?= (int)$c['order_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <button class="btn btn-xs btn-danger">放弃</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top:24px;" id="claim-history">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;font-size:16px;">
                <i class="fas fa-check-circle"></i> 已完成的交易
                <span style="font-size:12px;font-weight:400;color:var(--bct-text-secondary);margin-left:8px;">你作为买方或卖方参与的直接交易，双方确认收款后在此留档（最近 20 笔）</span>
            </h3>
        </div>
        <?php if (empty($completedClaims)): ?>
        <div class="text-center" style="padding:24px;color:var(--bct-text-secondary);">
            <p style="margin:0;font-size:13px;">暂无已完成交易 · 完成确认收款的交易会自动出现在这里</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mob-cards">
                <thead>
                    <tr>
                        <th>城市</th><th>我的角色</th><th>数量</th><th>单价</th><th>总价</th>
                        <th>对方</th><th>完成时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedClaims as $c):
                        $amBuyer = (int)$c['buyer_side_user_id'] === (int)$userId;
                        $counterName = $amBuyer ? ($c['seller_side_name'] ?: '用户#'.$c['seller_side_user_id']) : ($c['buyer_side_name'] ?: '用户#'.$c['buyer_side_user_id']);
                        $counterId = $amBuyer ? (int)$c['seller_side_user_id'] : (int)$c['buyer_side_user_id'];
                    ?>
                    <tr>
                        <td data-label="城市"><strong><?= htmlspecialchars($c['city']) ?></strong></td>
                        <td data-label="我的角色"><span class="side <?= $amBuyer ? 'buy' : 'sell' ?>"><?= $amBuyer ? '买方（付款）' : '卖方（收款）' ?></span></td>
                        <td data-label="数量"><?= number_format((int)$c['amount']) ?> BCT</td>
                        <td data-label="单价">¥<?= number_format((float)$c['price'], 2) ?></td>
                        <td data-label="总价">¥<?= number_format((int)$c['amount'] * (float)$c['price'], 2) ?></td>
                        <td data-label="对方">
                            <a href="../messages/index.php?with=<?= $counterId ?>" title="站内信联系">
                                <strong><?= htmlspecialchars($counterName) ?></strong>
                            </a>
                        </td>
                        <td data-label="完成时间" style="font-size:12px;color:var(--bct-text-secondary);">
                            <?= $c['finished_at'] ? date('Y-m-d H:i', strtotime($c['finished_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top:24px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;font-size:16px;">
                <i class="fas fa-clipboard-list"></i> 我的挂单
                <span style="font-size:12px;font-weight:400;color:var(--bct-text-secondary);margin-left:8px;">仅显示本账号发布的订单</span>
            </h3>
            <a href="../orders.php" class="btn btn-sm btn-default"><i class="fas fa-list"></i> 挂单大厅（看别人的挂单）</a>
        </div>
        <div style="padding:0 16px;">
            <div class="order-tabs" id="orderTabs">
                <a href="?tab=buy" data-tab="buy" class="order-tab <?= $tab=='buy'?'active':'' ?>">买入订单</a>
                <a href="?tab=sell" data-tab="sell" class="order-tab <?= $tab=='sell'?'active':'' ?>">卖出订单</a>
                <a href="?tab=completed" data-tab="completed" class="order-tab <?= $tab=='completed'?'active':'' ?>">已完成</a>
            </div>
        </div>
        <?php foreach (['buy','sell','completed'] as $t): ?>
        <div class="order-pane" data-pane="<?= $t ?>" style="display:<?= $tab===$t ? 'block' : 'none' ?>;">
            <?php renderOrdersPane($ordersByTab[$t] ?? [], $t, $order); ?>
        </div>
        <?php endforeach; ?>

        <div class="hall-hint">
            <i class="fas fa-info-circle"></i>
            以上挂单会同步展示在 <a href="../orders.php">挂单大厅</a>：
            <strong>直接交易</strong>单可被其他用户「接单」，接单后进入上方"进行中的交易"，走 线下转账 → 买方确认付款 → 卖方确认收款 的闭环；
            <strong>平台交易</strong>单（限 500 BCT 以下）由系统自动撮合，无需人工联系；
            <strong>中介交易</strong>单由所选中介协调成交。
        </div>
    </div>
</div>

<script>
$(function() {
    var pieEl = document.getElementById('portfolioPie');
    <?php if ($showPie): ?>
    var pieData = <?= json_encode(array_map(function($r) {
        return ['name'=>$r['name'], 'value'=>round($r['amount'],2)];
    }, $holdingRows)) ?>;
    if (pieEl && typeof BCTCharts !== 'undefined') BCTCharts.initPortfolioPie(pieEl, pieData);
    <?php endif; ?>

    // 我的挂单 tab 无刷新切换（三个列表已一次渲染）
    (function() {
        var tabs = document.querySelectorAll('#orderTabs .order-tab');
        if (!tabs.length) return;
        var panes = document.querySelectorAll('.order-pane[data-pane]');
        var valid = ['buy','sell','completed'];

        function activate(name, pushUrl) {
            if (valid.indexOf(name) < 0) name = 'buy';
            tabs.forEach(function(a) { a.classList.toggle('active', a.dataset.tab === name); });
            panes.forEach(function(p) { p.style.display = p.dataset.pane === name ? 'block' : 'none'; });
            if (pushUrl && window.history && history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                url.hash = '';
                history.replaceState(null, '', url.toString());
            }
        }

        tabs.forEach(function(a) {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                activate(a.dataset.tab, true);
            });
        });

        // 取消订单等 POST 后带 ?tab= 跳回，按参数落位（服务端渲染已保证，此处兜底同步）
        var urlTab = new URL(window.location.href).searchParams.get('tab');
        if (urlTab && valid.indexOf(urlTab) >= 0) activate(urlTab, false);
    })();

    var form = document.getElementById('holdingsForm');
    if (!form) return;

    var rows = Array.prototype.slice.call(form.querySelectorAll('tr.holding-row'));
    var dirtyEl = document.getElementById('holdingsDirty');
    var initial = {};
    rows.forEach(function(tr) { initial[tr.querySelector('.qty-input').name] = tr.querySelector('.qty-input').value; });

    function fmtMoney(n) { return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function fmtInt(n) { return n.toLocaleString('en-US'); }
    function rowQty(tr) {
        var v = parseInt(tr.querySelector('.qty-input').value, 10);
        return (isNaN(v) || v < 0) ? 0 : v;
    }

    function renderPie(data, total) {
        if (!pieEl) return;
        var emptyEl = document.getElementById('portfolioEmpty');
        if (total <= 0) {
            pieEl.style.display = 'none';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        pieEl.style.display = '';
        if (emptyEl) emptyEl.hidden = true;
        if (typeof echarts === 'undefined' || typeof BCTCharts === 'undefined') return;
        var inst = echarts.getInstanceByDom(pieEl);
        if (inst) { inst.setOption({ series: [{ data: data }] }); }
        else { BCTCharts.initPortfolioPie(pieEl, data); }
    }

    function recalc() {
        var total = 0, totalPop = 0, dirty = false;
        rows.forEach(function(tr) {
            var price = parseFloat(tr.getAttribute('data-price')) || 0;
            var input = tr.querySelector('.qty-input');
            var qty = rowQty(tr);
            totalPop += qty;
            total += qty * price;
            if (input.value !== initial[input.name]) dirty = true;
        });

        var pieData = [];
        rows.forEach(function(tr) {
            var price = parseFloat(tr.getAttribute('data-price')) || 0;
            var qty = rowQty(tr);
            var amount = qty * price;
            var ratio = total > 0 ? amount / total * 100 : 0;
            tr.querySelector('.row-amount').textContent = '¥' + fmtMoney(amount);
            tr.querySelector('.row-ratio').textContent = ratio.toFixed(2) + '%';
            pieData.push({ name: tr.getAttribute('data-city'), value: Math.round(amount * 100) / 100 });
        });

        document.getElementById('totalAmount').textContent = fmtMoney(total);
        document.getElementById('totalPop').textContent = fmtInt(totalPop);
        var sumAmount = document.getElementById('sumTotalAmount');
        var sumPop = document.getElementById('sumTotalPop');
        if (sumAmount) sumAmount.textContent = '¥' + fmtMoney(total);
        if (sumPop) sumPop.textContent = 'Ⓟ ' + fmtInt(totalPop);
        if (dirtyEl) dirtyEl.hidden = !dirty;
        renderPie(pieData, total);
    }

    form.addEventListener('click', function(e) {
        var btn = e.target.closest('.qty-btn');
        if (!btn) return;
        var input = btn.parentNode.querySelector('.qty-input');
        var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
        var v = (parseInt(input.value, 10) || 0) + step;
        input.value = v < 0 ? 0 : v;
        recalc();
    });

    form.addEventListener('input', function(e) {
        if (e.target.classList.contains('qty-input')) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
            recalc();
        }
    });

    var search = document.getElementById('holdingsSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            rows.forEach(function(tr) {
                var name = (tr.getAttribute('data-city') || '').toLowerCase();
                tr.classList.toggle('is-hidden', q !== '' && name.indexOf(q) === -1);
            });
        });
    }

    var resetBtn = document.getElementById('holdingsReset');
    if (resetBtn) resetBtn.addEventListener('click', function() { window.location.reload(); });
});
</script>

<?php require_once '../includes/footer.php'; ?>
