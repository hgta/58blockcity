<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Order.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$order = new Order($pdo);

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
    if ($myShop) { header('Location: orders.php?id=' . $myShop['id']); exit; }
    header('Location: create.php'); exit;
}

$orderId = intval($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    die('无效的订单ID');
}

$orderInfo = $order->getOrderById($orderId);
if (!$orderInfo || $orderInfo['shop_id'] != $shopId) {
    die('订单不存在或无权操作');
}

if ($orderInfo['status'] !== 'paid') {
    die('当前订单状态不允许发货');
}

$error = '';
$success = '';

// 处理发货提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shippingCompany = trim($_POST['shipping_company'] ?? '');
    $trackingNo = trim($_POST['tracking_no'] ?? '');
    
    if (empty($shippingCompany)) {
        $error = '请选择或填写物流公司';
    } elseif (empty($trackingNo)) {
        $error = '请填写运单号';
    } else {
        $result = $order->updateOrderStatus($orderId, 'shipped', '', [
            'shipping_company' => $shippingCompany,
            'tracking_no' => $trackingNo
        ]);
        
        if ($result) {
            $success = '订单已发货';
        } else {
            $error = '发货失败，请重试';
        }
    }
}

// 物流公司列表
$shippingCompanies = [
    '顺丰速运', '中通快递', '圆通速递', '申通快递', '韵达快递',
    '百世快递', '京东物流', '德邦快递', 'EMS', '邮政快递',
    '极兔速递', '天天快递', '优速快递', '宅急送', '其他'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单发货 - <?= htmlspecialchars($userShop['shop_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 30px; }
        .card-title { font-size: 20px; font-weight: bold; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .order-info { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 25px; }
        .order-info-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .order-info-label { color: #666; }
        .order-info-value { font-weight: 500; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; color: #333; margin-bottom: 8px; font-weight: 500; }
        .form-label .required { color: #e74c3c; margin-left: 4px; }
        .form-select, .form-input {
            width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 6px;
            font-size: 15px; outline: none; transition: border-color 0.3s;
        }
        .form-select:focus, .form-input:focus { border-color: #3498db; }
        .form-hint { font-size: 12px; color: #999; margin-top: 6px; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 15px;
               text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-back { background: #95a5a6; color: white; }
        .btn-back:hover { background: #7f8c8d; }
        .actions { display: flex; gap: 12px; margin-top: 25px; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-title"><i class="fas fa-truck"></i> 订单发货</div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <div class="actions">
                <a href="orders.php?id=<?= $shopId ?>" class="btn btn-primary"><i class="fas fa-list"></i> 返回订单列表</a>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <div class="order-info">
            <div class="order-info-row">
                <span class="order-info-label">订单号</span>
                <span class="order-info-value"><?= htmlspecialchars($orderInfo['order_no']) ?></span>
            </div>
            <div class="order-info-row">
                <span class="order-info-label">实付金额</span>
                <span class="order-info-value"><span class="bct-symbol">Ⓟ</span><?= number_format($orderInfo['total_amount'], 0) ?> 人气值</span>
            </div>
            <div class="order-info-row">
                <span class="order-info-label">收货地址</span>
                <span class="order-info-value"><?= htmlspecialchars($orderInfo['shipping_address'] ?? '未填写') ?></span>
            </div>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">物流公司 <span class="required">*</span></label>
                <select name="shipping_company" class="form-select" id="shipping-company" required>
                    <option value="">请选择物流公司</option>
                    <?php foreach ($shippingCompanies as $company): ?>
                        <option value="<?= htmlspecialchars($company) ?>"><?= htmlspecialchars($company) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="custom-company-wrapper" style="margin-top: 10px; display: none;">
                    <input type="text" name="shipping_company_custom" class="form-input" placeholder="请输入物流公司名称">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">运单号 <span class="required">*</span></label>
                <input type="text" name="tracking_no" class="form-input" placeholder="请输入快递单号" required>
                <div class="form-hint">请填写准确的快递单号，方便买家查询物流</div>
            </div>
            
            <div class="actions">
                <a href="orders.php?id=<?= $shopId ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> 返回</a>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> 确认发货</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('shipping-company').addEventListener('change', function() {
    var customWrapper = document.getElementById('custom-company-wrapper');
    var customInput = customWrapper.querySelector('input');
    if (this.value === '其他') {
        customWrapper.style.display = 'block';
        customInput.setAttribute('required', 'required');
        customInput.setAttribute('name', 'shipping_company');
        this.removeAttribute('name');
    } else {
        customWrapper.style.display = 'none';
        customInput.removeAttribute('required');
        customInput.setAttribute('name', 'shipping_company_custom');
        this.setAttribute('name', 'shipping_company');
    }
});
</script>

</body>
</html>
