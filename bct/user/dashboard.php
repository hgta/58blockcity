<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/BCTOrder.php';

checkLogin();
$userId = $_SESSION['user_id'];

$account = new UserBCTAccount($pdo);
$order   = new BCTOrder($pdo);

// 统计
$userAccounts = $account->getUserAccounts($userId);
$stats = $order->getUserOrderStats($userId);

// 当前 tab
$tab = $_GET['tab'] ?? 'buy';
$tab = in_array($tab, ['buy','sell','completed']) ? $tab : 'buy';

// 分页
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

if ($tab === 'completed') {
    $orders = $order->getUserOrders($userId, 'all', 'completed', $page, $perPage);
    $totalOrders = $stats['completed'];
} else {
    $orders = $order->getUserOrders($userId, $tab, 'active', $page, $perPage);
    $totalOrders = $stats[$tab . '_active'] ?? 0;
}
$totalPages = ceil($totalOrders / $perPage);

// 取消订单
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $oid = intval($_POST['order_id'] ?? 0);
    if ($_POST['action'] === 'cancel' && $oid) {
        if ($order->cancelOrder($oid, $userId)) {
            $msg = '<div class="alert alert-success">订单已取消</div>';
            // 刷新数据
            header("Location: dashboard.php?tab={$tab}");
            exit;
        } else {
            $msg = '<div class="alert alert-danger">取消失败</div>';
        }
    }
}

require_once '../includes/header.php';
?>

<style>
.dash-wrap { max-width:1100px; margin:0 auto; padding:20px 16px; }
.dash-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.dash-header h2 { font-size:22px; margin:0; color:#333; }
.dash-header-actions { display:flex; gap:10px; }
.dash-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:9px 18px; border-radius:8px; font-size:14px; font-weight:600;
    text-decoration:none; transition:.2s; border:none; cursor:pointer;
}
.dash-btn-primary { background:#ff6b00; color:#fff; }
.dash-btn-primary:hover { background:#e55a00; color:#fff; }
.dash-btn-outline { background:#fff; color:#ff6b00; border:2px solid #ff6b00; }
.dash-btn-outline:hover { background:#fff9f0; }

.dash-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.dash-stat { background:#fff; border-radius:12px; padding:18px 16px; box-shadow:0 2px 8px rgba(0,0,0,0.05); text-align:center; }
.dash-stat .val { font-size:26px; font-weight:800; color:#333; }
.dash-stat .lbl { font-size:13px; color:#888; margin-top:4px; }

.dash-card { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.05); margin-bottom:24px; overflow:hidden; }
.dash-card-header { padding:14px 20px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
.dash-card-title { font-size:16px; font-weight:700; color:#333; }

.dash-tabs { display:flex; gap:0; margin-bottom:0; }
.dash-tab {
    padding:10px 20px; font-size:14px; font-weight:600; color:#888;
    border-bottom:3px solid transparent; text-decoration:none; transition:.2s;
}
.dash-tab:hover { color:#ff6b00; }
.dash-tab.active { color:#ff6b00; border-bottom-color:#ff6b00; }

.dash-table { width:100%; border-collapse:collapse; font-size:14px; }
.dash-table th { text-align:left; padding:10px 16px; color:#888; font-weight:600; font-size:12px; text-transform:uppercase; border-bottom:2px solid #f0f0f0; }
.dash-table td { padding:12px 16px; border-bottom:1px solid #f5f5f5; }
.dash-table tr:hover td { background:#fafafa; }
.dash-table .badge {
    display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;
}
.badge-pending { background:#fff3e0; color:#e65100; }
.badge-processing { background:#e3f2fd; color:#1565c0; }
.badge-completed { background:#e8f5e9; color:#2e7d32; }
.badge-canceled { background:#f5f5f5; color:#9e9e9e; }
.badge-expired { background:#ffebee; color:#c62828; }

.time-expired { color:#c62828; }
.time-ok { color:#888; }
.btn-sm { padding:4px 12px; border-radius:6px; font-size:12px; cursor:pointer; border:none; font-weight:600; }
.btn-danger { background:#fee2e2; color:#dc2626; }
.btn-danger:hover { background:#dc2626; color:#fff; }
.btn-default { background:#f5f5f5; color:#666; text-decoration:none; display:inline-block; }
.btn-default:hover { background:#e5e5e5; }

.dash-pagination { display:flex; justify-content:center; gap:4px; padding:16px; flex-wrap:wrap; }
.dash-pagination a, .dash-pagination span {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:32px; height:32px; padding:0 8px; border-radius:6px;
    font-size:13px; text-decoration:none; border:1px solid #e5e5e5; color:#666; background:#fff;
}
.dash-pagination a:hover { border-color:#ff6b00; color:#ff6b00; }
.dash-pagination .active { background:#ff6b00; color:#fff; border-color:#ff6b00; font-weight:700; }
.dash-pagination .disabled { color:#ccc; pointer-events:none; }

.dash-empty { text-align:center; padding:40px 20px; color:#999; }
.dash-empty i { font-size:36px; display:block; margin-bottom:12px; opacity:.4; }

.alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
.alert-success { background:#e8f5e9; color:#2e7d32; }
.alert-danger { background:#ffebee; color:#c62828; }

@media(max-width:768px) {
    .dash-stats { grid-template-columns:repeat(2,1fr); }
    .dash-header { flex-direction:column; align-items:flex-start; }
}
</style>

<div class="dash-wrap">
    <?= $msg ?>

    <div class="dash-header">
        <h2>👤 欢迎回来，<?= htmlspecialchars($_SESSION['username']) ?></h2>
        <div class="dash-header-actions">
            <a href="../market.php?type=buy" class="dash-btn dash-btn-primary">买入BCT</a>
            <a href="../market.php?type=sell" class="dash-btn dash-btn-outline">卖出BCT</a>
            <a href="profile.php" class="dash-btn dash-btn-outline">账户设置</a>
        </div>
    </div>

    <!-- 统计卡片 -->
    <div class="dash-stats">
        <div class="dash-stat">
            <div class="val"><?= count($userAccounts) ?></div>
            <div class="lbl">持有城市</div>
        </div>
        <div class="dash-stat">
            <div class="val"><?= $stats['buy_active'] ?></div>
            <div class="lbl">买入中</div>
        </div>
        <div class="dash-stat">
            <div class="val"><?= $stats['sell_active'] ?></div>
            <div class="lbl">卖出中</div>
        </div>
        <div class="dash-stat">
            <div class="val"><?= $stats['completed'] ?></div>
            <div class="lbl">累计成交</div>
        </div>
    </div>

    <!-- BCT 资产 -->
    <div class="dash-card">
        <div class="dash-card-header"><span class="dash-card-title">我的 BCT 资产</span></div>
        <?php if (empty($userAccounts)): ?>
        <div class="dash-empty">
            <i>📭</i>
            <p>暂无 BCT 资产</p>
            <a href="../market.php" class="dash-btn dash-btn-primary" style="margin-top:10px;">去交易</a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="dash-table">
            <thead><tr>
                <th>城市</th><th>余额</th><th>当前价格</th><th>估值</th>
            </tr></thead>
            <tbody>
            <?php foreach ($userAccounts as $acc): ?>
            <tr>
                <td><strong><?= htmlspecialchars($acc['city']) ?></strong></td>
                <td><?= number_format($acc['balance']) ?> BCT</td>
                <td>¥<?= number_format($acc['current_price'], 4) ?></td>
                <td>¥<?= number_format($acc['balance'] * $acc['current_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- 订单列表 -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-tabs">
                <a href="?tab=buy" class="dash-tab <?= $tab=='buy'?'active':'' ?>">买入订单</a>
                <a href="?tab=sell" class="dash-tab <?= $tab=='sell'?'active':'' ?>">卖出订单</a>
                <a href="?tab=completed" class="dash-tab <?= $tab=='completed'?'active':'' ?>">已完成</a>
            </div>
        </div>

        <?php if (empty($orders)): ?>
        <div class="dash-empty">
            <i><?= $tab=='completed' ? '📋' : '📭' ?></i>
            <p><?= $tab=='completed' ? '暂无成交记录' : '暂无订单' ?></p>
            <a href="../market.php" class="dash-btn dash-btn-primary" style="margin-top:10px;">去交易</a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="dash-table">
            <thead><tr>
                <th>订单号</th><th>城市</th><th>数量</th><th>价格</th><th>总金额</th>
                <th>剩余时间</th><th>状态</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php foreach ($orders as $o):
                // 状态判断
                $status = $o['status'];
                $isExpired = false;
                if ($status !== 'completed' && $status !== 'canceled' && !empty($o['expires_at'])) {
                    if (strtotime($o['expires_at']) < time()) {
                        $isExpired = true;
                    }
                }
                $displayStatus = $isExpired ? 'expired' : $status;

                $statusMap = [
                    'pending'    => ['待成交', 'badge-pending'],
                    'processing' => ['部分成交', 'badge-processing'],
                    'completed'  => ['已完成', 'badge-completed'],
                    'canceled'   => ['已取消', 'badge-canceled'],
                    'expired'    => ['已过期', 'badge-expired'],
                ];
                $s = $statusMap[$displayStatus] ?? [$displayStatus, ''];

                // 剩余时间
                $timeText = '';
                $timeClass = 'time-ok';
                if ($displayStatus === 'expired') {
                    $timeText = '已过期';
                    $timeClass = 'time-expired';
                } elseif ($displayStatus === 'completed' || $displayStatus === 'canceled') {
                    $timeText = '—';
                } elseif (empty($o['expires_at'])) {
                    $timeText = '永久有效';
                } else {
                    $remaining = strtotime($o['expires_at']) - time();
                    $days = floor($remaining / 86400);
                    $hours = floor(($remaining % 86400) / 3600);
                    if ($days > 0) {
                        $timeText = "{$days}天{$hours}时";
                    } elseif ($hours > 0) {
                        $timeText = "{$hours}小时后";
                    } else {
                        $timeText = '即将过期';
                        $timeClass = 'time-expired';
                    }
                }
            ?>
            <tr>
                <td style="font-size:12px;color:#aaa;"><?= substr($o['order_no'], 0, 8) ?></td>
                <td><?= htmlspecialchars($o['city']) ?></td>
                <td><?= number_format($o['amount']) ?> BCT</td>
                <td>¥<?= number_format($o['price'], 4) ?></td>
                <td>¥<?= number_format($o['total_amount'] ?? ($o['amount']*$o['price']), 2) ?></td>
                <td><span class="<?= $timeClass ?>"><?= $timeText ?></span></td>
                <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                <td>
                    <?php if ($displayStatus === 'pending' || $displayStatus === 'processing'): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('确定取消该订单?')">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button class="btn-sm btn-danger">取消</button>
                    </form>
                    <?php elseif ($displayStatus === 'completed'): ?>
                    <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn-sm btn-default">查看</a>
                    <?php else: ?>
                    <span style="color:#ccc;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="dash-pagination">
            <?php
            $q = http_build_query(['tab'=>$tab]);
            $showPrev = $page > 1;
            $showNext = $page < $totalPages;
            ?>
            <?php if ($showPrev): ?><a href="?<?= $q ?>&page=<?= $page-1 ?>">◀</a>
            <?php else: ?><span class="disabled">◀</span><?php endif; ?>

            <?php
            $last = 0; $window = 2;
            for ($i = 1; $i <= $totalPages; $i++) {
                if ($i == 1 || $i == $totalPages || abs($i - $page) <= $window) {
                    if ($last && $i - $last > 1) echo '<span class="disabled">…</span>';
                    $last = $i;
                    if ($i == $page) echo '<span class="active">' . $i . '</span>';
                    else echo '<a href="?' . $q . '&page=' . $i . '">' . $i . '</a>';
                }
            }
            ?>

            <?php if ($showNext): ?><a href="?<?= $q ?>&page=<?= $page+1 ?>">▶</a>
            <?php else: ?><span class="disabled">▶</span><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
