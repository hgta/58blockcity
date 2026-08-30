<?php
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../classes/Block.php';
require_once '../classes/City.php';
require_once '../includes/auth.php';
checkLogin();

$auction = new Auction($pdo);
$block = new Block($pdo);
$city = new City($pdo);
$userId = $_SESSION['user_id'];

$msg = '';
$err = '';

// 编辑模式：?edit=<id> 仅「未开始」且归属本人的拍卖可编辑
$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editAuction = null;
if ($editId > 0) {
    $editAuction = $auction->getAuctionById($editId);
    if (!$editAuction || intval($editAuction['seller_id']) !== $userId) {
        $err = '无权编辑该拍卖';
        $editAuction = null;
    } elseif ($editAuction['status'] !== 'pending') {
        $err = '仅「未开始」的拍卖可编辑';
        $editAuction = null;
    }
}

// 获取用户拥有的区块
$myBlocks = $block->getUserBlocks($userId);

// 获取用户拥有的 NFT 持有记录（含 nft_city_user.id 作为拍卖 item_id）
$myNfts = [];
$nstmt = $pdo->prepare("
    SELECT ncu.id AS ncu_id, ncu.nft_id, ncu.city_id, c.name AS city_name, n.code, n.base_image
    FROM nft_city_user ncu
    JOIN nft_avatars n ON ncu.nft_id = n.id
    JOIN cities c ON ncu.city_id = c.id
    WHERE ncu.user_id = ? AND ncu.is_current = 1
    ORDER BY ncu.created_at DESC");
$nstmt->execute([$userId]);
$myNfts = $nstmt->fetchAll(PDO::FETCH_ASSOC);

// 所有城市（用于接受支付城市选择）
$allCities = $city->getAllCities();

// 表单预填值（编辑模式取自拍卖记录；提交失败时保留用户输入）
$fv = [
    'start_price'   => $editAuction ? $editAuction['start_price'] : '',
    'reserve_price' => ($editAuction && $editAuction['reserve_price'] !== null) ? $editAuction['reserve_price'] : '',
    'bid_increment' => $editAuction ? $editAuction['bid_increment'] : '1.00',
    'start_time'    => $editAuction ? date('Y-m-d\TH:i', strtotime($editAuction['start_time'])) : '',
    'end_time'      => $editAuction ? date('Y-m-d\TH:i', strtotime($editAuction['end_time'])) : '',
    'currency'      => $editAuction ? $editAuction['currency'] : 'cny',
    'accept_cities' => $editAuction ? (json_decode($editAuction['accept_cities'] ?? '[]', true) ?: []) : [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemType = $_POST['item_type'] ?? '';
    $itemId = intval($_POST['item_id'] ?? 0);
    $data = [
        'start_price'   => $_POST['start_price'] ?? 0,
        'reserve_price' => $_POST['reserve_price'] ?? '',
        'bid_increment' => $_POST['bid_increment'] ?? 0,
        'start_time'    => $_POST['start_time'] ?? '',
        'end_time'      => $_POST['end_time'] ?? '',
        'currency'      => $_POST['currency'] ?? 'cny',
        'accept_cities' => $_POST['accept_cities'] ?? [],
    ];
    // 保留用户输入
    $fv = [
        'start_price'   => $data['start_price'],
        'reserve_price' => $data['reserve_price'],
        'bid_increment' => $data['bid_increment'],
        'start_time'    => date('Y-m-d\TH:i', strtotime($data['start_time'])),
        'end_time'      => date('Y-m-d\TH:i', strtotime($data['end_time'])),
        'currency'      => $data['currency'],
        'accept_cities' => is_array($data['accept_cities']) ? $data['accept_cities'] : [],
    ];

    if ($editId > 0) {
        $result = $auction->updateAuction($editId, $userId, $data);
        if ($result['ok']) {
            header('Location: view.php?id=' . $editId);
            exit;
        }
        $err = $result['msg'];
    } else {
        $result = $auction->createAuction($userId, $itemType, $itemId, $data);
        if (is_int($result)) {
            header('Location: view.php?id=' . $result);
            exit;
        }
        $err = $result;
    }
}

$site_config['title'] = '发起拍卖 - 58拍卖';
require_once 'includes/header.php';
?>
<style>
.create-wrap { max-width: 720px; margin: 24px auto; padding: 0 15px; }
.create-card { background: #fff; border-radius: 12px; padding: 26px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.form-group { margin: 16px 0; }
.form-label { display: block; font-size: 14px; color: #555; margin-bottom: 6px; }
.form-input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.form-select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: #fff; }
.btn-primary { background: #ff6b00; color: #fff; border: none; border-radius: 8px; padding: 12px 24px; font-size: 15px; cursor: pointer; font-weight: bold; }
.btn-primary:hover { background: #e05d00; }
.alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
.alert-err { background: #f8d7da; color: #721c24; }
.item-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; max-height: 300px; overflow-y: auto; }
.item-opt { border: 2px solid #eee; border-radius: 8px; padding: 8px; cursor: pointer; text-align: center; transition: border-color .15s; }
.item-opt.active { border-color: #ff6b00; }
.item-opt img { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 4px; }
.item-opt .lbl { font-size: 12px; color: #555; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hidden { display: none; }
.guide-empty { text-align: center; padding: 30px 16px; background: #fff9f5; border: 1px dashed #ffd4c4; border-radius: 10px; color: #999; }
.guide-empty i { font-size: 36px; color: #ffb380; margin-bottom: 10px; }
.guide-empty div { font-size: 14px; margin-bottom: 12px; color: #666; }
.guide-link { display: inline-block; padding: 8px 18px; background: #ff6b00; color: #fff; border-radius: 20px; font-size: 13px; text-decoration: none; }
.guide-link:hover { background: #e05d00; }
</style>

<div class="create-wrap">
    <h1 style="font-size: 24px; margin: 0 0 16px;"><?= $editId > 0 ? '✏️ 编辑拍卖' : '➕ 发起拍卖' ?></h1>
    <?php if ($editId > 0): ?><div style="font-size:13px;color:#999;margin-bottom:12px;">仅「未开始」的拍卖可编辑，拍卖品不可更换。</div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="create-card">
        <form method="POST" id="auction-form">
            <div class="form-group">
                <label class="form-label">拍卖品类型</label>
                <div style="display:flex;gap:10px;">
                    <label><input type="radio" name="item_type" value="block" checked onchange="switchItemType('block')"> 区块</label>
                    <label><input type="radio" name="item_type" value="nft" onchange="switchItemType('nft')"> NFT头像</label>
                </div>
            </div>

            <?php if ($editAuction): ?>
            <!-- 编辑模式：物品锁定不可更换 -->
            <div class="form-group">
                <label class="form-label">拍卖品（不可更换）</label>
                <div style="background:#f5f5f5;border-radius:8px;padding:10px 14px;font-size:14px;color:#555;">
                    <i class="fas fa-lock"></i> <?= htmlspecialchars($editAuction['item_title'] ?? ('拍卖 #' . $editAuction['id'])) ?>
                </div>
                <input type="hidden" name="item_type" value="<?= htmlspecialchars($editAuction['item_type']) ?>">
                <input type="hidden" name="item_id" id="item_id" value="<?= intval($editAuction['item_id']) ?>">
            </div>
            <?php else: ?>
            <!-- 区块选择 -->
            <div class="form-group" id="block-select">
                <label class="form-label">选择区块</label>
                <?php if (empty($myBlocks)): ?>
                    <div class="guide-empty">
                        <i class="fas fa-map-marked-alt"></i>
                        <div>您还没有可拍卖的区块</div>
                        <a href="https://block.58.tl/city.php" target="_blank" class="guide-link">前往区块市场认领区块 →</a>
                    </div>
                <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($myBlocks as $b): ?>
                    <div class="item-opt" data-type="block" data-id="<?= $b['id'] ?>" onclick="selectItem(this, '<?= $b['id'] ?>')">
                        <span class="lbl"><?= htmlspecialchars($b['city_name']) ?> <?= $b['zone'] ?>区 #<?= $b['block_number'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- NFT 选择 -->
            <div class="form-group hidden" id="nft-select">
                <label class="form-label">选择 NFT 头像</label>
                <?php if (empty($myNfts)): ?>
                    <div class="guide-empty">
                        <i class="fas fa-image"></i>
                        <div>您还没有可拍卖的 NFT 头像</div>
                        <a href="https://nft.58.tl/nft/claim_list.php" target="_blank" class="guide-link">前往头像市场认领 NFT →</a>
                    </div>
                <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($myNfts as $n): ?>
                    <div class="item-opt" data-type="nft" data-id="<?= $n['ncu_id'] ?>" onclick="selectItem(this, '<?= $n['ncu_id'] ?>')">
                        <?php if ($n['base_image']): ?><img src="https://nft.58.tl/avatar/<?= htmlspecialchars($n['base_image']) ?>" alt="" onerror="this.style.display='none'"><?php endif; ?>
                        <span class="lbl">#<?= htmlspecialchars($n['code']) ?>（<?= htmlspecialchars($n['city_name']) ?>）</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <input type="hidden" name="item_id" id="item_id" value="">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">起拍价</label>
                <input type="number" name="start_price" class="form-input" step="0.01" min="0.01" required placeholder="如 100" value="<?= htmlspecialchars($fv['start_price']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">底价（选填，低于底价流拍）</label>
                <input type="number" name="reserve_price" class="form-input" step="0.01" min="0" placeholder="可不填" value="<?= htmlspecialchars($fv['reserve_price']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">加价幅度</label>
                <input type="number" name="bid_increment" class="form-input" step="0.01" min="0.01" value="<?= htmlspecialchars($fv['bid_increment']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">开始时间</label>
                <input type="datetime-local" name="start_time" class="form-input" required value="<?= htmlspecialchars($fv['start_time']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">截止时间</label>
                <input type="datetime-local" name="end_time" class="form-input" required value="<?= htmlspecialchars($fv['end_time']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">计价货币</label>
                <div style="display:flex;gap:10px;">
                    <label><input type="radio" name="currency" value="popularity" <?= $fv['currency'] === 'popularity' ? 'checked' : '' ?> onchange="toggleCities(true)"> 人气值 Ⓟ</label>
                    <label><input type="radio" name="currency" value="cny" <?= $fv['currency'] === 'cny' ? 'checked' : '' ?> onchange="toggleCities(false)"> 人民币 ¥</label>
                </div>
            </div>

            <div class="form-group <?= $fv['currency'] === 'popularity' ? '' : 'hidden' ?>" id="accept-cities-group">
                <label class="form-label">接受哪些城市的人气值支付（不选则接受全部）</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($allCities as $c): $accChecked = in_array($c['id'], $fv['accept_cities']); ?>
                    <label style="font-size:13px;"><input type="checkbox" name="accept_cities[]" value="<?= $c['id'] ?>" <?= $accChecked ? 'checked' : '' ?>> <?= htmlspecialchars($c['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-primary" onclick="return validateSubmit()"><?= $editId > 0 ? '保存修改' : '发布拍卖' ?></button>
        </form>
    </div>
</div>

<script>
function switchItemType(type) {
    document.getElementById('block-select').classList.toggle('hidden', type !== 'block');
    document.getElementById('nft-select').classList.toggle('hidden', type !== 'nft');
    // 清空选择
    document.getElementById('item_id').value = '';
    document.querySelectorAll('.item-opt').forEach(function(el) { el.classList.remove('active'); });
}
function selectItem(el, id) {
    var type = el.dataset.type;
    document.querySelectorAll('.item-opt[data-type="'+type+'"]').forEach(function(o){ o.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('item_id').value = id;
}
function toggleCities(show) {
    document.getElementById('accept-cities-group').classList.toggle('hidden', !show);
}
function validateSubmit() {
    if (!document.getElementById('item_id').value) {
        alert('请选择一个拍卖品');
        return false;
    }
    return true;
}
</script>

<?php require_once 'includes/footer.php'; ?>
