<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

require_once '../../classes/User.php';
$user = new User($pdo);

$toUser = intval($_GET['to'] ?? 0);
$toInfo = $toUser ? $user->getUserById($toUser) : null;

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    if ($content && $toUser) {
        $stmt = $pdo->prepare("INSERT INTO messages (from_user, to_user, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $toUser, $content]);
        $success = '消息已发送';
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
textarea.form-input { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:14px; min-height:120px; resize:vertical; }
.btn { width:100%; padding:14px; background:#ff6b00; color:white; border:none; border-radius:8px; font-size:16px; cursor:pointer; font-weight:bold; }
.alert-success { background:#d4edda; color:#155724; padding:12px; border-radius:6px; margin-bottom:15px; }
</style>

<div class="container">
    <div class="card">
        <h2>发送消息</h2>
        
        <?php if ($success): ?>
            <div class="alert-success"><?= $success ?></div>
            <div style="text-align:center;margin-top:15px;">
                <a href="../block/view.php?id=<?= $_GET['block_id'] ?? 0 ?>" style="color:#3498db;">返回区块详情</a>
            </div>
        <?php else: ?>
            <?php if ($toInfo): ?>
                <p style="margin-bottom:15px;">发送给: <strong><?= htmlspecialchars($toInfo['username']) ?></strong></p>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">消息内容</label>
                    <textarea name="content" class="form-input" placeholder="输入您的消息..." required></textarea>
                </div>
                <button type="submit" class="btn">发送消息</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
