<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
require_once '../../config/block_prices.php';
checkLogin();

$block = new Block($pdo);
$userId = $_SESSION['user_id'];
$userBlocks = $block->getUserBlocks($userId);
foreach ($userBlocks as &$b) {
    $b['calc_price'] = calculateBlockPriceNew((string)($b['zone'] ?? 'A'), (string)($b['block_number'] ?? '0101'));
}
unset($b);

$totalValue = 0;
foreach ($userBlocks as $b) { $totalValue += $b['calc_price'] ?? 0; }
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:1000px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; }
.summary { background:white; padding:15px 20px; border-radius:8px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.summary .total { font-size:18px; color:#ff6b00; font-weight:bold; }
.empty-state { text-align:center; padding:60px; color:#999; }

/* 城市分组 */
.city-group { margin-bottom:28px; background:#fff; border-radius:10px; padding:18px 18px 8px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #ff6b00; }
.city-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding:0 4px 10px; border-bottom:1px solid #f0f0f0; }
.city-name { font-size:18px; font-weight:700; color:#1a1a2e; }
.city-name a { color:#1a1a2e; text-decoration:none; }
.city-name a:hover { color:#ff6b00; }
.city-stat { font-size:13px; color:#6b7280; }
.city-stat strong { color:#ff6b00; }
.city-block-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.block-card { background:white; border-radius:8px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,0.08); text-decoration:none; color:inherit; transition:all .2s; }
.block-card:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(0,0,0,0.12); }
.block-card h3 { font-size:15px; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.zone-tag { background:#ff6b00; color:white; padding:2px 10px; border-radius:12px; font-size:12px; flex-shrink:0; }
.block-num { color:#6b7280; font-size:13px; }
.price { color:#e74c3c; font-weight:bold; font-size:15px; }
.actions { margin-top:10px; }
.actions a { display:inline-block; padding:5px 12px; border-radius:4px; font-size:12px; text-decoration:none; margin-right:6px; }
.btn-view { background:#3498db; color:white; }
.btn-sell { background:#e74c3c; color:white; }

@media(max-width:768px){ .city-block-grid { grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .city-block-grid { grid-template-columns:1fr; } }
</style>

<div class="container">
    <h1 class="page-title">我的区块</h1>

    <?php if (empty($userBlocks)): ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt" style="font-size:48px;display:block;margin-bottom:15px;"></i>
            <p>还没有任何区块</p>
            <a href="../city.php?name=beijing" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#ff6b00;color:white;border-radius:6px;text-decoration:none;">浏览区块城市</a>
        </div>
    <?php else:
        // 按城市分组
        $grouped = [];
        foreach ($userBlocks as $b) {
            $cityId = $b['city_id'] ?? 0;
            $cityName = $b['city_name'] ?? '未知城市';
            $cityPinyin = $b['city_pinyin'] ?? '';
            if (!isset($grouped[$cityId])) {
                $grouped[$cityId] = [
                    'name'    => $cityName,
                    'pinyin'  => $cityPinyin,
                    'blocks'  => [],
                    'total'   => 0,
                ];
            }
            $grouped[$cityId]['blocks'][] = $b;
            $grouped[$cityId]['total']   += $b['calc_price'] ?? 0;
        }
    ?>
        <div class="summary">
            <span>共 <?= count($userBlocks) ?> 个区块，覆盖 <?= count($grouped) ?> 个城市</span>
            <span class="total">总价值 ¥<?= number_format($totalValue, 2) ?></span>
        </div>

        <?php foreach ($grouped as $cityId => $city): ?>
        <section class="city-group">
            <div class="city-header">
                <div class="city-name">
                    <?php if ($city['pinyin']): ?>
                        <a href="../city.php?name=<?= urlencode($city['pinyin']) ?>"><?= htmlspecialchars($city['name']) ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($city['name']) ?>
                    <?php endif; ?>
                </div>
                <div class="city-stat"><?= count($city['blocks']) ?> 个区块，合计 <strong>¥<?= number_format($city['total'], 2) ?></strong></div>
            </div>
            <div class="city-block-grid">
                <?php foreach ($city['blocks'] as $b): ?>
                    <a href="../block/view.php?id=<?= $b['id'] ?>" class="block-card">
                        <h3>
                            <span class="zone-tag"><?= $b['zone'] ?>区</span>
                            <span class="block-num">#<?= $b['block_number'] ?></span>
                        </h3>
                        <div class="price">¥<?= number_format($b['calc_price'] ?? 0, 2) ?></div>
                        <div class="actions">
                            <span class="btn-view">查看详情</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
