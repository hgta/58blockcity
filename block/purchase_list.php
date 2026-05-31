<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/City.php';

$city = new City($pdo);

// Get purchase requests
$stmt = $pdo->query("SELECT pr.*, c.name as city_name, u.username 
    FROM purchase_requests pr 
    JOIN cities c ON pr.city_id = c.id 
    LEFT JOIN users u ON pr.user_id = u.id 
    WHERE pr.status = 'active' 
    ORDER BY pr.created_at DESC LIMIT 50");
$requests = $stmt->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<style>
.container { max-width:1000px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; color:#333; }
.req-list { display:grid; gap:15px; }
.req-card { background:white; border-radius:8px; padding:15px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.req-card h3 { font-size:16px; margin-bottom:8px; color:#333; }
.req-card .meta { font-size:13px; color:#666; }
.req-card .price { color:#e74c3c; font-weight:bold; }
.zone-tag { display:inline-block; background:#1976d2; color:white; padding:2px 10px; border-radius:12px; font-size:12px; margin-right:8px; }
.empty-state { text-align:center; padding:60px; color:#999; }
</style>

<div class="container">
    <h1 class="page-title"><i class="fas fa-hand-holding-usd"></i> 求购列表</h1>
    
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <p>暂无求购请求</p>
        </div>
    <?php else: ?>
        <div class="req-list">
            <?php foreach ($requests as $r): ?>
                <div class="req-card">
                    <h3>
                        <span class="zone-tag"><?= htmlspecialchars($r['zone'] ?? '?') ?>区</span>
                        <?= htmlspecialchars($r['city_name']) ?>
                        <?= $r['block_number'] ? ' #'.$r['block_number'] : ' (任意区块)' ?>
                    </h3>
                    <div class="meta">
                        <span>求购者: <?= htmlspecialchars($r['username'] ?? '匿名') ?></span>
                        <span class="price">最高出价: <?= $r['max_price'] ? '¥'.number_format($r['max_price'],2) : '面议' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
