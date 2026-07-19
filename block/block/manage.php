<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

require_once '../../classes/Block.php';
require_once '../../classes/BlockListing.php';
require_once '../../classes/UserPopularity.php';

$block = new Block($pdo);
$listing = new BlockListing($pdo);
$userPop = new UserPopularity($pdo);

$userId = $_SESSION['user_id'];

// 解析模式：合并块 merged_id / 单块 id
$mergedId = intval($_GET['merged_id'] ?? 0);
$blockId  = intval($_GET['id'] ?? 0);

$isMerged = $mergedId > 0;

if ($isMerged) {
    $mstmt = $pdo->prepare("SELECT * FROM merged_blocks WHERE id = ?");
    $mstmt->execute([$mergedId]);
    $target = $mstmt->fetch(PDO::FETCH_ASSOC);
    if (!$target || $target['owner_id'] != $userId) {
        header("Location: ../user/dashboard.php");
        exit();
    }
    $blockIds = $block->getMergedBlockIds($mergedId);
    $repId = $blockIds[0] ?? 0;
    $blockInfo = $block->getBlockById($repId);
    $title = $target['merge_size'] . ' 合并区块（编号 ' . $target['merged_blocks'] . '）';
    $cityId = $target['city_id'];
} else {
    $blockInfo = $block->getBlockById($blockId);
    if (!$blockInfo || $blockInfo['owner_id'] != $userId) {
        header("Location: ../user/dashboard.php");
        exit();
    }
    $blockIds = [$blockId];
    $repId = $blockId;
    $title = $blockInfo['city_name'] . ' ' . $blockInfo['zone'] . '区 #' . $blockInfo['block_number'];
    $cityId = $blockInfo['city_id'];
}

$msg = '';
$err = '';

// 当前挂牌（若有）
$current = null;
if ($isMerged) {
    $cstmt = $pdo->prepare("SELECT * FROM block_listings WHERE merged_block_id = ? AND status IN ('listed','pending') ORDER BY id DESC LIMIT 1");
    $cstmt->execute([$mergedId]);
} else {
    $cstmt = $pdo->prepare("SELECT * FROM block_listings WHERE block_id = ? AND merged_block_id IS NULL AND status IN ('listed','pending') ORDER BY id DESC LIMIT 1");
    $cstmt->execute([$blockId]);
}
$current = $cstmt->fetch(PDO::FETCH_ASSOC);

// 处理表单
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action_skin'])) {
            // 保存展示配置（皮肤）
            $displayType = $_POST['display_type'] ?? 'none';
            if (!in_array($displayType, ['none', 'image', 'text'])) $displayType = 'none';
            $displayText = trim($_POST['display_text'] ?? '');
            $displayColor = $_POST['display_color'] ?? null;
            if ($displayColor !== null && !in_array($displayColor, ['red', 'green', 'blue'])) $displayColor = null;
            $displayImage = null;

            if ($displayType === 'image' && isset($_FILES['display_image']) && $_FILES['display_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($_FILES['display_image']['type'], $allowed)) {
                    throw new Exception('仅支持 jpg/png/gif/webp 图片');
                }
                $ext = pathinfo($_FILES['display_image']['name'], PATHINFO_EXTENSION);
                $fname = 'block_' . $repId . '_' . time() . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $fname;
                if (!move_uploaded_file($_FILES['display_image']['tmp_name'], $dest)) {
                    throw new Exception('图片上传失败');
                }
                $displayImage = 'block/uploads/' . $fname;
            } elseif ($displayType === 'image') {
                // 未上传新图时保留原图
                $displayImage = $blockInfo['display_image'];
            }

            if ($block->saveBlockSkin($blockIds, $userId, $displayType, $displayImage, $displayText, $displayColor)) {
                $msg = '展示配置已保存';
                // 刷新
                $blockInfo = $block->getBlockById($repId);
            } else {
                $err = '保存失败，请确认区块归属';
            }
        } elseif (isset($_POST['action_list'])) {
            // 上架挂牌
            if ($current) {
                throw new Exception('该区块已在挂牌中，请先取消');
            }
            $price = floatval($_POST['price'] ?? 0);
            $currency = $_POST['currency'] ?? 'cny';
            $phone = trim($_POST['contact_phone'] ?? '');
            $wechat = trim($_POST['contact_wechat'] ?? '');
            if ($price <= 0) throw new Exception('请填写有效价格');
            $lid = $listing->createListing([
                'city_id' => $cityId,
                'seller_id' => $userId,
                'price' => $price,
                'currency' => $currency,
                'block_id' => $isMerged ? null : $blockId,
                'merged_block_id' => $isMerged ? $mergedId : null,
                'contact_phone' => $phone,
                'contact_wechat' => $wechat,
            ]);
            if ($lid) {
                $msg = '已上架，买家可前往出售列表查看';
                // 刷新 current
                if ($isMerged) {
                    $cstmt = $pdo->prepare("SELECT * FROM block_listings WHERE merged_block_id = ? AND status IN ('listed','pending') ORDER BY id DESC LIMIT 1");
                    $cstmt->execute([$mergedId]);
                } else {
                    $cstmt = $pdo->prepare("SELECT * FROM block_listings WHERE block_id = ? AND merged_block_id IS NULL AND status IN ('listed','pending') ORDER BY id DESC LIMIT 1");
                    $cstmt->execute([$blockId]);
                }
                $current = $cstmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $err = '上架失败，请检查输入';
            }
        } elseif (isset($_POST['action_cancel']) && $current) {
            if ($listing->cancelListing($current['id'], $userId)) {
                $msg = '已取消挂牌';
                $current = null;
            } else {
                $err = '取消失败';
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

// 买家在该城市的人气值（仅供提示）
$buyerPop = $userPop->getUserPopularity($userId, $blockInfo['city_name'] ?? '');

$skinColors = [
    'red'   => '#ff6060',
    'green' => '#35cc2d',
    'blue'  => '#337be6',
];
?>
<?php require_once '../includes/header.php'; ?>

<style>
.manage-wrap { max-width:720px; margin:30px auto; padding:0 15px; }
.manage-card { background:#fff; border-radius:12px; padding:26px; box-shadow:0 4px 20px rgba(0,0,0,.08); margin-bottom:22px; }
.manage-card h2 { font-size:19px; margin-bottom:18px; color:#222; }
.manage-sub { color:#666; font-size:13px; margin-bottom:16px; }
.info-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f2f2f2; font-size:14px; }
.info-label { color:#888; }
.form-group { margin:16px 0; }
.form-label { display:block; font-size:14px; color:#555; margin-bottom:6px; }
.form-input { width:100%; padding:11px 12px; border:1px solid #ddd; border-radius:8px; font-size:15px; }
.radio-row { display:flex; gap:14px; flex-wrap:wrap; margin:8px 0; }
.radio-item { display:flex; align-items:center; gap:6px; font-size:14px; }
.color-swatch { display:inline-block; width:18px; height:18px; border-radius:4px; vertical-align:middle; margin-right:4px; }
.skin-img-prev { max-width:120px; max-height:120px; border-radius:8px; margin-top:8px; border:1px solid #eee; }
.btn-primary { background:#ff6b00; color:#fff; border:none; border-radius:8px; padding:12px 18px; font-size:15px; cursor:pointer; font-weight:bold; }
.btn-primary:hover { background:#e05d00; }
.btn-ghost { background:#f2f2f2; color:#555; border:none; border-radius:8px; padding:11px 16px; cursor:pointer; }
.btn-danger { background:#e74c3c; color:#fff; border:none; border-radius:8px; padding:11px 16px; cursor:pointer; }
.alert { padding:12px 14px; border-radius:8px; margin-bottom:14px; font-size:14px; }
.alert-ok { background:#d4edda; color:#155724; }
.alert-err { background:#f8d7da; color:#721c24; }
.status-tag { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:bold; }
.st-listed { background:#e8f4ff; color:#337be6; }
.st-pending { background:#fff3e0; color:#ff6b00; }
</style>

<div class="manage-wrap">
    <div class="manage-card">
        <h2><i class="fas fa-cog"></i> 区块管理</h2>
        <div class="manage-sub"><?= htmlspecialchars($title) ?></div>
        <div class="info-row"><span class="info-label">城市</span><span><?= htmlspecialchars($blockInfo['city_name'] ?? '') ?></span></div>
        <div class="info-row"><span class="info-label">当前价值</span><span>¥<?= number_format($blockInfo['price'] ?? 0, 2) ?></span></div>
        <div class="info-row"><span class="info-label">我的该城人气值</span><span><?= $buyerPop ?> Ⓟ</span></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- 展示配置 -->
    <div class="manage-card">
        <h2><i class="fas fa-paint-brush"></i> 展示配置（区块皮肤）</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action_skin" value="1">
            <div class="form-group">
                <label class="form-label">皮肤类型</label>
                <div class="radio-row">
                    <label class="radio-item"><input type="radio" name="display_type" value="none" <?= ($blockInfo['display_type'] ?? 'none') === 'none' ? 'checked' : '' ?>> 默认</label>
                    <label class="radio-item"><input type="radio" name="display_type" value="image" <?= ($blockInfo['display_type'] ?? '') === 'image' ? 'checked' : '' ?>> 图片</label>
                    <label class="radio-item"><input type="radio" name="display_type" value="text" <?= ($blockInfo['display_type'] ?? '') === 'text' ? 'checked' : '' ?>> 文字</label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">图片（图片模式）</label>
                <input type="file" name="display_image" accept="image/*" class="form-input">
                <?php if (!empty($blockInfo['display_image'])): ?>
                    <img class="skin-img-prev" src="/<?= htmlspecialchars($blockInfo['display_image']) ?>" alt="当前皮肤">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">文字内容（文字模式，≤50字）</label>
                <input type="text" name="display_text" class="form-input" maxlength="50" value="<?= htmlspecialchars($blockInfo['display_text'] ?? '') ?>" placeholder="如：我的咖啡馆">
            </div>
            <div class="form-group">
                <label class="form-label">文字背景色（文字模式）</label>
                <div class="radio-row">
                    <label class="radio-item"><input type="radio" name="display_color" value="red" <?= ($blockInfo['display_color'] ?? '') === 'red' ? 'checked' : '' ?>><span class="color-swatch" style="background:#ff6060"></span>红</label>
                    <label class="radio-item"><input type="radio" name="display_color" value="green" <?= ($blockInfo['display_color'] ?? '') === 'green' ? 'checked' : '' ?>><span class="color-swatch" style="background:#35cc2d"></span>绿</label>
                    <label class="radio-item"><input type="radio" name="display_color" value="blue" <?= ($blockInfo['display_color'] ?? '') === 'blue' ? 'checked' : '' ?>><span class="color-swatch" style="background:#337be6"></span>蓝</label>
                </div>
            </div>
            <button type="submit" class="btn-primary">保存展示配置</button>
        </form>
    </div>

    <!-- 挂牌上架 -->
    <div class="manage-card">
        <h2><i class="fas fa-tag"></i> 挂牌出售</h2>
        <?php if ($current): ?>
            <div class="info-row">
                <span class="info-label">当前状态</span>
                <span class="status-tag <?= $current['status'] === 'listed' ? 'st-listed' : 'st-pending' ?>">
                    <?= $current['status'] === 'listed' ? '售卖中' : '有买家下单(待确认)' ?>
                </span>
            </div>
            <div class="info-row"><span class="info-label">价格</span><span><?= $current['currency'] === 'popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($current['price'], 2) ?></span></div>
            <div class="info-row"><span class="info-label">货币</span><span><?= $current['currency'] === 'popularity' ? '人气值' : '人民币' ?></span></div>
            <div style="margin-top:16px;">
                <form method="POST" onsubmit="return confirm('确认取消该挂牌？');">
                    <input type="hidden" name="action_cancel" value="1">
                    <button type="submit" class="btn-danger">取消挂牌</button>
                </form>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action_list" value="1">
                <div class="form-group">
                    <label class="form-label">售价</label>
                    <input type="number" name="price" class="form-input" step="0.01" min="1" required placeholder="请输入价格">
                </div>
                <div class="form-group">
                    <label class="form-label">计价货币</label>
                    <div class="radio-row">
                        <label class="radio-item"><input type="radio" name="currency" value="popularity"> 人气值 Ⓟ（同商城：买家向您的收款区块转账后您确认）</label>
                        <label class="radio-item"><input type="radio" name="currency" value="cny" checked> 人民币 ¥（意向交易，确认收款后完成）</label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">联系电话（选填）</label>
                    <input type="text" name="contact_phone" class="form-input" placeholder="方便买家联系">
                </div>
                <div class="form-group">
                    <label class="form-label">微信号（选填）</label>
                    <input type="text" name="contact_wechat" class="form-input" placeholder="方便买家联系">
                </div>
                <button type="submit" class="btn-primary">上架出售</button>
            </form>
        <?php endif; ?>
    </div>

    <div style="text-align:center;margin:10px 0 40px;">
        <a href="view.php?id=<?= $repId ?>" style="color:#3498db;">← 返回区块详情</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
