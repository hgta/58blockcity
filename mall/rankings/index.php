<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/MallRanking.php';

$ranking = new MallRanking($pdo);
$stats = $ranking->getRankingStats();

$mainTab = $_GET['tab'] ?? 'product';
$productType = $_GET['type'] ?? 'popular';
$shopType = $_GET['type'] ?? 'sales';

$productTypes = [
    'popular' => ['name'=>'人气榜','icon'=>'fire','desc'=>'最多人浏览的商品'],
    'sales'   => ['name'=>'销量榜','icon'=>'shopping-cart','desc'=>'最多人购买的商品'],
    'rating'  => ['name'=>'评分榜','icon'=>'star','desc'=>'评分最高的商品'],
    'reviews' => ['name'=>'口碑榜','icon'=>'comments','desc'=>'评价最多的商品'],
    'newest'  => ['name'=>'新品榜','icon'=>'clock','desc'=>'最新上架的商品'],
];
$shopTypes = [
    'sales'  => ['name'=>'销量榜','icon'=>'shopping-cart','desc'=>'总销量最高的店铺'],
    'rating' => ['name'=>'评分榜','icon'=>'star','desc'=>'评分最高的店铺'],
];

$productRanking = ($mainTab === 'product') ? $ranking->getProductRanking($productType, 20) : [];
$shopRanking = ($mainTab === 'shop') ? $ranking->getShopRanking($shopType, 20) : [];
$currentType = ($mainTab === 'product') ? $productType : $shopType;
$currentTypes = ($mainTab === 'product') ? $productTypes : $shopTypes;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>排行榜 - 58人气值商城</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Microsoft YaHei',Arial,sans-serif;background:#f5f5f5;color:#333}
.container{max-width:1200px;margin:0 auto;padding:0 15px}
a{text-decoration:none;color:inherit}
.stats-bar{display:flex;gap:15px;margin:20px 0;flex-wrap:wrap}
.stat-box{flex:1;min-width:140px;background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;align-items:center;gap:12px}
.stat-ico{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0}
.stat-num{font-size:22px;font-weight:700}
.stat-lbl{font-size:12px;color:#999}
.main-tabs{display:flex;background:#fff;border-radius:12px 12px 0 0;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.main-tab{flex:1;padding:18px;text-align:center;font-size:16px;font-weight:600;color:#999;cursor:pointer;border-bottom:3px solid transparent;transition:all .2s}
.main-tab:hover{color:#ff6b00;background:#fff9f5}
.main-tab.active{color:#ff6b00;border-bottom-color:#ff6b00;background:#fff9f5}
.sub-tabs{display:flex;gap:10px;padding:16px 20px;background:#fff;flex-wrap:wrap}
.sub-tab{padding:8px 18px;border-radius:20px;font-size:14px;font-weight:500;color:#666;background:#f5f5f5;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px}
.sub-tab:hover{color:#ff6b00;background:#fff0e6}
.sub-tab.active{color:#fff;background:linear-gradient(135deg,#ff6b00,#ff9500)}
.ranking-body{background:#fff;border-radius:0 0 12px 12px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:20px}
.rank-item,.shop-rank-item{display:flex;align-items:center;gap:16px;padding:16px;border-radius:10px;transition:background .2s;margin-bottom:8px}
.rank-item:hover,.shop-rank-item:hover{background:#f9f9f9}
.rank-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0;background:#f0f0f0;color:#999}
.rank-item:nth-child(2) .rank-num{background:linear-gradient(135deg,#ffd700,#ffa500);color:#fff}
.rank-item:nth-child(3) .rank-num{background:linear-gradient(135deg,#c0c0c0,#a8a8a8);color:#fff}
.rank-item:nth-child(4) .rank-num{background:linear-gradient(135deg,#cd7f32,#b87333);color:#fff}
.shop-rank-item:nth-child(2) .rank-num{background:linear-gradient(135deg,#ffd700,#ffa500);color:#fff}
.shop-rank-item:nth-child(3) .rank-num{background:linear-gradient(135deg,#c0c0c0,#a8a8a8);color:#fff}
.shop-rank-item:nth-child(4) .rank-num{background:linear-gradient(135deg,#cd7f32,#b87333);color:#fff}
.rank-img{width:64px;height:64px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f5f5f5}
.rank-info{flex:1;min-width:0}
.rank-name{font-size:15px;font-weight:600;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rank-name a:hover{color:#ff6b00}
.rank-meta{font-size:13px;color:#999;display:flex;gap:12px;flex-wrap:wrap}
.rank-price{text-align:right;flex-shrink:0;min-width:100px}
.price-val{font-size:18px;font-weight:700;color:#ff6b00}
.price-unit{font-size:12px;color:#ff6b00}
.rank-metric{font-size:12px;color:#999;margin-top:4px}
.rank-metric strong{color:#ff6b00;font-size:14px}
.shop-logo{width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0;background:#f5f5f5}
.shop-desc{font-size:13px;color:#999;margin:4px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.shop-metrics{display:flex;gap:20px;flex-shrink:0;text-align:center}
.m-val{font-size:18px;font-weight:700;color:#333}
.m-lbl{font-size:12px;color:#999}
.shop-metric.rating .m-val{color:#f39c12}
.shop-metric.sales .m-val{color:#ff6b00}
.empty{text-align:center;padding:60px 20px;color:#999}
.empty i{font-size:48px;margin-bottom:15px;opacity:.4}
@media(max-width:768px){.stats-bar{flex-direction:column}.rank-item,.shop-rank-item{flex-wrap:wrap}.rank-price{text-align:left}.shop-metrics{width:100%;justify-content:space-around}}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="stats-bar">
        <div class="stat-box"><div class="stat-ico" style="background:linear-gradient(135deg,#ff6b00,#ff9500)"><i class="fas fa-box"></i></div><div><div class="stat-num"><?= number_format($stats['total_products']) ?></div><div class="stat-lbl">在售商品</div></div></div>
        <div class="stat-box"><div class="stat-ico" style="background:linear-gradient(135deg,#3498db,#2980b9)"><i class="fas fa-eye"></i></div><div><div class="stat-num"><?= number_format($stats['total_views']) ?></div><div class="stat-lbl">总浏览量</div></div></div>
        <div class="stat-box"><div class="stat-ico" style="background:linear-gradient(135deg,#27ae60,#219a52)"><i class="fas fa-shopping-cart"></i></div><div><div class="stat-num"><?= number_format($stats['total_sales']) ?></div><div class="stat-lbl">总销量</div></div></div>
        <div class="stat-box"><div class="stat-ico" style="background:linear-gradient(135deg,#9b59b6,#8e44ad)"><i class="fas fa-store"></i></div><div><div class="stat-num"><?= number_format($stats['total_shops']) ?></div><div class="stat-lbl">活跃店铺</div></div></div>
    </div>

    <div class="main-tabs">
        <div class="main-tab <?= $mainTab==='product'?'active':'' ?>" onclick="location.href='?tab=product&type=<?= $productType ?>'"><i class="fas fa-trophy"></i> 商品排行榜</div>
        <div class="main-tab <?= $mainTab==='shop'?'active':'' ?>" onclick="location.href='?tab=shop&type=<?= $shopType ?>'"><i class="fas fa-store"></i> 店铺排行榜</div>
    </div>

    <div class="sub-tabs">
        <?php if ($mainTab === 'product'): ?>
            <?php foreach ($productTypes as $key => $t): ?>
                <div class="sub-tab <?= $productType===$key?'active':'' ?>" onclick="location.href='?tab=product&type=<?= $key ?>'"><i class="fas fa-<?= $t['icon'] ?>"></i> <?= $t['name'] ?></div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($shopTypes as $key => $t): ?>
                <div class="sub-tab <?= $shopType===$key?'active':'' ?>" onclick="location.href='?tab=shop&type=<?= $key ?>'"><i class="fas fa-<?= $t['icon'] ?>"></i> <?= $t['name'] ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="ranking-body">
        <p style="color:#999;font-size:14px;margin-bottom:16px;"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($currentTypes[$currentType]['desc'] ?? '') ?></p>

        <?php if ($mainTab === 'product'): ?>
            <?php if (empty($productRanking)): ?>
                <div class="empty"><i class="fas fa-box-open"></i><p>暂无排行数据</p></div>
            <?php else: ?>
                <?php foreach ($productRanking as $i => $p): ?>
                    <?php
                    $metricLabels = [
                        'popular'=>['浏览',$p['view_count']],
                        'sales'=>['已售',$p['sold_count']],
                        'rating'=>['评分',number_format($p['rating'],1)],
                        'reviews'=>['评价',$p['review_count']],
                        'newest'=>['上架',date('m-d',strtotime($p['created_at']))],
                    ];
                    $m = $metricLabels[$productType] ?? ['浏览',$p['view_count']];
                    ?>
                    <div class="rank-item">
                        <div class="rank-num"><?= $i+1 ?></div>
                        <img class="rank-img" src="../<?= htmlspecialchars($p['thumb_image']?:$p['main_image']?:'assets/images/default-product.jpg') ?>" alt="" onerror="this.src='../assets/images/default-product.jpg'">
                        <div class="rank-info">
                            <div class="rank-name"><a href="../product/detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></div>
                            <div class="rank-meta">
                                <span><i class="fas fa-store"></i> <?= htmlspecialchars($p['shop_name']?:'平台自营') ?></span>
                                <span><i class="fas fa-star" style="color:#f39c12"></i> <?= number_format($p['rating'],1) ?> (<?= $p['review_count'] ?>评)</span>
                            </div>
                        </div>
                        <div class="rank-price">
                            <?php if ($p['price_bct']>0): ?>
                                <span class="price-val"><?= number_format($p['price_bct'],0) ?></span><span class="price-unit"> BCT</span>
                            <?php elseif ($p['price_cny']>0): ?>
                                <span class="price-val">¥<?= number_format($p['price_cny'],2) ?></span>
                            <?php else: ?>
                                <span class="price-val" style="color:#27ae60">免费</span>
                            <?php endif; ?>
                            <div class="rank-metric"><?= $m[0] ?> <strong><?= $m[1] ?></strong></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if (empty($shopRanking)): ?>
                <div class="empty"><i class="fas fa-store"></i><p>暂无排行数据</p></div>
            <?php else: ?>
                <?php foreach ($shopRanking as $i => $s): ?>
                    <div class="shop-rank-item">
                        <div class="rank-num"><?= $i+1 ?></div>
                        <img class="shop-logo" src="../<?= htmlspecialchars($s['shop_logo']?:'assets/images/default-shop.jpg') ?>" alt="" onerror="this.src='../assets/images/default-shop.jpg'">
                        <div class="shop-info">
                            <div class="rank-name"><a href="../shop/view.php?id=<?= $s['id'] ?>"><?= htmlspecialchars($s['shop_name']) ?></a></div>
                            <div class="shop-desc"><?= htmlspecialchars(mb_substr($s['description']?:'这家店铺还没有描述...',0,40)) ?></div>
                            <div class="rank-meta">
                                <span><i class="fas fa-box"></i> <?= $s['active_products']??0 ?> 件商品</span>
                                <span><i class="fas fa-calendar"></i> <?= date('Y-m-d',strtotime($s['created_at'])) ?> 开店</span>
                            </div>
                        </div>
                        <div class="shop-metrics">
                            <div class="shop-metric sales"><div class="m-val"><?= number_format($s['total_sales']) ?></div><div class="m-lbl">总销量</div></div>
                            <div class="shop-metric rating"><div class="m-val"><?= number_format($s['rating'],1) ?></div><div class="m-lbl">评分</div></div>
                            <div class="shop-metric"><div class="m-val"><?= $s['review_count']??0 ?></div><div class="m-lbl">评价数</div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
