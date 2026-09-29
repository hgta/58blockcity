<?php
/**
 * 微信扫码注册 · 补全页（三步走的第 2-3 步）
 *
 * 流程：登录页输入公众号验证码 → 未绑定账号 → 跳转本页
 *   ① 新用户：补全用户名 + 邮箱/手机（至少一项）→ 建号并绑定微信 → 登录
 *   ② 老用户：输入已有账号密码 → 验证后绑定微信 → 登录
 *
 * 会话依赖：$_SESSION['wechat_pending_openid']（10 分钟内有效）
 */

if (!isset($site_config)) {
    $site_config = [
        'name'                   => '58区块城市',
        'desc'                   => '完成注册，开启城市之旅',
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
$wechatAuth = new WechatAuth($pdo);
$user = new User($pdo);

// 城市数据库（用于补全表单的城市选择器）
$cityOptions = [];
try {
    $stmt = $pdo->query("SELECT name, pinyin, is_hot FROM cities WHERE status = 'active' OR status IS NULL ORDER BY is_hot DESC, rank ASC, pinyin ASC LIMIT 1000");
    $cityOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cityOptions = [];
}

// ---- 前置校验：必须有扫码上下文 ----
$pendingOpenid = $_SESSION['wechat_pending_openid'] ?? '';
$pendingExpiry = $_SESSION['wechat_pending_expires'] ?? 0;

if ($pendingOpenid === '' || time() > $pendingExpiry) {
    unset($_SESSION['wechat_pending_openid'], $_SESSION['wechat_pending_expires']);
    header('Location: login.php');
    exit;
}

// 中途绑定成功/已登录则清理上下文
if (isLoggedIn()) {
    unset($_SESSION['wechat_pending_openid'], $_SESSION['wechat_pending_expires']);
    header('Location: ' . $site_config['redirect_after_login']);
    exit;
}

// 默认用户名（可改）
$defaultUsername = $wechatAuth->generateUsername();
$username = $_POST['username'] ?? $defaultUsername;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'new';

    try {
        if ($mode === 'bind') {
            /* ---------- 老用户绑定已有账号 ---------- */
            $bindUsername = trim($_POST['bind_username'] ?? '');
            $bindPassword = $_POST['bind_password'] ?? '';

            if ($bindUsername === '' || $bindPassword === '') {
                throw new Exception('请输入已有账号的用户名和密码');
            }

            $userData = $user->getUserByUsername($bindUsername);
            if (!$userData || !password_verify($bindPassword, $userData['password'])) {
                throw new Exception('用户名或密码错误，无法绑定');
            }
            if ($userData['status'] !== 'active') {
                throw new Exception('账户已被禁用，请联系管理员');
            }

            if (!$wechatAuth->bindOpenid((int)$userData['id'], $pendingOpenid)) {
                throw new Exception('绑定失败：该账号可能已绑定其他微信');
            }

            handleLogin((int)$userData['id'], $userData['username'], $userData['email'], $userData['role'], true);
            unset($_SESSION['wechat_pending_openid'], $_SESSION['wechat_pending_expires']);
            $redirectUrl = $_SESSION['redirect_url'] ?? $site_config['redirect_after_login'];
            unset($_SESSION['redirect_url']);
            header('Location: ' . $redirectUrl);
            exit;

        } else {
            /* ---------- 新用户建号：强制补全邮箱/手机 ---------- */
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $city     = normalizeCityName($_POST['city'] ?? '', $cityOptions);

            $userId = $wechatAuth->createUserFromWechat(
                $pendingOpenid,
                $username,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $password !== '' ? $password : null,
                $city
            );

            if ($userId <= 0) {
                throw new Exception('注册失败，请稍后重试');
            }

            $newUser = $user->getUserById($userId);
            handleLogin($userId, $newUser['username'] ?? $username, $newUser['email'] ?? '', $newUser['role'] ?? 'user', true);
            unset($_SESSION['wechat_pending_openid'], $_SESSION['wechat_pending_expires']);
            $redirectUrl = $_SESSION['redirect_url'] ?? $site_config['redirect_after_login'];
            unset($_SESSION['redirect_url']);
            header('Location: ' . $redirectUrl);
            exit;
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

?>
<?php include $site_config['includes_path'] . 'header.php'; ?>

<style>
.wc-container { max-width:480px; margin:40px auto; padding:20px; }
.wc-header { text-align:center; margin-bottom:20px; }
.wc-header h2 { color:#333; font-size:22px; margin-bottom:8px; }
.wc-header p { color:#999; font-size:14px; }
.wc-tip { background:#f0f9ff; border:1px solid #bbdefb; color:#1565c0; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:18px; text-align:center; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; margin-bottom:6px; font-weight:bold; color:#555; font-size:14px; }
.form-group input, .form-group select { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:15px; }
.form-group input:focus { border-color:#ff6b00; outline:none; }
.form-group .hint { font-size:12px; color:#999; font-weight:normal; margin-top:4px; }
.btn-primary { width:100%; padding:14px; background:#ff6b00; color:white; border:none; border-radius:6px; font-size:17px; font-weight:bold; cursor:pointer; }
.btn-primary:hover { background:#e05d00; }
.alert { padding:10px 15px; border-radius:4px; margin-bottom:15px; }
.alert-error { background:#ffebee; color:#c62828; }
.wc-tabs { display:flex; margin-bottom:20px; border-bottom:2px solid #eee; }
.wc-tabs button { flex:1; padding:12px 8px; background:none; border:none; border-bottom:3px solid transparent; font-size:15px; color:#999; cursor:pointer; margin-bottom:-2px; }
.wc-tabs button.active { color:#ff6b00; border-bottom-color:#ff6b00; font-weight:bold; }
.wc-panel { display:none; }
.wc-panel.active { display:block; }
.wc-footer { text-align:center; margin-top:16px; font-size:14px; color:#666; }
</style>

<div class="wc-container">
    <div class="wc-header">
        <h2>🎉 微信验证成功</h2>
        <p>只差最后一步，完善账号信息即可完成注册</p>
    </div>
    <div class="wc-tip">微信身份已确认 · 请补全账号信息（邮箱/手机至少填一项，用于账号找回）</div>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="wc-tabs">
        <button type="button" id="wcTabNew" class="active" onclick="switchWcTab('new')">新用户注册</button>
        <button type="button" id="wcTabBind" onclick="switchWcTab('bind')">绑定已有账号</button>
    </div>

    <!-- 新用户 -->
    <div id="wcPanelNew" class="wc-panel active">
        <form method="POST" action="wechat_complete.php">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="mode" value="new">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? $defaultUsername) ?>"
                       placeholder="4-20位字母/数字/下划线">
                <p class="hint">默认已为您生成，可修改为自己喜欢的用户名</p>
            </div>
            <div class="form-group">
                <label>邮箱 <span style="color:#999;font-weight:normal;">（与手机号至少填一项）</span></label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label>手机号</label>
                <input type="tel" name="phone" maxlength="11" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="用于账号找回">
            </div>
            <div class="form-group">
                <label>设置密码 <span style="color:#999;font-weight:normal;">（选填，不设则仅微信扫码登录）</span></label>
                <input type="password" name="password" placeholder="至少6位，含字母和数字">
            </div>
            <div class="form-group">
                <label>所在城市</label>
                <?php
                $cityPickerName  = 'city';
                $cityPickerValue = $_POST['city'] ?? '';
                $cityPickerId    = 'wcCityPicker';
                include $sharedIncludes . '/city_picker.php';
                ?>
            </div>
            <button type="submit" class="btn-primary">完成注册</button>
        </form>
    </div>

    <!-- 老用户绑定 -->
    <div id="wcPanelBind" class="wc-panel">
        <form method="POST" action="wechat_complete.php">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="mode" value="bind">
            <div class="form-group">
                <label>已有账号用户名</label>
                <input type="text" name="bind_username" required value="<?= htmlspecialchars($_POST['bind_username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="bind_password" required>
            </div>
            <button type="submit" class="btn-primary">验证并绑定微信</button>
        </form>
        <p style="font-size:12px;color:#999;margin-top:10px;text-align:center;">绑定后可直接扫码登录该账号，每个账号仅可绑定一个微信号</p>
    </div>

    <div class="wc-footer">
        <a href="login.php" style="color:#ff6b00;">← 返回重新扫码</a>
    </div>
</div>

<script>
function switchWcTab(tab) {
    document.getElementById('wcPanelNew').classList.toggle('active', tab === 'new');
    document.getElementById('wcPanelBind').classList.toggle('active', tab === 'bind');
    document.getElementById('wcTabNew').classList.toggle('active', tab === 'new');
    document.getElementById('wcTabBind').classList.toggle('active', tab === 'bind');
}
// 提交绑定失败时自动切回对应 Tab
<?php if (!empty($errors) && ($_POST['mode'] ?? 'new') === 'bind'): ?>
switchWcTab('bind');
<?php endif; ?>
</script>

<?php include $site_config['includes_path'] . 'footer.php'; ?>
