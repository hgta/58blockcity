<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/System.php';

// 检查管理员权限
checkAdmin();

// 初始化系统类
$system = new System($pdo);

// 获取当前系统设置
$settings = $system->getSystemSettings();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 基础设置
        if (isset($_POST['update_general'])) {
            $data = [
                'site_name' => $_POST['site_name'],
                'site_description' => $_POST['site_description'],
                'site_keywords' => $_POST['site_keywords'],
                'admin_email' => $_POST['admin_email'],
                'records_per_page' => intval($_POST['records_per_page'])
            ];
            $system->updateSettings($data);
            $_SESSION['message'] = '基本设置已更新';
        }
        
        // 维护设置
        elseif (isset($_POST['update_maintenance'])) {
            $data = [
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                'maintenance_message' => $_POST['maintenance_message']
            ];
            $system->updateSettings($data);
            $_SESSION['message'] = '维护设置已更新';
        }
        
        // 安全设置
        elseif (isset($_POST['update_security'])) {
            $data = [
                'login_attempts' => intval($_POST['login_attempts']),
                'password_strength' => intval($_POST['password_strength']),
                'enable_captcha' => isset($_POST['enable_captcha']) ? 1 : 0,
                'session_timeout' => intval($_POST['session_timeout'])
            ];
            $system->updateSettings($data);
            $_SESSION['message'] = '安全设置已更新';
        }
        
        // 邮件设置
        elseif (isset($_POST['update_email'])) {
            $data = [
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => intval($_POST['smtp_port']),
                'smtp_username' => $_POST['smtp_username'],
                'smtp_password' => $_POST['smtp_password'],
                'smtp_secure' => $_POST['smtp_secure'],
                'email_from' => $_POST['email_from'],
                'email_from_name' => $_POST['email_from_name']
            ];
            $system->updateSettings($data);
            $_SESSION['message'] = '邮件设置已更新';
            
            // 测试邮件发送
            if (isset($_POST['test_email'])) {
                $to = $_POST['test_email_address'];
                $subject = '58互访圈系统测试邮件';
                $message = '这是一封来自58互访圈系统的测试邮件，表示您的邮件配置已正确设置。';
                
                if ($system->sendEmail($to, $subject, $message)) {
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

<div class="container admin-container">
    <!-- 页面标题和面包屑导航 -->
    <div class="admin-header">
        <h1><i class="fas fa-cog"></i> 系统设置</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> 仪表盘</a></li>
                <li class="breadcrumb-item active" aria-current="page">系统设置</li>
            </ol>
        </nav>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 设置选项卡 -->
    <div class="card">
        <div class="card-header bg-white">
            <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab">
                        <i class="fas fa-info-circle"></i> 基本设置
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="maintenance-tab" data-toggle="tab" href="#maintenance" role="tab">
                        <i class="fas fa-tools"></i> 维护设置
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                        <i class="fas fa-shield-alt"></i> 安全设置
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="email-tab" data-toggle="tab" href="#email" role="tab">
                        <i class="fas fa-envelope"></i> 邮件设置
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="settingsTabContent">
                <!-- 基本设置 -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <form method="post">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">网站名称</label>
                            <div class="col-sm-9">
                                <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '58互访圈系统') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">网站描述</label>
                            <div class="col-sm-9">
                                <textarea name="site_description" class="form-control" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">网站关键词</label>
                            <div class="col-sm-9">
                                <input type="text" name="site_keywords" class="form-control" value="<?= htmlspecialchars($settings['site_keywords'] ?? '') ?>">
                                <small class="form-text text-muted">多个关键词用英文逗号分隔</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">管理员邮箱</label>
                            <div class="col-sm-9">
                                <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">每页记录数</label>
                            <div class="col-sm-9">
                                <input type="number" name="records_per_page" class="form-control" value="<?= htmlspecialchars($settings['records_per_page'] ?? 15) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="update_general" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 保存基本设置
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- 维护设置 -->
                <div class="tab-pane fade" id="maintenance" role="tabpanel">
                    <form method="post">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">维护模式</label>
                            <div class="col-sm-9">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="maintenance_mode">启用系统维护模式</label>
                                </div>
                                <small class="form-text text-muted">启用后普通用户将无法访问系统，仅管理员可登录</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">维护消息</label>
                            <div class="col-sm-9">
                                <textarea name="maintenance_message" class="form-control" rows="3"><?= htmlspecialchars($settings['maintenance_message'] ?? '系统正在维护中，请稍后再访问。给您带来不便，敬请谅解！') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="update_maintenance" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 保存维护设置
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- 安全设置 -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <form method="post">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">最大登录尝试次数</label>
                            <div class="col-sm-9">
                                <input type="number" name="login_attempts" class="form-control" value="<?= htmlspecialchars($settings['login_attempts'] ?? 5) ?>">
                                <small class="form-text text-muted">超过此次数账户将被临时锁定</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">密码强度要求</label>
                            <div class="col-sm-9">
                                <select name="password_strength" class="form-control">
                                    <option value="1" <?= ($settings['password_strength'] ?? 2) == 1 ? 'selected' : '' ?>>低 - 至少6个字符</option>
                                    <option value="2" <?= ($settings['password_strength'] ?? 2) == 2 ? 'selected' : '' ?>>中 - 至少8个字符，包含字母和数字</option>
                                    <option value="3" <?= ($settings['password_strength'] ?? 2) == 3 ? 'selected' : '' ?>>高 - 至少10个字符，包含大小写字母、数字和特殊字符</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">启用验证码</label>
                            <div class="col-sm-9">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enable_captcha" name="enable_captcha" <?= ($settings['enable_captcha'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="enable_captcha">在登录和注册页面启用验证码</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">会话超时(分钟)</label>
                            <div class="col-sm-9">
                                <input type="number" name="session_timeout" class="form-control" value="<?= htmlspecialchars($settings['session_timeout'] ?? 30) ?>">
                                <small class="form-text text-muted">用户不活动后自动登出的时间</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="update_security" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 保存安全设置
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- 邮件设置 -->
                <div class="tab-pane fade" id="email" role="tabpanel">
                    <form method="post">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">SMTP服务器</label>
                            <div class="col-sm-9">
                                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">SMTP端口</label>
                            <div class="col-sm-9">
                                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($settings['smtp_port'] ?? 465) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">SMTP用户名</label>
                            <div class="col-sm-9">
                                <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">SMTP密码</label>
                            <div class="col-sm-9">
                                <input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">加密方式</label>
                            <div class="col-sm-9">
                                <select name="smtp_secure" class="form-control">
                                    <option value="">无</option>
                                    <option value="ssl" <?= ($settings['smtp_secure'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="tls" <?= ($settings['smtp_secure'] ?? 'ssl') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">发件人邮箱</label>
                            <div class="col-sm-9">
                                <input type="email" name="email_from" class="form-control" value="<?= htmlspecialchars($settings['email_from'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">发件人名称</label>
                            <div class="col-sm-9">
                                <input type="text" name="email_from_name" class="form-control" value="<?= htmlspecialchars($settings['email_from_name'] ?? '58互访圈系统') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">测试邮件</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input type="email" name="test_email_address" class="form-control" placeholder="输入测试邮箱地址">
                                    <div class="input-group-append">
                                        <button type="submit" name="test_email" class="btn btn-outline-secondary">
                                            <i class="fas fa-paper-plane"></i> 发送测试邮件
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="update_email" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 保存邮件设置
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>