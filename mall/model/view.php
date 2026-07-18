<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Model.php';
require_once '../../classes/Message.php';
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
// 分享用 URL，中文编码防止微信等截断
$shareUrl = 'https://mall.58.tl/model/' . $modelId . '-' . rawurlencode(SeoHelper::slug($modelInfo['nickname'] ?? '')) . '.html';
$shareUrl = rtrim($shareUrl, '-.html') . '.html';
SeoHelper::redirectIfNotCanonical($canonicalUrl);

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;

// 获取关联商品
$products = $model->getModelProducts($modelId, $page, $perPage);
$totalProducts = $model->getProductCount($modelId);
$totalPages = ceil($totalProducts / $perPage);

// 获取图集（取更多商品图片，前端懒加载）
$galleryImages = $model->getModelProductImages($modelId, 100);

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

// 处理留言（通过统一站内信发给模特关联的用户）
$messageObj = new Message($pdo);
$msgSuccess = '';
$modelUserId = $modelInfo['user_id'] ?? 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text']) && $userId && $modelUserId) {
    $msg = trim($_POST['message_text'] ?? '');
    if (mb_strlen($msg) < 1) {
        $msgSuccess = '<p style="color:#e74c3c;">请输入留言内容</p>';
    } elseif ($messageObj->send($userId, $modelUserId, $msg)) {
        header("Location: view.php?id=$modelId#messages");
        exit;
    }
}

// 获取留言（当前用户与模特用户的会话）
$messages = [];
$messageCount = 0;
if ($modelUserId && $userId) {
    $messages = $messageObj->getMessages($userId, $modelUserId, 1, 10);
    $messageCount = count($messageObj->getMessages($userId, $modelUserId, 1, 9999));
}

// SEO 配置
$nickname = htmlspecialchars($modelInfo['nickname']);
$site_config['title']       = SeoHelper::title($nickname . ' - 58模特库');
$site_config['description'] = SeoHelper::description("模特{$nickname}的专属展示页，查看TA的模特作品与关联商品。", '58人气值商城');
$site_config['keywords']    = '58,模特,' . $nickname . ',商城,区块城市';
$site_config['canonical_url'] = $canonicalUrl;
$modelAvatar = '';
if (!empty($modelInfo['avatar'])) {
    $modelAvatar = '../' . $modelInfo['avatar'];
} elseif (!empty($modelInfo['user_avatar'])) {
    // 用户头像可能是 assets/uploads/avatars/xxx.jpg 或 uploads/avatars/xxx.jpg
    $ua = $modelInfo['user_avatar'];
    if (strpos($ua, '/') !== false) {
        $modelAvatar = '../' . $ua; // 已含路径
    } else {
        $modelAvatar = '/assets/images/' . $ua; // 旧格式文件名
    }
}
// OG image 必须用绝对 URL
$ogImage = $modelAvatar ? (strpos($modelAvatar, '://') !== false ? $modelAvatar : 'https://mall.58.tl/' . ltrim($modelAvatar, '/')) : 'https://58.tl/assets/images/og-mall.jpg';
$site_config['og_image']    = $ogImage;
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
                <?php if ($modelInfo['username']): ?><span><i class="fas fa-user"></i> <a href="#messages" style="color:#3498db;text-decoration:none;">@<?= htmlspecialchars($modelInfo['username']) ?></a></span><?php endif; ?>
                <?php if ($modelInfo['gender'] !== '保密'): ?><span><i class="fas fa-venus-mars"></i> <?= $modelInfo['gender'] ?></span><?php endif; ?>
                <?php if ($modelInfo['city']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($modelInfo['city']) ?></span><?php endif; ?>
                <?php if ($modelInfo['age']): ?><span><i class="fas fa-birthday-cake"></i> <?= $modelInfo['age'] ?>岁</span><?php endif; ?>
                <?php if ($modelInfo['height']): ?><span><i class="fas fa-ruler-vertical"></i> <?= $modelInfo['height'] ?>cm</span><?php endif; ?>
                <?php if ($modelInfo['weight']): ?><span><i class="fas fa-weight-scale"></i> <?= $modelInfo['weight'] ?>kg</span><?php endif; ?>
                <?php if ($modelInfo['measurements']): ?><span><i class="fas fa-ruler-combined"></i> <?= htmlspecialchars($modelInfo['measurements']) ?></span><?php endif; ?>
                <?php if ($modelInfo['zodiac']): ?><span><i class="fas fa-star"></i> <?= htmlspecialchars($modelInfo['zodiac']) ?></span><?php endif; ?>
                <?php if ($modelInfo['follower_count']): ?><span><i class="fas fa-users"></i> <?= htmlspecialchars($modelInfo['follower_count']) ?>粉丝</span><?php endif; ?>
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
                <span style="color:#666;"><a href="#messages" style="color:#666;text-decoration:none;">💬 <strong><?= $messageCount ?></strong> 留言</a></span>
                <?php if ($userId && $userId != $modelInfo['user_id']): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="like" value="1">
                    <button type="submit" style="padding:6px 16px;border-radius:20px;border:1px solid #ff6b00;background:<?= $isLiked ? '#ff6b00' : '#fff' ?>;color:<?= $isLiked ? '#fff' : '#ff6b00' ?>;cursor:pointer;font-size:14px;transition:all 0.2s;">
                        <?= $isLiked ? '❤️ 已赞' : '🤍 点赞' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <!-- 分享按钮 -->
            <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                <button onclick="shareModel()" style="padding:6px 14px;border-radius:20px;border:1px solid #ff6b00;background:#ff6b00;color:#fff;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:4px;">
                    📤 分享
                </button>
                <a href="https://service.weibo.com/share/share.php?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($nickname . ' - 58模特库') ?>" target="_blank" style="padding:6px 14px;border-radius:20px;border:1px solid #ddd;background:#fff;text-decoration:none;font-size:13px;color:#e6162d;display:flex;align-items:center;gap:4px;">
                    微博
                </a>
                <a href="javascript:void(0)" onclick="shareQQ()" style="padding:6px 14px;border-radius:20px;border:1px solid #ddd;background:#fff;text-decoration:none;font-size:13px;color:#12b7f5;display:flex;align-items:center;gap:4px;">
                    QQ
                </a>
            </div>
        </div>
    </div>

    <script>
    function shareModel() {
        var shareUrl = '<?= $shareUrl ?>';
        var shareTitle = <?= json_encode($nickname . ' - 58模特库') ?>;
        var shareText = <?= json_encode('快来看看' . $nickname . '的模特作品！') ?>;

        if (navigator.share) {
            navigator.share({
                title: shareTitle,
                text: shareText,
                url: shareUrl,
            }).catch(function(){});
        } else {
            var input = document.createElement('input');
            input.value = shareUrl;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            alert('链接已复制到剪贴板');
        }
    }
    function shareQQ() {
        window.open('https://connect.qq.com/widget/shareqq/index.html?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($nickname . ' - 58模特库') ?>&desc=<?= urlencode('快来看看' . $nickname . '的模特作品！') ?>');
    }
    </script>

    <!-- 留言区 -->
    <div id="messages" style="margin-bottom:30px;scroll-margin-top:80px;">
        <h3 style="font-size:20px;margin-bottom:15px;">💬 留言 (<?= $messageCount ?>条)</h3>

        <?php if ($userId): ?>
        <form method="post" style="margin-bottom:20px;display:flex;gap:10px;">
            <input type="text" name="message_text" maxlength="500" placeholder="给 <?= $nickname ?> 留言..."
                   style="flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            <button type="submit" style="padding:10px 20px;background:#ff6b00;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;white-space:nowrap;">发送</button>
        </form>
        <?php else: ?>
        <p style="color:#999;margin-bottom:16px;">请<a href="../auth/login.php" style="color:#ff6b00;">登录</a>后留言</p>
        <?php endif; ?>
        <?= $msgSuccess ?>

        <?php if (empty($messages)): ?>
        <p style="color:#999;padding:20px;text-align:center;">暂无留言，快来抢沙发~</p>
        <?php else: ?>
        <?php foreach ($messages as $msg): 
            $msgAvatar = !empty($msg['user_avatar']) ? '/assets/images/' . $msg['user_avatar'] : '';
        ?>
        <div style="display:flex;gap:12px;padding:14px 0;border-bottom:1px solid #f0f0f0;">
            <div style="width:40px;height:40px;border-radius:50%;overflow:hidden;background:#f0f0f0;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                <?php if ($msgAvatar): ?>
                <img src="<?= htmlspecialchars($msgAvatar) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <i class="fas fa-user" style="color:#ccc;font-size:18px;"></i>
                <?php endif; ?>
            </div>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <strong style="font-size:14px;"><?= htmlspecialchars($msg['username'] ?? '匿名') ?></strong>
                    <span style="font-size:12px;color:#999;"><?= date('m-d H:i', strtotime($msg['created_at'])) ?></span>
                </div>
                <p style="font-size:14px;color:#444;margin:0;word-break:break-all;"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 作品图集 -->
    <?php if (!empty($galleryImages)): ?>
    <?php $totalGallery = count($galleryImages); ?>
    <div style="margin-bottom:30px;">
        <h3 style="font-size:20px;margin-bottom:15px;">📸 作品图集 <small style="color:#999;font-size:13px;">(共<?= $totalGallery ?>张，点击查看大图)</small></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;" id="gallery-grid">
            <?php foreach ($galleryImages as $i => $img): ?>
            <div class="gallery-item" data-index="<?= $i ?>" data-src="../<?= htmlspecialchars($img) ?>"
                 style="aspect-ratio:1;overflow:hidden;border-radius:6px;background:#f5f5f5;cursor:pointer;transition:transform 0.2s;<?= $i >= 12 ? 'display:none' : '' ?>"
                 onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                <img src="../<?= htmlspecialchars($img) ?>" alt="作品图片" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($totalGallery > 12): ?>
        <div style="text-align:center;margin-top:12px;">
            <button onclick="loadMoreGallery()" id="gallery-load-btn"
                    style="padding:8px 30px;border:1px solid #ff6b00;background:#fff;color:#ff6b00;border-radius:20px;font-size:14px;cursor:pointer;">
                加载更多（剩余 <span id="gallery-remain"><?= $totalGallery - 12 ?></span> 张）
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 日常照片 -->
    <?php 
    $dailyPhotos = [];
    if (!empty($modelInfo['daily_photos'])) {
        $dailyPhotos = json_decode($modelInfo['daily_photos'], true);
        if (!is_array($dailyPhotos)) $dailyPhotos = [];
    }
    ?>
    <?php if (!empty($dailyPhotos)): ?>
    <div style="margin-bottom:30px;">
        <h3 style="font-size:20px;margin-bottom:15px;">📷 日常照片 <small style="color:#999;font-size:13px;">(点击查看大图)</small></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;">
            <?php 
            $dailyJsArr = [];
            foreach ($dailyPhotos as $dp): 
                $dailyJsArr[] = "'../" . addslashes($dp) . "'";
            ?>
            <div style="aspect-ratio:1;overflow:hidden;border-radius:6px;cursor:pointer;" onclick="openDailyLightbox(<?= count($dailyJsArr) - 1 ?>)">
                <img src="../<?= htmlspecialchars($dp) ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    var dailyImages = [<?= implode(',', $dailyJsArr) ?>];
    var dailyIdx = 0;
    function openDailyLightbox(idx) {
        dailyIdx = idx;
        showDailyLightbox();
    }
    function showDailyLightbox() {
        var lb = document.getElementById('daily-lightbox');
        if (!lb) {
            lb = document.createElement('div');
            lb.id = 'daily-lightbox';
            lb.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center;';
            lb.onclick = function(e){ if(e.target===lb) closeDailyLightbox(); };
            document.body.appendChild(lb);
        }
        lb.innerHTML = '<img src="' + dailyImages[dailyIdx] + '" style="max-width:90%;max-height:90%;border-radius:8px;">' +
            (dailyImages.length>1?'<div style="position:absolute;top:50%;left:10px;transform:translateY(-50%);font-size:36px;color:#fff;cursor:pointer;padding:10px;" onclick="event.stopPropagation();dailyIdx=(dailyIdx-1+dailyImages.length)%'+dailyImages.length+';showDailyLightbox()">‹</div>':'') +
            (dailyImages.length>1?'<div style="position:absolute;top:50%;right:10px;transform:translateY(-50%);font-size:36px;color:#fff;cursor:pointer;padding:10px;" onclick="event.stopPropagation();dailyIdx=(dailyIdx+1)%'+dailyImages.length+';showDailyLightbox()">›</div>':'') +
            '<div style="position:absolute;top:15px;right:20px;font-size:28px;color:#fff;cursor:pointer;" onclick="event.stopPropagation();closeDailyLightbox()">✕</div>' +
            (dailyImages.length>1?'<div style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:#fff;font-size:14px;background:rgba(0,0,0,0.5);padding:4px 14px;border-radius:12px;">'+(dailyIdx+1)+' / '+dailyImages.length+'</div>':'');
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeDailyLightbox() {
        var lb = document.getElementById('daily-lightbox');
        if (lb) { lb.style.display = 'none'; document.body.style.overflow = ''; }
    }
    document.addEventListener('keydown', function(e) {
        var lb = document.getElementById('daily-lightbox');
        if (lb && lb.style.display === 'flex') {
            if (e.key === 'ArrowLeft') { dailyIdx = (dailyIdx-1+dailyImages.length)%dailyImages.length; showDailyLightbox(); }
            if (e.key === 'ArrowRight') { dailyIdx = (dailyIdx+1)%dailyImages.length; showDailyLightbox(); }
            if (e.key === 'Escape') closeDailyLightbox();
        }
    });
    </script>
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

// 图集懒加载
var galleryPage = 0, galleryPerPage = 12;
function loadMoreGallery() {
    var items = document.querySelectorAll('#gallery-grid .gallery-item');
    var start = galleryPage * galleryPerPage;
    var end = start + galleryPerPage;
    var shown = 0;
    for (var i = start; i < Math.min(end, items.length); i++) {
        if (items[i].style.display === 'none') {
            items[i].style.display = '';
            shown++;
        }
    }
    galleryPage++;
    var remain = 0;
    for (var j = end; j < items.length; j++) {
        if (items[j].style.display === 'none') remain++;
    }
    var remainEl = document.getElementById('gallery-remain');
    if (remainEl) remainEl.textContent = remain;
    if (remain === 0) {
        var btn = document.getElementById('gallery-load-btn');
        if (btn) { btn.textContent = '已加载全部'; btn.disabled = true; btn.style.opacity = '0.5'; }
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
