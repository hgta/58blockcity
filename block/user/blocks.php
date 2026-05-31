<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
checkLogin();

$block = new Block($pdo);
$userId = $_SESSION['user_id'];
$userBlocks = $block->getUserBlocks($userId);

$totalValue = 0;
foreach ($userBlocks as $b) { $totalValue += $b['price'] ?? 0; }
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:1000px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; }
.block-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:15px; }
.block-card { background:white; border-radius:8px; padding:15px; box-shadow:0 2px 8px rgba(0,0,0,0.08); text-decoration:none; color:inherit; }
.block-card:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(0,0,0,0.12); }
.block-card h3 { font-size:16px; margin-bottom:8px; }
.zone-tag { background:#ff6b00; color:white; padding:2px 10px; border-radius:12px; font-size:12px; margin-right:6px; }
.price { color:#e74c3c; font-weight:bold; }
.actions { margin-top:10px; }
.actions a { display:inline-block; padding:5px 12px; border-radius:4px; font-size:12px; text-decoration:none; margin-right:6px; }
.btn-view { background:#3498db; color:white; }
.btn-sell { background:#e74c3c; color:white; }
.summary { background:white; padding:15px 20px; border-radius:8px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.summary .total { font-size:18px; color:#ff6b00; font-weight:bold; }
.empty-state { text-align:center; padding:60px; color:#999; }
@media(max-width:768px){ .block-grid { grid-template-columns:1fr; } }
</style>

<div class="container">
    <h1 class="page-title">我的区块</h1>
    
    <?php if (empty($userBlocks)): ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt" style="font-size:48px;display:block;margin-bottom:15px;"></i>
            <p>还没有任何区块</p>
            <a href="../city.php?name=beijing" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#ff6b00;color:white;border-radius:6px;text-decoration:none;">浏览区块城市</a>
        </div>
    <?php else: ?>
        <div class="summary">
            <span>共 <?= count($userBlocks) ?> 个区块</span>
            <span class="total">总价值 ¥<?= number_format($totalValue, 2) ?></span>
        </div>
        
        <div class="block-grid">
            <?php foreach ($userBlocks as $b): ?>
                <a href="../block/view.php?id=<?= $b['id'] ?>" class="block-card">
                    <h3>
                        <span class="zone-tag"><?= $b['zone'] ?>区</span>
                        <?= htmlspecialchars($b['city_name']) ?> #<?= $b['block_number'] ?>
                    </h3>
                    <div class="price">¥<?= number_format($b['price'] ?? 0, 2) ?></div>
                    <div class="actions">
                        <span class="btn-view">查看详情</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
