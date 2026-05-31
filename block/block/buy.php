<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

require_once '../../classes/Block.php';
$block = new Block($pdo);

$blockId = intval($_GET['id'] ?? $_POST['block_id'] ?? 0);
$blockInfo = $block->getBlockById($blockId);
if (!$blockInfo) { header("Location: ../city.php"); exit(); }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($blockInfo['status'] !== 'available') {
            $error = '该区块不可购买';
        } else {
            $result = $block->purchaseBlock($blockId, $_SESSION['user_id'], $blockInfo['price']);
            if ($result) {
                $success = '购买成功！';
                header("Location: view.php?id={$blockId}");
                exit();
            }
        }
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
.info-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f0f0f0; }
.info-label { color:#666; }
.price-big { font-size:32px; font-weight:bold; color:#ff6b00; text-align:center; margin:20px 0; }
.btn-buy { width:100%; padding:15px; background:#ff6b00; color:white; border:none; border-radius:8px; font-size:18px; cursor:pointer; font-weight:bold; }
.btn-buy:hover { background:#e05d00; }
.alert { padding:12px; border-radius:6px; margin-bottom:15px; }
.alert-error { background:#f8d7da; color:#721c24; }
.alert-success { background:#d4edda; color:#155724; }
</style>

<div class="container">
    <div class="card">
        <h2>确认购买</h2>
        
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        
        <div class="info-row">
            <span class="info-label">城市</span>
            <span><?= htmlspecialchars($blockInfo['city_name'] ?? '') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">区域/区块</span>
            <span><?= $blockInfo['zone'] ?? '' ?>区 #<?= $blockInfo['block_number'] ?? '' ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">当前状态</span>
            <span style="color:<?= $blockInfo['status']=='available'?'#27ae60':'#e74c3c' ?>">
                <?= $blockInfo['status']=='available'?'可购买':'已售' ?>
            </span>
        </div>
        
        <div class="price-big">¥<?= number_format($blockInfo['price'] ?? 0, 2) ?></div>
        
        <?php if ($blockInfo['status'] == 'available'): ?>
            <form method="POST">
                <input type="hidden" name="block_id" value="<?= $blockId ?>">
                <button type="submit" class="btn-buy" onclick="return confirm('确认以 ¥<?= number_format($blockInfo['price'],2) ?> 购买此区块？')">
                    确认购买
                </button>
            </form>
        <?php endif; ?>
        
        <div style="text-align:center;margin-top:15px;">
            <a href="view.php?id=<?= $blockId ?>" style="color:#3498db;">返回区块详情</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
