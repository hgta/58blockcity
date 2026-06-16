<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Coupon.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$coupon = new Coupon($pdo);

// 获取店铺ID
$shopId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$shopId) {
    $userShop = $shop->getShopByUserId($_SESSION['user_id']);
    if (!$userShop) { header('Location: create.php'); exit; }
    $shopId = $userShop['id'];
}

$userShop = $shop->getShopById($shopId);
if (!$userShop || ($userShop['user_id'] != $_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin')) {
    $myShop = $shop->getShopByUserId($_SESSION['user_id']);
    if ($myShop) { header('Location: coupons.php?id=' . $myShop['id']); exit; }
    header('Location: create.php'); exit;
}

$error = '';
$success = '';

// 处理删除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['coupon_id'])) {
    $cid = intval($_GET['coupon_id']);
    $c = $coupon->getCouponById($cid);
    if ($c && $c['shop_id'] == $shopId) {
        $coupon->deleteCoupon($cid);
        $success = '优惠券已删除';
    } else {
        $error = '优惠券不存在或无权操作';
    }
    header('Location: coupons.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理状态切换
if (isset($_GET['action']) && in_array($_GET['action'], ['activate', 'deactivate']) && isset($_GET['coupon_id'])) {
    $cid = intval($_GET['coupon_id']);
    $c = $coupon->getCouponById($cid);
    if ($c && $c['shop_id'] == $shopId) {
        $newStatus = $_GET['action'] === 'activate' ? 'active' : 'inactive';
        $coupon->updateCoupon($cid, ['status' => $newStatus]);
        $success = '优惠券状态已更新';
    } else {
        $error = '优惠券不存在或无权操作';
    }
    header('Location: coupons.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_action'])) {
    $actionType = $_POST['coupon_action'];
    $data = [
        'shop_id' => $shopId,
        'title' => trim($_POST['title']),
        'code' => trim($_POST['code']) ?: null,
        'type' => $_POST['type'],
        'value' => floatval($_POST['value']),
        'min_order_amount' => floatval($_POST['min_order_amount'] ?? 0),
        'max_discount' => $_POST['max_discount'] ? floatval($_POST['max_discount']) : null,
        'total_quantity' => intval($_POST['total_quantity'] ?? 0),
        'start_date' => $_POST['start_date'] ?: null,
        'end_date' => $_POST['end_date'] ?: null,
        'status' => $_POST['status'] ?? 'active'
    ];

    if (empty($data['title'])) {
        $error = '优惠券名称不能为空';
    } elseif ($data['value'] <= 0) {
        $error = '优惠值必须大于0';
    } else {
        if ($actionType === 'add') {
            $coupon->createCoupon($data);
            $success = '优惠券创建成功';
        } elseif ($actionType === 'edit' && isset($_POST['coupon_id'])) {
            $coupon->updateCoupon(intval($_POST['coupon_id']), $data);
            $success = '优惠券更新成功';
        }
        header('Location: coupons.php?id=' . $shopId . '&success=' . urlencode($success));
        exit;
    }
}

if (isset($_GET['success'])) $success = $_GET['success'];
if (isset($_GET['error'])) $error = $_GET['error'];

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

$stats = $coupon->getShopCouponStats($shopId);
$coupons = $coupon->getShopCoupons($shopId, $statusFilter);

// 处理过期状态（自动标记）
$today = date('Y-m-d');
foreach ($coupons as &$c) {
    if ($c['status'] === 'active' && $c['end_date'] && $c['end_date'] < $today) {
        $c['status'] = 'expired';
    }
}
unset($c);
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">店铺管理</h5></div>
                <div class="list-group list-group-flush">
                    <a href="manage.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action"><i class="fas fa-tachometer-alt"></i> 店铺概览</a>
                    <a href="products.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action"><i class="fas fa-box"></i> 商品管理</a>
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="list-group-item list-group-item-action"><i class="fas fa-plus"></i> 添加商品</a>
                    <a href="orders.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action"><i class="fas fa-shopping-cart"></i> 订单管理</a>
                    <a href="coupons.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action active"><i class="fas fa-ticket-alt"></i> 优惠券</a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action"><i class="fas fa-cog"></i> 支付设置</a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <?php if ($action === 'add' || ($action === 'edit' && isset($_GET['coupon_id']))): ?>
                <?php
                $editCoupon = null;
                if ($action === 'edit') {
                    $editCoupon = $coupon->getCouponById(intval($_GET['coupon_id']));
                    if (!$editCoupon || $editCoupon['shop_id'] != $shopId) {
                        echo '<div class="alert alert-danger">优惠券不存在</div>';
                        require_once '../includes/footer.php';
                        exit;
                    }
                }
                ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><?= $action === 'add' ? '新建优惠券' : '编辑优惠券' ?></h4>
                        <a href="coupons.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> 返回</a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="coupon_action" value="<?= $action ?>">
                            <?php if ($editCoupon): ?><input type="hidden" name="coupon_id" value="<?= $editCoupon['id'] ?>"><?php endif; ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>优惠券名称 *</label>
                                        <input type="text" name="title" class="form-control" required maxlength="100" value="<?= $editCoupon ? htmlspecialchars($editCoupon['title']) : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>优惠码（留空则用户自动领取）</label>
                                        <input type="text" name="code" class="form-control" maxlength="50" placeholder="如 SUMMER2024" value="<?= $editCoupon ? htmlspecialchars($editCoupon['code'] ?? '') : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>优惠类型 *</label>
                                        <select name="type" class="form-control" required>
                                        <option value="fixed" <?= ($editCoupon && $editCoupon['type'] === 'fixed') ? 'selected' : '' ?>>固定金额减免（BCT）</option>
                                        <option value="percent" <?= ($editCoupon && $editCoupon['type'] === 'percent') ? 'selected' : '' ?>>百分比折扣（%）</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>优惠值 *</label>
                                        <input type="number" name="value" class="form-control" step="1" min="1" required value="<?= $editCoupon ? intval($editCoupon['value']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>最低订单金额（0为无限制）</label>
                                        <input type="number" name="min_order_amount" class="form-control" step="1" min="0" value="<?= $editCoupon ? intval($editCoupon['min_order_amount']) : '0' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>最大优惠金额（百分比时生效，留空不限制）</label>
                                        <input type="number" name="max_discount" class="form-control" step="1" min="0" value="<?= $editCoupon ? ($editCoupon['max_discount'] ? intval($editCoupon['max_discount']) : '') : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>发行总量（0为不限量）</label>
                                        <input type="number" name="total_quantity" class="form-control" min="0" value="<?= $editCoupon ? $editCoupon['total_quantity'] : '0' ?>">
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>生效日期</label>
                                                <input type="date" name="start_date" class="form-control" value="<?= $editCoupon ? $editCoupon['start_date'] : '' ?>">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>失效日期</label>
                                                <input type="date" name="end_date" class="form-control" value="<?= $editCoupon ? $editCoupon['end_date'] : '' ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>状态</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?= ($editCoupon && $editCoupon['status'] === 'active') ? 'selected' : '' ?>>启用</option>
                                            <option value="inactive" <?= ($editCoupon && $editCoupon['status'] === 'inactive') ? 'selected' : '' ?>>停用</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $action === 'add' ? '创建' : '保存' ?></button>
                            <a href="coupons.php?id=<?= $shopId ?>" class="btn btn-secondary">取消</a>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">优惠券管理</h4>
                        <a href="coupons.php?action=add&id=<?= $shopId ?>" class="btn btn-primary"><i class="fas fa-plus"></i> 新建优惠券</a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                        <!-- 统计卡片 -->
                        <div class="coupon-stats-row">
                            <div class="coupon-stat-card">
                                <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
                                <div class="stat-label">优惠券总数</div>
                            </div>
                            <div class="coupon-stat-card active-stat">
                                <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
                                <div class="stat-label">进行中</div>
                            </div>
                            <div class="coupon-stat-card">
                                <div class="stat-value"><?= $stats['inactive'] ?? 0 ?></div>
                                <div class="stat-label">已停用</div>
                            </div>
                            <div class="coupon-stat-card">
                                <div class="stat-value"><?= $stats['expired'] ?? 0 ?></div>
                                <div class="stat-label">已过期</div>
                            </div>
                            <div class="coupon-stat-card used-stat">
                                <div class="stat-value"><?= $stats['total_used'] ?? 0 ?></div>
                                <div class="stat-label">总使用次数</div>
                            </div>
                        </div>

                        <!-- 筛选 -->
                        <div class="filter-tabs mb-3">
                            <a href="?id=<?= $shopId ?>" class="filter-tab <?= $statusFilter == 'all' ? 'active' : '' ?>">全部</a>
                            <a href="?id=<?= $shopId ?>&status=active" class="filter-tab <?= $statusFilter == 'active' ? 'active' : '' ?>">进行中</a>
                            <a href="?id=<?= $shopId ?>&status=inactive" class="filter-tab <?= $statusFilter == 'inactive' ? 'active' : '' ?>">已停用</a>
                            <a href="?id=<?= $shopId ?>&status=expired" class="filter-tab <?= $statusFilter == 'expired' ? 'active' : '' ?>">已过期</a>
                        </div>

                        <?php if (empty($coupons)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">暂无优惠券</h5>
                                <a href="coupons.php?action=add&id=<?= $shopId ?>" class="btn btn-primary mt-2"><i class="fas fa-plus"></i> 创建第一个优惠券</a>
                            </div>
                        <?php else: ?>
                            <div class="coupon-grid">
                                <?php foreach ($coupons as $c): 
                                    $progress = ($c['total_quantity'] > 0) ? min(100, round($c['used_quantity'] / $c['total_quantity'] * 100)) : 0;
                                    $isExpired = $c['status'] === 'expired' || ($c['end_date'] && $c['end_date'] < date('Y-m-d'));
                                    $statusLabel = $isExpired ? '已过期' : ($c['status'] === 'active' ? '进行中' : '已停用');
                                    $statusClass = $isExpired ? 'expired' : $c['status'];
                                ?>
                                    <div class="coupon-card status-<?= $statusClass ?>">
                                        <div class="coupon-header">
                                            <h5 class="coupon-title"><?= htmlspecialchars($c['title']) ?></h5>
                                            <span class="coupon-status-badge"><?= $statusLabel ?></span>
                                        </div>
                                        <div class="coupon-value">
                                            <?php if ($c['type'] === 'fixed'): ?>
                                                <span class="value-num">¥<?= number_format($c['value'], 0) ?></span>
                                                <span class="value-unit">立减</span>
                                            <?php else: ?>
                                                <span class="value-num"><?= number_format($c['value'], 0) ?>%</span>
                                                <span class="value-unit">折扣</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($c['min_order_amount'] > 0): ?>
                                            <div class="coupon-condition">满 ¥<?= number_format($c['min_order_amount'], 2) ?> 可用</div>
                                        <?php endif; ?>
                                        <?php if ($c['code']): ?>
                                            <div class="coupon-code">码: <code><?= htmlspecialchars($c['code']) ?></code></div>
                                        <?php endif; ?>
                                        <div class="coupon-date">
                                            <?= $c['start_date'] ? $c['start_date'] : '即日起' ?> ~ <?= $c['end_date'] ? $c['end_date'] : '无期限' ?>
                                        </div>
                                        <?php if ($c['total_quantity'] > 0): ?>
                                            <div class="coupon-progress">
                                                <div class="progress-bar-wrap">
                                                    <div class="progress-bar-fill" style="width:<?= $progress ?>%"></div>
                                                </div>
                                                <div class="progress-text">已用 <?= $c['used_quantity'] ?> / <?= $c['total_quantity'] ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="coupon-progress">
                                                <div class="progress-text">已使用 <?= $c['used_quantity'] ?> 次（不限量）</div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="coupon-actions">
                                            <a href="coupons.php?action=edit&id=<?= $shopId ?>&coupon_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">编辑</a>
                                            <?php if ($c['status'] === 'active' && !$isExpired): ?>
                                                <a href="coupons.php?action=deactivate&id=<?= $shopId ?>&coupon_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('停用此优惠券？')">停用</a>
                                            <?php elseif ($c['status'] === 'inactive' && !$isExpired): ?>
                                                <a href="coupons.php?action=activate&id=<?= $shopId ?>&coupon_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('启用此优惠券？')">启用</a>
                                            <?php endif; ?>
                                            <a href="coupons.php?action=delete&id=<?= $shopId ?>&coupon_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确认删除？')">删除</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.coupon-stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
.coupon-stat-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 14px 8px;
    text-align: center;
}
.coupon-stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a2e;
}
.coupon-stat-card .stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}
.coupon-stat-card.active-stat .stat-value { color: #28a745; }
.coupon-stat-card.used-stat .stat-value { color: #ff6b00; }

.coupon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}
.coupon-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 16px;
    position: relative;
    overflow: hidden;
    transition: all 0.2s;
}
.coupon-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.coupon-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}
.coupon-card.status-active::before { background: #28a745; }
.coupon-card.status-inactive::before { background: #6c757d; }
.coupon-card.status-expired::before { background: #dc3545; }

.coupon-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.coupon-title {
    font-size: 15px;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0;
    padding-right: 8px;
}
.coupon-status-badge {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #e9ecef;
    color: #6c757d;
    white-space: nowrap;
}
.coupon-card.status-active .coupon-status-badge { background: #d4edda; color: #155724; }
.coupon-card.status-inactive .coupon-status-badge { background: #e2e3e5; color: #383d41; }
.coupon-card.status-expired .coupon-status-badge { background: #f8d7da; color: #721c24; }

.coupon-value {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 6px;
}
.value-num {
    font-size: 28px;
    font-weight: 800;
    color: #ff6b00;
}
.value-unit {
    font-size: 13px;
    color: #6c757d;
}
.coupon-condition,
.coupon-code,
.coupon-date {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 4px;
}
.coupon-code code {
    background: #f1f3f5;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 12px;
    color: #ff6b00;
}
.coupon-progress {
    margin: 10px 0;
}
.progress-bar-wrap {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6b00, #ff8533);
    border-radius: 3px;
    transition: width 0.3s;
}
.progress-text {
    font-size: 11px;
    color: #6c757d;
    margin-top: 4px;
}
.coupon-actions {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f1f3f5;
}

@media (max-width: 768px) {
    .coupon-stats-row { grid-template-columns: repeat(3, 1fr); }
}
</style>

<?php require_once '../includes/footer.php'; ?>
