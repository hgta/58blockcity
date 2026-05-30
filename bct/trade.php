<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once '../classes/UserBCTAccount.php';

// 检查登录
checkLogin();

$account = new UserBCTAccount($pdo);

// 获取城市参数
$selectedCity = $_GET['city'] ?? '';

// 获取城市数据
$cities = $pdo->query("SELECT * FROM cities WHERE status = 'active' ORDER BY rank ASC, name ASC")->fetchAll();
$hotCities = array_filter($cities, function($city) {
    return $city['is_hot'] == 1;
});

// 获取选中的城市信息
$cityInfo = null;
if ($selectedCity) {
    foreach ($cities as $city) {
        if ($city['name'] === $selectedCity) {
            $cityInfo = $city;
            break;
        }
    }
}

$userAccount = $selectedCity && $cityInfo ? $account->getAccount($_SESSION['user_id'], $selectedCity) : null;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $city = $_POST['city'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $price = floatval($_POST['price'] ?? 0); // 用户填写的单价
    $tradeType = $_POST['trade_type'] ?? '';
    $contactInfo = $_POST['contact_info'] ?? '';
    $mediatorId = $_POST['mediator_id'] ?? null;
    
    // 简单验证
    if (!$city || $amount <= 0 || $price <= 0 || !in_array($tradeType, ['platform', 'mediator', 'direct'])) {
        $_SESSION['error'] = '请填写完整的交易信息';
        header('Location: trade.php?city=' . urlencode($city));
        exit;
    }
    
    // 验证价格不低于基础价格 (修改为0.01元)
    if ($price < 0.01) {
        $_SESSION['error'] = '单价不能低于0.01元';
        header('Location: trade.php?city=' . urlencode($city));
        exit;
    }
    
    // 移除出售时的余额检查，简化流程
    // if ($type === 'sell') {
    //     $userAccount = $account->getAccount($_SESSION['user_id'], $city);
    //     if (!$userAccount || $userAccount['balance'] - $userAccount['frozen'] < $amount) {
    //         $_SESSION['error'] = '可用余额不足';
    //         header('Location: trade.php?city=' . urlencode($city));
    //         exit;
    //     }
    // }
    
    // 创建订单 - 使用直接数据库操作
    try {
        $pdo->beginTransaction();
        
        // 生成订单号
        $orderNo = date('YmdHis') . mt_rand(1000, 9999);
        $totalAmount = $amount * $price;
        
        // 插入订单
        $stmt = $pdo->prepare("INSERT INTO bct_orders 
                              (order_no, user_id, city, type, amount, price, total_amount, trade_type, contact_info) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $orderNo,
            $_SESSION['user_id'],
            $city,
            $type,
            $amount,
            $price,
            $totalAmount,
            $tradeType,
            $contactInfo
        ]);
        
        // 如果是出售订单，不再冻结余额（简化流程）
        // if ($type === 'sell') {
        //     $stmt = $pdo->prepare("UPDATE user_bct_account SET frozen = frozen + ? 
        //                           WHERE user_id = ? AND city = ?");
        //     $stmt->execute([$amount, $_SESSION['user_id'], $city]);
        // }
        
        $pdo->commit();
        $_SESSION['message'] = '交易发布成功！';
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = '交易发布失败，请重试';
        header('Location: trade.php?city=' . urlencode($city));
        exit;
    }
}

// 显示消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}
?>

<div class="container">
    <!-- 页面标题 -->
    <div class="page-header">
        <h1>
            <i class="glyphicon glyphicon-plus"></i>
            发布交易
        </h1>
        <p class="text-muted">发布您的人气值买卖需求</p>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- 交易表单卡片 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-edit"></i> 交易信息</h3>
                </div>
                <div class="card-body">
                    <!-- 城市选择 -->
                    <div class="form-section">
                        <h4><i class="glyphicon glyphicon-map-marker"></i> 选择城市</h4>
                        
                        <!-- 热门城市 -->
                        <?php if (!empty($hotCities)): ?>
                        <div class="hot-cities-section">
                            <h5>热门城市</h5>
                            <div class="city-selector">
                                <?php foreach ($hotCities as $city): ?>
                                <button type="button" class="city-option <?= $selectedCity === $city['name'] ? 'active' : '' ?>" 
                                        data-city="<?= htmlspecialchars($city['name']) ?>">
                                    <div class="city-name"><?= htmlspecialchars($city['name']) ?></div>
                                    <div class="city-rank">#<?= $city['rank'] ?></div>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 所有城市下拉选择 -->
                        <div class="all-cities-section">
                            <h5>所有城市</h5>
                            <div class="form-group">
                                <select class="form-control city-select2" id="citySelect" data-placeholder="选择或输入城市名称">
                                    <option value=""></option>
                                    <?php foreach ($cities as $city): ?>
                                    <option value="<?= htmlspecialchars($city['name']) ?>" 
                                            <?= $selectedCity === $city['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($city['name']) ?> 
                                        (<?= $city['pinyin'] ?><?= $city['is_hot'] ? ' - 热门' : '' ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php if ($selectedCity && $cityInfo): ?>
                    <!-- 交易类型选择 -->
                    <div class="form-section">
                        <h4><i class="glyphicon glyphicon-transfer"></i> 交易类型</h4>
                        <div class="trade-type-selector">
                            <button type="button" class="trade-type-option active" data-type="buy">
                                <i class="glyphicon glyphicon-shopping-cart"></i>
                                <span>我要购买</span>
                                <small>买入人气值</small>
                            </button>
                            <button type="button" class="trade-type-option" data-type="sell">
                                <i class="glyphicon glyphicon-yen"></i>
                                <span>我要出售</span>
                                <small>卖出人气值</small>
                            </button>
                        </div>
                    </div>

                    <!-- 交易表单 -->
                    <form id="tradeForm" method="post" action="trade.php">
                        <input type="hidden" name="city" value="<?= htmlspecialchars($selectedCity) ?>">
                        <input type="hidden" name="type" id="tradeType" value="buy">
                        
                        <!-- 交易信息 -->
                        <div class="form-section">
                            <h4><i class="glyphicon glyphicon-info-sign"></i> 交易详情</h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">交易数量 (BCT)</label>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               min="1" max="100000" required 
                                               placeholder="请输入交易数量">
                                        <small class="form-text text-muted">单次交易数量范围：1 - 100,000 BCT</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="price">单价 (元/BCT)</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               min="0.01" max="100" step="0.01" required 
                                               placeholder="请输入单价"
                                               value="0.10">
                                        <small class="form-text text-muted">最低价格: 0.01 元</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>交易方式</label>
                                <div class="trade-method-options">
								
                                    <div class="method-option">
                                        <input type="radio" name="trade_type" value="direct" id="direct" checked>
                                        <label for="direct">
                                            <div class="method-icon">
                                                <i class="glyphicon glyphicon-transfer"></i>
                                            </div>
                                            <div class="method-info">
                                                <div class="method-title">直接交易</div>
                                                <div class="method-desc">双方直接联系，无手续费</div>
                                                <div class="method-tip">快捷但需注意风险</div>
                                            </div>
                                        </label>
                                    </div>
									
									<div class="form-group" id="contactInfoGroup" style="">
										<label for="contact_info">联系方式</label>
										<input type="text" class="form-control" id="contact_info" name="contact_info" 
											   placeholder="请输入您的手机号、微信或QQ等联系方式">
										<small class="form-text text-muted">此信息将展示给交易对方</small>
									</div>
									
                                    <div class="method-option">
                                        <input type="radio" name="trade_type" value="platform" id="platform" >
                                        <label for="platform">
                                            <div class="method-icon">
                                                <i class="glyphicon glyphicon-shopping-cart"></i>
                                            </div>
                                            <div class="method-info">
                                                <div class="method-title">平台交易</div>
                                                <div class="method-desc">500BCT以下适用，手续费10%</div>
                                                <div class="method-tip">推荐：安全便捷</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="method-option">
                                        <input type="radio" name="trade_type" value="mediator" id="mediator">
                                        <label for="mediator">
                                            <div class="method-icon">
                                                <i class="glyphicon glyphicon-user"></i>
                                            </div>
                                            <div class="method-info">
                                                <div class="method-title">中介交易</div>
                                                <div class="method-desc">平台客服中介，手续费2%</div>
                                                <div class="method-tip">安全有保障</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            
                        </div>
                        
                        <!-- 交易预览 -->
                        <div class="form-section">
                            <h4><i class="glyphicon glyphicon-eye-open"></i> 交易预览</h4>
                            <div class="trade-preview">
                                <div class="preview-item">
                                    <span>城市：</span>
                                    <strong><?= htmlspecialchars($selectedCity) ?></strong>
                                </div>
                                <div class="preview-item">
                                    <span>类型：</span>
                                    <strong id="previewType">购买</strong>
                                </div>
                                <div class="preview-item">
                                    <span>数量：</span>
                                    <strong id="previewAmount">0</strong> BCT
                                </div>
                                <div class="preview-item">
                                    <span>单价：</span>
                                    <strong id="previewPrice">0.01</strong> 元
                                </div>
                                <div class="preview-item">
                                    <span>总价：</span>
                                    <strong id="previewTotal">0.00</strong> 元
                                </div>
                                <div class="preview-item">
                                    <span>手续费：</span>
                                    <strong id="previewFee">0.00</strong> 元
                                </div>
                                <div class="preview-item total">
                                    <span>实付/实收：</span>
                                    <strong id="previewNet">0.00</strong> 元
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="glyphicon glyphicon-ok"></i> 确认发布交易
                        </button>
                    </form>
                    <?php elseif ($selectedCity): ?>
                    <div class="alert alert-warning">
                        <i class="glyphicon glyphicon-warning-sign"></i>
                        未找到该城市的信息
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="glyphicon glyphicon-info-sign"></i>
                        请先选择城市
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- 城市信息卡片 -->
            <?php if ($selectedCity && $cityInfo): ?>
            <div class="card">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-stats"></i> 城市信息</h4>
                </div>
                <div class="card-body">
                    <div class="city-info">
                        <div class="info-item">
                            <span>城市：</span>
                            <strong><?= htmlspecialchars($selectedCity) ?></strong>
                        </div>
                        <div class="info-item">
                            <span>排名：</span>
                            <strong>#<?= $cityInfo['rank'] ?></strong>
                        </div>
                        <div class="info-item">
                            <span>居民数量：</span>
                            <strong><?= number_format($cityInfo['resident_count']) ?> 人</strong>
                        </div>
                        <div class="info-item">
                            <span>已开启区块：</span>
                            <strong><?= number_format($cityInfo['activated_blocks']) ?> 个</strong>
                        </div>
                        <div class="info-item">
                            <span>基金总额：</span>
                            <strong><?= number_format($cityInfo['total_fund'], 2) ?> 元</strong>
                        </div>
                        <div class="info-item">
                            <span>当前余额：</span>
                            <strong><?= number_format($cityInfo['current_balance'], 2) ?> 元</strong>
                        </div>
                        <div class="info-item">
                            <span>已产生人气值：</span>
                            <strong><?= number_format($cityInfo['popularity']) ?> BCT</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 用户账户信息（仅作展示，不影响交易） -->
            <?php if ($userAccount): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-user"></i> 我的账户（仅供参考）</h4>
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <div class="info-item">
                            <span>可用余额：</span>
                            <strong><?= number_format($userAccount['balance']) ?> BCT</strong>
                        </div>
                        <div class="info-item">
                            <span>冻结中：</span>
                            <strong><?= number_format($userAccount['frozen']) ?> BCT</strong>
                        </div>
                        <div class="info-item">
                            <span>总价值：</span>
                            <strong class="text-primary">
                                <?= number_format($userAccount['balance'] * 0.01, 2) ?> 元
                            </strong>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3" style="font-size: 12px; padding: 8px;">
                        <i class="glyphicon glyphicon-info-sign"></i>
                        温馨提示：当前系统已简化流程，发布出售订单无需验证余额
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- 交易说明 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-question-sign"></i> 交易提示</h4>
                </div>
                <div class="card-body">
                    <div class="tips-list">
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>请确保交易信息准确无误</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>交易发布后不可修改</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>单价可自定义，最低0.01元</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>出售订单无需验证余额</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>直接交易需自行承担风险</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入Select2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<!-- 页面特定样式 -->
<style>
/* 表单区域 */
.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.form-section h4 {
    color: #333;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section h5 {
    color: #666;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
}

/* 热门城市选择器 */
.hot-cities-section {
    margin-bottom: 20px;
}

.city-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
}

.city-option {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.city-option:hover {
    border-color: #ff6b00;
    transform: translateY(-2px);
}

.city-option.active {
    border-color: #ff6b00;
    background: #fff8f5;
}

.city-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.city-rank {
    color: #ff6b00;
    font-size: 12px;
    font-weight: 500;
}

/* 所有城市下拉选择 */
.all-cities-section {
    margin-top: 20px;
}

/* Select2 自定义样式 */
.select2-container--default .select2-selection--single {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    height: 46px;
    padding: 8px 12px;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 44px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 30px;
    color: #333;
}

.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #ff6b00;
}

/* 交易类型选择器 */
.trade-type-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.trade-type-option {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.trade-type-option:hover {
    border-color: #ff6b00;
}

.trade-type-option.active {
    border-color: #ff6b00;
    background: #fff8f5;
}

.trade-type-option i {
    font-size: 24px;
    color: #ff6b00;
    margin-bottom: 10px;
}

.trade-type-option span {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.trade-type-option small {
    color: #666;
    font-size: 12px;
}

/* 交易方式选项 */
.trade-method-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.method-option input[type="radio"] {
    display: none;
}

.method-option label {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.method-option input[type="radio"]:checked + label {
    border-color: #ff6b00;
    background: #fff8f5;
}

.method-option label:hover {
    border-color: #ff6b00;
}

.method-icon {
    width: 40px;
    height: 40px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.method-icon i {
    color: #ff6b00;
}

.method-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 2px;
}

.method-desc {
    color: #666;
    font-size: 12px;
    margin-bottom: 2px;
}

.method-tip {
    color: #ff6b00;
    font-size: 11px;
    font-weight: 500;
}

/* 交易预览 */
.trade-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.preview-item.total {
    border-top: 2px solid #dee2e6;
    border-bottom: none;
    margin-top: 10px;
    padding-top: 15px;
    font-size: 16px;
}

.preview-item.total strong {
    color: #ff6b00;
    font-size: 18px;
}

/* 信息卡片 */
.city-info, .account-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f1f1;
}

.info-item:last-child {
    border-bottom: none;
}

/* 提示列表 */
.tips-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #666;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .city-selector {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
    
    .trade-type-selector {
        grid-template-columns: 1fr;
    }
    
    .method-option label {
        flex-direction: column;
        text-align: center;
    }
    
    .method-icon {
        margin-right: 0;
        margin-bottom: 10px;
    }
}
</style>

<!-- 引入Select2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/zh-CN.js"></script>

<!-- 页面特定脚本 -->
<script>
$(document).ready(function() {
    // 初始化Select2
    $('.city-select2').select2({
        language: "zh-CN",
        placeholder: "选择或输入城市名称",
        allowClear: true,
        width: '100%'
    });
    
    // 城市选择 - 热门城市按钮
    $('.city-option').click(function() {
        const city = $(this).data('city');
        window.location.href = 'trade.php?city=' + encodeURIComponent(city);
    });
    
    // 城市选择 - 下拉框
    $('#citySelect').on('change', function() {
        const city = $(this).val();
        if (city) {
            window.location.href = 'trade.php?city=' + encodeURIComponent(city);
        }
    });
    
    // 交易类型切换
    $('.trade-type-option').click(function() {
        const type = $(this).data('type');
        
        $('.trade-type-option').removeClass('active');
        $(this).addClass('active');
        
        $('#tradeType').val(type);
        $('#previewType').text(type === 'buy' ? '购买' : '出售');
        
        updatePreview();
    });
    
    // 交易方式切换
    $('input[name="trade_type"]').change(function() {
        const method = $(this).val();
        
        // 显示/隐藏联系方式输入框
        if (method === 'direct') {
            $('#contactInfoGroup').show();
        } else {
            $('#contactInfoGroup').hide();
        }
        
        updatePreview();
    });
    
    // 数量输入变化
    $('#amount').on('input', function() {
        updatePreview();
    });
    
    // 单价输入变化
    $('#price').on('input', function() {
        updatePreview();
    });
    
    // 更新交易预览
    function updatePreview() {
        const amount = parseInt($('#amount').val()) || 0;
        const price = parseFloat($('#price').val()) || 0.01;
        const type = $('#tradeType').val();
        const method = $('input[name="trade_type"]:checked').val();
        
        // 计算手续费率
        let feeRate = 0;
        if (method === 'platform') feeRate = 0.10;
        else if (method === 'mediator') feeRate = 0.02;
        
        const total = amount * price;
        const fee = total * feeRate;
        const net = type === 'buy' ? total + fee : total - fee;
        
        $('#previewAmount').text(amount.toLocaleString());
        $('#previewPrice').text(price.toFixed(2));
        $('#previewTotal').text(total.toFixed(2));
        $('#previewFee').text(fee.toFixed(2));
        $('#previewNet').text(net.toFixed(2));
    }
    
    // 初始更新预览
    updatePreview();
});
</script>

<?php require_once 'includes/footer.php'; ?>