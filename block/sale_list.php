<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/Block.php';
require_once '../classes/City.php';

$block = new Block($pdo);
$city = new City($pdo);

// Get blocks for sale (status='available' with owner_id set = listed for sale)
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Get all sold blocks that could be resold
$stmt = $pdo->prepare("SELECT b.*, c.name as city_name FROM blocks b 
    JOIN cities c ON b.city_id = c.id 
    WHERE b.status = 'sold' 
    ORDER BY b.updated_at DESC 
    LIMIT " . (($page-1)*$perPage) . "," . $perPage);
$stmt->execute();
$blocks = $stmt->fetchAll();

$totalStmt = $pdo->query("SELECT COUNT(*) FROM blocks WHERE status = 'sold'");
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);
?>
<?php require_once 'includes/header.php'; ?>

<style>
.container { max-width:1000px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; color:#333; }
.block-list { display:grid; grid-template-columns:repeat(2,1fr); gap:15px; }
.block-card { background:white; border-radius:8px; padding:15px; box-shadow:0 2px 8px rgba(0,0,0,0.08); text-decoration:none; color:inherit; transition:all .3s; }
.block-card:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(0,0,0,0.12); }
.block-card h3 { font-size:16px; color:#333; margin-bottom:8px; }
.block-card .zone-tag { display:inline-block; background:#ff6b00; color:white; padding:2px 10px; border-radius:12px; font-size:12px; margin-right:8px; }
.block-card .price { color:#e74c3c; font-weight:bold; font-size:16px; }
.block-card .city { color:#666; font-size:13px; }
.empty-state { text-align:center; padding:60px; color:#999; }
.empty-state i { font-size:48px; margin-bottom:15px; }
.pagination { display:flex; justify-content:center; gap:8px; margin-top:25px; }
.pagination a { padding:8px 14px; border:1px solid #ddd; border-radius:4px; color:#333; text-decoration:none; }
.pagination a.active { background:#ff6b00; color:white; border-color:#ff6b00; }
@media(max-width:768px){ .block-list { grid-template-columns:1fr; } }
</style>

<div class="container">
    <h1 class="page-title"><i class="fas fa-tag"></i> 售卖中的区块</h1>
    
    <?php if (empty($blocks)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>暂无可售区块</p>
            <a href="city.php?name=beijing" class="btn" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#ff6b00;color:white;border-radius:6px;text-decoration:none;">浏览区块城市</a>
        </div>
    <?php else: ?>
        <div class="block-list">
            <?php foreach ($blocks as $b): ?>
                <a href="block/view.php?id=<?= $b['id'] ?>" class="block-card">
                    <h3>
                        <span class="zone-tag"><?= $b['zone'] ?>区</span>
                        <?= htmlspecialchars($b['city_name']) ?> #<?= $b['block_number'] ?>
                    </h3>
                    <div class="price">¥<?= number_format($b['price'] ?? 0, 2) ?></div>
                    <div class="city"><?= htmlspecialchars($b['city_name']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
                <a href="?page=<?=$i?>" class="<?=$i==$page?'active':''?>"><?=$i?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
