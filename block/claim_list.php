<?php
require_once '../config/database.php';
require_once 'includes/auth.php';

// Recently claimed blocks (status='sold', ordered by created_at)
$stmt = $pdo->query("SELECT b.*, c.name as city_name, u.username 
    FROM blocks b 
    JOIN cities c ON b.city_id = c.id 
    LEFT JOIN users u ON b.owner_id = u.id 
    WHERE b.status = 'sold' 
    ORDER BY b.created_at DESC LIMIT 30");
$blocks = $stmt->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<style>
.container { max-width:1000px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; color:#333; }
.block-list { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
.block-card { background:white; border-radius:8px; padding:12px 15px; box-shadow:0 1px 5px rgba(0,0,0,0.06); text-decoration:none; color:inherit; display:flex; justify-content:space-between; align-items:center; }
.block-card:hover { box-shadow:0 3px 12px rgba(0,0,0,0.1); }
.block-card .info { flex:1; }
.block-card .city { color:#666; font-size:12px; }
.block-card .owner { color:#999; font-size:12px; }
.zone-tag { display:inline-block; background:#e8f5e8; color:#2e7d32; padding:2px 10px; border-radius:12px; font-size:12px; margin-right:6px; }
.empty-state { text-align:center; padding:60px; color:#999; }
@media(max-width:768px){ .block-list { grid-template-columns:1fr; } }
</style>

<div class="container">
    <h1 class="page-title"><i class="fas fa-hand-holding-heart"></i> 最近认领</h1>
    
    <?php if (empty($blocks)): ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt"></i>
            <p>暂无认领记录</p>
        </div>
    <?php else: ?>
        <div class="block-list">
            <?php foreach ($blocks as $b): ?>
                <a href="block/view.php?id=<?= $b['id'] ?>" class="block-card">
                    <div class="info">
                        <span class="zone-tag"><?= $b['zone'] ?>区</span>
                        <?= htmlspecialchars($b['city_name']) ?> #<?= $b['block_number'] ?>
                        <div class="owner"><?= htmlspecialchars($b['username'] ?? '匿名') ?></div>
                    </div>
                    <span style="color:#ff6b00;font-weight:bold;">¥<?= number_format($b['price'] ?? 0, 2) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
