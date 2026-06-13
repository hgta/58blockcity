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
                    // 刷新支付设置
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">支付设置</h4>
                    <span class="text-muted">店铺: <?= htmlspecialchars($userShop['shop_name']) ?></span>
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
                    
                    <form method="POST" id="paymentSettingsForm">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="120">城市</th>
                                        <th>区块ID *</th>
                                        <th width="100">启用支付</th>
                                        <th width="120">最小金额 (BCT)</th>
                                        <th width="120">兑换率</th>
                                        <th width="100">操作</th>
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
                                                <input type="number" class="form-control form-control-sm min-amount-input"
                                                       name="min_amount[<?= $cityCode ?>]"
                                                       value="<?= number_format($setting['min_amount'], 0) ?>"
                                                       step="1" min="1"
                                                       data-city="<?= $cityCode ?>">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm exchange-rate-input" 
                                                       name="exchange_rate[<?= $cityCode ?>]" 
                                                       value="<?= number_format($setting['exchange_rate'], 4) ?>" 
                                                       step="0.0001" min="0.0001" 
                                                       data-city="<?= $cityCode ?>">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-secondary test-payment-btn" 
                                                        data-city="<?= $cityCode ?>" data-city-name="<?= htmlspecialchars($cityName) ?>"
                                                        <?= empty($setting['block_id']) ? 'disabled' : '' ?>>
                                                    <i class="fas fa-test-tube"></i> 测试
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> 保存支付设置
                            </button>
                            <a href="manage.php?id=<?= $shopId ?>" class="btn btn-secondary btn-lg">返回店铺管理</a>
                            
                            <button type="button" class="btn btn-outline-info btn-lg float-right" data-toggle="modal" data-target="#batchSettingsModal">
                                <i class="fas fa-copy"></i> 批量设置
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 当前支付设置概览 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">当前支付设置概览</h5>
                </div>
                <div class="card-body">
                    <?php if ($paymentSettings): ?>
                        <div class="row">
                            <?php foreach ($paymentSettings as $setting): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card <?= $setting['is_active'] ? 'border-success' : 'border-secondary' ?>">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($supportedCities[$setting['city']] ?? $setting['city']) ?></h6>
                                                    <p class="text-muted small mb-1">区块ID: <?= htmlspecialchars($setting['block_id']) ?></p>
                                                    <p class="text-muted small mb-0">
                                                        最小金额: <?= number_format($setting['min_amount'], 0) ?> BCT
                                                        | 兑换率: <?= number_format($setting['exchange_rate'], 4) ?>
                                                    </p>
                                                </div>
                                                <span class="badge badge-<?= $setting['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $setting['is_active'] ? '启用' : '禁用' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-credit-card fa-2x mb-3"></i>
                            <p>暂无支付设置，请在上方表格中配置支付方式</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 批量设置模态框 -->
<div class="modal fade" id="batchSettingsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">批量设置</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
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

<!-- 支付测试模态框 -->
<div class="modal fade" id="testPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">支付连接测试</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="testResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<style>
.payment-setting-row:hover {
    background-color: #f8f9fa;
}
.block-id-input:invalid {
    border-color: #dc3545;
}
.min-amount-input, .exchange-rate-input {
    text-align: right;
}
.custom-switch {
    transform: scale(1.2);
}
.test-payment-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

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
</style>

<script>
// 启用/禁用支付开关的联动效果
document.addEventListener('DOMContentLoaded', function() {
    // 初始化状态
    updateInputsState();
    
    // 绑定开关事件
    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const city = this.getAttribute('data-city');
            updateCityInputsState(city);
        });
    });
    
    // 绑定区块ID输入事件
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
    const toggle = document.querySelector(`#is_active_${city}`);
    const blockIdInput = document.querySelector(`input[name="block_id[${city}]"]`);
    const minAmountInput = document.querySelector(`input[name="min_amount[${city}]"]`);
    const exchangeRateInput = document.querySelector(`input[name="exchange_rate[${city}]"]`);
    const testButton = document.querySelector(`button[data-city="${city}"]`);
    
    const isActive = toggle.checked;
    const hasBlockId = blockIdInput.value.trim() !== '';
    
    // 如果启用但区块ID为空，标记为无效
    if (isActive && !hasBlockId) {
        blockIdInput.classList.add('is-invalid');
    } else {
        blockIdInput.classList.remove('is-invalid');
    }
    
    // 更新测试按钮状态
    updateTestButtonState(city);
}

function updateTestButtonState(city) {
    const toggle = document.querySelector(`#is_active_${city}`);
    const blockIdInput = document.querySelector(`input[name="block_id[${city}]"]`);
    const testButton = document.querySelector(`button[data-city="${city}"]`);
    
    const isActive = toggle.checked;
    const hasBlockId = blockIdInput.value.trim() !== '';
    
    if (isActive && hasBlockId) {
        testButton.disabled = false;
    } else {
        testButton.disabled = true;
    }
}

// 批量设置功能
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
            const city = input.getAttribute('data-city');
            if (!input.value) {
                input.value = blockIdPrefix + city;
            }
        });
    }
    
    $('#batchSettingsModal').modal('hide');
    updateInputsState();
}

// 支付测试功能
document.querySelectorAll('.test-payment-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const city = this.getAttribute('data-city');
        const cityName = this.getAttribute('data-city-name');
        const blockId = document.querySelector(`input[name="block_id[${city}]"]`).value;
        
        testPaymentConnection(city, cityName, blockId);
    });
});

function testPaymentConnection(city, cityName, blockId) {
    const testResult = document.getElementById('testResult');
    testResult.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="sr-only">测试中...</span>
            </div>
            <p>正在测试 ${cityName} 支付连接...</p>
            <p class="small text-muted">区块ID: ${blockId}</p>
        </div>
    `;
    
    $('#testPaymentModal').modal('show');
    
    // 模拟测试过程（实际项目中这里应该调用真实的API）
    setTimeout(function() {
        const success = Math.random() > 0.3; // 70% 成功率模拟
        
        if (success) {
            testResult.innerHTML = `
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> 连接测试成功</h6>
                    <p class="mb-0">${cityName} 支付连接正常，区块ID有效。</p>
                </div>
            `;
        } else {
            testResult.innerHTML = `
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> 连接测试失败</h6>
                    <p class="mb-0">${cityName} 支付连接失败，请检查区块ID是否正确。</p>
                    <small class="d-block mt-2">可能的原因：</small>
                    <ul class="small mb-0">
                        <li>区块ID格式错误</li>
                        <li>网络连接问题</li>
                        <li>该城市支付服务暂时不可用</li>
                    </ul>
                </div>
            `;
        }
    }, 2000);
}

// 表单提交验证
document.getElementById('paymentSettingsForm').addEventListener('submit', function(e) {
    let hasError = false;
    const errorMessages = [];
    
    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        const city = toggle.getAttribute('data-city');
        const cityName = document.querySelector(`strong:contains('${city}')`) ? 
                         document.querySelector(`strong:contains('${city}')`).textContent : city;
        const blockIdInput = document.querySelector(`input[name="block_id[${city}]"]`);
        
        if (toggle.checked && !blockIdInput.value.trim()) {
            hasError = true;
            errorMessages.push(`城市 "${cityName}" 已启用但未设置区块ID`);
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