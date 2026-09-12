<?php
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../classes/Block.php';
require_once '../classes/City.php';
require_once '../includes/auth.php';
require_once '../includes/lot_helpers.php';
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
$selectedItemId = $editAuction ? intval($editAuction['item_id']) : 0;

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
        'start_time'    => !empty($data['start_time']) ? date('Y-m-d\TH:i', strtotime($data['start_time'])) : '',
        'end_time'      => !empty($data['end_time']) ? date('Y-m-d\TH:i', strtotime($data['end_time'])) : '',
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

$editItemType = $editAuction ? $editAuction['item_type'] : 'block';

$site_config['title'] = ($editId > 0 ? '编辑拍卖' : '发起拍卖') . ' - 58拍卖';
require_once 'includes/header.php';
?>

<div class="ac-wrap" style="max-width:760px;">
    <h1 style="font-size:22px;font-weight:800;margin:6px 0 16px;">
        <i class="fas fa-gavel" style="color:var(--brand);"></i> <?= $editId > 0 ? '编辑拍卖' : '发起拍卖' ?>
    </h1>
    <p class="ac-hint" style="margin:0 0 16px;">
        <?= $editId > 0 ? '仅「未开始」的拍卖可编辑，拍卖品不可更换。' : '发布后拍品将进入竞价大厅，秒级倒计时开拍，价高者得。' ?>
    </p>

    <?php if ($err): ?><div class="ac-alert ac-alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="ac-alert ac-alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="ac-form">
        <form method="POST" id="auction-form">
            <div class="ac-field">
                <label>拍卖品类型</label>
                <div class="ac-radio-row">
                    <label><input type="radio" name="item_type" value="block" <?= $editItemType === 'block' ? 'checked' : '' ?> onchange="switchItemType('block')"> 区块</label>
                    <label><input type="radio" name="item_type" value="nft" <?= $editItemType === 'nft' ? 'checked' : '' ?> onchange="switchItemType('nft')"> NFT 头像</label>
                </div>
            </div>

            <?php if ($editAuction): ?>
            <!-- 编辑模式：物品锁定不可更换 -->
            <div class="ac-field">
                <label>拍卖品（不可更换）</label>
                <div style="background:var(--surface-2);border:1px solid var(--line);border-radius:10px;padding:11px 14px;font-size:14px;">
                    <i class="fas fa-lock" style="color:var(--brand);"></i> <?= htmlspecialchars($editAuction['item_title'] ?? ('拍卖 #' . $editAuction['id'])) ?>
                </div>
                <input type="hidden" name="item_id" id="item_id" value="<?= intval($editAuction['item_id']) ?>">
            </div>
            <?php else: ?>
            <!-- 区块选择 -->
            <div class="ac-field <?= $editItemType === 'block' ? '' : 'ac-hidden' ?>" id="block-select">
                <label>选择区块</label>
                <?php if (empty($myBlocks)): ?>
                    <div class="ac-guide-empty">
                        <i class="fas fa-map-marked-alt"></i>
                        <div class="t">您还没有可拍卖的区块</div>
                        <a href="https://block.58.tl/city.php" target="_blank" class="ac-btn ac-btn-primary">前往区块市场认领区块 →</a>
                    </div>
                <?php else: ?>
                <div class="ac-item-grid">
                    <?php foreach ($myBlocks as $b): ?>
                    <div class="ac-item-opt <?= $selectedItemId == $b['id'] && $editItemType === 'block' ? 'active' : '' ?>" data-type="block" data-id="<?= $b['id'] ?>" onclick="selectItem(this, '<?= $b['id'] ?>')">
                        <span class="lbl"><?= htmlspecialchars($b['city_name']) ?> <?= $b['zone'] ?>区 #<?= $b['block_number'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- NFT 选择 -->
            <div class="ac-field <?= $editItemType === 'nft' ? '' : 'ac-hidden' ?>" id="nft-select">
                <label>选择 NFT 头像</label>
                <?php if (empty($myNfts)): ?>
                    <div class="ac-guide-empty">
                        <i class="fas fa-image"></i>
                        <div class="t">您还没有可拍卖的 NFT 头像</div>
                        <a href="https://nft.58.tl/nft/claim_list.php" target="_blank" class="ac-btn ac-btn-primary">前往头像市场认领 NFT →</a>
                    </div>
                <?php else: ?>
                <div class="ac-item-grid">
                    <?php foreach ($myNfts as $n): ?>
                    <div class="ac-item-opt" data-type="nft" data-id="<?= $n['ncu_id'] ?>" onclick="selectItem(this, '<?= $n['ncu_id'] ?>')">
                        <?php if ($n['base_image']): ?><img src="https://nft.58.tl/avatar/<?= htmlspecialchars($n['base_image']) ?>" alt="" onerror="this.style.display='none'"><?php endif; ?>
                        <span class="lbl">#<?= htmlspecialchars($n['code']) ?>（<?= htmlspecialchars($n['city_name']) ?>）</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <input type="hidden" name="item_id" id="item_id" value="">
            <?php endif; ?>

            <div class="ac-row2">
                <div class="ac-field">
                    <label>起拍价</label>
                    <input type="number" name="start_price" step="0.01" min="0.01" required placeholder="如 100" value="<?= htmlspecialchars($fv['start_price']) ?>">
                </div>
                <div class="ac-field">
                    <label>底价（选填，低于底价流拍）</label>
                    <input type="number" name="reserve_price" step="0.01" min="0" placeholder="可不填" value="<?= htmlspecialchars($fv['reserve_price']) ?>">
                </div>
            </div>

            <div class="ac-row2">
                <div class="ac-field">
                    <label>加价幅度（快捷加价按此分档）</label>
                    <input type="number" name="bid_increment" step="0.01" min="0.01" required value="<?= htmlspecialchars($fv['bid_increment']) ?>">
                </div>
                <div class="ac-field">
                    <label>计价货币</label>
                    <div class="ac-radio-row" style="padding-top:9px;">
                        <label><input type="radio" name="currency" value="popularity" <?= $fv['currency'] === 'popularity' ? 'checked' : '' ?> onchange="toggleCities(true)"> 人气值 Ⓟ</label>
                        <label><input type="radio" name="currency" value="cny" <?= $fv['currency'] === 'cny' ? 'checked' : '' ?> onchange="toggleCities(false)"> 人民币 ¥</label>
                    </div>
                </div>
            </div>

            <div class="ac-row2">
                <div class="ac-field">
                    <label>开始时间</label>
                    <input type="datetime-local" name="start_time" required value="<?= htmlspecialchars($fv['start_time']) ?>">
                </div>
                <div class="ac-field">
                    <label>截止时间</label>
                    <input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($fv['end_time']) ?>">
                </div>
            </div>

            <div class="ac-field <?= $fv['currency'] === 'popularity' ? '' : 'ac-hidden' ?>" id="accept-cities-group">
                <label>接受哪些城市的人气值支付（不选则接受全部）</label>
                <div class="ac-check-grid">
                    <?php foreach ($allCities as $c): $accChecked = in_array($c['id'], $fv['accept_cities']); ?>
                    <label><input type="checkbox" name="accept_cities[]" value="<?= $c['id'] ?>" <?= $accChecked ? 'checked' : '' ?>> <?= htmlspecialchars($c['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ac-section" style="margin-bottom:18px;background:var(--surface-2);">
                <h3><i class="fas fa-shield-halved" style="color:var(--brand);"></i> 防狙击自动延时（平台默认）</h3>
                <p class="ac-hint" style="margin:0;">
                    落槌前 2 分钟内若有新出价，结束时间将自动顺延 2 分钟（单场最多 10 次，累计不超过 30 分钟），
                    由平台统一执行，卖家无需设置也不能关闭。
                </p>
            </div>

            <button type="submit" class="ac-btn ac-btn-primary ac-btn-block" onclick="return validateSubmit()">
                <i class="fas fa-gavel"></i> <?= $editId > 0 ? '保存修改' : '发布拍卖' ?>
            </button>
        </form>
    </div>
</div>

<script>
function switchItemType(type) {
    document.getElementById('block-select').classList.toggle('ac-hidden', type !== 'block');
    document.getElementById('nft-select').classList.toggle('ac-hidden', type !== 'nft');
    document.getElementById('item_id').value = '';
    document.querySelectorAll('.ac-item-opt').forEach(function (el) { el.classList.remove('active'); });
}
function selectItem(el, id) {
    var type = el.dataset.type;
    document.querySelectorAll('.ac-item-opt[data-type="' + type + '"]').forEach(function (o) { o.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('item_id').value = id;
}
function toggleCities(show) {
    document.getElementById('accept-cities-group').classList.toggle('ac-hidden', !show);
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
