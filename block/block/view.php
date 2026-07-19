<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
require_once '../../classes/User.php';
require_once '../../config/block_prices.php';

$blockId = intval($_GET['id'] ?? 0);

$block = new Block($pdo);
$user = new User($pdo);

$blockInfo = $block->getBlockById($blockId);
if (!$blockInfo) {
    header("Location: ../city/");
    exit();
}

$ownerInfo = $blockInfo['owner_id'] ? $user->getUserById($blockInfo['owner_id']) : null;
$isOwner = isLoggedIn() && $_SESSION['user_id'] == $blockInfo['owner_id'];
$currentUserId = isLoggedIn() ? $_SESSION['user_id'] : null;

// 正确价格（动态计算）
$realPrice = calculateBlockPriceNew(
    (string)($blockInfo['zone'] ?? 'A'),
    (string)($blockInfo['block_number'] ?? '0101')
);

// 合并组信息（该区块是否属于某个合并组）
$mergedInfo = null;
$mergeListing = null;
$mStmt = $pdo->prepare("SELECT * FROM merged_blocks WHERE city_id = ? AND zone = ? AND FIND_IN_SET(?, REPLACE(merged_blocks, ' ', '')) > 0 LIMIT 1");
$mStmt->execute([$blockInfo['city_id'], $blockInfo['zone'], $blockInfo['block_number']]);
$mergedInfo = $mStmt->fetch(PDO::FETCH_ASSOC);
$mergeTitle = '';
$manageParam = 'id=' . $blockInfo['id'];
$skinPreview = $blockInfo; // 默认单块皮肤
if ($mergedInfo) {
    $mergedNums = array_map('trim', explode(',', $mergedInfo['merged_blocks']));
    $mergeTitle = $mergedInfo['merge_size'] . ' 合并组（' . min($mergedNums) . '~' . max($mergedNums) . '）';
    // 合并块价值 = 所有子块价格之和
    $realPrice = 0;
    foreach ($mergedNums as $mn) {
        $realPrice += calculateBlockPriceNew((string)$mergedInfo['zone'], (string)$mn);
    }
    if ($isOwner) $manageParam = 'merged_id=' . $mergedInfo['id'];
    // 合并组的皮肤在代表区块（取首格）
    $repIds = $block->getMergedBlockIds($mergedInfo['id']);
    if (!empty($repIds)) {
        $skinPreview = $block->getBlockById($repIds[0]);
    }
    // 合并组挂牌
    $mlStmt = $pdo->prepare("SELECT * FROM block_listings WHERE merged_block_id = ? AND status IN ('listed','pending') ORDER BY id DESC LIMIT 1");
    $mlStmt->execute([$mergedInfo['id']]);
    $mergeListing = $mlStmt->fetch(PDO::FETCH_ASSOC);
}

// 单块挂牌 + 合并组挂牌
$soloListing = null;
if (!$mergedInfo || !$mergeListing) {
    $slStmt = $pdo->prepare("SELECT * FROM block_listings WHERE block_id = ? AND status IN ('listed','pending') ORDER BY id DESC LIMIT 1");
    $slStmt->execute([$blockInfo['id']]);
    $soloListing = $slStmt->fetch(PDO::FETCH_ASSOC);
}
$activeListing = $mergeListing ?: $soloListing;

// 求购请求
$purStmt = $pdo->prepare("SELECT pr.*, u.username FROM purchase_requests pr LEFT JOIN users u ON pr.user_id = u.id WHERE pr.city_id = ? AND pr.zone = ? AND pr.block_number = ? AND pr.status = 'active' ORDER BY pr.created_at DESC");
$purStmt->execute([$blockInfo['city_id'], $blockInfo['zone'], $blockInfo['block_number']]);
$purchaseReqs = $purStmt->fetchAll(PDO::FETCH_ASSOC);

// 该城市该区已认领总数
$zoneStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE city_id = ? AND zone = ? AND status = 'sold'");
$zoneStmt->execute([$blockInfo['city_id'], $blockInfo['zone']]);
$zoneSoldCount = (int)$zoneStmt->fetchColumn();

// 所有者区块数
$ownerBlockCount = 0;
if ($ownerInfo) {
    $ocStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE owner_id = ? AND status = 'sold'");
    $ocStmt->execute([$blockInfo['owner_id']]);
    $ownerBlockCount = (int)$ocStmt->fetchColumn();
}

// 皮肤相关
$skinType = $skinPreview['display_type'] ?? 'none';
$skinImage = $skinPreview['display_image'] ?? '';
$skinText  = $skinPreview['display_text'] ?? '';
$skinColor = $skinPreview['display_color'] ?? 'blue';
$skinColors = ['red' => '#ff6060', 'green' => '#35cc2d', 'blue' => '#337be6'];
$skinColorHex = $skinColors[$skinColor] ?? '#337be6';

// 城市拼音（city map 链接所需）
$cityPinyin = '';
$cpStmt = $pdo->prepare("SELECT pinyin FROM cities WHERE id = ?");
$cpStmt->execute([$blockInfo['city_id']]);
$cityPinyin = $cpStmt->fetchColumn() ?: '';

$pageTitle = ($blockInfo['city_name'] ?? '') . ' ' . $blockInfo['zone'] . '区 #' . $blockInfo['block_number'] . ' - 58区块城市';
?>
<?php require_once '../includes/header.php'; ?>

<style>
.vw-wrap { max-width:1100px; margin:0 auto; padding:20px 15px; }
.vw-hero { display:flex; gap:24px; margin-bottom:28px; flex-wrap:wrap; }
.vw-skin-box { flex:0 0 280px; width:280px; max-width:100%; }
.vw-skin { border-radius:14px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.1); aspect-ratio:1/1; background:#f3f4f6; display:flex; align-items:center; justify-content:center; }
.vw-skin img { width:100%; height:100%; object-fit:cover; display:block; }
.vw-skin.vw-img-none { color:#bbb; font-size:48px; }
.vw-skin.vw-text { color:#fff; font-weight:bold; font-size:28px; text-align:center; padding:20px; line-height:1.3; align-items:center; justify-content:center; display:flex; }
.vw-skin-actions { margin-top:12px; text-align:center; }
.vw-skin-actions a { font-size:13px; color:#3498db; text-decoration:none; }
.vw-skin-actions a:hover { text-decoration:underline; }

.vw-info { flex:1; min-width:260px; }
.vw-title { font-size:24px; font-weight:800; color:#1a1a2e; margin-bottom:6px; }
.vw-title .badge { font-size:11px; padding:3px 9px; border-radius:20px; margin-left:6px; vertical-align:middle; }
.badge-own { background:#d2ffc6; color:#2e7d32; }
.badge-sell { background:#fff3e0; color:#ef6c00; }
.badge-merg { background:#e3f2fd; color:#1976d2; }
.vw-sub { font-size:14px; color:#888; margin-bottom:16px; }
.vw-price { font-size:32px; font-weight:800; color:#ff6b00; margin-bottom:18px; }
.vw-price small { font-size:16px; font-weight:400; color:#999; margin-left:6px; }
.vw-tags { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.vw-tag { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:20px; font-size:13px; font-weight:600; background:#f8f9fa; color:#555; }
.vw-tag .dot { width:8px; height:8px; border-radius:50%; }
.vw-tag.active { background:#e8f5e9; color:#2e7d32; }
.vw-tag.active .dot { background:#2e7d32; }
.vw-tag.sold { background:#ffebee; color:#c62828; }
.vw-tag.sold .dot { background:#c62828; }
.vw-tag.listed { background:#fff3e0; color:#ef6c00; }
.vw-tag.listed .dot { background:#ef6c00; }
.vw-owner { display:flex; align-items:center; gap:10px; padding:12px 16px; background:#f8fafc; border-radius:10px; }
.vw-owner-avatar { width:40px; height:40px; border-radius:50%; background:#337be6; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:bold; flex-shrink:0; }
.vw-owner-meta { font-size:13px; color:#666; }
.vw-owner-name { font-size:15px; font-weight:600; color:#333; }

/* 卡片通用 */
.vw-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:16px; }
.vw-card h3 { font-size:16px; font-weight:700; color:#1a1a2e; margin:0 0 12px; display:flex; align-items:center; gap:8px; }
.vw-card h3 i { color:#ff6b00; width:20px; text-align:center; }
.vw-row { display:flex; gap:16px; flex-wrap:wrap; }
.vw-main { flex:1; min-width:320px; }
.vw-side { flex:0 0 300px; max-width:100%; }

/* 信息行 */
.vw-info-row { display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f5f5f5; font-size:14px; }
.vw-info-row:last-child { border-bottom:none; }
.vw-info-label { color:#888; }
.vw-info-value { color:#333; font-weight:500; }

/* 求购列表 */
.vw-pur-item { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f5f5f5; font-size:14px; }
.vw-pur-item:last-child { border-bottom:none; }
.vw-pur-price { color:#e74c3c; font-weight:bold; }

/* 操作按钮 */
.vw-btn { display:block; width:100%; padding:13px; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; text-decoration:none; text-align:center; margin-bottom:10px; transition:all .2s; }
.vw-btn-primary { background:linear-gradient(135deg,#ff6b00,#ff9500); color:#fff; box-shadow:0 4px 15px rgba(255,107,0,.25); }
.vw-btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(255,107,0,.35); color:#fff; text-decoration:none; }
.vw-btn-secondary { background:#f0f0f0; color:#555; }
.vw-btn-secondary:hover { background:#e0e0e0; color:#333; text-decoration:none; }
.vw-btn-outline { background:#fff; color:#3498db; border:2px solid #3498db; }
.vw-btn-outline:hover { background:#3498db; color:#fff; text-decoration:none; }
.vw-btn-danger { background:#e74c3c; color:#fff; }
.vw-btn-danger:hover { background:#c0392b; color:#fff; text-decoration:none; }
.vw-btn:last-child { margin-bottom:0; }

/* Empty */
.vw-empty { text-align:center; padding:20px; color:#aaa; font-size:13px; }

/* Nearby */
.vw-nearby { margin:30px auto 0; }
.vw-nearby h3 { font-size:18px; color:#333; margin-bottom:12px; }
.vw-nearby-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px; }
.vw-nearby-card { display:block; padding:12px; background:#fff; border-radius:8px; border:1px solid #eee; text-decoration:none; text-align:center; transition:.2s; }
.vw-nearby-card:hover { border-color:#ff6b00; transform:translateY(-2px); }
.vw-nearby-num { font-size:15px; font-weight:700; color:#333; }
.vw-nearby-price { font-size:13px; color:#ff6b00; font-weight:600; margin-top:4px; }

@media(max-width:768px) {
    .vw-hero { flex-direction:column; }
    .vw-skin-box { flex:0 0 auto; width:100%; max-width:260px; margin:0 auto; }
    .vw-title { font-size:20px; }
    .vw-price { font-size:26px; }
    .vw-side { flex:1; }
}
</style>

<div class="vw-wrap">

    <!-- ===== 顶部：皮肤 + 核心信息 ===== -->
    <div class="vw-hero">
        <!-- 区块皮肤预览 -->
        <div class="vw-skin-box">
            <?php if ($skinType === 'image' && !empty($skinImage)): ?>
                <div class="vw-skin"><img src="/<?= htmlspecialchars($skinImage) ?>" alt="区块皮肤" onerror="this.parentElement.innerHTML='<i class=fas fa-map-marker-alt></i>';this.parentElement.classList.add('vw-img-none')"></div>
            <?php elseif ($skinType === 'text' && !empty($skinText)): ?>
                <div class="vw-skin vw-text" style="background:<?= $skinColorHex ?>"><?= htmlspecialchars($skinText) ?></div>
            <?php else: ?>
                <div class="vw-skin vw-img-none"><i class="fas fa-map-marker-alt"></i></div>
            <?php endif; ?>
            <?php if ($mergedInfo): ?>
                <div class="vw-skin-actions"><span class="badge badge-merg"><?= htmlspecialchars($mergeTitle) ?></span></div>
            <?php endif; ?>
        </div>

        <!-- 核心信息 -->
        <div class="vw-info">
            <div class="vw-title">
                <?= htmlspecialchars($blockInfo['city_name'] ?? '') ?> · <?= $blockInfo['zone'] ?>区 #<?= $blockInfo['block_number'] ?>
                <?php if ($isOwner): ?>
                    <span class="badge badge-own">我的区块</span>
                <?php endif; ?>
                <?php if ($activeListing): ?>
                    <span class="badge badge-sell">在售中</span>
                <?php endif; ?>
            </div>
            <div class="vw-sub">
                <?= htmlspecialchars($blockInfo['zone']) ?>区 · 第 <?= $blockInfo['block_number'] ?> 号区块
                <?php if ($mergedInfo): ?>（<?= htmlspecialchars($mergeTitle) ?>）<?php endif; ?>
            </div>

            <div class="vw-price">
                ¥<?= number_format($realPrice, 2) ?>
                <small>当前价值</small>
                <?php if ($activeListing): ?>
                    <div style="font-size:15px;font-weight:600;color:#ef6c00;margin-top:2px;">
                        <?= $activeListing['currency']==='popularity'?'Ⓟ':'¥' ?>
                        <?= number_format($activeListing['price'], 2) ?> 挂牌售价
                    </div>
                <?php endif; ?>
            </div>

            <!-- 状态标签 -->
            <div class="vw-tags">
                <span class="vw-tag <?= $blockInfo['status']==='sold'?'active':'' ?>">
                    <span class="dot" style="background:<?= $blockInfo['status']==='sold'?'#2e7d32':'#999' ?>"></span>
                    <?= $blockInfo['status']==='sold'?'已认领':'可认领' ?>
                </span>
                <?php if ($activeListing): ?>
                    <span class="vw-tag listed"><span class="dot"></span>挂牌出售中</span>
                <?php endif; ?>
                <?php if ($mergedInfo): ?>
                    <span class="vw-tag" style="background:#e3f2fd;color:#1976d2;"><i class="fas fa-th"></i> 合并区块</span>
                <?php endif; ?>
            </div>

            <!-- 拥有者 -->
            <?php if ($ownerInfo): ?>
            <div class="vw-owner">
                <div class="vw-owner-avatar"><?= mb_substr($ownerInfo['username'], 0, 2) ?></div>
                <div class="vw-owner-meta">
                    <div class="vw-owner-name"><?= htmlspecialchars($ownerInfo['username']) ?></div>
                    <div>拥有 <?= $ownerBlockCount ?> 个区块 · <?= htmlspecialchars($ownerInfo['city'] ?? '') ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== 两栏 ===== -->
    <div class="vw-row">
        <!-- 左栏：详细信息 -->
        <div class="vw-main">
            <!-- 区块详情 -->
            <div class="vw-card">
                <h3><i class="fas fa-info-circle"></i> 区块详情</h3>
                <div class="vw-info-row"><span class="vw-info-label">所属城市</span><span class="vw-info-value"><?= htmlspecialchars($blockInfo['city_name'] ?? '') ?></span></div>
                <div class="vw-info-row"><span class="vw-info-label">所在区域</span><span class="vw-info-value"><?= $blockInfo['zone'] ?>区</span></div>
                <div class="vw-info-row"><span class="vw-info-label">区块编号</span><span class="vw-info-value">#<?= $blockInfo['block_number'] ?></span></div>
                <?php if ($mergedInfo): ?>
                    <div class="vw-info-row"><span class="vw-info-label">合并尺寸</span><span class="vw-info-value"><?= htmlspecialchars($mergedInfo['merge_size']) ?>（<?= htmlspecialchars(implode(', ', $mergedNums)) ?>）</span></div>
                <?php endif; ?>
                <div class="vw-info-row"><span class="vw-info-label">当前价值</span><span class="vw-info-value" style="color:#ff6b00;font-weight:700;">¥<?= number_format($realPrice, 2) ?></span></div>
                <div class="vw-info-row"><span class="vw-info-label"><?= $blockInfo['zone'] ?>区已认领</span><span class="vw-info-value"><?= number_format($zoneSoldCount) ?> 个</span></div>
                <?php if ($skinType !== 'none'): ?>
                    <div class="vw-info-row"><span class="vw-info-label">皮肤类型</span><span class="vw-info-value"><?= $skinType==='image'?'图片':'文字' ?></span></div>
                <?php endif; ?>
            </div>

            <!-- 挂牌出售状态 -->
            <div class="vw-card">
                <h3><i class="fas fa-tag"></i> 挂牌出售</h3>
                <?php if ($activeListing): ?>
                    <div class="vw-info-row"><span class="vw-info-label">挂牌编号</span><span class="vw-info-value">#<?= $activeListing['id'] ?></span></div>
                    <div class="vw-info-row"><span class="vw-info-label">售价</span><span class="vw-info-value" style="color:#ef6c00;font-weight:700;"><?= $activeListing['currency']==='popularity'?'Ⓟ':'¥' ?> <?= number_format($activeListing['price'], 2) ?></span></div>
                    <div class="vw-info-row">
                        <span class="vw-info-label">计价货币</span>
                        <span class="vw-info-value"><?= $activeListing['currency']==='popularity'?'人气值':'人民币' ?></span>
                    </div>
                    <div class="vw-info-row">
                        <span class="vw-info-label">状态</span>
                        <span class="vw-info-value" style="color:<?= $activeListing['status']==='listed'?'#2e7d32':'#ef6c00' ?>;"><?= $activeListing['status']==='listed'?'售卖中':'交易中' ?></span>
                    </div>
                    <?php if (!$isOwner): ?>
                        <div style="margin-top:12px;">
                            <a href="buy.php?listing=<?= $activeListing['id'] ?>" class="vw-btn vw-btn-primary">前往购买</a>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:12px;font-size:13px;color:#999;">
                            <i class="fas fa-info-circle"></i> 这是你的挂牌，可到<a href="manage.php?<?= $manageParam ?>">管理页</a>取消
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="vw-empty">
                        <i class="fas fa-store-slash" style="font-size:20px;display:block;margin-bottom:6px;"></i>
                        暂无挂牌出售
                        <?php if ($isOwner): ?>
                            <div style="margin-top:8px;"><a href="manage.php?<?= $manageParam ?>#sell" class="vw-btn vw-btn-primary" style="display:inline-block;width:auto;padding:8px 20px;">立即上架出售</a></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 求购信息 -->
            <div class="vw-card">
                <h3><i class="fas fa-search-dollar"></i> 求购请求</h3>
                <?php if (!empty($purchaseReqs)): ?>
                    <?php foreach ($purchaseReqs as $pr): ?>
                        <div class="vw-pur-item">
                            <div>
                                <strong><?= htmlspecialchars($pr['username'] ?? '用户'.$pr['user_id']) ?></strong>
                                <span style="font-size:12px;color:#999;margin-left:8px;"><?= date('m-d H:i', strtotime($pr['created_at'])) ?></span>
                            </div>
                            <div class="vw-pur-price">¥<?= number_format($pr['max_price'] ?? 0, 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (isLoggedIn() && !$isOwner && empty($activeListing)): ?>
                        <div style="margin-top:12px;"><a href="../purchase_create.php?city_id=<?= $blockInfo['city_id'] ?>&zone=<?= $blockInfo['zone'] ?>&block_number=<?= $blockInfo['block_number'] ?>" class="vw-btn vw-btn-outline">我也要发布求购</a></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="vw-empty">
                        <i class="fas fa-inbox" style="font-size:20px;display:block;margin-bottom:6px;"></i>
                        暂无求购请求
                        <?php if (isLoggedIn() && !$isOwner): ?>
                            <div style="margin-top:8px;"><a href="../purchase_create.php?city_id=<?= $blockInfo['city_id'] ?>&zone=<?= $blockInfo['zone'] ?>&block_number=<?= $blockInfo['block_number'] ?>" class="vw-btn vw-btn-secondary" style="display:inline-block;width:auto;padding:8px 20px;">发布求购</a></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 右栏：操作 -->
        <div class="vw-side">
            <!-- 操作 -->
            <div class="vw-card">
                <h3><i class="fas fa-bolt"></i> 操作</h3>
                <?php if ($isOwner): ?>
                    <a href="manage.php?<?= $manageParam ?>" class="vw-btn vw-btn-primary"><i class="fas fa-cog"></i> 管理区块</a>
                    <a href="manage.php?<?= $manageParam ?>#sell" class="vw-btn vw-btn-secondary"><i class="fas fa-tag"></i> 挂牌出售</a>
                <?php elseif (isLoggedIn() && $activeListing): ?>
                    <a href="buy.php?listing=<?= $activeListing['id'] ?>" class="vw-btn vw-btn-primary"><i class="fas fa-shopping-cart"></i> 购买此区块</a>
                <?php elseif (isLoggedIn() && $blockInfo['status'] === 'available'): ?>
                    <a href="../city.php?name=<?= urlencode($cityPinyin) ?>&zone=<?= $blockInfo['zone'] ?>" class="vw-btn vw-btn-primary"><i class="fas fa-hand-pointer"></i> 去认领</a>
                <?php elseif (isLoggedIn() && $ownerInfo): ?>
                    <a href="../messages/new.php?to=<?= $ownerInfo['id'] ?>" class="vw-btn vw-btn-secondary"><i class="fas fa-envelope"></i> 联系拥有者</a>
                    <a href="../purchase_create.php?city_id=<?= $blockInfo['city_id'] ?>&zone=<?= $blockInfo['zone'] ?>&block_number=<?= $blockInfo['block_number'] ?>" class="vw-btn vw-btn-outline"><i class="fas fa-search-dollar"></i> 发布求购</a>
                <?php else: ?>
                    <a href="../../auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="vw-btn vw-btn-primary"><i class="fas fa-sign-in-alt"></i> 登录进行操作</a>
                <?php endif; ?>

                <?php if (!$isOwner && isLoggedIn()): ?>
                    <a href="../purchase_create.php?city_id=<?= $blockInfo['city_id'] ?>&zone=<?= $blockInfo['zone'] ?>&block_number=<?= $blockInfo['block_number'] ?>" class="vw-btn vw-btn-secondary"><i class="fas fa-search-dollar"></i> 我要求购</a>
                <?php endif; ?>
            </div>

            <!-- 城市入口 -->
            <div class="vw-card">
                <h3><i class="fas fa-map"></i> 城市地图</h3>
                <div style="font-size:13px;color:#666;margin-bottom:10px;"><?= htmlspecialchars($blockInfo['city_name'] ?? '') ?> · <?= $blockInfo['zone'] ?>区</div>
                <a href="../city.php?name=<?= urlencode($cityPinyin) ?>&zone=<?= $blockInfo['zone'] ?>" class="vw-btn vw-btn-secondary"><i class="fas fa-map-marked-alt"></i> 查看城市地图</a>
            </div>
        </div>
    </div>
</div>

<?php
// 同城市同区附近区块
$nearbyStmt = $pdo->prepare("SELECT b.id, b.block_number, b.zone FROM blocks b WHERE b.city_id = ? AND b.zone = ? AND b.id != ? AND b.status = 'sold' ORDER BY RAND() LIMIT 8");
$nearbyStmt->execute([$blockInfo['city_id'], $blockInfo['zone'], $blockId]);
$nearbyBlocks = $nearbyStmt->fetchAll();
if ($nearbyBlocks):
?>
<div class="vw-wrap vw-nearby">
    <h3>🏘 同城市 <?= htmlspecialchars($blockInfo['zone']) ?>区 热门区块</h3>
    <div class="vw-nearby-grid">
        <?php foreach ($nearbyBlocks as $nb): ?>
        <a href="view.php?id=<?= $nb['id'] ?>" class="vw-nearby-card">
            <div class="vw-nearby-num"><?= htmlspecialchars($nb['block_number']) ?></div>
            <div class="vw-nearby-price">¥<?= number_format(calculateBlockPriceNew($blockInfo['zone'], $nb['block_number'])) ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
