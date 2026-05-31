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

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    try {
        $stmt = $pdo->prepare("UPDATE blocks SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $desc, $blockId]);
        $success = '区块信息已更新';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:600px; margin:40px auto; padding:20px; }
.card { background:white; border-radius:12px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
.card h2 { margin-bottom:20px; color:#333; }
.form-group { margin-bottom:15px; }
.form-label { display:block; font-size:14px; color:#666; margin-bottom:6px; }
.form-input { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
textarea.form-input { min-height:100px; resize:vertical; }
.btn { padding:12px 24px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
.btn-save { background:#ff6b00; color:white; width:100%; font-size:16px; }
.alert-success { background:#d4edda; color:#155724; padding:12px; border-radius:6px; margin-bottom:15px; }
.alert-error { background:#f8d7da; color:#721c24; padding:12px; border-radius:6px; margin-bottom:15px; }
</style>

<div class="container">
    <div class="card">
        <h2>编辑区块信息</h2>
        
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <p style="color:#666;margin-bottom:20px;">
            <?= htmlspecialchars($blockInfo['city_name']) ?> <?= $blockInfo['zone'] ?>区 #<?= $blockInfo['block_number'] ?>
        </p>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">区块名称</label>
                <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($blockInfo['name'] ?? '') ?>" placeholder="给你的区块起个名字">
            </div>
            <div class="form-group">
                <label class="form-label">描述</label>
                <textarea name="description" class="form-input" placeholder="描述一下这个区块..."><?= htmlspecialchars($blockInfo['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-save">保存修改</button>
        </form>
        
        <div style="text-align:center;margin-top:15px;">
            <a href="view.php?id=<?= $blockId ?>" style="color:#3498db;">返回区块详情</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
