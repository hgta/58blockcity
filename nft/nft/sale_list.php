<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';

$nft = new NFT($pdo);
$city = new City($pdo);

// 获取筛选参数
$searchCode = trim($_GET['code'] ?? '');
$selectedCity = $_GET['city'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$currency = $_GET['currency'] ?? 'all';

// 分页设置
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

// 数据
$sales = $nft->getSaleList($selectedCity, $minPrice, $maxPrice, $currency, $sort, $perPage, $offset);
$totalSales = $nft->getTotalSaleCount($selectedCity, $minPrice, $maxPrice, $currency);
$totalPages = ceil($totalSales / $perPage);

// 筛选选项
$cities = $city->getAllCities();

// 统计各城市挂售数（非过滤状态下的全局统计）
$cityCount = 0;
if (empty($selectedCity)) {
    $cityStmt = $pdo->query("SELECT COUNT(DISTINCT t.city_id) FROM nft_transactions t WHERE t.status = 'listed'");
    $cityCount = (int)$cityStmt->fetchColumn();
} else {
    $cityCount = 1;
}

// 搜索逻辑：后端编号搜索
if (!empty($searchCode)) {
    $filtered = [];
    foreach ($sales as $s) {
        if (stripos($s['code'], $searchCode) !== false) {
            $filtered[] = $s;
        }
    }
    $sales = $filtered;
    $totalSales = count($filtered);
    $totalPages = ceil($totalSales / $perPage);
}

require_once '../includes/header.php';
?>

<style>
/* ===== 顶部统计栏 ===== */
.sale-hero {
    background: linear-gradient(135deg, #ff6b00, #e55a00);
    color: #fff;
    padding: 24px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.sale-hero h1 {
    font-size: 22px;
    margin: 0 0 4px;
    font-weight: 700;
}
.sale-hero p {
    font-size: 13px;
    opacity: 0.85;
    margin: 0;
}
.sale-stats {
    display: flex;
    gap: 28px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.sale-stat {
    font-size: 13px;
    opacity: 0.9;
}
.sale-stat strong {
    font-size: 20px;
    font-weight: 800;
}

/* ===== 筛选栏 ===== */
.sale-filters {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    padding: 14px 0;
    margin-bottom: 16px;
}
.sale-filters input,
.sale-filters select {
    padding: 9px 14px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    background: #fff;
    color: #333;
    transition: border-color 0.2s;
}
.sale-filters input:focus,
.sale-filters select:focus {
    border-color: #ff6b00;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.08);
}
.sale-filters input {
    flex: 1;
    min-width: 160px;
}
.sale-filters select {
    min-width: 110px;
}
.sale-filter-btn {
    padding: 9px 20px;
    background: #ff6b00;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
}
.sale-filter-btn:hover {
    background: #e55a00;
}
.sale-reset-btn {
    padding: 9px 16px;
    background: #f5f5f5;
    color: #666;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}

/* ===== 卡片网格 ===== */
.sale-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
    gap: 14px;
    margin-bottom: 30px;
}

/* ===== 卡片 ===== */
.sale-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
    display: block;
    text-align: center;
    padding-bottom: 12px;
}
.sale-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.12);
}

/* 圆形头像区 */
.sale-avatar-wrap {
    display: flex;
    justify-content: center;
    padding: 16px 0 10px;
}
.sale-avatar-circle {
    width: 78px;
    height: 78px;
    border-radius: 50%;
    border: 3px solid #ff6b00;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    overflow: hidden;
}
.sale-avatar-circle img {
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
    border-radius: 50%;
}

/* 编号 */
.sale-code {
    font-size: 15px;
    font-weight: 700;
    color: #ff6b00;
    padding: 0 10px;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 城市标签 */
.sale-city {
    display: inline-block;
    font-size: 11px;
    color: #555;
    background: #f0f0f0;
    padding: 3px 10px;
    border-radius: 10px;
    margin-bottom: 6px;
}

/* 价格 */
.sale-price {
    font-size: 17px;
    font-weight: 800;
    background: linear-gradient(135deg, #ff6b00, #f97316);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 6px;
    line-height: 1.2;
}
.sale-price-cny {
    font-size: 15px;
    font-weight: 700;
    color: #22c55e;
    background: none;
    -webkit-text-fill-color: #22c55e;
}

/* 卖家+求购行 */
.sale-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 11px;
    color: #999;
    padding: 0 10px;
    margin-bottom: 8px;
}
.sale-seller {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 70px;
}
.sale-hot {
    color: #e74c3c;
    font-weight: 600;
    white-space: nowrap;
}

/* 查看按钮 */
.sale-btn {
    display: inline-block;
    padding: 5px 16px;
    background: linear-gradient(135deg, #ff6b00, #f97316);
    color: #fff;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.2s;
}
.sale-card:hover .sale-btn {
    opacity: 0.9;
}

/* ===== 分页 ===== */
.sale-pagination {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin: 30px 0 50px;
    align-items: center;
    flex-wrap: wrap;
}
.sale-pagination a,
.sale-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #555;
    font-size: 14px;
    background: #fff;
    transition: all 0.15s;
}
.sale-pagination a:hover {
    background: #fff3f0;
    border-color: #ff6b00;
    color: #ff6b00;
}
.sale-pagination .active {
    background: #ff6b00;
    color: #fff;
    border-color: #ff6b00;
    font-weight: 700;
}
.sale-pagination .disabled {
    color: #ccc;
    background: #f9f9f9;
    pointer-events: none;
}

/* ===== 空状态 ===== */
.sale-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #999;
}
.sale-empty i {
    font-size: 48px;
    display: block;
    margin-bottom: 16px;
    opacity: 0.35;
}

/* ===== 响应式 ===== */
@media (max-width: 768px) {
    .sale-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .sale-avatar-circle {
        width: 64px;
        height: 64px;
    }
    .sale-code {
        font-size: 13px;
    }
    .sale-price {
        font-size: 15px;
    }
    .sale-stats {
        gap: 16px;
    }
    .sale-filters {
        flex-direction: column;
        align-items: stretch;
    }
    .sale-filters input {
        min-width: auto;
    }
}
@media (max-width: 480px) {
    .sale-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .sale-avatar-circle {
        width: 56px;
        height: 56px;
        border-width: 2px;
    }
}
</style>

<div class="container">
    <!-- 顶部统计栏 -->
    <div class="sale-hero">
        <h1>🏷 NFT 销售市场</h1>
        <p>发现心仪头像，立即入手收藏</p>
        <div class="sale-stats">
            <div class="sale-stat">
                <strong><?= number_format($totalSales) ?></strong> 个在售
            </div>
            <div class="sale-stat">
                <strong><?= number_format($cityCount) ?></strong> 个城市有挂售
            </div>
        </div>
    </div>

    <!-- 筛选栏 -->
    <form method="get" class="sale-filters">
        <input type="text" name="code" placeholder="🔍 搜索编号..." 
               value="<?= htmlspecialchars($searchCode) ?>">
        <select name="city">
            <option value="">📍 全部城市</option>
            <?php foreach ($cities as $c): ?>
            <?php $cityName = is_array($c) ? $c['name'] : $c; ?>
            <option value="<?= htmlspecialchars($cityName) ?>" 
                <?= $selectedCity == $cityName ? 'selected' : '' ?>>
                <?= htmlspecialchars($cityName) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="currency">
            <option value="all" <?= $currency == 'all' ? 'selected' : '' ?>>💰 全部货币</option>
            <option value="popularity" <?= $currency == 'popularity' ? 'selected' : '' ?>>Ⓟ 人气值</option>
            <option value="cny" <?= $currency == 'cny' ? 'selected' : '' ?>>¥ 人民币</option>
        </select>
        <select name="sort">
            <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>📋 最新上架</option>
            <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>📈 价格从低到高</option>
            <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>📉 价格从高到低</option>
            <option value="hot" <?= $sort == 'hot' ? 'selected' : '' ?>>🔥 求购热度</option>
        </select>
        <button type="submit" class="sale-filter-btn">筛选</button>
        <a href="sale_list.php" class="sale-reset-btn">重置</a>
    </form>

    <!-- NFT 卡片网格 -->
    <div class="sale-grid">
        <?php if (empty($sales)): ?>
        <div class="sale-empty">
            <i class="fas fa-image"></i>
            <p>没有找到符合条件的 NFT</p>
            <a href="sale_list.php" class="sale-btn">查看全部在售</a>
        </div>
        <?php else: foreach ($sales as $s): ?>
        <a href="/nft/buy.php?tx=<?= $s['transaction_id'] ?>" class="sale-card">
            <!-- 圆形头像 -->
            <div class="sale-avatar-wrap">
                <div class="sale-avatar-circle">
                    <img src="../avatar/<?= htmlspecialchars($s['base_image']) ?>" 
                         alt="NFT <?= htmlspecialchars($s['code']) ?>" loading="lazy">
                </div>
            </div>
            
            <!-- 编号 -->
            <div class="sale-code"><?= htmlspecialchars($s['code']) ?></div>
            
            <!-- 城市归属 -->
            <?php if (!empty($s['city_name'])): ?>
            <div class="sale-city">📍 <?= htmlspecialchars($s['city_name']) ?></div>
            <?php endif; ?>
            
            <!-- 价格 -->
            <div class="sale-price <?= ($s['currency'] ?? '') == 'cny' ? 'sale-price-cny' : '' ?>">
                <?php if (($s['currency'] ?? '') == 'cny'): ?>
                ¥<?= number_format($s['price'], 2) ?>
                <?php else: ?>
                Ⓟ <?= number_format($s['price']) ?>
                <?php endif; ?>
            </div>
            
            <!-- 卖家 + 求购热度 -->
            <div class="sale-footer">
                <span class="sale-seller">@<?= htmlspecialchars($s['seller_name'] ?? '') ?></span>
                <?php if (($s['purchase_count'] ?? 0) > 0): ?>
                <span class="sale-hot">🔥<?= $s['purchase_count'] ?>人求购</span>
                <?php endif; ?>
            </div>
            
            <!-- 查看按钮 -->
            <span class="sale-btn">查看详情</span>
        </a>
        <?php endforeach; endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <nav class="sale-pagination">
        <?php
        // 构建查询字符串（保留筛选参数）
        $keep = $_GET;
        unset($keep['page']);
        $qs = http_build_query($keep);
        $baseUrl = 'sale_list.php?' . ($qs ? $qs . '&' : '');
        $showPrev = $page > 1;
        $showNext = $page < $totalPages;
        ?>
        <?php if ($showPrev): ?>
        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>
        
        <?php
        $last = 0;
        $window = 2;
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || abs($i - $page) <= $window) {
                if ($last && $i - $last > 1) echo '<span class="disabled">…</span>';
                $last = $i;
                if ($i == $page) echo '<span class="active">' . $i . '</span>';
                else echo '<a href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a>';
            }
        }
        ?>
        
        <?php if ($showNext): ?>
        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
        <?php else: ?>
        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
