<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
checkLogin();

$block = new Block($pdo);
$userId = $_SESSION['user_id'];
$requests = [];//$block->getUserPurchaseRequests($userId);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:800px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; }
.card { background:white; border-radius:8px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.08); margin-bottom:20px; }
.form-group { margin-bottom:15px; }
.form-label { display:block; font-size:14px; color:#666; margin-bottom:6px; }
.form-input, .form-select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
.btn { padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
.btn-submit { background:#ff6b00; color:white; }
.empty-state { text-align:center; padding:40px; color:#999; }
</style>

<div class="container">
    <h1 class="page-title"><i class="fas fa-hand-holding-usd"></i> 我的求购</h1>
    
    <div class="card">
        <h3 style="margin-bottom:15px;">发布求购请求</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">城市</label>
                <select name="city_id" class="form-select">
                    <option value="">选择城市...</option>
                    <?php
                    $cities = $pdo->query("SELECT id, name FROM cities ORDER BY rank LIMIT 50");
                    foreach ($cities as $c) {
                        echo "<option value='{$c['id']}'>{$c['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">区域</label>
                <select name="zone" class="form-select">
                    <?php foreach(['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                        <option value="<?=$z?>"><?=$z?>区</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">区块编号（可选，不填表示任意）</label>
                <input type="text" name="block_number" class="form-input" placeholder="如: 0101">
            </div>
            <div class="form-group">
                <label class="form-label">最高出价 (¥)</label>
                <input type="number" name="max_price" class="form-input" placeholder="面议可不填" step="0.01">
            </div>
            <button type="submit" class="btn btn-submit">发布求购</button>
        </form>
    </div>
    
    <?php if (!empty($requests)): ?>
        <?php foreach ($requests as $r): ?>
            <div class="card">
                <strong><?= htmlspecialchars($r['city_name']) ?></strong> <?= $r['zone'] ?>区
                <?= $r['block_number'] ? ' #'.$r['block_number'] : '(任意)' ?>
                <span style="color:#e74c3c;float:right;">¥<?= number_format($r['max_price']??0, 2) ?></span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-search" style="font-size:36px;display:block;margin-bottom:10px;"></i>
            <p>暂无活跃求购</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
