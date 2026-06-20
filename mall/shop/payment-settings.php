<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Block.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$payment = new Payment($pdo);

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
    if ($myShop) { header('Location: payment-settings.php?id=' . $myShop['id']); exit; }
    header('Location: create.php'); exit;
}

// 获取店铺支付设置
$paymentSettings = $shop->getPaymentSettings($shopId);

// 获取支持的城市列表
$supportedCities = $shop->getSupportedCities();

// 获取当前用户在所有城市已认领的区块（按城市分组）
$block = new Block($pdo);
$userAllBlocks = $block->getUserBlocks($_SESSION['user_id']);
$userBlocksByCity = [];
foreach ($userAllBlocks as $b) {
    $userBlocksByCity[$b['city_id']][] = $b;
}

$error = '';
$success = '';

// 处理支付设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("payment-settings POST: shop_id={$shopId}, raw_post=" . json_encode($_POST));

    $paymentSettingsData = [];
    $hasActivePayment = false;
    
    if (isset($_POST['payment_cities']) && is_array($_POST['payment_cities']) && isset($_POST['block_id']) && is_array($_POST['block_id'])) {
        $blockList = $_POST['block_id'];
        $isActiveList = (isset($_POST['is_active']) && is_array($_POST['is_active'])) ? $_POST['is_active'] : [];

        foreach ($_POST['payment_cities'] as $city) {
            $blockId = trim($blockList[$city] ?? '');
            $isActive = isset($isActiveList[$city]) ? 1 : 0;

            if ($isActive && empty($blockId)) {
                $error = "城市 '{$city}' 已启用但未选择区块";
                break;
            }

            // 保存所有城市（包括未启用的），保留 is_active 状态
            $entry = [
                'city' => $city,
                'block_id' => $blockId ?: '',
                'is_active' => $isActive,
                'min_amount' => 1,
                'exchange_rate' => 0.1000
            ];
            $paymentSettingsData[] = $entry;
            if ($isActive) {
                $hasActivePayment = true;
            }
        }
    }
    
    if (!$error) {
        if (!$hasActivePayment) {
            $error = '请至少启用一个城市的支付方式';
        } else {
            try {
                if ($shop->updatePaymentSettings($shopId, $paymentSettingsData)) {
                    error_log("payment-settings 保存成功: shop_id={$shopId}, count=" . count($paymentSettingsData));
                    // PRG 重定向，避免刷新重复提交
                    $redirectUrl = 'payment-settings.php?id=' . $shopId . '&saved=1';
                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    $error = '支付设置更新失败';
                }
            } catch (Exception $e) {
                error_log("payment-settings 保存异常: shop_id={$shopId}, error=" . $e->getMessage());
                $error = '系统错误：' . $e->getMessage();
            }
        }
    }
}

// 从 URL 参数读取保存成功提示
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $success = '支付设置更新成功';
}

// 将支付设置转换为以城市为键的数组，便于模板使用
$paymentSettingsByCity = [];
foreach ($paymentSettings as $setting) {
    $paymentSettingsByCity[$setting['city']] = $setting;
}

// 城市列表分组：已配置/已启用的始终显示；未配置的只显示前50个
$displayCities = [];
$hiddenCities = [];
$displayCount = 0;
$maxDisplay = 20;

foreach ($supportedCities as $cityCode => $city) {
    $isConfigured = isset($paymentSettingsByCity[$cityCode]);
    if ($isConfigured) {
        $displayCities[$cityCode] = $city;
    } elseif ($displayCount < $maxDisplay) {
        $displayCities[$cityCode] = $city;
        $displayCount++;
    } else {
        $hiddenCities[$cityCode] = $city;
    }
}

require_once '../includes/header.php';
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
                        <p class="mb-0">选择城市并启用开关，即可接收对应城市的人气值支付。</p>
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
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted small">
                            已配置 <?= count($paymentSettings) ?> 个城市，其中 <?= count($activePayments) ?> 个已启用
                        </div>
                    </div>

                    <!-- 城市搜索添加 -->
                    <div class="d-flex align-items-center gap-2 mb-3" id="citySearchBox">
                        <input type="text" class="form-control form-control-sm" id="citySearchInput"
                               placeholder="搜索城市（如：北京、上海）" style="max-width:260px;">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSearchedCity()">
                            <i class="fas fa-plus"></i> 添加
                        </button>
                        <span class="text-muted small">已显示 <?= count($displayCities) ?> 个城市，还可搜索添加其他城市</span>
                    </div>

                    <form method="POST" id="paymentSettingsForm">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="paymentSettingsTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="100">城市</th>
                                        <th>接收区块</th>
                                        <th width="80" class="text-center">启用</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($displayCities as $cityCode => $city): ?>
                                        <?php
                                        $cityName = $city['name'];
                                        $cityId = $city['id'];
                                        $setting = $paymentSettingsByCity[$cityCode] ?? [
                                            'city' => $cityCode,
                                            'block_id' => '',
                                            'is_active' => 0,
                                            'block_zone' => '',
                                            'block_number' => ''
                                        ];
                                        $hasBlocks = !empty($userBlocksByCity[$cityId]);
                                        ?>
                                        <tr class="payment-setting-row" data-city-id="<?= $cityId ?>" data-city="<?= $cityCode ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($cityName) ?></strong>
                                                <input type="hidden" name="payment_cities[]" value="<?= $cityCode ?>">
                                            </td>
                                            <td>
                                                <select class="form-control form-control-sm block-id-select"
                                                        name="block_id[<?= $cityCode ?>]"
                                                        data-city="<?= $cityCode ?>"
                                                        data-city-id="<?= $cityId ?>"
                                                        <?= !$hasBlocks ? 'disabled' : '' ?>>
                                                    <option value="">请选择区块</option>
                                                    <?php if ($hasBlocks): ?>
                                                        <?php foreach ($userBlocksByCity[$cityId] as $ub): ?>
                                                            <option value="<?= $ub['id'] ?>"
                                                                <?= ($setting['block_id'] == $ub['id']) ? 'selected' : '' ?>>
                                                                <?= $ub['zone'] ?>区 #<?= $ub['block_number'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <small class="form-text text-muted block-hint" data-city="<?= $cityCode ?>">
                                                    <?php if (!$hasBlocks): ?>
                                                        <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> 您在该城市暂无已认领区块</span>
                                                        <a href="https://block.58.tl/block/city.php?id=<?= $cityId ?>" target="_blank" class="text-primary" style="text-decoration:underline;">去认领 &rarr;</a>
                                                    <?php else: ?>
                                                        选择该城市的收款区块
                                                    <?php endif; ?>
                                                </small>
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

/* 页面专用样式 */
.payment-setting-row:hover { background-color: #f8f9fa; }
.d-flex { display: flex !important; }
.justify-content-between { justify-content: space-between !important; }
.align-items-center { align-items: center !important; }
.flex-wrap { flex-wrap: wrap !important; }
.gap-2 { gap: 8px !important; }
.mb-3 { margin-bottom: 16px !important; }
.mt-3 { margin-top: 16px !important; }
.mt-4 { margin-top: 24px !important; }
.mb-0 { margin-bottom: 0 !important; }
.text-center { text-align: center !important; }
.text-muted { color: #6c757d !important; }
</style>

<script>
// 隐藏城市数据（供搜索添加）
const hiddenCities = <?= json_encode($hiddenCities, JSON_UNESCAPED_UNICODE) ?>;
const userBlocksByCity = <?= json_encode($userBlocksByCity, JSON_UNESCAPED_UNICODE) ?>;
const allCities = <?= json_encode($supportedCities, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const city = this.getAttribute('data-city');
            validateCityBlock(city);
        });
    });

    document.querySelectorAll('.block-id-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const city = this.getAttribute('data-city');
            validateCityBlock(city);
        });
    });
});

function validateCityBlock(city) {
    const toggle = document.querySelector('#is_active_' + city);
    const blockIdSelect = document.querySelector('select[name="block_id[' + city + ']"]');
    if (!toggle || !blockIdSelect) return;

    const isActive = toggle.checked;
    const hasBlockId = blockIdSelect.value.trim() !== '';

    if (isActive && !hasBlockId) {
        blockIdSelect.classList.add('is-invalid');
    } else {
        blockIdSelect.classList.remove('is-invalid');
    }
}

function createCityRowHTML(cityCode, city) {
    const cityName = city.name;
    const cityId = city.id;
    const blocks = userBlocksByCity[cityId] || [];
    const hasBlocks = blocks.length > 0;

    let options = '<option value="">请选择区块</option>';
    if (hasBlocks) {
        blocks.forEach(function(b) {
            options += '<option value="' + b.id + '">' + b.zone + '区 #' + b.block_number + '</option>';
        });
    }

    let hint = hasBlocks
        ? '选择该城市的收款区块'
        : '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> 您在该城市暂无已认领区块</span> <a href="https://block.58.tl/block/city.php?id=' + cityId + '" target="_blank" class="text-primary" style="text-decoration:underline;">去认领 &rarr;</a>';

    return '<tr class="payment-setting-row" data-city-id="' + cityId + '" data-city="' + cityCode + '">' +
        '<td><strong>' + cityName + '</strong><input type="hidden" name="payment_cities[]" value="' + cityCode + '"></td>' +
        '<td>' +
            '<select class="form-control form-control-sm block-id-select" name="block_id[' + cityCode + ']" data-city="' + cityCode + '" data-city-id="' + cityId + '"' + (hasBlocks ? '' : ' disabled') + '>' + options + '</select>' +
            '<small class="form-text text-muted block-hint" data-city="' + cityCode + '">' + hint + '</small>' +
        '</td>' +
        '<td class="text-center">' +
            '<div class="custom-control custom-switch">' +
                '<input type="checkbox" class="custom-control-input payment-toggle" id="is_active_' + cityCode + '" name="is_active[' + cityCode + ']" value="1" data-city="' + cityCode + '">' +
                '<label class="custom-control-label" for="is_active_' + cityCode + '"></label>' +
            '</div>' +
        '</td>' +
    '</tr>';
}

function addSearchedCity() {
    const input = document.getElementById('citySearchInput');
    const keyword = input.value.trim();
    if (!keyword) return;

    let matchedCode = null;
    let matchedCity = null;
    const all = Object.assign({}, allCities, hiddenCities);

    for (const code in all) {
        const c = all[code];
        if (c.name === keyword || code === keyword.toLowerCase()) {
            matchedCode = code;
            matchedCity = c;
            break;
        }
    }

    if (!matchedCity) {
        alert('未找到城市：' + keyword + '，请检查城市名称');
        return;
    }

    if (document.querySelector('tr[data-city="' + matchedCode + '"]')) {
        alert('城市「' + matchedCity.name + '」已在列表中');
        input.value = '';
        return;
    }

    const tbody = document.querySelector('#paymentSettingsTable tbody');
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = '<table>' + createCityRowHTML(matchedCode, matchedCity) + '</table>';
    const newRow = tempDiv.querySelector('tr');
    tbody.appendChild(newRow);

    const toggle = newRow.querySelector('.payment-toggle');
    toggle.addEventListener('change', function() {
        validateCityBlock(matchedCode);
    });
    const select = newRow.querySelector('.block-id-select');
    select.addEventListener('change', function() {
        validateCityBlock(matchedCode);
    });

    input.value = '';
}

// 搜索框回车支持
document.getElementById('citySearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addSearchedCity();
    }
});

// 表单提交验证
document.getElementById('paymentSettingsForm').addEventListener('submit', function(e) {
    let hasError = false;
    const errorMessages = [];

    document.querySelectorAll('.payment-toggle').forEach(function(toggle) {
        const city = toggle.getAttribute('data-city');
        const blockIdSelect = document.querySelector('select[name="block_id[' + city + ']"]');
        if (!blockIdSelect) return;
        const cityName = blockIdSelect.closest('tr').querySelector('td strong').textContent;

        if (toggle.checked && !blockIdSelect.value.trim()) {
            hasError = true;
            errorMessages.push('城市 "' + cityName + '" 已启用但未选择区块');
            blockIdSelect.classList.add('is-invalid');
        } else {
            blockIdSelect.classList.remove('is-invalid');
        }
    });

    if (hasError) {
        e.preventDefault();
        alert('请修正以下错误：\n\n' + errorMessages.join('\n'));
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
