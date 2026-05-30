<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';
require_once '../../classes/User.php';
require_once '../includes/functions.php';

$nft = new NFT($pdo);
$city = new City($pdo);

// 获取筛选参数
$selectedCity = $_GET['city'] ?? '';
$selectedRarity = $_GET['rarity'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$currency = $_GET['currency'] ?? 'all';

// 分页设置
$page = $_GET['page'] ?? 1;
$perPage = 24;
$offset = ($page - 1) * $perPage;

// 获取销售中的NFT
$sales = $nft->getSaleList($selectedCity, $selectedRarity, $minPrice, $maxPrice, $currency, $sort, $perPage, $offset);
$totalSales = $nft->getTotalSaleCount($selectedCity, $selectedRarity, $minPrice, $maxPrice, $currency);

// 获取筛选选项
$cities = $city->getAllCities();
$rarities = $nft->getAllRarities();
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-tag me-2"></i>NFT销售市场</h2>
        <div class="total-count">
            共 <?= number_format($totalSales) ?> 个NFT在售
        </div>
    </div>

    <!-- 筛选栏 -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <!-- 城市筛选 -->
                <!-- 城市筛选 -->
				<div class="col-md-3">
					<label for="cityFilter" class="form-label">城市</label>
					<select id="cityFilter" name="city" class="form-select">
						<option value="">全部城市</option>
						<?php foreach ($cities as $city): ?>
							<?php 
							// 确保我们获取的是城市名称字符串
							$cityName = is_array($city) ? $city['name'] : $city;
							?>
							<option value="<?= htmlspecialchars($cityName) ?>" 
								<?= $selectedCity == $cityName ? 'selected' : '' ?>>
								<?= htmlspecialchars($cityName) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

                <!-- 价格范围 -->
                <div class="col-md-3">
                    <label class="form-label">价格范围</label>
                    <div class="input-group">
                        <input type="number" name="min_price" class="form-control" placeholder="最低" 
                               value="<?= htmlspecialchars($minPrice) ?>">
                        <span class="input-group-text">-</span>
                        <input type="number" name="max_price" class="form-control" placeholder="最高" 
                               value="<?= htmlspecialchars($maxPrice) ?>">
                    </div>
                </div>

                <!-- 货币类型 -->
                <div class="col-md-2">
                    <label for="currencyFilter" class="form-label">货币</label>
                    <select id="currencyFilter" name="currency" class="form-select">
                        <option value="all" <?= $currency == 'all' ? 'selected' : '' ?>>全部</option>
                        <option value="popularity" <?= $currency == 'popularity' ? 'selected' : '' ?>>人气值</option>
                        <option value="cny" <?= $currency == 'cny' ? 'selected' : '' ?>>人民币</option>
                    </select>
                </div>

                <!-- 排序方式 -->
                <div class="col-md-2">
                    <label for="sortFilter" class="form-label">排序</label>
                    <select id="sortFilter" name="sort" class="form-select">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>最新上架</option>
                        <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>价格从低到高</option>
                        <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>价格从高到低</option>
                        <option value="rare" <?= $sort == 'rare' ? 'selected' : '' ?>>稀有度优先</option>
                    </select>
                </div>

                <!-- 操作按钮 -->
                <div class="col-md-12 d-flex justify-content-end align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> 筛选
                    </button>
                    <a href="sale_list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt me-1"></i> 重置
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- NFT销售列表 -->
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3">
        <?php if (empty($sales)): ?>
            <div class="col-12">
                <div class="empty-state text-center py-5">
                    <div class="empty-icon text-muted mb-3">
                        <i class="fas fa-image fa-3x"></i>
                    </div>
                    <h3 class="h5">没有找到符合条件的NFT</h3>
                    <p class="text-muted">尝试调整筛选条件</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($sales as $sale): ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm position-relative nft-sale-card">
                        <!-- 稀有度标记 -->
                        <span class="position-absolute top-0 start-0 badge bg-<?= $sale['rarity'] ?>">
                            <?= htmlspecialchars($sale['rarity']) ?>
                        </span>
                        
                        <!-- 圆形头像 -->
                        <div class="avatar-circle mx-auto mt-4">
                            <img src="../avatar/<?= htmlspecialchars($sale['base_image']) ?>" 
                                 class="avatar-img rounded-circle" 
                                 alt="NFT <?= htmlspecialchars($sale['code']) ?>"
                                 loading="lazy">
                        </div>
                        
                        <div class="card-body text-center pt-1 pb-3">
                            <h6 class="card-title mb-1"><?= htmlspecialchars($sale['code']) ?></h6>
                            <p class="card-text small text-muted mb-1">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($sale['city']) ?>
                            </p>
                            
                            <!-- 价格 -->
                            <div class="price-tag mb-2">
                                <span class="price-value"><?= number_format($sale['price'], $sale['currency'] == 'cny' ? 2 : 0) ?></span>
                                <span class="price-currency">
                                    <?= $sale['currency'] == 'cny' ? '¥' : '人气值' ?>
                                </span>
                            </div>
                            
                            <!-- 卖家信息 -->
                            <p class="seller-info small text-muted mb-2">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($sale['seller_name']) ?>
                            </p>
                            
                            <!-- 操作按钮 -->
                            <div class="d-grid">
                                <a href="/nft/view.php?id=<?= $sale['nft_id'] ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-shopping-cart me-1"></i> 购买
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($totalSales > $perPage): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= buildQueryString(['page' => $page - 1]) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= ceil($totalSales / $perPage); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= buildQueryString(['page' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $page >= ceil($totalSales / $perPage) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= buildQueryString(['page' => $page + 1]) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>