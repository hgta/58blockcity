<?php
require_once '../config/database.php';
require_once 'includes/auth.php';

// 检查登录（必须在header之前）
checkLogin();

require_once 'includes/header.php';
require_once '../classes/UserBCTAccount.php';
require_once '../classes/CityBCT.php';
require_once '../classes/BatchOrderParser.php';
require_once '../classes/BCTOrder.php';

$account = new UserBCTAccount($pdo);
$cityBCT = new CityBCT($pdo);

// 获取城市参数
$selectedCity = $_GET['city'] ?? '';

// 模式：single（单条）/ batch（批量）
$mode = ($_GET['mode'] ?? 'single') === 'batch' ? 'batch' : 'single';

// 批量发布结果 / 中介列表
$batchResult = $_SESSION['batch_publish_result'] ?? null;
unset($_SESSION['batch_publish_result']);

$mediators = [];
try {
    $mediators = $pdo->query("SELECT id, name, contact FROM mediators ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {
    $mediators = [];
}

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

// 选中城市的 BCT 行情
$selectedCityBCT = null;
if ($selectedCity) {
    $selectedCityBCT = $cityBCT->getCityBCT($selectedCity);
    if ($selectedCityBCT) {
        $changes = $cityBCT->get24hChanges();
        $selectedCityBCT['change_pct'] = $changes[$selectedCity] ?? 0;
        $selectedCityBCT['volume_24h'] = $cityBCT->getCity24hVolume($selectedCity);
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

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-plus-circle"></i> 发布交易</h1>
        <div class="subtitle">发布您的人气值买卖需求</div>
    </div>
    <div>
        <a href="market.php" class="btn btn-default"><i class="fas fa-chart-line"></i> 行情中心</a>
    </div>
</div>

<!-- 模式切换 -->
<div class="mode-tabs">
    <a href="trade.php?<?= $selectedCity ? 'city='.urlencode($selectedCity).'&' : '' ?>mode=single"
       class="mode-tab <?= $mode === 'single' ? 'active' : '' ?>">
        <i class="glyphicon glyphicon-edit"></i> 单条模式
    </a>
    <a href="trade.php?mode=batch" class="mode-tab <?= $mode === 'batch' ? 'active' : '' ?>">
        <i class="glyphicon glyphicon-list-alt"></i> 批量模式
    </a>
</div>

<?php if ($mode === 'batch'): ?>
<!-- ======================= 批量模式 ======================= -->
<?php if ($batchResult): ?>
<div class="card batch-result-card">
    <div class="card-header">
        <h4>
            <i class="glyphicon glyphicon-ok-circle"></i> 批量发布结果
            <span class="result-summary">
                成功 <strong class="up"><?= (int)$batchResult['success'] ?></strong> 条
                · 失败 <strong class="down"><?= (int)$batchResult['failed'] ?></strong> 条
            </span>
        </h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table batch-table">
                <thead>
                    <tr>
                        <th>城市</th><th class="text-right">数量</th><th class="text-right">单价</th>
                        <th class="text-right">总价</th><th>状态</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($batchResult['items'] as $it): ?>
                    <tr>
                        <td><?= htmlspecialchars($it['city']) ?></td>
                        <td class="text-right num"><?= number_format($it['amount']) ?></td>
                        <td class="text-right num"><?= number_format($it['price'], 2) ?></td>
                        <td class="text-right num"><?= number_format($it['amount'] * $it['price'], 2) ?></td>
                        <td>
                            <?php if ($it['ok']): ?>
                                <span class="badge badge-success">已发布 #<?= (int)$it['order_id'] ?></span>
                            <?php else: ?>
                                <span class="badge badge-danger"><?= htmlspecialchars($it['message']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($batchResult['missing'])): ?>
        <div class="batch-problem">
            <strong class="down">城市不存在（<?= count($batchResult['missing']) ?> 行）</strong>
            <?php foreach ($batchResult['missing'] as $m): ?>
                <span class="problem-chip">第<?= (int)$m['line'] ?>行 <?= htmlspecialchars($m['text']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($batchResult['invalid'])): ?>
        <div class="batch-problem">
            <strong class="down">格式错误（<?= count($batchResult['invalid']) ?> 行）</strong>
            <?php foreach ($batchResult['invalid'] as $m): ?>
                <span class="problem-chip">第<?= (int)$m['line'] ?>行 <?= htmlspecialchars($m['reason']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="batch-layout">
    <div class="card">
        <div class="card-header">
            <h3><i class="glyphicon glyphicon-list-alt"></i> 批量发布交易</h3>
        </div>
        <div class="card-body">
            <!-- 方向 -->
            <div class="field-block">
                <label class="field-label">交易方向</label>
                <div class="segmented segmented-type" id="batchTypeSegmented">
                    <button type="button" class="segment active" data-batch-type="buy">
                        <i class="glyphicon glyphicon-shopping-cart"></i> 全部买入
                    </button>
                    <button type="button" class="segment" data-batch-type="sell">
                        <i class="glyphicon glyphicon-yen"></i> 全部卖出
                    </button>
                </div>
                <div class="field-hint">同一批次方向统一，不支持买入与卖出混合</div>
            </div>

            <!-- 交易方式 -->
            <div class="field-block">
                <label class="field-label">交易方式</label>
                <div class="tabs-method" id="batchMethodTabs">
                    <button type="button" class="method-tab active" data-batch-method="direct">
                        <i class="glyphicon glyphicon-transfer"></i> 直接交易
                        <small>无手续费</small>
                    </button>
                    <button type="button" class="method-tab" data-batch-method="mediator">
                        <i class="glyphicon glyphicon-user"></i> 中介交易
                        <small>手续费2%</small>
                    </button>
                </div>
                <div class="field-hint">批量模式不提供平台交易（平台交易限制 500 BCT 以下）</div>

                <div class="field-block mt-12" id="batchContactGroup">
                    <label class="field-label" for="batch_contact_info">联系方式</label>
                    <input type="text" class="form-control" id="batch_contact_info"
                           placeholder="手机号 / 微信 / QQ（本批次共用）">
                </div>

                <div class="field-block mt-12" id="batchMediatorGroup" style="display:none;">
                    <label class="field-label" for="batch_mediator_id">选择中介（本批次共用）</label>
                    <select class="form-control" id="batch_mediator_id">
                        <option value="">请选择中介</option>
                        <?php foreach ($mediators as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['name']) ?>（<?= htmlspecialchars($m['contact']) ?>）</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($mediators)): ?>
                    <small class="form-text">暂无可选中介</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 有效期（整批共用） -->
            <div class="field-block">
                <label class="field-label" for="batch_duration">有效期（整批共用）</label>
                <select class="form-control" id="batch_duration">
                    <?php foreach (BCTOrder::getDurationOptions() as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $val === BCTOrder::DEFAULT_DURATION ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint">本批次所有挂单共用该有效期，到期后自动取消</div>
            </div>

            <!-- 粘贴区 -->
            <div class="field-block">
                <label class="field-label" for="batch_text">粘贴挂单</label>
                <textarea id="batch_text" class="batch-textarea" rows="9"
                          placeholder="<?= htmlspecialchars("吐鲁番 65000 0.03\n西双版纳 60000 0.03\n锡林郭勒 36000 0.03\n鲸探 27000 0.05") ?>"></textarea>
                <div class="field-hint">
                    每行一条：<code>城市 数量 价格</code>（支持空格/全角空格/Tab；<code>#</code> 或 <code>//</code> 开头为注释）<br>
                    城市支持名称或拼音，自建城市可直接填写；同城市同价格自动累加数量
                </div>
                <div class="batch-actions">
                    <button type="button" class="btn btn-primary" id="btnParse">
                        <i class="glyphicon glyphicon-search"></i> 识别
                    </button>
                    <button type="button" class="btn btn-success" id="btnSubmitBatch" disabled>
                        <i class="glyphicon glyphicon-ok"></i> 确认发布
                    </button>
                    <span class="batch-status" id="batchStatus"></span>
                </div>
            </div>

            <!-- 预览 -->
            <div class="form-section" id="batchPreviewSection" style="display:none;">
                <h4><i class="glyphicon glyphicon-eye-open"></i> 交易预览</h4>
                <div class="table-responsive">
                    <table class="table batch-table">
                        <thead>
                            <tr>
                                <th>城市</th><th class="text-right">数量</th><th class="text-right">单价</th>
                                <th class="text-right">总价</th><th>状态</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="batchPreviewBody"></tbody>
                    </table>
                </div>
                <div id="batchProblems"></div>
            </div>
        </div>
    </div>
</div>

<form id="batchSubmitForm" method="post" action="process_batch_orders.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="type" id="batchTypeInput" value="buy">
    <input type="hidden" name="trade_type" id="batchTradeTypeInput" value="direct">
    <input type="hidden" name="contact_info" id="batchContactInput" value="">
    <input type="hidden" name="mediator_id" id="batchMediatorInput" value="">
    <input type="hidden" name="duration" id="batchDurationInput" value="<?= htmlspecialchars(BCTOrder::DEFAULT_DURATION) ?>">
    <input type="hidden" name="batch_text" id="batchTextInput" value="">
</form>

<?php else: ?>
<!-- ======================= 单条模式 ======================= -->
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
                    <!-- 交易表单 -->
                    <form id="tradeForm" method="post" action="process_order.php">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="city" value="<?= htmlspecialchars($selectedCity) ?>">
                        <input type="hidden" name="type" id="tradeType" value="buy">

                        <!-- 交易方向 -->
                        <div class="field-block">
                            <label class="field-label">交易方向</label>
                            <div class="segmented segmented-type" id="typeSegmented">
                                <button type="button" class="segment active" data-type="buy">
                                    <i class="glyphicon glyphicon-shopping-cart"></i> 购买
                                </button>
                                <button type="button" class="segment" data-type="sell">
                                    <i class="glyphicon glyphicon-yen"></i> 出售
                                </button>
                            </div>
                        </div>

                        <!-- 数量 / 单价 -->
                        <div class="field-inline">
                            <div class="field-col">
                                <label class="field-label" for="amount">交易数量 <span class="unit">BCT</span></label>
                                <input type="number" class="form-control" id="amount" name="amount"
                                       min="<?= BatchOrderParser::MIN_AMOUNT ?>" max="<?= BatchOrderParser::MAX_AMOUNT ?>" required
                                       placeholder="请输入数量">
                            </div>
                            <div class="field-col">
                                <label class="field-label" for="price">单价 <span class="unit">元/BCT</span></label>
                                <input type="number" class="form-control" id="price" name="price"
                                       min="0.01" max="100" step="0.01" required
                                       placeholder="请输入单价"
                                       value="<?= $selectedCityBCT ? number_format($selectedCityBCT['current_price'], 2) : '0.10' ?>">
                            </div>
                        </div>
                        <div class="field-hint">数量范围 <?= number_format(BatchOrderParser::MIN_AMOUNT) ?> - <?= number_format(BatchOrderParser::MAX_AMOUNT) ?> BCT · 最低单价 0.01 元</div>

                        <!-- 交易方式 -->
                        <div class="field-block">
                            <label class="field-label">交易方式</label>
                            <div class="tabs-method">
                                <button type="button" class="method-tab active" data-method="direct">
                                    <i class="glyphicon glyphicon-transfer"></i> 直接交易
                                    <small>无手续费</small>
                                </button>
                                <button type="button" class="method-tab" data-method="platform">
                                    <i class="glyphicon glyphicon-shopping-cart"></i> 平台交易
                                    <small>手续费10%</small>
                                </button>
                                <button type="button" class="method-tab" data-method="mediator">
                                    <i class="glyphicon glyphicon-user"></i> 中介交易
                                    <small>手续费2%</small>
                                </button>
                            </div>
                            <input type="hidden" name="trade_type" id="tradeTypeMethod" value="direct">
                            <div class="method-note" id="methodNote">
                                <i class="glyphicon glyphicon-info-sign"></i> 双方直接联系，无手续费，快捷但需自行注意风险
                            </div>
                        </div>

                        <!-- 联系方式 -->
                        <div class="field-block" id="contactInfoGroup">
                            <label class="field-label" for="contact_info">联系方式</label>
                            <input type="text" class="form-control" id="contact_info" name="contact_info"
                                   placeholder="手机号 / 微信 / QQ（将展示给交易对方）">
                        </div>

                        <!-- 有效期 -->
                        <div class="field-block">
                            <label class="field-label" for="duration">有效期</label>
                            <select class="form-control" id="duration" name="duration">
                                <?php foreach (BCTOrder::getDurationOptions() as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $val === BCTOrder::DEFAULT_DURATION ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-hint">到期后订单将自动取消，不再出现在行情中</div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block btn-publish">
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
            <?php if ($selectedCityBCT): ?>
            <?php $cls = $selectedCityBCT['change_pct'] >= 0 ? 'up' : 'down'; $sign = $selectedCityBCT['change_pct'] >= 0 ? '+' : ''; ?>
            <!-- 行情（紧凑） -->
            <div class="card quote-card">
                <div class="card-body">
                    <div class="quote-head">
                        <span class="quote-city">
                            <i class="fas fa-chart-line"></i> <?= htmlspecialchars($selectedCity) ?>
                        </span>
                        <a href="city.php?city=<?= urlencode($selectedCity) ?>" class="quote-link">详情 <i class="fas fa-angle-right"></i></a>
                    </div>
                    <div class="quote-row">
                        <span class="quote-price">¥<?= number_format($selectedCityBCT['current_price'], 2) ?></span>
                        <span class="quote-change <?= $cls ?>"><?= $sign ?><?= number_format($selectedCityBCT['change_pct'], 2) ?>%</span>
                    </div>
                    <div class="quote-vol">24h 成交 ¥<?= number_format($selectedCityBCT['volume_24h'], 2) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 交易预览 -->
            <div class="card <?= $selectedCityBCT ? 'mt-3' : '' ?>">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-eye-open"></i> 交易预览</h4>
                </div>
                <div class="card-body">
                    <div class="trade-preview">
                        <?php if ($selectedCity): ?>
                        <div class="preview-item">
                            <span>城市：</span>
                            <strong><?= htmlspecialchars($selectedCity) ?></strong>
                        </div>
                        <?php endif; ?>
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
            </div>

            <!-- 城市信息（不含基金总额/当前余额） -->
            <?php if ($selectedCity && $cityInfo): ?>
            <div class="card mt-3">
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
                            <span>已产生人气值：</span>
                            <strong><?= number_format($cityInfo['popularity']) ?> BCT</strong>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 我的账户（仅作展示，不影响交易） -->
            <?php if ($userAccount): ?>
            <div class="card mt-3">
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

            <!-- 交易提示 -->
            <div class="card mt-3">
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
<?php endif; // 模式分支结束 ?>

<!-- 引入Select2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<!-- 页面特定样式 -->
<style>
/* 模式切换 */
.mode-tabs {
    display: inline-flex;
    gap: 6px;
    padding: 4px;
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    margin-bottom: 20px;
}
.mode-tab {
    padding: 7px 18px;
    border-radius: 6px;
    color: var(--bct-text-secondary);
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}
.mode-tab:hover {
    color: var(--bct-text);
    background: var(--bct-bg-hover);
    text-decoration: none;
}
.mode-tab.active {
    background: var(--bct-accent);
    color: #0b0e11;
}
.mode-tab.active:hover { color: #0b0e11; }

/* 批量模式 */
.batch-hint {
    color: var(--bct-text-secondary);
    font-size: 13px;
    line-height: 1.9;
    margin: 0 0 12px;
}
.batch-hint code {
    color: var(--bct-text);
    background: var(--bct-bg-hover);
    padding: 1px 6px;
    border-radius: 4px;
}
.batch-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    color: var(--bct-text);
    font-size: 13px;
    font-family: 'Roboto Mono', 'SF Mono', Menlo, Consolas, monospace;
    line-height: 1.8;
    resize: vertical;
}
.batch-textarea:focus {
    outline: none;
    border-color: var(--bct-accent);
    box-shadow: 0 0 0 2px rgba(240, 185, 11, 0.2);
}
.batch-textarea::placeholder { color: var(--bct-text-muted); }

.batch-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
}
.batch-status {
    font-size: 13px;
    color: var(--bct-text-secondary);
}

.batch-table { margin-bottom: 0; color: var(--bct-text); }
.batch-table > thead > tr > th {
    background: var(--bct-bg-tertiary);
    color: var(--bct-text-secondary);
    border-bottom: 1px solid var(--bct-border);
    font-weight: 500;
    font-size: 12px;
    white-space: nowrap;
}
.batch-table > tbody > tr > td {
    border-top: 1px solid var(--bct-border);
    color: var(--bct-text);
    vertical-align: middle;
    font-size: 13px;
    white-space: nowrap;
}
.batch-table .num { font-family: 'Roboto Mono', Monaco, monospace; font-variant-numeric: tabular-nums; }

.row-bad td { background: rgba(246, 70, 93, 0.06); }
.row-bad .batch-issue { color: var(--bct-down); font-size: 12px; }
.row-merged .batch-merged { color: var(--bct-accent); font-size: 11px; margin-left: 6px; }

.btn-remove-row {
    background: transparent;
    border: 1px solid var(--bct-border);
    border-radius: 6px;
    color: var(--bct-text-secondary);
    padding: 2px 8px;
    cursor: pointer;
}
.btn-remove-row:hover { border-color: var(--bct-down); color: var(--bct-down); }

.batch-problem {
    margin-top: 14px;
    padding: 12px;
    background: rgba(246, 70, 93, 0.08);
    border: 1px solid rgba(246, 70, 93, 0.25);
    border-radius: var(--bct-radius);
    font-size: 13px;
}
.problem-chip {
    display: inline-block;
    margin: 4px 6px 0 0;
    padding: 2px 8px;
    background: var(--bct-bg-tertiary);
    border-radius: 4px;
    color: var(--bct-text-secondary);
    font-size: 12px;
}
.batch-result-card .result-summary {
    float: right;
    font-size: 13px;
    font-weight: 400;
    color: var(--bct-text-secondary);
}
.batch-field label { color: var(--bct-text-secondary); }

/* 输入框下方的辅助说明（含中介为空提示） */
.field-block .form-text,
.field-block .field-hint {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.6;
    color: var(--bct-text-muted);
}

/* ===== 紧凑表单基元 ===== */
.field-block { margin-bottom: 16px; }
.field-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--bct-text-secondary);
    margin-bottom: 7px;
}
.field-label .unit {
    font-weight: 400;
    font-size: 11px;
    color: var(--bct-text-muted);
}
.field-hint {
    font-size: 12px;
    color: var(--bct-text-muted);
    margin-top: 6px;
    line-height: 1.6;
}
.field-hint code {
    color: var(--bct-text-secondary);
    background: var(--bct-bg-hover);
    padding: 0 4px;
    border-radius: 3px;
}
.field-inline {
    display: flex;
    gap: 14px;
    margin-bottom: 6px;
}
.field-col { flex: 1 1 0; min-width: 0; }
.mt-12 { margin-top: 12px; }

/* 输入框统一样式（补足主题未定义的高度与内边距） */
.field-block .form-control,
.field-inline .form-control {
    width: 100%;
    height: 42px;
    padding: 9px 13px;
    font-size: 14px;
    line-height: 1.4;
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    color: var(--bct-text);
    box-shadow: none;
}
/* select：去掉原生外观并重绘下拉箭头，保证与 input 视觉一致 */
.field-block select.form-control {
    -webkit-appearance: none;
    appearance: none;
    padding-right: 32px;
    background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235e6673' d='M6 8.5 1.5 4h9z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    cursor: pointer;
}
.field-block .form-control:focus,
.field-inline .form-control:focus {
    background-color: var(--bct-bg-tertiary);
    border-color: var(--bct-accent);
    color: var(--bct-text);
    box-shadow: 0 0 0 2px rgba(240, 185, 11, 0.2);
    outline: none;
}
.field-block .form-control::placeholder,
.field-inline .form-control::placeholder {
    color: var(--bct-text-muted);
    font-size: 13px;
    font-weight: 400;
}
/* 数量/单价：等宽、数字对齐 */
.field-inline .form-control {
    font-family: 'Roboto Mono', 'SF Mono', Monaco, monospace;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    letter-spacing: 0.3px;
}
#amount, #price { font-size: 15px; height: 44px; }
.field-inline .field-label { margin-bottom: 6px; }

/* 分段控件（交易方向） */
.segmented {
    display: inline-flex;
    padding: 3px;
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
}
.segmented-type { display: flex; width: 100%; }
.segment {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--bct-text-secondary);
    font-size: 14px;
    font-weight: 500;
    padding: 8px 18px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.segment i { margin-right: 4px; }
.segment:hover { color: var(--bct-text); }
.segment.active {
    background: var(--bct-accent);
    color: #0b0e11;
    font-weight: 600;
}
.segment.active i { color: #0b0e11; }

/* 标签式交易方式 */
.tabs-method {
    display: flex;
    gap: 8px;
}
.method-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 9px 6px;
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    color: var(--bct-text-secondary);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1.3;
}
.method-tab i { font-size: 15px; margin-bottom: 1px; }
.method-tab small {
    font-size: 11px;
    color: var(--bct-text-muted);
    font-weight: 400;
}
.method-tab:hover { border-color: var(--bct-accent); color: var(--bct-text); }
.method-tab.active {
    border-color: var(--bct-accent);
    background: rgba(240, 185, 11, 0.12);
    color: var(--bct-accent);
}
.method-tab.active small { color: var(--bct-accent); opacity: 0.8; }
.method-note {
    font-size: 12px;
    color: var(--bct-text-muted);
    margin-top: 8px;
    padding: 8px 10px;
    background: var(--bct-bg-tertiary);
    border-radius: 6px;
    border: 1px solid var(--bct-border);
}
.method-note i { color: var(--bct-accent); margin-right: 4px; }

.btn-publish { margin-top: 4px; }

/* 表单区域 */
.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--bct-border);
}

.form-section h4 {
    color: var(--bct-text);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section h5 {
    color: var(--bct-text-secondary);
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
}

/* 热门城市选择器 */
.hot-cities-section {
    margin-bottom: 20px;
}

.hot-cities-section h5 {
    margin-bottom: 8px;
}

.city-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.city-option {
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: 999px;
    padding: 5px 12px;
    line-height: 1.2;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
}

.city-option:hover {
    border-color: var(--bct-accent);
    color: var(--bct-accent);
}

.batch-layout { max-width: 900px; }

.city-option.active {
    border-color: var(--bct-accent);
    background: rgba(240, 185, 11, 0.12);
}

.city-name {
    font-weight: 600;
    color: var(--bct-text);
    margin-bottom: 0;
}

.city-option.active .city-name,
.city-option:hover .city-name {
    color: var(--bct-accent);
}

.city-rank {
    color: var(--bct-text-muted);
    font-size: 11px;
    font-weight: 500;
}

/* 所有城市下拉选择 */
.all-cities-section {
    margin-top: 20px;
}

/* Select2 自定义样式 */
.select2-container--default .select2-selection--single {
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    height: 46px;
    padding: 8px 12px;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 44px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 30px;
    color: var(--bct-text);
}

.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: var(--bct-text-muted);
}

.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: var(--bct-accent);
}

.select2-dropdown {
    background: var(--bct-bg-secondary);
    border: 1px solid var(--bct-border);
    color: var(--bct-text);
}
.select2-container--default .select2-results__option {
    color: var(--bct-text);
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: var(--bct-bg-hover);
    color: var(--bct-accent);
}
.select2-search--dropdown .select2-search__field {
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    color: var(--bct-text);
}

/* 交易方式切换的说明文案 */
.method-note { margin-bottom: 0; }

/* 行情（紧凑） */
.quote-card .card-body { padding: 12px 14px; }
.quote-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
}
.quote-city {
    font-size: 13px;
    font-weight: 600;
    color: var(--bct-text);
}
.quote-city i { color: var(--bct-accent); margin-right: 4px; }
.quote-link {
    font-size: 12px;
    color: var(--bct-text-secondary);
}
.quote-link:hover { color: var(--bct-accent); }
.quote-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
}
.quote-price {
    font-size: 22px;
    font-weight: 700;
    font-family: 'Roboto Mono', 'SF Mono', Monaco, monospace;
    font-variant-numeric: tabular-nums;
    color: var(--bct-text);
    line-height: 1.2;
}
.quote-change {
    font-size: 13px;
    font-weight: 600;
    font-family: 'Roboto Mono', Monaco, monospace;
}
.quote-vol {
    font-size: 12px;
    color: var(--bct-text-secondary);
    margin-top: 4px;
}

/* 交易预览 */
.trade-preview {
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    padding: 20px;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 10px 0;
    border-bottom: 1px solid var(--bct-border);
    color: var(--bct-text-secondary);
    font-size: 14px;
}

.preview-item:last-child {
    border-bottom: none;
}

.preview-item strong {
    color: var(--bct-text);
    font-weight: 600;
    font-family: 'Roboto Mono', 'SF Mono', Monaco, 'Courier New', monospace;
    font-variant-numeric: tabular-nums;
}

.preview-item.total {
    border-top: 1px solid var(--bct-border);
    border-bottom: none;
    margin-top: 10px;
    padding-top: 15px;
    font-size: 16px;
}

.preview-item.total strong {
    color: var(--bct-accent);
    font-size: 20px;
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
    border-bottom: 1px solid var(--bct-border);
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
    color: var(--bct-text-secondary);
}

/* 响应式调整 */
@media (max-width: 768px) {
    .tabs-method { flex-wrap: wrap; }
    .method-tab { flex: 1 1 30%; font-size: 12px; padding: 8px 4px; }
    .segment { padding: 8px 10px; font-size: 13px; }
}
@media (max-width: 480px) {
    .city-option { font-size: 12px; padding: 4px 10px; }
    .method-tab small { display: none; }
    button[type="submit"] { width: 100%; padding: 14px; font-size: 16px; }
    input, select, textarea { font-size: 16px; }
}
</style>

<!-- 引入Select2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/zh-CN.js"></script>

<!-- 页面特定脚本 -->
<script>
$(document).ready(function() {
    // ===== 批量模式 =====
    initBatchMode();

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
    
    // 交易方向切换（仅单条模式）
    $('#typeSegmented .segment').click(function() {
        const type = $(this).data('type');
        
        $('#typeSegmented .segment').removeClass('active');
        $(this).addClass('active');
        
        $('#tradeType').val(type);
        $('#previewType').text(type === 'buy' ? '购买' : '出售');
        
        updatePreview();
    });
    
    // 交易方式切换（标签式）
    var methodNotes = {
        direct: '双方直接联系，无手续费，快捷但需自行注意风险',
        platform: '平台担保交易，限 500 BCT 以下，手续费 10%，安全便捷',
        mediator: '平台客服中介，手续费 2%，安全有保障'
    };
    $('.method-tab[data-method]').click(function() {
        const method = $(this).data('method');
        
        $('.method-tab[data-method]').removeClass('active');
        $(this).addClass('active');
        $('#tradeTypeMethod').val(method);
        $('#methodNote').html('<i class="glyphicon glyphicon-info-sign"></i> ' + methodNotes[method]);
        
        // 直接交易需填写联系方式；平台/中介由平台联系
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
        const method = $('#tradeTypeMethod').val();
        
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

/* ================= 批量模式 ================= */
var batchRows = [];

function initBatchMode() {
    if (!$('#batch_text').length) return;

    // 方向切换
    $('#batchTypeSegmented .segment').click(function() {
        $('#batchTypeSegmented .segment').removeClass('active');
        $(this).addClass('active');
        $('#batchTypeInput').val($(this).data('batch-type'));
        resetBatchPreview();
    });

    // 交易方式切换（标签式）
    $('#batchMethodTabs .method-tab').click(function() {
        var m = $(this).data('batch-method');
        $('#batchMethodTabs .method-tab').removeClass('active');
        $(this).addClass('active');
        $('#batchTradeTypeInput').val(m);
        if (m === 'mediator') {
            $('#batchContactGroup').hide();
            $('#batchMediatorGroup').show();
        } else {
            $('#batchMediatorGroup').hide();
            $('#batchContactGroup').show();
        }
        resetBatchPreview();
    });

    // 识别
    $('#btnParse').click(function() {
        doParse();
    });

    // 文本变化后需重新识别
    $('#batch_text').on('input', function() {
        resetBatchPreview();
    });

    // 确认发布
    $('#btnSubmitBatch').click(function() {
        if (!$('#btnSubmitBatch').prop('disabled')) {
            submitBatch();
        }
    });
}

function resetBatchPreview() {
    batchRows = [];
    $('#batchPreviewSection').hide();
    $('#batchPreviewBody').empty();
    $('#batchProblems').empty();
    $('#btnSubmitBatch').prop('disabled', true);
    $('#batchStatus').text('');
}

function batchParams() {
    return {
        type: $('#batchTypeInput').val(),
        trade_type: $('#batchTradeTypeInput').val(),
        text: $('#batch_text').val()
    };
}

function doParse() {
    var text = $('#batch_text').val();
    if (!text || !text.trim()) {
        $('#batchStatus').text('请先粘贴挂单内容');
        return;
    }

    $('#batchStatus').text('识别中…');
    $('#btnParse').prop('disabled', true);

    $.ajax({
        url: 'api/parse_batch_orders.php',
        type: 'POST',
        dataType: 'json',
        data: batchParams(),
        success: function(res) {
            $('#btnParse').prop('disabled', false);
            if (!res.success) {
                $('#batchStatus').text(res.message || '识别失败');
                return;
            }
            renderBatchPreview(res);
        },
        error: function() {
            $('#btnParse').prop('disabled', false);
            $('#batchStatus').text('识别请求失败，请重试');
        }
    });
}

function renderBatchPreview(res) {
    batchRows = res.orders || [];
    var $body = $('#batchPreviewBody').empty();
    var okCount = 0;

    if (!batchRows.length) {
        $('#batchStatus').text('未识别到有效挂单');
        $('#batchPreviewSection').hide();
        $('#btnSubmitBatch').prop('disabled', true);
        renderBatchProblems(res);
        return;
    }

    $.each(batchRows, function(i, o) {
        if (o.ok) okCount++;
        var $tr = $('<tr></tr>').attr('data-idx', i);
        if (!o.ok) $tr.addClass('row-bad');
        if (o.merged) $tr.addClass('row-merged');

        $tr.append($('<td></td>').text(o.city).append(
            o.merged ? $('<span class="batch-merged">（累加）</span>') : ''
        ));
        $tr.append($('<td class="text-right num"></td>').text(fmt(o.amount)));
        $tr.append($('<td class="text-right num"></td>').text(Number(o.price).toFixed(2)));
        $tr.append($('<td class="text-right num"></td>').text(Number(o.total).toFixed(2)));

        if (o.ok) {
            $tr.append('<td><span class="badge badge-success">可发布</span></td>');
        } else {
            $tr.append($('<td></td>').append(
                $('<span class="batch-issue"></span>').text((o.issues || []).join('；'))
            ));
        }

        var $del = $('<button type="button" class="btn-remove-row" title="移除此行">×</button>');
        $del.click(function() {
            $tr.remove();
            batchRows[i]._removed = true;
            updateSubmitState();
        });
        $tr.append($('<td></td>').append($del));

        $body.append($tr);
    });

    $('#batchPreviewSection').show();
    $('#batchStatus').text('识别完成：可发布 ' + okCount + ' / ' + batchRows.length + ' 条');
    renderBatchProblems(res);
    updateSubmitState();
}

function renderBatchProblems(res) {
    var $p = $('#batchProblems').empty();
    if (!res) return;

    if (res.missing && res.missing.length) {
        var html = '<div class="batch-problem"><strong class="down">城市不存在（' + res.missing.length + ' 行）</strong>';
        $.each(res.missing, function(i, m) {
            html += '<span class="problem-chip">第' + m.line + '行 ' + esc(m.text) + '</span>';
        });
        $p.append(html + '</div>');
    }

    if (res.invalid && res.invalid.length) {
        var html2 = '<div class="batch-problem"><strong class="down">格式错误（' + res.invalid.length + ' 行）</strong>';
        $.each(res.invalid, function(i, m) {
            html2 += '<span class="problem-chip">第' + m.line + '行 ' + esc(m.reason) + '</span>';
        });
        $p.append(html2 + '</div>');
    }
}

function updateSubmitState() {
    var remain = $.grep(batchRows, function(o) { return !o._removed && o.ok; }).length;
    $('#btnSubmitBatch').prop('disabled', remain === 0);
    if (remain === 0) {
        $('#batchStatus').text('没有可发布的挂单');
    } else {
        $('#batchStatus').text('待发布 ' + remain + ' 条');
    }
}

function submitBatch() {
    var tradeType = $('#batchTradeTypeInput').val();
    var contact = $('#batch_contact_info').val() || '';
    var mediator = $('#batch_mediator_id').val() || '';

    if (tradeType === 'direct' && !contact.trim()) {
        alert('请填写联系方式');
        return;
    }
    if (tradeType === 'mediator' && !mediator) {
        alert('请选择中介');
        return;
    }

    // 只提交未被删除且校验通过的挂单行
    var lines = [];
    $.each(batchRows, function(i, o) {
        if (o._removed || !o.ok) return;
        lines.push(o.city + ' ' + o.amount + ' ' + Number(o.price).toFixed(2));
    });
    if (!lines.length) {
        alert('没有可发布的挂单');
        return;
    }

    $('#batchContactInput').val(contact);
    $('#batchMediatorInput').val(mediator);
    $('#batchDurationInput').val($('#batch_duration').val() || '<?= BCTOrder::DEFAULT_DURATION ?>');
    $('#batchTextInput').val(lines.join("\n"));

    $('#btnSubmitBatch').prop('disabled', true).text('发布中…');
    $('#batchSubmitForm').submit();
}

function fmt(n) { return Number(n).toLocaleString(); }
function esc(s) {
    return $('<div></div>').text(s == null ? '' : s).html();
}
</script>

<?php require_once 'includes/footer.php'; ?>