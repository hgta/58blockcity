<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/System.php';

// 检查管理员权限
checkAdmin();

$system = new System($pdo);
$settings = $system->getSystemSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_general'])) {
            $system->updateSettings([
                'site_name' => $_POST['site_name'],
                'site_description' => $_POST['site_description'],
                'site_keywords' => $_POST['site_keywords'],
                'admin_email' => $_POST['admin_email'],
                'records_per_page' => intval($_POST['records_per_page'])
            ]);
            $_SESSION['message'] = '基本设置已更新';
        }
        elseif (isset($_POST['update_maintenance'])) {
            $system->updateSettings([
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                'maintenance_message' => $_POST['maintenance_message']
            ]);
            $_SESSION['message'] = '维护设置已更新';
        }
        elseif (isset($_POST['update_security'])) {
            $system->updateSettings([
                'login_attempts' => intval($_POST['login_attempts']),
                'password_strength' => intval($_POST['password_strength']),
                'enable_captcha' => isset($_POST['enable_captcha']) ? 1 : 0,
                'session_timeout' => intval($_POST['session_timeout'])
            ]);
            $_SESSION['message'] = '安全设置已更新';
        }
        elseif (isset($_POST['update_email'])) {
            $system->updateSettings([
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => intval($_POST['smtp_port']),
                'smtp_username' => $_POST['smtp_username'],
                'smtp_password' => $_POST['smtp_password'],
                'smtp_secure' => $_POST['smtp_secure'],
                'email_from' => $_POST['email_from'],
                'email_from_name' => $_POST['email_from_name']
            ]);
            $_SESSION['message'] = '邮件设置已更新';
            if (isset($_POST['test_email'])) {
                $to = $_POST['test_email_address'];
                if ($system->sendEmail($to, '58互访圈系统测试邮件', '这是一封来自58互访圈系统的测试邮件，表示您的邮件配置已正确设置。')) {
                    $_SESSION['message'] .= '，且测试邮件已发送';
                } else {
                    $_SESSION['error'] = '设置已保存，但测试邮件发送失败';
                }
            }
        }
        header('Location: system_settings.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = '保存设置时出错: ' . $e->getMessage();
        header('Location: system_settings.php');
        exit();
    }
}

$admin_site_config = ['site' => 'hufang', 'page_title' => '系统设置'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="admin-alert success"><i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<style>
.settings-tabs { display:flex; gap:4px; border-bottom:1px solid var(--admin-border); margin-bottom:20px; flex-wrap:wrap; }
.settings-tab { padding:10px 18px; cursor:pointer; color:var(--admin-text-muted); border:none; background:none; font-size:14px; font-weight:600; border-bottom:2px solid transparent; transition:var(--admin-transition); }
.settings-tab:hover { color:var(--admin-text); }
.settings-tab.active { color:var(--admin-accent); border-bottom-color:var(--admin-accent); }
.settings-panel { display:none; }
.settings-panel.active { display:block; }
.settings-checkbox-row { display:flex; align-items:center; gap:10px; }
.settings-checkbox-row input[type="checkbox"] { width:18px; height:18px; cursor:pointer; }
</style>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="settings-tabs">
            <button class="settings-tab active" onclick="switchTab('general')"><i class="fas fa-info-circle"></i> 基本设置</button>
            <button class="settings-tab" onclick="switchTab('maintenance')"><i class="fas fa-tools"></i> 维护设置</button>
            <button class="settings-tab" onclick="switchTab('security')"><i class="fas fa-shield-alt"></i> 安全设置</button>
            <button class="settings-tab" onclick="switchTab('email')"><i class="fas fa-envelope"></i> 邮件设置</button>
        </div>

        <!-- 基本设置 -->
        <div class="settings-panel active" id="panel-general">
            <form method="post" style="max-width:600px;">
                <div class="admin-form-group"><label class="admin-form-label">网站名称</label><input type="text" name="site_name" class="admin-form-input" value="<?= htmlspecialchars($settings['site_name'] ?? '58互访圈系统') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">网站描述</label><textarea name="site_description" class="admin-form-input" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea></div>
                <div class="admin-form-group"><label class="admin-form-label">网站关键词</label><input type="text" name="site_keywords" class="admin-form-input" value="<?= htmlspecialchars($settings['site_keywords'] ?? '') ?>"><span class="admin-form-hint">多个关键词用英文逗号分隔</span></div>
                <div class="admin-form-group"><label class="admin-form-label">管理员邮箱</label><input type="email" name="admin_email" class="admin-form-input" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">每页记录数</label><input type="number" name="records_per_page" class="admin-form-input" value="<?= htmlspecialchars($settings['records_per_page'] ?? 15) ?>"></div>
                <button type="submit" name="update_general" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 保存基本设置</button>
            </form>
        </div>

        <!-- 维护设置 -->
        <div class="settings-panel" id="panel-maintenance">
            <form method="post" style="max-width:600px;">
                <div class="admin-form-group">
                    <label class="admin-form-label">维护模式</label>
                    <div class="settings-checkbox-row"><input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? 0) ? 'checked' : '' ?>><label for="maintenance_mode" style="cursor:pointer;">启用系统维护模式</label></div>
                    <span class="admin-form-hint">启用后普通用户将无法访问系统，仅管理员可登录</span>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">维护消息</label><textarea name="maintenance_message" class="admin-form-input" rows="3"><?= htmlspecialchars($settings['maintenance_message'] ?? '系统正在维护中，请稍后再访问。给您带来不便，敬请谅解！') ?></textarea></div>
                <button type="submit" name="update_maintenance" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 保存维护设置</button>
            </form>
        </div>

        <!-- 安全设置 -->
        <div class="settings-panel" id="panel-security">
            <form method="post" style="max-width:600px;">
                <div class="admin-form-group"><label class="admin-form-label">最大登录尝试次数</label><input type="number" name="login_attempts" class="admin-form-input" value="<?= htmlspecialchars($settings['login_attempts'] ?? 5) ?>"><span class="admin-form-hint">超过此次数账户将被临时锁定</span></div>
                <div class="admin-form-group"><label class="admin-form-label">密码强度要求</label><select name="password_strength" class="admin-form-select"><option value="1" <?= ($settings['password_strength'] ?? 2) == 1 ? 'selected' : '' ?>>低 - 至少6个字符</option><option value="2" <?= ($settings['password_strength'] ?? 2) == 2 ? 'selected' : '' ?>>中 - 至少8个字符，包含字母和数字</option><option value="3" <?= ($settings['password_strength'] ?? 2) == 3 ? 'selected' : '' ?>>高 - 至少10个字符，包含大小写字母、数字和特殊字符</option></select></div>
                <div class="admin-form-group"><label class="admin-form-label">启用验证码</label><div class="settings-checkbox-row"><input type="checkbox" id="enable_captcha" name="enable_captcha" <?= ($settings['enable_captcha'] ?? 1) ? 'checked' : '' ?>><label for="enable_captcha" style="cursor:pointer;">在登录和注册页面启用验证码</label></div></div>
                <div class="admin-form-group"><label class="admin-form-label">会话超时(分钟)</label><input type="number" name="session_timeout" class="admin-form-input" value="<?= htmlspecialchars($settings['session_timeout'] ?? 30) ?>"><span class="admin-form-hint">用户不活动后自动登出的时间</span></div>
                <button type="submit" name="update_security" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 保存安全设置</button>
            </form>
        </div>

        <!-- 邮件设置 -->
        <div class="settings-panel" id="panel-email">
            <form method="post" style="max-width:600px;">
                <div class="admin-form-group"><label class="admin-form-label">SMTP服务器</label><input type="text" name="smtp_host" class="admin-form-input" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">SMTP端口</label><input type="number" name="smtp_port" class="admin-form-input" value="<?= htmlspecialchars($settings['smtp_port'] ?? 465) ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">SMTP用户名</label><input type="text" name="smtp_username" class="admin-form-input" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">SMTP密码</label><input type="password" name="smtp_password" class="admin-form-input" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">加密方式</label><select name="smtp_secure" class="admin-form-select"><option value="">无</option><option value="ssl" <?= ($settings['smtp_secure'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option><option value="tls" <?= ($settings['smtp_secure'] ?? 'ssl') === 'tls' ? 'selected' : '' ?>>TLS</option></select></div>
                <div class="admin-form-group"><label class="admin-form-label">发件人邮箱</label><input type="email" name="email_from" class="admin-form-input" value="<?= htmlspecialchars($settings['email_from'] ?? '') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">发件人名称</label><input type="text" name="email_from_name" class="admin-form-input" value="<?= htmlspecialchars($settings['email_from_name'] ?? '58互访圈系统') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">测试邮件</label><div style="display:flex;gap:8px;"><input type="email" name="test_email_address" class="admin-form-input" placeholder="输入测试邮箱地址"><button type="submit" name="test_email" class="admin-btn admin-btn-default"><i class="fas fa-paper-plane"></i> 发送</button></div></div>
                <button type="submit" name="update_email" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 保存邮件设置</button>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    event.target.closest('.settings-tab').classList.add('active');
    document.getElementById('panel-' + name).classList.add('active');
}
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
