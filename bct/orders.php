<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/BCTOrder.php';

$bctOrder = new BCTOrder($pdo);

// 惰性推进过期订单，保证大厅不展示已超期的挂单
try {
    $bctOrder->expireOverdueOrders(null, null, 500);
} catch (Exception $e) {
    error_log('BCT hall expire error: ' . $e->getMessage());
}

$loggedIn = isLoggedIn();
$currentUserId = $loggedIn ? (int)$_SESSION['user_id'] : 0;
$loginUrl = '../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);

// 筛选参数
$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'buy', 'sell'], true)) $type = 'all';
$tradeType = $_GET['trade_type'] ?? 'all';
if (!in_array($tradeType, ['all', 'direct', 'platform', 'mediator'], true)) $tradeType = 'all';
$citySearch = trim($_GET['city'] ?? '');
$sort = $_GET['sort'] ?? 'time';
if (!in_array($sort, ['time', 'price_asc', 'price_desc', 'amount_desc'], true)) $sort = 'time';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$filters = [
    'type' => $type !== 'all' ? $type : null,
    'trade_type' => $tradeType !== 'all' ? $tradeType : null,
    'city' => $citySearch !== '' ? $citySearch : null,
];

$orders = [];
$total = 0;
$totalPages = 1;
$buyCount = $sellCount = 0;

try {
    $total = $bctOrder->countActiveOrders($filters);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $orders = $bctOrder->searchActiveOrders($filters, $page, $perPage, $sort);

    // 顶部统计（不受城市/方式筛选影响，只按方向统计）
    $buyCount = $bctOrder->countActiveOrders(['type' => 'buy']);
    $sellCount = $bctOrder->countActiveOrders(['type' => 'sell']);
} catch (Exception $e) {
    error_log('BCT hall list error: ' . $e->getMessage());
}

$tradeTypeText = [
    'direct' => '直接交易',
    'platform' => '平台交易',
    'mediator' => '中介交易',
];

function hallUrl(array $overrides = []) {
    $q = array_merge([
        'type' => $GLOBALS['type'],
        'trade_type' => $GLOBALS['tradeType'],
        'city' => $GLOBALS['citySearch'],
        'sort' => $GLOBALS['sort'],
        'page' => 1,
    ], $overrides);
    $q = array_filter($q, function($v, $k) {
        if ($k === 'type' || $k === 'trade_type') return $v !== 'all';
        if ($k === 'city') return $v !== '';
        if ($k === 'sort') return $v !== 'time';
        if ($k === 'page') return $v > 1;
        return true;
    }, ARRAY_FILTER_USE_BOTH);
    return '?' . http_build_query($q);
}

$site_config['title'] = '挂单大厅 - 买卖挂单明细 | 58人气值市场';
$site_config['description'] = '查看全部城市人气值(BCT)买卖挂单明细：挂单人、单价、数量、交易方式与联系方式，支持直接交易与站内信联系。';

require_once 'includes/header.php';
?>

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-list"></i> 挂单大厅</h1>
        <div class="subtitle">全部在挂买卖订单 · 可直接联系挂单人协商交易</div>
    </div>
    <div>
        <a href="trade.php" class="btn btn-primary"><i class="fas fa-plus"></i> 发布交易</a>
        <a href="market.php" class="btn btn-default" style="margin-left:8px;"><i class="fas fa-chart-line"></i> 行情中心</a>
    </div>
</div>

<!-- 方向/统计切换 -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div class="hall-type-tabs">
            <a href="<?= hallUrl(['type' => 'all', 'page' => 1]) ?>" class="btn btn-sm <?= $type === 'all' ? 'btn-default active' : 'btn-outline' ?>">全部 (<?= number_format($buyCount + $sellCount) ?>)</a>
            <a href="<?= hallUrl(['type' => 'buy', 'page' => 1]) ?>" class="btn btn-sm <?= $type === 'buy' ? 'btn-primary active' : 'btn-outline' ?>"><i class="fas fa-arrow-down" style="color:var(--bct-up);"></i> 求购 (<?= number_format($buyCount) ?>)</a>
            <a href="<?= hallUrl(['type' => 'sell', 'page' => 1]) ?>" class="btn btn-sm <?= $type === 'sell' ? 'btn-primary active' : 'btn-outline' ?>"><i class="fas fa-arrow-up" style="color:var(--bct-down);"></i> 挂售 (<?= number_format($sellCount) ?>)</a>
        </div>
        <?php if (!$loggedIn): ?>
        <div style="font-size:13px;color:var(--bct-text-secondary);">
            <i class="fas fa-lock"></i>
            <a href="<?= htmlspecialchars($loginUrl) ?>" style="color:var(--bct-accent);">登录后</a>可查看联系方式并发起私信
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 筛选 -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body">
        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <?php if ($type !== 'all'): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><?php endif; ?>
            <div style="flex:1;min-width:180px;">
                <input type="text" name="city" class="form-control" placeholder="按城市筛选..." value="<?= htmlspecialchars($citySearch) ?>">
            </div>
            <select name="trade_type" class="form-control" style="width:auto;">
                <option value="all">全部方式</option>
                <option value="direct" <?= $tradeType === 'direct' ? 'selected' : '' ?>>直接交易</option>
                <option value="platform" <?= $tradeType === 'platform' ? 'selected' : '' ?>>平台交易</option>
                <option value="mediator" <?= $tradeType === 'mediator' ? 'selected' : '' ?>>中介交易</option>
            </select>
            <select name="sort" class="form-control" style="width:auto;">
                <option value="time">最新发布</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>价格从高到低</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>价格从低到高</option>
                <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>数量最多</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 筛选</button>
            <?php if ($citySearch !== '' || $tradeType !== 'all' || $sort !== 'time'): ?>
            <a href="<?= hallUrl(['city' => '', 'trade_type' => 'all', 'sort' => 'time', 'page' => 1]) ?>" class="btn btn-default">重置</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 挂单列表 -->
<div class="card">
    <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-stream"></i> 挂单明细 <span style="font-weight:400;font-size:13px;color:var(--bct-text-secondary);">共 <?= number_format($total) ?> 条</span></h3></div>
    <div class="table-responsive">
        <table class="table bct-hall-table">
            <thead>
                <tr>
                    <th>方向</th>
                    <th>城市</th>
                    <th>单价</th>
                    <th>数量</th>
                    <th>总价</th>
                    <th>挂单人</th>
                    <th>交易方式</th>
                    <th>联系方式</th>
                    <th>有效期</th>
                    <th>发布时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="11" class="text-center" style="padding:40px;color:var(--bct-text-secondary);">暂无符合条件的挂单</td></tr>
                <?php else: ?>
                <?php foreach ($orders as $o):
                    $isBuy = $o['type'] === 'buy';
                    $isMine = $currentUserId > 0 && (int)$o['user_id'] === $currentUserId;
                ?>
                <tr>
                    <td><span class="side <?= $isBuy ? 'buy' : 'sell' ?>"><?= $isBuy ? '求购' : '挂售' ?></span></td>
                    <td><a href="city.php?city=<?= urlencode($o['city']) ?>" style="color:var(--bct-text);"><strong><?= htmlspecialchars($o['city']) ?></strong></a></td>
                    <td class="price">¥<?= number_format($o['price'], 2) ?></td>
                    <td><?= number_format($o['amount']) ?></td>
                    <td>¥<?= number_format($o['amount'] * $o['price'], 2) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($o['username'] ?: '匿名用户') ?></strong>
                        <?php if ($isMine): ?><span class="hall-badge mine">我</span><?php endif; ?>
                    </td>
                    <td><?= $tradeTypeText[$o['trade_type']] ?? $o['trade_type'] ?></td>
                    <td class="hall-contact">
                        <?php if ($o['trade_type'] !== 'direct'): ?>
                            <span class="text-muted"><?= $o['trade_type'] === 'mediator' ? '由中介协调' : '平台撮合' ?></span>
                        <?php elseif (empty($o['contact_info'])): ?>
                            <span class="text-muted">未填写 · 站内信联系</span>
                        <?php elseif ($loggedIn): ?>
                            <strong class="hall-contact-value"><?= htmlspecialchars($o['contact_info']) ?></strong>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($loginUrl) ?>" style="font-size:12px;"><i class="fas fa-lock"></i> 登录后可见</a>
                        <?php endif; ?>
                    </td>
                    <td><?= $o['expires_at'] ? date('m-d H:i', strtotime($o['expires_at'])) : '长期' ?></td>
                    <td class="text-muted" style="white-space:nowrap;"><?= date('m-d H:i', strtotime($o['created_at'])) ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($isMine): ?>
                            <a href="user/orders.php" class="btn btn-xs btn-default">管理</a>
                        <?php else: ?>
                            <?php if ($loggedIn): ?>
                            <a href="messages/index.php?with=<?= (int)$o['user_id'] ?>" class="btn btn-xs btn-default" title="站内信联系挂单人"><i class="fas fa-comment-dots"></i> 私信</a>
                            <?php else: ?>
                            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-xs btn-default"><i class="fas fa-comment-dots"></i> 私信</a>
                            <?php endif; ?>
                            <a href="trade.php?city=<?= urlencode($o['city']) ?>" class="btn btn-xs btn-primary">交易</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:16px;display:flex;justify-content:center;">
        <ul class="pagination">
            <?php if ($page > 1): ?>
            <li><a href="<?= hallUrl(['page' => $page - 1]) ?>">上一页</a></li>
            <?php else: ?><li class="disabled"><span>上一页</span></li><?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++):
                if ($i == 1 || $i == $totalPages || abs($i - $page) <= 2):
            ?>
            <li <?= $i == $page ? 'class="active"' : '' ?>><a href="<?= hallUrl(['page' => $i]) ?>"><?= $i ?></a></li>
            <?php elseif (abs($i - $page) == 3): ?><li class="disabled"><span>...</span></li><?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <li><a href="<?= hallUrl(['page' => $page + 1]) ?>">下一页</a></li>
            <?php else: ?><li class="disabled"><span>下一页</span></li><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- 交易安全提示 -->
<div class="card" style="margin-top:24px;">
    <div class="card-body" style="font-size:13px;color:var(--bct-text-secondary);line-height:2;">
        <strong style="color:var(--bct-text);"><i class="fas fa-shield-alt"></i> 交易提示：</strong>
        直接交易请先与挂单人通过站内信或联系方式充分沟通，确认数量、单价与支付方式后再线下完成交易；
        大额交易建议选择中介交易（手续费 2%）保障双方资金安全；平台交易（限 500 BCT 以下）由系统自动撮合并结算。
    </div>
</div>

<style>
.bct-hall-table { margin-bottom: 0; color: var(--bct-text); }
.bct-hall-table > thead > tr > th {
    background: var(--bct-bg-tertiary);
    color: var(--bct-text-secondary);
    border-bottom: 1px solid var(--bct-border);
    font-weight: 500;
    font-size: 12px;
    white-space: nowrap;
}
.bct-hall-table > tbody > tr > td {
    border-top: 1px solid var(--bct-border);
    color: var(--bct-text);
    vertical-align: middle;
    font-size: 13px;
}
.bct-hall-table > tbody > tr:hover { background: var(--bct-bg-hover); }
.bct-hall-table .price { font-weight: 600; }
.bct-hall-table .side.buy { color: var(--bct-up); font-weight: 600; }
.bct-hall-table .side.sell { color: var(--bct-down); font-weight: 600; }
.hall-badge.mine {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 6px;
    font-size: 11px;
    border-radius: 4px;
    background: rgba(240, 185, 11, 0.15);
    color: var(--bct-accent);
}
.hall-contact-value { color: var(--bct-accent); word-break: break-all; }
.hall-type-tabs { display: inline-flex; gap: 8px; flex-wrap: wrap; }
.btn-outline {
    background: transparent;
    border: 1px solid var(--bct-border);
    color: var(--bct-text-secondary);
}
.btn-outline:hover { border-color: var(--bct-accent); color: var(--bct-accent); }
.text-muted { color: var(--bct-text-muted); }
</style>

<?php require_once 'includes/footer.php'; ?>
