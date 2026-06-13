<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Payment.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$payment = new Payment($pdo);

// 获取用户店铺信息
$userShop = $shop->getShopByUserId($_SESSION['user_id']);
if (!$userShop) {
    header('Location: create.php');
    exit;
}

// 获取店铺ID
$shopId = isset($_GET['id']) ? intval($_GET['id']) : $userShop['id'];

// 验证用户是否有权限管理该店铺
if ($userShop['id'] != $shopId) {
    header('Location: payment-settings.php?id=' . $userShop['id']);
    exit;
}

// 获取店铺支付设置
$paymentSettings = $shop->getPaymentSettings($shopId);

// 获取支持的城市列表
$supportedCities = $shop->getSupportedCities();

$error = '';
$success = '';

// 处理支付设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentSettings = [];
    $hasActivePayment = false;
    
    if (isset($_POST['payment_cities']) && is_array($_POST['payment_cities'])) {
        foreach ($_POST['payment_cities'] as $city) {
            $blockId = trim($_POST['block_id'][$city] ?? '');
            $isActive = isset($_POST['is_active'][$city]) ? 1 : 0;
            $minAmount = floatval($_POST['min_amount'][$city] ?? 0.01);
            $exchangeRate = floatval($_POST['exchange_rate'][$city] ?? 1.0000);
            
            if ($isActive && empty($blockId)) {
                $error = "城市 '{$city}' 已启用但未设置区块ID";
                break;
            }
            
            if (!empty($blockId)) {
                $paymentSettings[] = [
                    'city' => $city,
                    'block_id' => $blockId,
                    'is_active' => $isActive,
                    'min_amount' => $minAmount,
                    'exchange_rate' => $exchangeRate
                ];
                
                if ($isActive) {
                    $hasActivePayment = true;
                }
            }
        }
    }
    
    if (!$error) {
        if (!$hasActivePayment) {
            $error = '请至少启用一个城市的支付方式';
        } else {
            try {
                if ($shop->updatePaymentSettings($shopId, $paymentSettings)) {
                    $success = '支付设置更新成功';
                    $paymentSettings = $shop->getPaymentSettings($shopId);
                } else {
                    $error = '支付设置更新失败';
                }
            } catch (Exception $e) {
                $error = '系统错误：' . $e->getMessage();
            }
        }
    }
}

// 将支付设置转换为以城市为键的数组，便于模板使用
$paymentSettingsByCity = [];
foreach ($paymentSettings as $setting) {
    $paymentSettingsByCity[$setting['city']] = $setting;
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="manage.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i> 店铺概览
                    </a>
                    <a href="products.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="nav-item active">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                    <a href="view.php?id=<?= $shopId ?>" class="nav-item" target="_blank">
                        <i class="fas fa-external-link-alt"></i> 查看店铺
                    </a>
                </nav>
                
                <div class="sidebar-card">
                    <h4>支付统计</h4>
                    <?php
                    $activePayments = array_filter($paymentSettings, function($setting) {
                        return $setting['is_active'] == 1;
                    });
                    ?>
                    <div class="sidebar-stat-row">
                        <span class="label">支持城市</span>
                        <span class="value text-primary"><?= count($paymentSettings) ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">启用支付</span>
                        <span class="value text-success"><?= count($activePayments) ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">总城市数</span>
                        <span class="value text-info"><?= count($supportedCities) ?></span>
                    </div>
                </div>
                
                <div class="sidebar-card mt-3">
                    <h4>帮助说明</h4>
                    <div class="small text-muted">
                        <p class="mb-1"><strong>区块ID:</strong> 您在各城市人气值平台的收款地址标识</p>
                        <p class="mb-1"><strong>最小金额:</strong> 该城市支持的最小支付金额</p>
                        <p class="mb-0"><strong>兑换率:</strong> BCT与人民币的兑换比例</p>
                    </div>
                </div>
            </aside>
        </div>
        
        <div class="col-md-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="card-title mb-0">支付设置</h4>
                    <span class="text-muted"><?= htmlspecialchars($userShop['shop_name']) ?></span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> 支付设置说明</h6>
                        <p class="mb-0">
                            请设置您店铺支持的各城市人气值支付方式。每个城市需要填写对应的区块ID（Block ID），
                            这是您在各城市人气值平台的收款地址标识。启用后，买家可以使用相应城市的人气值购买您的商品。
                        </p>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button type="button" class="btn btn-outline-info btn-sm" data-toggle="modal" data-target="#batchSettingsModal">
                                <i class="fas fa-copy"></i> 批量设置
                            </button>
                        </div>
                        <div class="text-muted small">
                            已配置 <?= count($paymentSettings) ?> 个城市，其中 <?= count($activePayments) ?> 个已启用
                        </div>
                    </div>
                    
                    <form method="POST" id="paymentSettingsForm">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="100">城市</th>
                                        <th>区块ID *</th>
                                        <th width="80">启用</th>
                                        <th width="160">支付参数</th>
                                        <th width="80">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supportedCities as $cityCode => $cityName): ?>
                                        <?php
                                        $setting = $paymentSettingsByCity[$cityCode] ?? [
                                            'city' => $cityCode,
                                            'block_id' => '',
                                            'is_active' => 0,
                                            'min_amount' => 0.01,
                                            'exchange_rate' => 1.0000
                                        ];
                                        ?>
                                        <tr class="payment-setting-row">
                                            <td>
                                                <strong><?= htmlspecialchars($cityName) ?></strong>
                                                <input type="hidden" name="payment_cities[]" value="<?= $cityCode ?>">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm block-id-input" 
                                                       name="block_id[<?= $cityCode ?>]" 
                                                       value="<?= htmlspecialchars($setting['block_id']) ?>" 
                                                       placeholder="请输入区块ID" 
                                                       data-city="<?= $cityCode ?>">
                                                <small class="form-text text-muted">该城市的收款区块ID</small>
                                            </td>
                                            <td class="text-center">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input payment-toggle" 
                                                           id="is_active_<?= $cityCode ?>" 
                                                           name="is_active[<?= $cityCode ?>]" 
                                                           value="1" 
                                                           <?= $setting['is_active'] ? 'checked' : '' ?>
                                                           data-city="<?= $cityCode ?>">
                                                    <label class="custom-control-label" for="is_active_<?= $cityCode ?>"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="param-row">
                                                    <span class="param-label">最小金额:</span>
                                                    <input type="number" class="form-control form-control-sm min-amount-input"
                                                           name="min_amount[<?= $cityCode ?>]"
                                                           value="<?= number_format($setting['min_amount'], 0) ?>"
                                                           step="1" min="1"
                                                           data-city="<?= $cityCode ?>">
                                                    <span class="param-unit">BCT</span>
                                                </div>
                                                <div class="param-row mt-1">
                                                    <span class="param-label">兑换率:</span>
                                                    <input type="number" class="form-control form-control-sm exchange-rate-input" 
                                                           name="exchange_rate[<?= $cityCode ?>]" 
                                                           value="<?= number_format($setting['exchange_rate'], 4) ?>" 
                                                           step="0.0001" min="0.0001" 
                                                           data-city="<?= $cityCode ?>">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-secondary test-payment-btn" 
                                                        data-city="<?= $cityCode ?>" data-city-name="<?= htmlspecialchars($cityName) ?>"
                                                        <?= empty($setting['block_id']) ? 'disabled' : '' ?>>
                                                    <i class="fas fa-vial"></i> 测试
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="form-group mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存支付设置
                            </button>
                            <a href="manage.php?id=<?= $shopId ?>" class="btn btn-secondary">返回店铺管理</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 批量设置模态框 -->
<div class="modal fade" id="batchSettingsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">批量设置</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="关闭">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="batch_min_amount">统一最小金额 (BCT)</label>
                    <input type="number" class="form-control" id="batch_min_amount" step="1" min="1" value="1">
                </div>
                <div class="form-group">
                    <label for="batch_exchange_rate">统一兑换率</label>
                    <input type="number" class="form-control" id="batch_exchange_rate" step="0.0001" min="0.0001" value="1.0000">
                </div>
                <div class="form-group">
                    <label for="batch_block_id_prefix">区块ID前缀</label>
                    <input type="text" class="form-control" id="batch_block_id_prefix" placeholder="例如: pay_">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="applyBatchSettings()">应用设置</button>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== 侧边栏（统一风格） ===== */
.shop-sidebar { width: 100%; }
.sidebar-nav { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; position: relative; }
.nav-item:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
.nav-item.active { background: #fff7ed; color: #ea580c; }
.nav-badge { margin-left: auto; background: #f1f5f9; color: #64748b; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.nav-item.active .nav-badge { background: #fed7aa; color: #c2410c; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.sidebar-card h4 { font-size: 14px; font-weight: 600; color: #1e293b; margin: 0 0 12px; }
.sidebar-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.sidebar-stat-row:last-child { border-bottom: none; }
.sidebar-stat-row .label { color: #64748b; }
.sidebar-stat-row .value { font-weight: 600; }

/* ===== Bootstrap 组件样式补充 ===== */

/* Alert */
.alert { position: relative; padding: 12px 16px; margin-bottom: 16px; border: 1px solid transparent; border-radius: 8px; font-size: 14px; }
.alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }
.alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
.alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
.alert h6 { margin: 0 0 6px; font-size: 14px; font-weight: 600; }
.alert p { margin: 0; }

/* Badge */
.badge { display: inline-block; padding: 4px 8px; font-size: 12px; font-weight: 600; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 4px; }
.badge-success { color: #fff; background-color: #28a745; }
.badge-secondary { color: #fff; background-color: #6c757d; }
.badge-warning { color: #212529; background-color: #ffc107; }

/* Form Control */
.form-control { display: block; width: 100%; padding: 6px 10px; font-size: 14px; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: 6px; transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out; }
.form-control-sm { padding: 4px 8px; font-size: 13px; border-radius: 4px; }
.form-control:focus { color: #495057; background-color: #fff; border-color: #ff6b00; outline: 0; box-shadow: 0 0 0 3px rgba(255,107,0,0.15); }
.form-group { margin-bottom: 16px; }
.form-text { display: block; margin-top: 4px; font-size: 12px; color: #6c757d; }

/* Custom Switch */
.custom-control { position: relative; display: block; min-height: 20px; padding-left: 44px; }
.custom-control-input { position: absolute; left: 0; z-index: -1; width: 20px; height: 20px; opacity: 0; }
.custom-control-label { position: relative; margin-bottom: 0; vertical-align: top; cursor: pointer; }
.custom-control-label::before { position: absolute; top: 2px; left: -44px; display: block; width: 36px; height: 20px; pointer-events: all; content: ""; background-color: #dee2e6; border-radius: 10px; transition: background-color .15s ease-in-out; }
.custom-control-label::after { position: absolute; top: 4px; left: -42px; display: block; width: 16px; height: 16px; content: ""; background-color: #fff; border-radius: 50%; transition: transform .15s ease-in-out; }
.custom-control-input:checked ~ .custom-control-label::before { background-color: #ff6b00; }
.custom-control-input:checked ~ .custom-control-label::after { transform: translateX(16px); }
.custom-switch { padding-left: 44px; }

/* Table */
.table { width: 100%; max-width: 100%; margin-bottom: 16px; background-color: transparent; border-collapse: collapse; }
.table th, .table td { padding: 10px 12px; vertical-align: middle; border-top: 1px solid #dee2e6; font-size: 14px; }
.table thead th { vertical-align: bottom; border-bottom: 2px solid #dee2e6; background-color: #f8f9fa; font-weight: 600; color: #495057; }
.table-bordered { border: 1px solid #dee2e6; }
.table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }
.table-bordered thead th { border-bottom-width: 2px; }

/* Modal */
.modal { position: fixed; top: 0; left: 0; z-index: 1050; display: none; width: 100%; height: 100%; overflow: hidden; outline: 0; }
.modal.show { display: block; }
.modal.fade .modal-dialog { transition: transform .3s ease-out, opacity .3s ease-out; transform: translateY(-50px); opacity: 0; }
.modal.show .modal-dialog { transform: translateY(0); opacity: 1; }
.modal-dialog { position: relative; width: auto; max-width: 500px; margin: 48px auto; pointer-events: none; }
.modal-content { position: relative; display: flex; flex-direction: column; width: 100%; pointer-events: auto; background-color: #fff; background-clip: padding-box; border: 1px solid rgba(0,0,0,0.2); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); outline: 0; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; }
.modal-header .close { padding: 0; margin: 0; background: transparent; border: none; font-size: 24px; line-height: 1; color: #6c757d; cursor: pointer; }
.modal-header .close:hover { color: #1a1a2e; }
.modal-title { margin: 0; font-size: 16px; font-weight: 600; }
.modal-body { position: relative; flex: 1 1 auto; padding: 20px; }
.modal-footer { display: flex; align-items: center; justify-content: flex-end; gap: 8px; padding: 16px 20px; border-top: 1px solid #e9ecef; border-radius: 0 0 12px 12px; }
.modal-backdrop { position: fixed; top: 0; left: 0; z-index: 1040; width: 100vw; height: 100vh; background-color: #000; opacity: 0; transition: opacity .15s linear; }
.modal-backdrop.show { opacity: 0.5; }

/* 页面专用样式 */
.payment-setting-row:hover { background-color: #f8f9fa; }
.param-row { display: flex; align-items: center; gap: 6px; }
.param-label { font-size: 12px; color: #6c757d; white-space: nowrap; }
.param-unit { font-size: 12px; color: #6c757d; }
.min-amount-input, .exchange-rate-input { text-align: right; max-width: 90px; }
.test-payment-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.d-flex { display: flex !important; }
.justify-content-between { justify-content: space-between !important; }
.align-items-center { align-items: center !important; }
.flex-wrap { flex-wrap: wrap !important; }
.gap-2 { gap: 8px !important; }
.mb-3 { margin-bottom: 16px !important; }
.mt-1 { margin-top: 4px !important; }
.mt-3 { margin-top: 16px !important; }
.mt-4 { margin-top: 24px !important; }
.mb-0 { margin-bottom: 0 !important; }
.text-center { text-align: center !important; }
.text-muted { color: #6c757d !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    updateInputsState();
    
    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const city = this.getAttribute('data-city');
            updateCityInputsState(city);
        });
    });
    
    document.querySelectorAll('.block-id-input').forEach(function(input) {
        input.addEventListener('input', function() {
            const city = this.getAttribute('data-city');
            updateTestButtonState(city);
        });
    });
});

function updateInputsState() {
    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        const city = toggle.getAttribute('data-city');
        updateCityInputsState(city);
    });
}

function updateCityInputsState(city) {
    const toggle = document.querySelector('#is_active_' + city);
    const blockIdInput = document.querySelector('input[name="block_id[' + city + ']"]');
    const testButton = document.querySelector('button[data-city="' + city + '"]');
    
    const isActive = toggle.checked;
    const hasBlockId = blockIdInput.value.trim() !== '';
    
    if (isActive && !hasBlockId) {
        blockIdInput.classList.add('is-invalid');
    } else {
        blockIdInput.classList.remove('is-invalid');
    }
    
    updateTestButtonState(city);
}

function updateTestButtonState(city) {
    const toggle = document.querySelector('#is_active_' + city);
    const blockIdInput = document.querySelector('input[name="block_id[' + city + ']"]');
    const testButton = document.querySelector('button[data-city="' + city + '"]');
    
    const isActive = toggle.checked;
    const hasBlockId = blockIdInput.value.trim() !== '';
    
    testButton.disabled = !(isActive && hasBlockId);
}

function applyBatchSettings() {
    const minAmount = document.getElementById('batch_min_amount').value;
    const exchangeRate = document.getElementById('batch_exchange_rate').value;
    const blockIdPrefix = document.getElementById('batch_block_id_prefix').value;
    
    document.querySelectorAll('.min-amount-input').forEach(function(input) {
        input.value = minAmount;
    });
    
    document.querySelectorAll('.exchange-rate-input').forEach(function(input) {
        input.value = exchangeRate;
    });
    
    if (blockIdPrefix) {
        document.querySelectorAll('.block-id-input').forEach(function(input) {
            if (!input.value) {
                input.value = blockIdPrefix + input.getAttribute('data-city');
            }
        });
    }
    
    $('#batchSettingsModal').modal('hide');
    updateInputsState();
}

// 测试按钮改为简单提示
document.querySelectorAll('.test-payment-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const cityName = this.getAttribute('data-city-name');
        alert('「' + cityName + '」支付测试功能开发中，敬请期待');
    });
});

// 表单提交验证
document.getElementById('paymentSettingsForm').addEventListener('submit', function(e) {
    let hasError = false;
    const errorMessages = [];
    
    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        const city = toggle.getAttribute('data-city');
        const blockIdInput = document.querySelector('input[name="block_id[' + city + ']"]');
        const cityName = blockIdInput.closest('tr').querySelector('td strong').textContent;
        
        if (toggle.checked && !blockIdInput.value.trim()) {
            hasError = true;
            errorMessages.push('城市 "' + cityName + '" 已启用但未设置区块ID');
            blockIdInput.classList.add('is-invalid');
        } else {
            blockIdInput.classList.remove('is-invalid');
        }
    });
    
    if (hasError) {
        e.preventDefault();
        alert('请修正以下错误：\n\n' + errorMessages.join('\n'));
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
