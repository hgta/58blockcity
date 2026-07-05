<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Model.php';
require_once '../../classes/SeoHelper.php';

$modelId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($modelId <= 0) {
    http_response_code(404);
    include '../../404.php';
    exit;
}

$model = new Model($pdo);
$modelInfo = $model->getById($modelId);
if (!$modelInfo || $modelInfo['status'] !== 'active') {
    http_response_code(404);
    include '../../404.php';
    exit;
}

// 旧 URL 301 跳转到规范 URL
$canonicalUrl = SeoHelper::modelUrl($modelId, $modelInfo['nickname'] ?? '');
SeoHelper::redirectIfNotCanonical($canonicalUrl);

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;

// 获取关联商品
$products = $model->getModelProducts($modelId, $page, $perPage);
$totalProducts = $model->getProductCount($modelId);
$totalPages = ceil($totalProducts / $perPage);

// 获取图集
$galleryImages = $model->getModelProductImages($modelId, 24);

// 点赞处理
$isLiked = false;
$userId = $_SESSION['user_id'] ?? 0;
if ($userId) {
    $isLiked = $model->isLiked($modelId, $userId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like']) && $userId) {
    $model->like($modelId, $userId);
    header("Location: view.php?id=$modelId");
    exit;
}

// SEO 配置
$nickname = htmlspecialchars($modelInfo['nickname']);
$site_config['title']       = SeoHelper::title($nickname . ' - 58模特库');
$site_config['description'] = SeoHelper::description("模特{$nickname}的专属展示页，查看TA的模特作品与关联商品。", '58人气值商城');
$site_config['keywords']    = '58,模特,' . $nickname . ',商城,区块城市';
$site_config['canonical_url'] = $canonicalUrl;
$modelAvatar = !empty($modelInfo['avatar']) ? '../' . $modelInfo['avatar'] : (!empty($modelInfo['user_avatar']) ? '/assets/images/' . $modelInfo['user_avatar'] : '');
$site_config['og_image']    = $modelAvatar ?: 'https://58.tl/assets/images/og-mall.jpg';
$site_config['og_type']     = 'profile';

// Person JSON-LD
$personJsonLd = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $nickname,
    'url'      => $canonicalUrl,
    'gender'   => ($modelInfo['gender'] === '男') ? 'Male' : (($modelInfo['gender'] === '女') ? 'Female' : null),
    'height'   => $modelInfo['height'] ? ['@type' => 'QuantitativeValue', 'value' => (float)$modelInfo['height'], 'unitCode' => 'CMT'] : null,
    'weight'   => $modelInfo['weight'] ? ['@type' => 'QuantitativeValue', 'value' => (float)$modelInfo['weight'], 'unitCode' => 'KGM'] : null,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

// BreadcrumbList
$breadcrumbJsonLd = SeoHelper::breadcrumbList([
    ['name' => '58人气值商城', 'url' => 'https://mall.58.tl/'],
    ['name' => '模特库', 'url' => 'https://mall.58.tl/model/list.php'],
    ['name' => $nickname, 'url' => null],
]);

$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . $personJsonLd . $breadcrumbJsonLd;

require_once '../includes/header.php';
?>

<div class="container" style="max-width:1200px;margin:30px auto;padding:0 15px;">
    <!-- 模特头部 -->
    <div style="display:flex;gap:30px;margin-bottom:30px;flex-wrap:wrap;">
        <div style="flex-shrink:0;">
            <div style="width:160px;height:160px;border-radius:50%;overflow:hidden;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border:3px solid #ff6b00;">
                <?php if ($modelAvatar): ?>
                <img src="<?= htmlspecialchars($modelAvatar) ?>" alt="<?= $nickname ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <i class="fas fa-user" style="font-size:60px;color:#ccc;"></i>
                <?php endif; ?>
            </div>
        </div>
        <div style="flex:1;min-width:250px;">
            <h1 style="font-size:28px;margin:0 0 10px;"><?= $nickname ?></h1>
            <div style="display:flex;flex-wrap:wrap;gap:15px;color:#666;font-size:15px;margin-bottom:15px;">
                <?php if ($modelInfo['username']): ?><span><i class="fas fa-user"></i> @<?= htmlspecialchars($modelInfo['username']) ?></span><?php endif; ?>
                <?php if ($modelInfo['gender'] !== '保密'): ?><span><i class="fas fa-venus-mars"></i> <?= $modelInfo['gender'] ?></span><?php endif; ?>
                <?php if ($modelInfo['city']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($modelInfo['city']) ?></span><?php endif; ?>
                <?php if ($modelInfo['age']): ?><span><i class="fas fa-birthday-cake"></i> <?= $modelInfo['age'] ?>岁</span><?php endif; ?>
                <?php if ($modelInfo['height']): ?><span><i class="fas fa-ruler-vertical"></i> <?= $modelInfo['height'] ?>cm</span><?php endif; ?>
                <?php if ($modelInfo['weight']): ?><span><i class="fas fa-weight-scale"></i> <?= $modelInfo['weight'] ?>kg</span><?php endif; ?>
                <?php if ($modelInfo['measurements']): ?><span><i class="fas fa-ruler-combined"></i> <?= htmlspecialchars($modelInfo['measurements']) ?></span><?php endif; ?>
            </div>
            <?php if ($modelInfo['hobbies']): ?>
            <div style="margin-bottom:10px;"><strong>爱好：</strong><?= nl2br(htmlspecialchars($modelInfo['hobbies'])) ?></div>
            <?php endif; ?>
            <!-- 社交链接 -->
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:15px;">
                <?php if ($modelInfo['qq']): ?><span style="padding:4px 10px;background:#12b7f5;color:#fff;border-radius:4px;font-size:13px;"><i class="fab fa-qq"></i> QQ: <?= htmlspecialchars($modelInfo['qq']) ?></span><?php endif; ?>
                <?php if ($modelInfo['weixin']): ?><span style="padding:4px 10px;background:#07c160;color:#fff;border-radius:4px;font-size:13px;"><i class="fab fa-weixin"></i> 微信: <?= htmlspecialchars($modelInfo['weixin']) ?></span><?php endif; ?>
                <?php if ($modelInfo['weibo']): ?><span style="padding:4px 10px;background:#e6162d;color:#fff;border-radius:4px;font-size:13px;"><i class="fab fa-weibo"></i> <?= htmlspecialchars($modelInfo['weibo']) ?></span><?php endif; ?>
                <?php if ($modelInfo['xiaohongshu']): ?><span style="padding:4px 10px;background:#ff2442;color:#fff;border-radius:4px;font-size:13px;">📕 <?= htmlspecialchars($modelInfo['xiaohongshu']) ?></span><?php endif; ?>
            </div>
            <!-- 统计数据 + 点赞 -->
            <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
                <span style="color:#666;"><strong><?= $modelInfo['product_count'] ?></strong> 件商品</span>
                <span style="color:#666;">❤️ <strong><?= $modelInfo['like_count'] ?></strong> 赞</span>
                <?php if ($userId && $userId != $modelInfo['user_id']): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="like" value="1">
                    <button type="submit" style="padding:6px 16px;border-radius:20px;border:1px solid #ff6b00;background:<?= $isLiked ? '#ff6b00' : '#fff' ?>;color:<?= $isLiked ? '#fff' : '#ff6b00' ?>;cursor:pointer;font-size:14px;transition:all 0.2s;">
                        <?= $isLiked ? '❤️ 已赞' : '🤍 点赞' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 作品图集 -->
    <?php if (!empty($galleryImages)): ?>
    <div style="margin-bottom:30px;">
        <h3 style="font-size:20px;margin-bottom:15px;">📸 作品图集 <small style="color:#999;font-size:13px;">(点击查看大图)</small></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;" id="gallery-grid">
            <?php foreach ($galleryImages as $i => $img): ?>
            <div class="gallery-item" data-index="<?= $i ?>" data-src="../<?= htmlspecialchars($img) ?>"
                 style="aspect-ratio:1;overflow:hidden;border-radius:6px;background:#f5f5f5;cursor:pointer;transition:transform 0.2s;"
                 onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                <img src="../<?= htmlspecialchars($img) ?>" alt="作品图片" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 关联商品列表 -->
    <div>
        <h3 style="font-size:20px;margin-bottom:15px;">🛍 关联商品（<?= $totalProducts ?>件）</h3>
        <?php if (empty($products)): ?>
        <p style="color:#999;padding:30px;text-align:center;">暂无关联商品</p>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:15px;">
            <?php foreach ($products as $p): 
                $pUrl = SeoHelper::productUrl($p['id'], $p['name']);
                $pImage = !empty($p['main_image']) ? '../' . htmlspecialchars($p['main_image']) : '../assets/images/default-product.jpg';
            ?>
            <a href="<?= $pUrl ?>" style="text-decoration:none;color:inherit;">
                <div style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                    <div style="aspect-ratio:1;overflow:hidden;background:#f5f5f5;">
                        <img src="<?= $pImage ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
                    </div>
                    <div style="padding:10px;">
                        <div style="font-size:14px;font-weight:bold;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:12px;color:#999;margin-top:4px;"><?= htmlspecialchars($p['shop_name'] ?? '') ?></div>
                        <div style="color:#e74c3c;font-weight:bold;margin-top:4px;">Ⓟ <?= number_format($p['price_bct'] ?? 0, 0) ?> 人气值</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div style="text-align:center;margin-top:20px;">
            <?php if ($page > 1): ?>
            <a href="?id=<?= $modelId ?>&page=<?= $page-1 ?>" style="display:inline-block;padding:8px 16px;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;margin:0 4px;">上一页</a>
            <?php endif; ?>
            <span style="color:#666;"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?id=<?= $modelId ?>&page=<?= $page+1 ?>" style="display:inline-block;padding:8px 16px;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;margin:0 4px;">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 图片灯箱 Modal -->
<div id="lightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:9999;align-items:center;justify-content:center;" onclick="closeLightbox(event)">
    <span style="position:absolute;top:20px;right:30px;color:#fff;font-size:36px;cursor:pointer;z-index:10000;" onclick="closeLightbox()">&times;</span>
    <button onclick="event.stopPropagation();prevImage()" style="position:absolute;left:20px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);color:#fff;border:none;font-size:32px;padding:12px 18px;cursor:pointer;border-radius:50%;z-index:10000;">‹</button>
    <img id="lightbox-img" src="" style="max-width:90%;max-height:90%;object-fit:contain;border-radius:4px;" onclick="event.stopPropagation()">
    <button onclick="event.stopPropagation();nextImage()" style="position:absolute;right:20px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);color:#fff;border:none;font-size:32px;padding:12px 18px;cursor:pointer;border-radius:50%;z-index:10000;">›</button>
    <div style="position:absolute;bottom:30px;color:#fff;font-size:14px;" id="lightbox-counter"></div>
</div>

<script>
var galleryImages = [];
var currentIndex = 0;
document.querySelectorAll('.gallery-item').forEach(function(el, i) {
    galleryImages.push(el.dataset.src);
    el.addEventListener('click', function(e) { e.stopPropagation(); openLightbox(i); });
});
function openLightbox(idx) {
    currentIndex = idx;
    document.getElementById('lightbox-img').src = galleryImages[idx];
    document.getElementById('lightbox-counter').textContent = (idx+1) + ' / ' + galleryImages.length;
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox(e) {
    if (e && e.target !== document.getElementById('lightbox')) return;
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
function prevImage() {
    currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
    openLightbox(currentIndex);
}
function nextImage() {
    currentIndex = (currentIndex + 1) % galleryImages.length;
    openLightbox(currentIndex);
}
document.addEventListener('keydown', function(e) {
    if (document.getElementById('lightbox').style.display === 'flex') {
        if (e.key === 'ArrowLeft') prevImage();
        if (e.key === 'ArrowRight') nextImage();
        if (e.key === 'Escape') { document.getElementById('lightbox').style.display = 'none'; document.body.style.overflow = ''; }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
