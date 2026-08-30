<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Author.php';
require_once '../../classes/Message.php';
require_once '../../classes/SeoHelper.php';
require_once './card.php';

$authorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($authorId <= 0) {
    http_response_code(404);
    include '../../404.php';
    exit;
}

$author = new Author($pdo);
$authorInfo = $author->getById($authorId);
if (!$authorInfo || $authorInfo['status'] !== 'active') {
    http_response_code(404);
    include '../../404.php';
    exit;
}

// 记录浏览（轻量累加，不做去重）
$author->recordView($authorId);

// 旧 URL 301 跳转到规范 URL（仅无分页参数时跳转）
$canonicalUrl = SeoHelper::authorUrl($authorId, $authorInfo['nickname'] ?? '');
if (empty($_GET['page'])) {
    SeoHelper::redirectIfNotCanonical($canonicalUrl);
}

$shareUrl = $canonicalUrl;

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;

// 获取关联商品
$products = $author->getAuthorProducts($authorId, $page, $perPage);
$totalProducts = $author->getProductCount($authorId);
$totalPages = ceil($totalProducts / $perPage);

// 原创作品图集（author_works，权威）
$worksImages = $author->getAuthorWorks($authorId);

// 点赞处理
$isLiked = false;
$isFollowedAuthor = false;
$userId = $_SESSION['user_id'] ?? 0;
if ($userId) {
    $isLiked = $author->isLiked($authorId, $userId);
    $isFollowedAuthor = $author->isFollowed($authorId, $userId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like']) && $userId) {
    $author->like($authorId, $userId);
    header("Location: view.php?id=$authorId");
    exit;
}

// 处理留言（通过统一站内信发给作者关联的用户）
$messageObj = new Message($pdo);
$msgSuccess = '';
$authorUserId = $authorInfo['user_id'] ?? 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text']) && $userId && $authorUserId) {
    $msg = trim($_POST['message_text'] ?? '');
    if (mb_strlen($msg) < 1) {
        $msgSuccess = '<p style="color:#e74c3c;">请输入留言内容</p>';
    } elseif ($messageObj->send($userId, $authorUserId, $msg)) {
        header("Location: view.php?id=$authorId#messages");
        exit;
    }
}

// 获取留言（当前用户与作者用户的会话）
$messages = [];
$messageCount = 0;
if ($authorUserId && $userId) {
    $messages = $messageObj->getMessages($userId, $authorUserId, 1, 10);
    $messageCount = count($messageObj->getMessages($userId, $authorUserId, 1, 9999));
}

// SEO 配置
$nickname = htmlspecialchars($authorInfo['nickname']);
$site_config['title']       = SeoHelper::title($nickname . ' - 58作者库');
$site_config['description'] = SeoHelper::description((!empty($authorInfo['bio']) ? $authorInfo['bio'] . '。' : '') . "图案作者{$nickname}的专属展示页，查看TA的原创作品与图案商品。", '58人气值商城');
$site_config['keywords']    = '58,作者,' . $nickname . ',插画,国潮,商城,区块城市';
$site_config['canonical_url'] = $canonicalUrl;
$authorAvatar = '';
if (!empty($authorInfo['avatar'])) {
    $authorAvatar = '../' . $authorInfo['avatar'];
} elseif (!empty($authorInfo['user_avatar'])) {
    $ua = $authorInfo['user_avatar'];
    if (strpos($ua, '/') !== false) {
        $authorAvatar = '../' . $ua;
    } else {
        $authorAvatar = '/assets/images/' . $ua;
    }
}
$ogImage = $authorAvatar ? (strpos($authorAvatar, '://') !== false ? $authorAvatar : 'https://mall.58.tl/' . ltrim($authorAvatar, '/')) : 'https://58.tl/assets/images/og-mall.jpg';
$site_config['og_image']    = $ogImage;
$site_config['og_type']     = 'profile';

// Person JSON-LD（不含身高体重，作者无身体数据）
$personJsonLd = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $nickname,
    'url'      => $canonicalUrl,
    'jobTitle' => '图案作者',
    'description' => !empty($authorInfo['bio']) ? $authorInfo['bio'] : null,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

// BreadcrumbList
$breadcrumbJsonLd = SeoHelper::breadcrumbList([
    ['name' => '58人气值商城', 'url' => 'https://mall.58.tl/'],
    ['name' => '作者库', 'url' => 'https://mall.58.tl/author/list.php'],
    ['name' => $nickname, 'url' => null],
]);

$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . $personJsonLd . $breadcrumbJsonLd;

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="style.css">
<div class="container" style="max-width:1200px;margin:30px auto;padding:0 15px;">
    <!-- 作者头部 -->
    <div style="display:flex;gap:30px;margin-bottom:30px;flex-wrap:wrap;">
        <div style="flex-shrink:0;">
            <div style="width:160px;height:160px;border-radius:50%;overflow:hidden;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border:3px solid #6c5ce7;">
                <?php if ($authorAvatar): ?>
                <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= $nickname ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <i class="fas fa-palette" style="font-size:60px;color:#ccc;"></i>
                <?php endif; ?>
            </div>
        </div>
        <div style="flex:1;min-width:250px;">
            <h1 style="font-size:28px;margin:0 0 10px;"><?= $nickname ?></h1>
            <div style="display:flex;flex-wrap:wrap;gap:15px;color:#666;font-size:15px;margin-bottom:15px;">
                <?php if ($authorInfo['username']): ?><span><i class="fas fa-user"></i> <a href="#messages" style="color:#6c5ce7;text-decoration:none;">@<?= htmlspecialchars($authorInfo['username']) ?></a></span><?php endif; ?>
                <?php if ($authorInfo['gender'] !== '保密'): ?><span><i class="fas fa-venus-mars"></i> <?= $authorInfo['gender'] ?></span><?php endif; ?>
                <?php if ($authorInfo['city']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($authorInfo['city']) ?></span><?php endif; ?>
                <?php if ($authorInfo['zodiac']): ?><span><i class="fas fa-star"></i> <?= htmlspecialchars($authorInfo['zodiac']) ?></span><?php endif; ?>
                <?php if ($authorInfo['style']): ?><span><i class="fas fa-palette"></i> <?= htmlspecialchars($authorInfo['style']) ?></span><?php endif; ?>
                <?php if ($authorInfo['follower_count']): ?><span><i class="fas fa-users"></i> <b class="follower-count"><?= Author::formatFollower($authorInfo['follower_count']) ?></b>粉丝</span><?php endif; ?>
                <span><i class="fas fa-eye"></i> <?= number_format(($authorInfo['view_count'] ?? 0) + 1) ?>次访问</span>
            </div>
            <?php if ($authorInfo['bio']): ?>
            <div style="margin-bottom:10px;"><strong>简介：</strong><?= nl2br(htmlspecialchars($authorInfo['bio'])) ?></div>
            <?php endif; ?>
            <!-- 社交链接 -->
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:15px;">
                <?php if ($authorInfo['qq']): ?><span style="padding:4px 10px;background:#12b7f5;color:#fff;border-radius:4px;font-size:13px;"><i class="fab fa-qq"></i> QQ: <?= htmlspecialchars($authorInfo['qq']) ?></span><?php endif; ?>
                <?php if ($authorInfo['weixin']): ?><span style="padding:4px 10px;background:#07c160;color:#fff;border-radius:4px;font-size:13px;"><i class="fab fa-weixin"></i> 微信: <?= htmlspecialchars($authorInfo['weixin']) ?></span><?php endif; ?>
                <?php if ($authorInfo['weibo']): ?><span style="padding:4px 10px;background:#e6162d;color:#fff;border-radius:4px;font-size:13px;"><i class="fab fa-weibo"></i> <?= htmlspecialchars($authorInfo['weibo']) ?></span><?php endif; ?>
                <?php if ($authorInfo['xiaohongshu']): ?><span style="padding:4px 10px;background:#ff2442;color:#fff;border-radius:4px;font-size:13px;">📕 <?= htmlspecialchars($authorInfo['xiaohongshu']) ?></span><?php endif; ?>
            </div>
            <!-- 统计数据 + 点赞 -->
            <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
                <span style="color:#666;"><strong><?= $authorInfo['product_count'] ?></strong> 件图案商品</span>
                <span style="color:#666;">❤️ <strong><?= $authorInfo['like_count'] ?></strong> 赞</span>
                <?php if ($authorUserId): ?>
                <span style="color:#666;"><a href="#messages" style="color:#666;text-decoration:none;">💬 <strong><?= $messageCount ?></strong> 留言</a></span>
                <?php endif; ?>
                <?php if ($userId && $userId != $authorInfo['user_id']): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="like" value="1">
                    <button type="submit" style="padding:6px 16px;border-radius:20px;border:1px solid #6c5ce7;background:<?= $isLiked ? '#6c5ce7' : '#fff' ?>;color:<?= $isLiked ? '#fff' : '#6c5ce7' ?>;cursor:pointer;font-size:14px;transition:all 0.2s;">
                        <?= $isLiked ? '❤️ 已赞' : '🤍 点赞' ?>
                    </button>
                </form>
                <button class="author-follow-btn <?= $isFollowedAuthor ? 'followed' : '' ?>" style="margin-left:10px;"
                        data-author-id="<?= $authorId ?>" data-logged-in="1"
                        data-login-url="../auth/login.php?redirect=<?= urlencode($canonicalUrl) ?>">
                    <?= $isFollowedAuthor ? '已关注' : '+ 关注' ?>
                </button>
                <?php endif; ?>
            </div>
            <!-- 分享按钮 -->
            <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                <button onclick="shareAuthor()" style="padding:6px 14px;border-radius:20px;border:1px solid #6c5ce7;background:#6c5ce7;color:#fff;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:4px;">
                    📤 分享
                </button>
                <a href="https://service.weibo.com/share/share.php?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($nickname . ' - 58作者库') ?>" target="_blank" style="padding:6px 14px;border-radius:20px;border:1px solid #ddd;background:#fff;text-decoration:none;font-size:13px;color:#e6162d;display:flex;align-items:center;gap:4px;">
                    微博
                </a>
                <a href="javascript:void(0)" onclick="shareQQ()" style="padding:6px 14px;border-radius:20px;border:1px solid #ddd;background:#fff;text-decoration:none;font-size:13px;color:#12b7f5;display:flex;align-items:center;gap:4px;">
                    QQ
                </a>
            </div>
        </div>
    </div>

    <script>
    function shareAuthor() {
        var shareUrl = '<?= $shareUrl ?>';
        var shareTitle = <?= json_encode($nickname . ' - 58作者库') ?>;
        var shareText = <?= json_encode('快来看看' . $nickname . '的原创图案作品！') ?>;

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
        window.open('https://connect.qq.com/widget/shareqq/index.html?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($nickname . ' - 58作者库') ?>&desc=<?= urlencode('快来看看' . $nickname . '的原创图案作品！') ?>');
    }
    </script>

    <?php if ($authorUserId): ?>
    <!-- 留言区（仅关联站内用户时显示） -->
    <div id="messages" style="margin-bottom:30px;scroll-margin-top:80px;">
        <h3 style="font-size:20px;margin-bottom:15px;">💬 留言 (<?= $messageCount ?>条)</h3>

        <?php if ($userId): ?>
        <form method="post" style="margin-bottom:20px;display:flex;gap:10px;">
            <input type="text" name="message_text" maxlength="500" placeholder="给 <?= $nickname ?> 留言..."
                   style="flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            <button type="submit" style="padding:10px 20px;background:#6c5ce7;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;white-space:nowrap;">发送</button>
        </form>
        <?php else: ?>
        <p style="color:#999;margin-bottom:16px;">请<a href="../auth/login.php" style="color:#6c5ce7;">登录</a>后留言</p>
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
    <?php endif; ?>

    <!-- 原创作品图集 -->
    <?php if (!empty($worksImages)): ?>
    <?php $totalWorks = count($worksImages); ?>
    <div style="margin-bottom:30px;">
        <h3 style="font-size:20px;margin-bottom:15px;">🎨 原创作品 <small style="color:#999;font-size:13px;">(共<?= $totalWorks ?>张，点击查看大图)</small></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;" id="works-grid">
            <?php foreach ($worksImages as $i => $img): ?>
            <div class="work-item" data-index="<?= $i ?>" data-src="../<?= htmlspecialchars($img) ?>"
                 style="aspect-ratio:1;overflow:hidden;border-radius:6px;background:#f5f5f5;cursor:pointer;transition:transform 0.2s;<?= $i >= 12 ? 'display:none' : '' ?>"
                 onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                <img src="../<?= htmlspecialchars($img) ?>" alt="原创作品" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($totalWorks > 12): ?>
        <div style="text-align:center;margin-top:12px;">
            <button onclick="loadMoreWorks()" id="works-load-btn"
                    style="padding:8px 30px;border:1px solid #6c5ce7;background:#fff;color:#6c5ce7;border-radius:20px;font-size:14px;cursor:pointer;">
                加载更多（剩余 <span id="works-remain"><?= $totalWorks - 12 ?></span> 张）
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 关联商品列表 -->
    <div>
        <h3 style="font-size:20px;margin-bottom:15px;">🛍 图案商品（<?= $totalProducts ?>件）</h3>
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
            <a href="?id=<?= $authorId ?>&page=<?= $page-1 ?>" style="display:inline-block;padding:8px 16px;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;margin:0 4px;">上一页</a>
            <?php endif; ?>
            <span style="color:#666;"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?id=<?= $authorId ?>&page=<?= $page+1 ?>" style="display:inline-block;padding:8px 16px;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;margin:0 4px;">下一页</a>
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
var worksImages = [];
var currentIndex = 0;
document.querySelectorAll('.work-item').forEach(function(el, i) {
    worksImages.push(el.dataset.src);
    el.addEventListener('click', function(e) { e.stopPropagation(); openLightbox(i); });
});
function openLightbox(idx) {
    currentIndex = idx;
    document.getElementById('lightbox-img').src = worksImages[idx];
    document.getElementById('lightbox-counter').textContent = (idx+1) + ' / ' + worksImages.length;
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox(e) {
    if (e && e.target !== document.getElementById('lightbox')) return;
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
function prevImage() {
    currentIndex = (currentIndex - 1 + worksImages.length) % worksImages.length;
    openLightbox(currentIndex);
}
function nextImage() {
    currentIndex = (currentIndex + 1) % worksImages.length;
    openLightbox(currentIndex);
}
document.addEventListener('keydown', function(e) {
    if (document.getElementById('lightbox').style.display === 'flex') {
        if (e.key === 'ArrowLeft') prevImage();
        if (e.key === 'ArrowRight') nextImage();
        if (e.key === 'Escape') { document.getElementById('lightbox').style.display = 'none'; document.body.style.overflow = ''; }
    }
});

// 作品图集懒加载
var worksPage = 0, worksPerPage = 12;
function loadMoreWorks() {
    var items = document.querySelectorAll('#works-grid .work-item');
    var start = worksPage * worksPerPage;
    var end = start + worksPerPage;
    var shown = 0;
    for (var i = start; i < Math.min(end, items.length); i++) {
        if (items[i].style.display === 'none') {
            items[i].style.display = '';
            shown++;
        }
    }
    worksPage++;
    var remain = 0;
    for (var j = end; j < items.length; j++) {
        if (items[j].style.display === 'none') remain++;
    }
    var remainEl = document.getElementById('works-remain');
    if (remainEl) remainEl.textContent = remain;
    if (remain === 0) {
        var btn = document.getElementById('works-load-btn');
        if (btn) { btn.textContent = '已加载全部'; btn.disabled = true; btn.style.opacity = '0.5'; }
    }
}
</script>

<?php
// 相关作者（同 风格+城市+星座+性别 加权）
$relatedAuthors = $author->getRelated($authorId, 5);
if (!empty($relatedAuthors)):
    $relIds = array_column($relatedAuthors, 'id');
    $relFollowed = [];
    if ($userId && $relIds) {
        $ph = implode(',', array_map('intval', $relIds));
        $stmt = $pdo->prepare("SELECT author_id FROM author_follows WHERE user_id = ? AND author_id IN ($ph)");
        $stmt->execute([$userId]);
        $relFollowed = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    }
?>
<div style="max-width:1200px;margin:30px auto;padding:0 15px;">
    <h3 style="font-size:20px;margin-bottom:15px;">💡 相关作者</h3>
    <div class="author-grid">
        <?php foreach ($relatedAuthors as $ra): ?>
            <?= renderAuthorCard($ra, [], isset($relFollowed[$ra['id']]), $userId) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script src="follow.js"></script>

<?php require_once '../includes/footer.php'; ?>
