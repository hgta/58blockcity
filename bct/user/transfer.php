<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/BCTTransaction.php';

$account = new UserBCTAccount($pdo);
$userAccounts = $account->getUserAccounts($_SESSION['user_id']);

$city = $_GET['city'] ?? '';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromCity = $_POST['from_city'] ?? '';
    $toCity = $_POST['to_city'] ?? '';
    $toUser = trim($_POST['to_user'] ?? '');
    $amount = intval($_POST['amount'] ?? 0);
    
    if (!$fromCity || !$toCity || !$toUser || $amount <= 0) {
        $error = '请填写完整信息';
    } else {
        try {
            // Find recipient user
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $userStmt->execute([$toUser]);
            $recipient = $userStmt->fetch();
            
            if (!$recipient) {
                $error = '收款用户不存在';
            } else {
                $fromAcc = $account->getAccount($_SESSION['user_id'], $fromCity);
                if (!$fromAcc || ($fromAcc['balance'] - $fromAcc['frozen']) < $amount) {
                    $error = '余额不足';
                } else {
                    // Create transfer transaction
                    $bctTx = new BCTTransaction($pdo);
                    $bctTx->create(0, $_SESSION['user_id'], $recipient['id'], 
                        $fromCity . '→' . $toCity, $amount, 1.0, 0, null, $amount, 'transfer');
                    $success = "成功转账 {$amount} BCT 给 {$toUser}";
                }
            }
        } catch (Exception $e) {
            $error = '转账失败: ' . $e->getMessage();
        }
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
.form-input, .form-select { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
.btn { width:100%; padding:14px; background:#ff6b00; color:white; border:none; border-radius:8px; font-size:16px; cursor:pointer; font-weight:bold; }
.btn:hover { background:#e05d00; }
.alert { padding:12px; border-radius:6px; margin-bottom:15px; }
.alert-success { background:#d4edda; color:#155724; }
.alert-error { background:#f8d7da; color:#721c24; }
.accounts-list { margin-bottom:20px; padding:15px; background:#f8f9fa; border-radius:8px; }
.accounts-list h4 { margin-bottom:10px; font-size:14px; color:#666; }
.account-item { display:flex; justify-content:space-between; padding:6px 0; font-size:13px; border-bottom:1px dotted #ddd; }
</style>

<div class="container">
    <div class="card">
        <h2><i class="fas fa-exchange-alt"></i> BCT转账</h2>
        
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <div class="accounts-list">
            <h4>我的账户</h4>
            <?php if (empty($userAccounts)): ?>
                <p style="color:#999;">暂无BCT账户</p>
            <?php else: ?>
                <?php foreach ($userAccounts as $acc): ?>
                <div class="account-item">
                    <span><?= htmlspecialchars($acc['city']) ?></span>
                    <span><strong><?= number_format($acc['balance'] - $acc['frozen']) ?></strong> BCT可用</span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">转出城市</label>
                <select name="from_city" class="form-select" required>
                    <option value="">选择城市...</option>
                    <?php foreach ($userAccounts as $acc): ?>
                        <option value="<?= htmlspecialchars($acc['city']) ?>" <?= $city==$acc['city']?'selected':'' ?>>
                            <?= htmlspecialchars($acc['city']) ?> (<?= number_format($acc['balance']-$acc['frozen']) ?> BCT)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">收款城市</label>
                <input type="text" name="to_city" class="form-input" placeholder="收款方所在城市" required>
            </div>
            <div class="form-group">
                <label class="form-label">收款用户名</label>
                <input type="text" name="to_user" class="form-input" placeholder="对方用户名" required>
            </div>
            <div class="form-group">
                <label class="form-label">转账数量 (BCT)</label>
                <input type="number" name="amount" class="form-input" min="1" placeholder="请输入转账数量" required>
            </div>
            <button type="submit" class="btn">确认转账</button>
        </form>
        
        <div style="text-align:center;margin-top:15px;">
            <a href="dashboard.php" style="color:#3498db;"><i class="fas fa-arrow-left"></i> 返回仪表盘</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
