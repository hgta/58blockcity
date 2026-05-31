<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once '../classes/CityBCT.php';

$cityBCT = new CityBCT($pdo);
$cities = $cityBCT->getAllCitiesBCT();

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
            <i class="glyphicon glyphicon-stats"></i>
            人气值(BCT)行情
        </h1>
        <p class="text-muted">查看各城市人气值实时价格与流通情况</p>
    </div>

    <!-- 城市筛选 -->
    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6">
                    <h3><i class="glyphicon glyphicon-filter"></i> 城市筛选</h3>
                </div>
                <div class="col-md-6 text-right">
                    <div class="input-group">
                        <input type="text" id="citySearch" class="form-control" placeholder="搜索城市...">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" type="button">
                                <i class="glyphicon glyphicon-search"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="letter-nav">
                <div class="letter-nav-container">
                    <?php 
                    $letters = range('A', 'Z');
                    foreach ($letters as $letter): ?>
                    <a href="#<?= $letter ?>" class="letter-link"><?= $letter ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 热门城市行情 -->
    <div class="card mt-4">
        <div class="card-header">
            <h3><i class="glyphicon glyphicon-fire"></i> 热门城市行情</h3>
        </div>
        <div class="card-body">
            <div class="hot-city-grid">
                <?php 
                $hotCities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '苏州', '天津', '南京'];
                foreach ($hotCities as $city): 
                    $cityInfo = array_filter($cities, function($c) use ($city) {
                        return $c['city'] === $city;
                    });
                    $cityInfo = reset($cityInfo);
                    if ($cityInfo):
                ?>
                <a href="#<?= $cityInfo['city'] ?>" class="hot-city-item">
                    <div class="hot-city-name"><?= htmlspecialchars($cityInfo['city']) ?></div>
                    <div class="hot-city-price"><?= number_format($cityInfo['current_price'], 2) ?>元</div>
                    <div class="hot-city-change">
                        <?php if (!empty($cityInfo['change_24h'])): ?>
                            <i class="glyphicon glyphicon-arrow-<?= $cityInfo['change_24h'] >= 0 ? 'up' : 'down' ?> <?= $cityInfo['change_24h'] >= 0 ? 'text-success' : 'text-danger' ?>"></i>
                            <?= $cityInfo['change_24h'] >= 0 ? '+' : '' ?><?= number_format($cityInfo['change_24h'], 1) ?>%
                        <?php else: ?>
                            <span class="text-muted">--</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 全部城市行情 -->
    <div class="card mt-4">
        <div class="card-header">
            <h3><i class="glyphicon glyphicon-globe"></i> 全部城市行情</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>城市</th>
                            <th>当前价格</th>
                            <th>基础价格</th>
                            <th>流通量</th>
                            <th>总供应量</th>
                            <th>24h变化</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cities)): ?>
                        <tr>
                            <td colspan="7" class="text-center">暂无城市数据</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($cities as $city): ?>
                        <tr id="<?= htmlspecialchars($city['city']) ?>">
                            <td>
                                <strong><?= htmlspecialchars($city['city']) ?></strong>
                            </td>
                            <td><?= number_format($city['current_price'], 4) ?>元</td>
                            <td><?= number_format($city['base_price'], 4) ?>元</td>
                            <td><?= number_format($city['circulating_supply']) ?></td>
                            <td><?= number_format($city['total_supply']) ?></td>
                            <td>
                                <?php if (!empty($city['change_24h'])): ?>
                                    <span class="<?= $city['change_24h'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <i class="glyphicon glyphicon-arrow-<?= $city['change_24h'] >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= $city['change_24h'] >= 0 ? '+' : '' ?><?= number_format($city['change_24h'], 1) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="trade.php?city=<?= urlencode($city['city']) ?>" class="btn btn-sm btn-primary">
                                    交易
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 行情说明 -->
    <div class="card mt-4">
        <div class="card-header">
            <h3><i class="glyphicon glyphicon-info-sign"></i> 行情说明</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h4>价格形成机制</h4>
                    <p>各城市人气值(BCT)价格由市场供需关系决定，系统会根据买卖订单比例自动调整价格，最低不低于基础价格0.10元。</p>
                </div>
                <div class="col-md-6">
                    <h4>流通量说明</h4>
                    <p>流通量指当前市场上可交易的人气值数量，总供应量为2100万，每个城市独立计算。</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 页面特定样式 -->
<style>
/* 字母导航 - 与首页一致 */
.letter-nav {
    background-color: white;
    padding: 10px 0;
    position: sticky;
    top: 80px;
    z-index: 90;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.letter-nav-container {
    display: flex;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    padding: 0 15px;
    justify-content: center;
}

.letter-nav-container::-webkit-scrollbar {
    display: none;
}

.letter-link {
    padding: 5px 12px;
    font-size: 16px;
    color: #666;
    border-radius: 15px;
    margin-right: 5px;
}

.letter-link.active, .letter-link:hover {
    background-color: #ff6b00;
    color: white;
}

/* 热门城市网格 */
.hot-city-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
}

.hot-city-item {
    background-color: #fff8f5;
    padding: 15px;
    text-align: center;
    border-radius: 5px;
    color: #ff6b00;
    font-weight: bold;
    transition: all 0.3s;
    border: 1px solid #ffe0d2;
}

.hot-city-item:hover {
    background-color: #ff6b00;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(255,107,0,0.2);
}

.hot-city-name {
    font-size: 16px;
    margin-bottom: 5px;
}

.hot-city-price {
    font-size: 18px;
    margin-bottom: 5px;
}

.hot-city-change {
    font-size: 12px;
    opacity: 0.8;
}

/* 响应式调整 */
@media (max-width: 992px) {
    .hot-city-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .hot-city-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 576px) {
    .hot-city-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .letter-nav {
        top: 140px;
    }
}
</style>

<!-- 页面特定脚本 -->
<script>
$(document).ready(function() {
    // 城市搜索功能
    $('#citySearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // 字母导航点击滚动
    $('.letter-link').click(function(e) {
        e.preventDefault();
        var letter = $(this).attr('href');
        var target = $(letter);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>