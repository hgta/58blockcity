<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

require_once '../../classes/Block.php';
$block = new Block($pdo);

$blockId = intval($_GET['id'] ?? 0);
$blockInfo = $block->getBlockById($blockId);
if (!$blockInfo || $blockInfo['owner_id'] != $_SESSION['user_id']) {
    header("Location: ../user/dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = floatval($_POST['price'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE blocks SET status = 'available', price = ?, owner_id = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$price, $blockId]);
        header("Location: view.php?id={$blockId}");
        exit();
    } catch (Exception $e) {
        $error = '出售发布失败: ' . $e->getMessage();
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:600px; margin:40px auto; padding:20px; }
.card { background:white; border-radius:12px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
.card h2 { margin-bottom:20px; color:#333; }
.info-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f0f0f0; }
.info-label { color:#666; }
.form-group { margin:20px 0; }
.form-label { display:block; font-size:14px; color:#666; margin-bottom:6px; }
.form-input { width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:18px; font-weight:bold; color:#ff6b00; }
.btn { width:100%; padding:15px; border:none; border-radius:8px; font-size:16px; cursor:pointer; font-weight:bold; }
.btn-sell { background:#ff6b00; color:white; }
.btn-sell:hover { background:#e05d00; }
.alert { padding:12px; border-radius:6px; margin-bottom:15px; }
.alert-error { background:#f8d7da; color:#721c24; }
</style>

<div class="container">
    <div class="card">
        <h2><i class="fas fa-tag"></i> 出售区块</h2>
        
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <div class="info-row">
            <span class="info-label">区块</span>
            <span><?= htmlspecialchars($blockInfo['city_name']) ?> <?= $blockInfo['zone'] ?>区 #<?= $blockInfo['block_number'] ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">当前价值</span>
            <span>¥<?= number_format($blockInfo['price'] ?? 0, 2) ?></span>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">售价 (¥)</label>
                <input type="number" name="price" class="form-input" value="<?= $blockInfo['price'] ?>" step="0.01" min="1" required>
            </div>
            <button type="submit" class="btn btn-sell" onclick="return confirm('确认出售此区块？')">
                确认出售
            </button>
        </form>
        
        <div style="text-align:center;margin-top:15px;">
            <a href="view.php?id=<?= $blockId ?>" style="color:#3498db;">返回区块详情</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
