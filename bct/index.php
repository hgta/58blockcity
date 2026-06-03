<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

// 显示消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}

// 从数据库获取真实的订单数据
try {
    // 获取售卖订单 (type = 'sell')
    $stmtSell = $pdo->prepare("
        SELECT o.*, u.username 
        FROM bct_orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.type = 'sell' AND o.status IN ('pending', 'processing')
        ORDER BY o.created_at DESC 
        LIMIT 20
    ");
    $stmtSell->execute();
    $sellOrders = $stmtSell->fetchAll();

    // 获取求购订单 (type = 'buy')
    $stmtBuy = $pdo->prepare("
        SELECT o.*, u.username 
        FROM bct_orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.type = 'buy' AND o.status IN ('pending', 'processing')
        ORDER BY o.created_at DESC 
        LIMIT 20
    ");
    $stmtBuy->execute();
    $buyOrders = $stmtBuy->fetchAll();

    // 获取订单统计
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(DISTINCT user_id) as active_traders,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as today_trades
        FROM bct_orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmtStats->execute();
    $stats = $stmtStats->fetch();

} catch (PDOException $e) {
    // 如果查询出错，使用空数组
    $sellOrders = [];
    $buyOrders = [];
    $stats = [
        'total_orders' => 0,
        'active_traders' => 0,
        'today_trades' => 0
    ];
    error_log("数据库查询错误: " . $e->getMessage());
}
?>

<div class="container">
    <!-- 页面标题 -->
    <div class="page-header">
        <h1>
            <i class="glyphicon glyphicon-transfer"></i>
            人气值(BCT)交易市场
        </h1>
        <p class="text-muted">官方BCT交易增强版 · 百万级交易 · 低至0%手续费</p>
    </div>

    <!-- 核心优势 -->
    <div class="advantage-banner" style="background:linear-gradient(135deg,#1e3a5f,#2563eb,#7c3aed);color:#fff;border-radius:10px;padding:20px 25px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <div style="flex:1;min-width:200px;">
            <h3 style="margin:0 0 4px 0;font-size:18px;"><i class="glyphicon glyphicon-star"></i> 为什么选择 bct.58.tl？</h3>
            <p style="font-size:13px;opacity:.85;margin:0;">官方BCT交易市场的增强替代方案</p>
        </div>
        <div style="display:flex;gap:25px;flex-wrap:wrap;">
            <div style="text-align:center;">
                <div style="font-size:24px;font-weight:800;">100,000</div>
                <div style="font-size:11px;opacity:.8;">单笔最大BCT <span style="text-decoration:line-through;opacity:.6;">(官方500)</span></div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:24px;font-weight:800;">0-10%</div>
                <div style="font-size:11px;opacity:.8;">灵活手续费 <span style="text-decoration:line-through;opacity:.6;">(官方固定10%)</span></div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:24px;font-weight:800;">3种</div>
                <div style="font-size:11px;opacity:.8;">交易方式 <span style="text-decoration:line-through;opacity:.6;">(官方仅1种)</span></div>
            </div>
        </div>
    </div>

    <!-- 快速统计 -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="glyphicon glyphicon-stats"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format(count($sellOrders) + count($buyOrders)) ?></div>
                    <div class="stat-label">活跃订单</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="glyphicon glyphicon-user"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['active_traders'] ?? 0) ?></div>
                    <div class="stat-label">活跃交易者</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="glyphicon glyphicon-ok"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['today_trades'] ?? 0) ?></div>
                    <div class="stat-label">今日成交(BCT)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="glyphicon glyphicon-lock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">交易安全</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 交易列表卡片 -->
    <div class="card" style="margin-bottom: 20px; border: 1px solid #e9ecef;">
        <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e9ecef;">
            <div class="row">
                <div class="col-md-6">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="glyphicon glyphicon-list"></i> 交易市场
                    </h3>
                </div>
                <div class="col-md-6 text-right">
                    <a href="trade.php" class="btn btn-primary">
                        <i class="glyphicon glyphicon-plus"></i> 发布交易
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 筛选栏 -->
        <div class="filter-bar" style="background-color: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #e9ecef;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="filter-label" style="font-weight: 500; color: #555; margin-bottom: 5px; display: block;">城市:</label>
                        <select class="form-control" id="cityFilter">
                            <option value="">全部城市</option>
                            <?php
                            // 获取所有城市选项
                            $allCities = [];
                            foreach ($sellOrders as $order) {
                                if (!empty($order['city'])) {
                                    $allCities[] = $order['city'];
                                }
                            }
                            foreach ($buyOrders as $order) {
                                if (!empty($order['city'])) {
                                    $allCities[] = $order['city'];
                                }
                            }
                            $cities = array_unique($allCities);
                            sort($cities);
                            foreach ($cities as $city): 
                            ?>
                            <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="filter-label" style="font-weight: 500; color: #555; margin-bottom: 5px; display: block;">价格范围:</label>
                        <select class="form-control" id="priceFilter">
                            <option value="">全部</option>
                            <option value="0.1-0.5">0.1-0.5元</option>
                            <option value="0.5-1">0.5-1元</option>
                            <option value="1-2">1-2元</option>
                            <option value="2-10">2-10元</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="filter-label" style="font-weight: 500; color: #555; margin-bottom: 5px; display: block;">排序:</label>
                        <select class="form-control" id="sortFilter">
                            <option value="newest">最新发布</option>
                            <option value="price_low">价格最低</option>
                            <option value="price_high">价格最高</option>
                            <option value="amount_high">数量最多</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="filter-label" style="font-weight: 500; color: #555; margin-bottom: 5px; display: block;">&nbsp;</label>
                        <button class="btn btn-default btn-block" id="resetFilter">
                            <i class="glyphicon glyphicon-refresh"></i> 重置筛选
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 选项卡导航 -->
        <div class="trade-tabs-nav" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
            <div class="nav-tabs-container" style="display: flex; padding: 0 20px;">
                <button class="nav-tab active" data-tab="sell" style="background: none; border: none; padding: 12px 25px; color: #ff6b00; font-weight: 500; border-bottom: 3px solid #ff6b00; transition: all 0.3s; display: flex; align-items: center; gap: 8px; flex: 1; justify-content: center; background: white;">
                    <i class="glyphicon glyphicon-arrow-up"></i> 
                    人气售卖
                    <span class="badge" style="background: #ff6b00; color: white; font-size: 11px; padding: 2px 6px; border-radius: 8px; margin-left: 5px;"><?= count($sellOrders) ?></span>
                </button>
                <button class="nav-tab" data-tab="buy" style="background: none; border: none; padding: 12px 25px; color: #666; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.3s; display: flex; align-items: center; gap: 8px; flex: 1; justify-content: center;">
                    <i class="glyphicon glyphicon-arrow-down"></i> 
                    人气求购
                    <span class="badge" style="background: #ff6b00; color: white; font-size: 11px; padding: 2px 6px; border-radius: 8px; margin-left: 5px;"><?= count($buyOrders) ?></span>
                </button>
            </div>
        </div>
        
        <!-- 选项卡内容 -->
        <div class="trade-tabs-content" style="background: white;">
            <!-- 售卖列表 -->
            <div class="tab-pane active" id="sell-tab" style="display: block;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="12%">城市</th>
                                <th width="12%">售卖数量</th>
                                <th width="12%">售卖单价</th>
                                <th width="12%">总价</th>
                                <th width="15%">出售者</th>
                                <th width="27%">联系方式</th>
                                <th width="10%">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sellOrders)): ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="empty-state">
                                        <i class="glyphicon glyphicon-inbox"></i>
                                        <p>暂无售卖订单</p>
                                        <a href="trade.php" class="btn btn-primary">发布售卖</a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($sellOrders as $order): ?>
                            <tr class="order-row" data-city="<?= htmlspecialchars($order['city'] ?? '') ?>" data-price="<?= $order['price'] ?? 0 ?>">
                                <td><?= htmlspecialchars($order['city'] ?? '未知') ?></td>
                                <td><span class="amount" style="font-weight: 600; color: #333; white-space: nowrap;"><?= number_format($order['amount'] ?? 0) ?> BCT</span></td>
                                <td><span class="price" style="color: #e53935; font-weight: 600; white-space: nowrap;"><?= number_format($order['price'] ?? 0, 2) ?> 元</span></td>
                                <td><span class="total-price" style="color: #1976d2; font-weight: 600; white-space: nowrap;"><?= number_format($order['total_amount'] ?? 0, 2) ?> 元</span></td>
                                <td>
                                    <span class="seller" style="color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; display: inline-block;">
                                        <?= htmlspecialchars($order['username'] ?? '用户_' . substr($order['user_id'] ?? '', -4)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="contact-info" style="display: flex; flex-direction: column; gap: 3px;">
                                        <?php if (!empty($order['contact_info'])): ?>
                                        <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                            <i class="glyphicon glyphicon-user" style="color: #ff6b00; font-size: 11px;"></i> <?= htmlspecialchars($order['contact_info']) ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                            <i class="glyphicon glyphicon-info-sign" style="color: #ff6b00; font-size: 11px;"></i> 请通过交易联系
                                        </span>
                                        <?php endif; ?>
                                        <?php if (isset($order['trade_type'])): ?>
                                            <?php if ($order['trade_type'] === 'direct'): ?>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                                <i class="glyphicon glyphicon-transfer" style="color: #ff6b00; font-size: 11px;"></i> 直接交易
                                            </span>
                                            <?php elseif ($order['trade_type'] === 'mediator'): ?>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                                <i class="glyphicon glyphicon-user" style="color: #ff6b00; font-size: 11px;"></i> 中介交易
                                            </span>
                                            <?php else: ?>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                                <i class="glyphicon glyphicon-shopping-cart" style="color: #ff6b00; font-size: 11px;"></i> 平台交易
                                            </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm trade-btn" onclick="buyOrder(<?= $order['id'] ?? 0 ?>, '<?= htmlspecialchars($order['city'] ?? '') ?>', <?= $order['amount'] ?? 0 ?>, <?= $order['price'] ?? 0 ?>)" style="min-width: 60px; white-space: nowrap;">
                                        购买
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 求购列表 -->
            <div class="tab-pane" id="buy-tab" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="12%">城市</th>
                                <th width="12%">求购数量</th>
                                <th width="12%">求购单价</th>
                                <th width="12%">总价</th>
                                <th width="15%">求购者</th>
                                <th width="27%">联系方式</th>
                                <th width="10%">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($buyOrders)): ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="empty-state">
                                        <i class="glyphicon glyphicon-shopping-cart"></i>
                                        <p>暂无求购订单</p>
                                        <a href="trade.php" class="btn btn-primary">发布求购</a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($buyOrders as $order): ?>
                            <tr class="order-row" data-city="<?= htmlspecialchars($order['city'] ?? '') ?>" data-price="<?= $order['price'] ?? 0 ?>">
                                <td><?= htmlspecialchars($order['city'] ?? '未知') ?></td>
                                <td><span class="amount" style="font-weight: 600; color: #333; white-space: nowrap;"><?= number_format($order['amount'] ?? 0) ?> BCT</span></td>
                                <td><span class="price" style="color: #e53935; font-weight: 600; white-space: nowrap;"><?= number_format($order['price'] ?? 0, 2) ?> 元</span></td>
                                <td><span class="total-price" style="color: #1976d2; font-weight: 600; white-space: nowrap;"><?= number_format($order['total_amount'] ?? 0, 2) ?> 元</span></td>
                                <td>
                                    <span class="seller" style="color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; display: inline-block;">
                                        <?= htmlspecialchars($order['username'] ?? '用户_' . substr($order['user_id'] ?? '', -4)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="contact-info" style="display: flex; flex-direction: column; gap: 3px;">
                                        <?php if (!empty($order['contact_info'])): ?>
                                        <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                            <i class="glyphicon glyphicon-user" style="color: #ff6b00; font-size: 11px;"></i> <?= htmlspecialchars($order['contact_info']) ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                            <i class="glyphicon glyphicon-info-sign" style="color: #ff6b00; font-size: 11px;"></i> 请通过交易联系
                                        </span>
                                        <?php endif; ?>
                                        <?php if (isset($order['trade_type'])): ?>
                                            <?php if ($order['trade_type'] === 'direct'): ?>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                                <i class="glyphicon glyphicon-transfer" style="color: #ff6b00; font-size: 11px;"></i> 直接交易
                                            </span>
                                            <?php elseif ($order['trade_type'] === 'mediator'): ?>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                                <i class="glyphicon glyphicon-user" style="color: #ff6b00; font-size: 11px;"></i> 中介交易
                                            </span>
                                            <?php else: ?>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #666; line-height: 1.2;">
                                                <i class="glyphicon glyphicon-shopping-cart" style="color: #ff6b00; font-size: 11px;"></i> 平台交易
                                            </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm trade-btn" onclick="sellOrder(<?= $order['id'] ?? 0 ?>, '<?= htmlspecialchars($order['city'] ?? '') ?>', <?= $order['amount'] ?? 0 ?>, <?= $order['price'] ?? 0 ?>)" style="min-width: 60px; white-space: nowrap;">
                                        出售
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 交易说明 -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h3><i class="glyphicon glyphicon-info-sign"></i> 交易说明</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h4><i class="glyphicon glyphicon-shopping-cart text-primary"></i> 平台交易</h4>
                    <p>500BCT以下适用，平台自动撮合交易，手续费10%，安全便捷。</p>
                </div>
                <div class="col-md-4">
                    <h4><i class="glyphicon glyphicon-user text-warning"></i> 中介交易</h4>
                    <p>通过平台客服完成交易，手续费2%，资金安全有保障。</p>
                </div>
                <div class="col-md-4">
                    <h4><i class="glyphicon glyphicon-transfer text-success"></i> 直接交易</h4>
                    <p>买卖双方直接联系，无手续费，交易快捷但需注意风险。</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 交易确认模态框 -->
<div class="modal fade" id="tradeModal" tabindex="-1" role="dialog" aria-labelledby="tradeModalLabel" style="display: none;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="tradeModalLabel">确认交易</h4>
            </div>
            <div class="modal-body">
                <div id="tradeInfo">
                    <!-- 交易信息将在这里动态显示 -->
                </div>
                <form id="tradeForm">
                    <input type="hidden" id="tradeOrderId" name="order_id">
                    <input type="hidden" id="tradeType" name="trade_type">
                    <input type="hidden" id="tradeCity" name="city">
                    
                    <div class="form-group">
                        <label for="tradeAmount">交易数量 (BCT)</label>
                        <input type="number" class="form-control" id="tradeAmount" name="amount" min="1" required>
                        <small class="form-text text-muted" id="maxAmountHint"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="tradePrice">单价 (元/BCT)</label>
                        <input type="number" class="form-control" id="tradePrice" name="price" step="0.01" min="0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>交易方式</label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="execute_type" value="platform" checked> 平台交易
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="execute_type" value="mediator"> 中介交易
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="execute_type" value="direct"> 直接交易
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>交易预览</strong>
                        <div class="trade-preview-small">
                            <div>数量: <span id="previewAmount">0</span> BCT</div>
                            <div>单价: <span id="previewPrice">0</span> 元</div>
                            <div>总价: <span id="previewTotal">0</span> 元</div>
                            <div>手续费: <span id="previewFee">0</span> 元</div>
                            <div class="total">实付/实收: <span id="previewNet">0</span> 元</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmTrade">确认交易</button>
            </div>
        </div>
    </div>
</div>

<style>
/* 只保留必要的样式，避免冲突 */
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    height: 100%;
}

.stat-icon {
    background: linear-gradient(135deg, #ff6b00, #ff8c00);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.stat-icon i {
    font-size: 20px;
}

.stat-number {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    line-height: 1;
}

.stat-label {
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #ddd;
}

.empty-state p {
    margin-bottom: 20px;
    font-size: 16px;
}

.trade-preview-small {
    font-size: 14px;
    line-height: 1.5;
}

.trade-preview-small .total {
    font-weight: bold;
    color: #ff6b00;
    margin-top: 5px;
    padding-top: 5px;
    border-top: 1px solid #ddd;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
        padding: 15px;
    }
    
    .stat-icon {
        margin-right: 0;
        margin-bottom: 10px;
        width: 40px;
        height: 40px;
    }
    
    .stat-icon i {
        font-size: 18px;
    }
    
    .stat-number {
        font-size: 18px;
    }
}
</style>

<script>
$(document).ready(function() {
    // 选项卡切换
    $('.nav-tab').click(function() {
        const tabId = $(this).data('tab');
        
        // 更新选项卡状态
        $('.nav-tab').removeClass('active');
        $('.nav-tab').css({
            'color': '#666',
            'border-bottom-color': 'transparent',
            'background': 'none'
        });
        
        $(this).addClass('active');
        $(this).css({
            'color': '#ff6b00',
            'border-bottom-color': '#ff6b00',
            'background': 'white'
        });
        
        // 更新内容显示
        $('.tab-pane').hide();
        $('#' + tabId + '-tab').show();
    });
    
    // 筛选功能
    $('#cityFilter, #priceFilter, #sortFilter').change(function() {
        filterOrders();
    });
    
    // 重置筛选
    $('#resetFilter').click(function() {
        $('#cityFilter, #priceFilter, #sortFilter').val('');
        filterOrders();
    });
    
    // 交易表单实时计算
    $('#tradeAmount, #tradePrice').on('input', updateTradePreview);
    $('input[name="execute_type"]').change(updateTradePreview);
    
    // 确认交易按钮
    $('#confirmTrade').click(function() {
        executeTrade();
    });
});

// 购买订单
function buyOrder(orderId, city, maxAmount, price) {
    event.stopPropagation();
    showTradeModal('buy', orderId, city, maxAmount, price);
}

// 出售订单
function sellOrder(orderId, city, maxAmount, price) {
    event.stopPropagation();
    showTradeModal('sell', orderId, city, maxAmount, price);
}

// 显示交易模态框
function showTradeModal(type, orderId, city, maxAmount, price) {
    const title = type === 'buy' ? '购买人气值' : '出售人气值';
    const action = type === 'buy' ? '购买' : '出售';
    
    $('#tradeModalLabel').text(title);
    $('#tradeOrderId').val(orderId);
    $('#tradeType').val(type);
    $('#tradeCity').val(city);
    $('#tradeAmount').val(maxAmount).attr('max', maxAmount);
    $('#tradePrice').val(price);
    $('#maxAmountHint').text(`最大数量: ${maxAmount.toLocaleString()} BCT`);
    
    // 更新交易信息显示
    $('#tradeInfo').html(`
        <p><strong>订单信息</strong></p>
        <p>城市: ${city}</p>
        <p>${type === 'buy' ? '出售者' : '求购者'}报价: ${price} 元/BCT</p>
        <p>可${action}数量: ${maxAmount.toLocaleString()} BCT</p>
    `);
    
    updateTradePreview();
    $('#tradeModal').modal('show');
}

// 更新交易预览
function updateTradePreview() {
    const amount = parseInt($('#tradeAmount').val()) || 0;
    const price = parseFloat($('#tradePrice').val()) || 0;
    const type = $('#tradeType').val();
    const method = $('input[name="execute_type"]:checked').val();
    
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

// 执行交易
function executeTrade() {
    const orderId = $('#tradeOrderId').val();
    const type = $('#tradeType').val();
    const amount = $('#tradeAmount').val();
    const price = $('#tradePrice').val();
    const executeType = $('input[name="execute_type"]:checked').val();
    
    if (!amount || amount <= 0) {
        alert('请输入有效的交易数量');
        return;
    }
    
    if (!price || price < 0.1) {
        alert('单价不能低于0.1元');
        return;
    }
    
    // 显示加载状态
    $('#confirmTrade').prop('disabled', true).html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> 处理中...');
    
    // 获取 CSRF token
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    
    // 真实 API 调用
    $.ajax({
        url: 'api/execute_trade.php',
        type: 'POST',
        dataType: 'json',
        data: {
            order_id: orderId,
            amount: amount,
            price: price,
            execute_type: executeType,
            csrf_token: csrfToken
        },
        success: function(res) {
            if (res.success) {
                alert('交易成功！页面将刷新。');
                location.reload();
            } else {
                alert('交易失败：' + res.message);
                $('#confirmTrade').prop('disabled', false).text('确认交易');
            }
        },
        error: function() {
            alert('网络错误，请重试');
            $('#confirmTrade').prop('disabled', false).text('确认交易');
        }
    });
}

function getTradeTypeText(type) {
    switch(type) {
        case 'platform': return '平台交易';
        case 'mediator': return '中介交易';
        case 'direct': return '直接交易';
        default: return type;
    }
}

function filterOrders() {
    const city = $('#cityFilter').val();
    const priceRange = $('#priceFilter').val();
    const sort = $('#sortFilter').val();
    
    $('.order-row').each(function() {
        const rowCity = $(this).data('city');
        const rowPrice = $(this).data('price');
        
        let show = true;
        
        // 城市筛选
        if (city && rowCity !== city) {
            show = false;
        }
        
        // 价格筛选
        if (priceRange) {
            const [min, max] = priceRange.split('-').map(Number);
            if (rowPrice < min || rowPrice > max) {
                show = false;
            }
        }
        
        $(this).toggle(show);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>