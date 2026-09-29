<?php
/**
 * 微信通道 · 重置密码落地页
 *
 * 用户在公众号发送「重置」→ 回复一次性链接（本页 ?code=XXXXXX）
 * → 校验重置码 → 显示绑定账号 → 设置新密码
 */

if (!isset($site_config)) {
    $site_config = [
        'name'                   => '58区块城市',
        'desc'                   => '设置新密码',
        'redirect_after_login'   => '../index.php',
        'home_url'               => '../index.php',
        'db_path'                => '../config/database.php',
        'class_path'             => '../classes/',
        'includes_path'          => __DIR__ . '/../mall/includes/',
    ];
}

$sharedIncludes = dirname(__DIR__) . '/includes';

require_once $site_config['db_path'];
require_once $site_config['class_path'] . 'User.php';
require_once $site_config['class_path'] . 'WechatAuth.php';
require_once $sharedIncludes . '/functions.php';
require_once $sharedIncludes . '/auth.php';

$errors = [];
$done = false;
$wechatAuth = new WechatAuth($pdo);
$user = new User($pdo);

// 第一步：校验链接中的重置码
$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$targetUser = null;

if ($code !== '') {
    // 校验 + 核销（一次性）。注意：只有 POST 设置新密码成功才核销，
    // 因此这里先"预检"（不核销），提交时再真正核销。
    $openid = null;
    if (preg_match('/^\d{6}$/', $code)) {
        // 预检：查码不核销 —— consumeCode 是一次性的，放在 POST 成功后调用
        // 这里直接尝试：GET 阶段用"非破坏性"查询
        try {
            $stmt = $pdo->prepare(
                "SELECT openid FROM wechat_login_codes
                 WHERE code = ? AND purpose = 'reset' AND is_used = 0 AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$code]);
            $openid = $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            error_log('reset_password 预检错误: ' . $e->getMessage());
        }
    }

    if ($openid !== null) {
        $targetUser = $wechatAuth->getUserByOpenid($openid);
        if (!$targetUser) {
            $errors[] = '该微信尚未绑定任何账号。请先在登录页用微信验证码登录完成绑定，再重置密码。';
        }
    } else {
        $errors[] = '重置链接无效或已过期（5 分钟有效、只能使用一次）。请在公众号重新发送「重置」获取新链接。';
    }
} else {
    $errors[] = '缺少重置码参数';
}

// 第二步：设置新密码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $targetUser) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $errors[] = '密码至少6位';
    } elseif (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = '密码需包含字母和数字';
    } elseif ($password !== $confirm) {
        $errors[] = '两次密码不一致';
    } else {
        // 真正核销重置码（一次性）
        $consumedOpenid = $wechatAuth->consumeCode($code, WechatAuth::CODE_PURPOSE_RESET);
        if ($consumedOpenid === null) {
            $errors[] = '重置码刚被使用或已过期，请在公众号重新发送「重置」。';
            $targetUser = null;
        } elseif (!$user->updateUserPassword((int)$targetUser['id'], password_hash($password, PASSWORD_DEFAULT))) {
            $errors[] = '密码更新失败，请稍后重试';
        } else {
            $done = true;
        }
    }
}
?>
<?php include $site_config['includes_path'] . 'header.php'; ?>

<style>
.rp-container { max-width:460px; margin:60px auto; padding:20px; }
.rp-header { text-align:center; margin-bottom:25px; }
.rp-header h2 { color:#333; font-size:22px; margin-bottom:8px; }
.rp-header p { color:#999; font-size:14px; }
.rp-card { background:#fff; border:1px solid #eee; border-radius:10px; padding:28px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
.rp-user { background:#f0f9ff; border-radius:8px; padding:10px 14px; font-size:14px; color:#1565c0; margin-bottom:18px; text-align:center; }
.rp-user b { font-size:16px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; margin-bottom:6px; font-weight:bold; color:#555; font-size:14px; }
.form-group input { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:15px; }
.form-group input:focus { border-color:#ff6b00; outline:none; }
.btn-primary { width:100%; padding:14px; background:#ff6b00; color:white; border:none; border-radius:6px; font-size:17px; font-weight:bold; cursor:pointer; }
.btn-primary:hover { background:#e05d00; }
.alert { padding:10px 15px; border-radius:4px; margin-bottom:15px; font-size:14px; }
.alert-error { background:#ffebee; color:#c62828; }
.alert-success { background:#d4edda; color:#155724; }
.rp-footer { text-align:center; margin-top:16px; font-size:14px; }
.rp-footer a { color:#ff6b00; font-weight:bold; }
</style>

<div class="rp-container">
    <div class="rp-header">
        <h2>设置新密码</h2>
        <p>通过微信公众号验证的身份重置密码</p>
    </div>

    <div class="rp-card">
        <?php if ($done): ?>
            <div class="alert alert-success">✅ 密码已重置成功，请使用新密码登录</div>
            <a href="login.php" class="btn-primary" style="display:block;text-align:center;text-decoration:none;">前往登录</a>
        <?php else: ?>
            <?php foreach ($errors as $e): ?>
                <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>

            <?php if ($targetUser): ?>
                <div class="rp-user">将为账号 <b><?= htmlspecialchars($targetUser['username']) ?></b> 设置新密码</div>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    <div class="form-group">
                        <label>新密码</label>
                        <input type="password" name="password" required placeholder="至少6位，含字母和数字">
                    </div>
                    <div class="form-group">
                        <label>确认新密码</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary">重置密码</button>
                </form>
            <?php else: ?>
                <a href="forgot_password.php" class="btn-primary" style="display:block;text-align:center;text-decoration:none;">重新获取重置链接</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="rp-footer">
        <a href="forgot_password.php">← 返回找回密码</a> &nbsp;•&nbsp; <a href="login.php">返回登录</a>
    </div>
</div>

<?php include $site_config['includes_path'] . 'footer.php'; ?>
