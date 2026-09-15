<?php
/**
 * BCT 后台 - 交易管理
 *
 * 订单列表（筛选/分页）+ 订单状态操作（取消 / 完成 / 删除）
 */
require_once '../../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$message = '';
$error = '';

// ---------------- 操作处理 ----------------
function adminOrderFail($msg) {
    $_SESSION['admin_order_error'] = $msg;
    header('Location: orders.php' . (isset($_POST['return_query']) ? '?' . $_POST['return_query'] : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);
    $returnQuery = $_POST['return_query'] ?? '';

    if ($orderId <= 0) adminOrderFail('无效的订单');

    try {
        $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) adminOrderFail('订单不存在');

        switch ($action) {
            case 'cancel':
                if ($target['status'] === 'completed') adminOrderFail('已完成的订单不可取消');
                $pdo->prepare("UPDATE bct_orders SET status = 'canceled' WHERE id = ?")->execute([$orderId]);
                $_SESSION['admin_order_message'] = '订单 ' . $target['order_no'] . ' 已取消';
                break;

            case 'complete':
                if ($target['status'] === 'canceled') adminOrderFail('已取消的订单不可完成');
                $pdo->prepare("UPDATE bct_orders SET status = 'completed' WHERE id = ?")->execute([$orderId]);
                $_SESSION['admin_order_message'] = '订单 ' . $target['order_no'] . ' 已标记完成';
                break;

            case 'pending':
                // 恢复为进行中
                $pdo->prepare("UPDATE bct_orders SET status = 'pending' WHERE id = ?")->execute([$orderId]);
                $_SESSION['admin_order_message'] = '订单 ' . $target['order_no'] . ' 已恢复为进行中';
                break;

            case 'delete':
                // 存在关联交易记录时不允许删除，避免破坏账目
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bct_transactions WHERE order_id = ?");
                $stmt->execute([$orderId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    adminOrderFail('该订单已有成交记录，不可删除');
                }
                $pdo->prepare("DELETE FROM bct_orders WHERE id = ?")->execute([$orderId]);
                $_SESSION['admin_order_message'] = '订单 ' . $target['order_no'] . ' 已删除';
                break;

            default:
                adminOrderFail('未知操作');
        }
    } catch (Exception $e) {
        adminOrderFail('操作失败：' . $e->getMessage());
    }

    header('Location: orders.php' . ($returnQuery ? '?' . $returnQuery : ''));
    exit;
}

if (!empty($_SESSION['admin_order_message'])) {
    $message = $_SESSION['admin_order_message'];
    unset($_SESSION['admin_order_message']);
}
if (!empty($_SESSION['admin_order_error'])) {
    $error = $_SESSION['admin_order_error'];
    unset($_SESSION['admin_order_error']);
}

// ---------------- 筛选与分页 ----------------
$filterCity   = trim($_GET['city'] ?? '');
$filterType   = $_GET['type'] ?? '';
$filterTrade  = $_GET['trade_type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterUser   = trim($_GET['user'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where = [];
$params = [];

if ($filterCity !== '') {
    $where[] = 'o.city = ?';
    $params[] = $filterCity;
}
if (in_array($filterType, ['buy', 'sell'], true)) {
    $where[] = 'o.type = ?';
    $params[] = $filterType;
}
if (in_array($filterTrade, ['platform', 'mediator', 'direct'], true)) {
    $where[] = 'o.trade_type = ?';
    $params[] = $filterTrade;
}
if (in_array($filterStatus, ['pending', 'processing', 'completed', 'canceled', 'expired'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $filterStatus;
}
if ($filterUser !== '') {
    $where[] = '(u.username LIKE ? OR o.contact_info LIKE ?)';
    $params[] = '%' . $filterUser . '%';
    $params[] = '%' . $filterUser . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// 总数
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM bct_orders o
    LEFT JOIN users u ON o.user_id = u.id
    $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// 列表
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.phone,
           (SELECT COUNT(*) FROM bct_transactions t WHERE t.order_id = o.id) AS tx_count
    FROM bct_orders o
    LEFT JOIN users u ON o.user_id = u.id
    $whereSql
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 汇总统计（不受筛选影响，便于概览）
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) AS processing,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status='canceled' THEN 1 ELSE 0 END) AS canceled,
        SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) AS expired,
        COALESCE(SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END), 0) AS completed_amount
    FROM bct_orders
")->fetch(PDO::FETCH_ASSOC);

// 城市下拉（仅取有订单的城市）
$cityOptions = $pdo->query("SELECT DISTINCT city FROM bct_orders ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);

// 保留当前筛选，供操作后跳回
$returnQuery = http_build_query(array_filter([
    'city' => $filterCity,
    'type' => $filterType,
    'trade_type' => $filterTrade,
    'status' => $filterStatus,
    'user' => $filterUser,
    'page' => $page > 1 ? $page : '',
]));

$typeLabels  = ['buy' => '买入', 'sell' => '卖出'];
$tradeLabels = ['platform' => '平台', 'mediator' => '中介', 'direct' => '直接'];
$statusLabels = [
    'pending'    => '进行中',
    'processing' => '部分成交',
    'completed'  => '已完成',
    'canceled'   => '已取消',
    'expired'    => '已过期',
];
$statusBadge = [
    'pending'    => 'warning',
    'processing' => 'info',
    'completed'  => 'success',
    'canceled'   => 'default',
    'expired'    => 'default',
];

$admin_site_config = ['site' => 'bct', 'page_title' => '交易管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if ($message): ?>
<div class="admin-alert success" style="margin-bottom:16px;">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="admin-alert danger" style="margin-bottom:16px;">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- 统计卡片 -->
<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
        <div class="stat-label">总订单</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= number_format(($stats['pending'] ?? 0) + ($stats['processing'] ?? 0)) ?></div>
        <div class="stat-label">进行中</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= number_format($stats['completed'] ?? 0) ?></div>
        <div class="stat-label">已完成</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-coins"></i></div>
        <div class="stat-value">¥<?= number_format($stats['completed_amount'] ?? 0, 2) ?></div>
        <div class="stat-label">完成成交额</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-hourglass-end"></i></div>
        <div class="stat-value"><?= number_format($stats['expired'] ?? 0) ?></div>
        <div class="stat-label">已过期</div>
    </div>
</div>

<!-- 筛选 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div style="min-width:150px;">
                <label class="admin-form-label">城市</label>
                <select name="city" class="admin-form-select">
                    <option value="">全部城市</option>
                    <?php foreach ($cityOptions as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filterCity === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:110px;">
                <label class="admin-form-label">方向</label>
                <select name="type" class="admin-form-select">
                    <option value="">全部</option>
                    <option value="buy"  <?= $filterType === 'buy'  ? 'selected' : '' ?>>买入</option>
                    <option value="sell" <?= $filterType === 'sell' ? 'selected' : '' ?>>卖出</option>
                </select>
            </div>
            <div style="min-width:110px;">
                <label class="admin-form-label">交易方式</label>
                <select name="trade_type" class="admin-form-select">
                    <option value="">全部</option>
                    <option value="platform" <?= $filterTrade === 'platform' ? 'selected' : '' ?>>平台</option>
                    <option value="mediator" <?= $filterTrade === 'mediator' ? 'selected' : '' ?>>中介</option>
                    <option value="direct"   <?= $filterTrade === 'direct'   ? 'selected' : '' ?>>直接</option>
                </select>
            </div>
            <div style="min-width:120px;">
                <label class="admin-form-label">状态</label>
                <select name="status" class="admin-form-select">
                    <option value="">全部</option>
                    <?php foreach ($statusLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:160px;flex:1;">
                <label class="admin-form-label">用户 / 联系方式</label>
                <input type="text" name="user" class="admin-form-input" value="<?= htmlspecialchars($filterUser) ?>" placeholder="用户名或联系方式">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 筛选</button>
                <a href="orders.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
            </div>
        </form>
    </div>
</div>

<!-- 订单列表 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-list"></i> 订单列表（共 <?= number_format($total) ?> 条）</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div style="overflow-x:auto;">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>订单号</th>
                        <th>用户</th>
                        <th>城市</th>
                        <th>方向</th>
                        <th style="text-align:right;">数量</th>
                        <th style="text-align:right;">单价</th>
                        <th style="text-align:right;">总价</th>
                        <th>方式</th>
                        <th>联系方式</th>
                        <th>状态</th>
                        <th>发布时间</th>
                        <th style="text-align:center;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($o['order_no']) ?></td>
                        <td><?= htmlspecialchars($o['username'] ?? ('#' . $o['user_id'])) ?></td>
                        <td><?= htmlspecialchars($o['city']) ?></td>
                        <td>
                            <span class="admin-badge <?= $o['type'] === 'buy' ? 'success' : 'danger' ?>">
                                <?= $typeLabels[$o['type']] ?? $o['type'] ?>
                            </span>
                        </td>
                        <td style="text-align:right;font-family:monospace;"><?= number_format($o['amount']) ?></td>
                        <td style="text-align:right;font-family:monospace;"><?= number_format($o['price'], 2) ?></td>
                        <td style="text-align:right;font-family:monospace;"><?= number_format($o['total_amount'], 2) ?></td>
                        <td><?= $tradeLabels[$o['trade_type']] ?? $o['trade_type'] ?></td>
                        <td style="font-size:12px;color:#94a3b8;"><?= htmlspecialchars($o['contact_info'] ?: '-') ?></td>
                        <td>
                            <span class="admin-badge <?= $statusBadge[$o['status']] ?? 'default' ?>">
                                <?= $statusLabels[$o['status']] ?? $o['status'] ?>
                            </span>
                            <?php if ((int)$o['tx_count'] > 0): ?>
                            <i class="fas fa-exchange-alt" title="已有成交记录" style="color:#60a5fa;font-size:11px;margin-left:4px;"></i>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:#94a3b8;white-space:nowrap;">
                            <?= $o['created_at'] ? date('Y-m-d H:i', strtotime($o['created_at'])) : '-' ?>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <form method="post" style="display:inline;" onsubmit="return confirm('确定执行该操作？');">
                                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                <?php if ($o['status'] !== 'completed'): ?>
                                <button type="submit" name="action" value="complete" class="admin-btn admin-btn-sm admin-btn-primary" title="标记完成">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($o['status'] !== 'canceled'): ?>
                                <button type="submit" name="action" value="cancel" class="admin-btn admin-btn-sm admin-btn-secondary" title="取消订单">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($o['status'] !== 'pending'): ?>
                                <button type="submit" name="action" value="pending" class="admin-btn admin-btn-sm admin-btn-default" title="恢复为进行中">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ((int)$o['tx_count'] === 0): ?>
                                <button type="submit" name="action" value="delete" class="admin-btn admin-btn-sm admin-btn-danger" title="删除订单">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="12" style="text-align:center;color:#64748b;padding:32px;">暂无订单记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 分页 -->
<?php if ($totalPages > 1): ?>
<?php
$qs = function ($p) use ($filterCity, $filterType, $filterTrade, $filterStatus, $filterUser) {
    return '?' . http_build_query(array_filter([
        'city' => $filterCity,
        'type' => $filterType,
        'trade_type' => $filterTrade,
        'status' => $filterStatus,
        'user' => $filterUser,
        'page' => $p,
    ]));
};
$start = max(1, $page - 2);
$end = min($totalPages, $page + 2);
?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap;">
    <?php if ($page > 1): ?>
    <a href="<?= $qs($page - 1) ?>" class="admin-btn admin-btn-default admin-btn-sm">上一页</a>
    <?php endif; ?>

    <?php if ($start > 1): ?>
    <a href="<?= $qs(1) ?>" class="admin-btn admin-btn-sm <?= $page === 1 ? 'admin-btn-primary' : 'admin-btn-default' ?>">1</a>
    <?php if ($start > 2): ?><span style="color:#64748b;">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
    <a href="<?= $qs($i) ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($end < $totalPages): ?>
    <?php if ($end < $totalPages - 1): ?><span style="color:#64748b;">…</span><?php endif; ?>
    <a href="<?= $qs($totalPages) ?>" class="admin-btn admin-btn-sm <?= $page === $totalPages ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $totalPages ?></a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
    <a href="<?= $qs($page + 1) ?>" class="admin-btn admin-btn-default admin-btn-sm">下一页</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
