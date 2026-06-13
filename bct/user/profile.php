<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/UserBCTAccount.php';

checkLogin();

$userId = $_SESSION['user_id'];
$user = new User($pdo);
$account = new UserBCTAccount($pdo);

// 获取用户数据
$userData = $user->getUserById($userId);
$userAccounts = $account->getUserAccounts($userId);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 基本验证
        $requiredFields = ['username', 'email', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("请填写所有必填字段");
            }
        }

        // 处理头像上传
        $avatarPath = $userData['avatar']; // 默认为原头像
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/images/uploads/avatars/';
            
            // 确保上传目录存在
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("只允许上传 JPG, PNG 或 GIF 格式的图片");
            }
            
            // 验证文件大小 (不超过2MB)
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                throw new Exception("头像图片大小不能超过2MB");
            }
            
            // 生成唯一文件名
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarFilename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $avatarPath = 'uploads/avatars/' . $avatarFilename;
            
            // 移动上传文件到临时位置
            $tmpFile = $uploadDir . 'tmp_' . $avatarFilename;
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $tmpFile)) {
                throw new Exception("头像上传失败");
            }

            // 自动缩放头像到 200x200（保持比例，统一为 JPEG）
            $maxSize = 200;
            list($origWidth, $origHeight) = getimagesize($tmpFile);
            $ratio = min($maxSize / $origWidth, $maxSize / $origHeight);
            $newWidth  = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);

            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));

            switch ($fileType) {
                case 'image/jpeg': $src = imagecreatefromjpeg($tmpFile); break;
                case 'image/png':  $src = imagecreatefrompng($tmpFile);  break;
                case 'image/gif':  $src = imagecreatefromgif($tmpFile);  break;
                default: $src = false;
            }

            if ($src) {
                imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                $avatarFilename = 'avatar_' . $userId . '_' . time() . '.jpg';
                $avatarPath = 'uploads/avatars/' . $avatarFilename;
                imagejpeg($thumb, $uploadDir . $avatarFilename, 90);
                imagedestroy($src);
                imagedestroy($thumb);
            }
            unlink($tmpFile);

            // 删除旧头像文件 (如果不是默认头像)
            if ($userData['avatar'] !== 'default.jpg' && file_exists('../assets/images/' . $userData['avatar'])) {
                unlink('../assets/images/' . $userData['avatar']);
            }
        }
		

        // 更新用户信息
        $updateData = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'] ?? null,
            'city' => $_POST['city'],
            'avatar' => $avatarPath,
            'id' => $userId
        ];

        // 如果有密码变更
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("两次输入的密码不一致");
            }
            $updateData['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }

        // 执行更新
        $result = $user->updateUser($updateData);
        
        if ($result) {
            $_SESSION['message'] = "资料更新成功";
            // 更新session中的用户名和头像
            $_SESSION['username'] = $updateData['username'];
            $_SESSION['avatar'] = $updateData['avatar'];
            // 刷新页面
            header("Location: profile.php");
            exit();
        } else {
            throw new Exception("资料更新失败");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// 显示消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <!-- 页面标题 -->
    <div class="page-header">
        <h1>
            <i class="glyphicon glyphicon-user"></i>
            个人资料设置
        </h1>
        <p class="text-muted">管理您的账户信息和隐私设置</p>
    </div>

    <div class="row">
        <!-- 左侧 - 个人资料表单 -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-edit"></i> 基本资料</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="username">用户名 *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($userData['username']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">电子邮箱 *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($userData['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">手机号码</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="city">所在城市 *</label>
                            <select class="form-control" id="city" name="city" required>
                                <?php 
                                $majorCities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '苏州', '天津', '南京'];
                                foreach ($majorCities as $city): ?>
                                <option value="<?= $city ?>" <?= $userData['city'] === $city ? 'selected' : '' ?>>
                                    <?= $city ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="avatar">头像</label>
                            <div class="avatar-upload">
                                <div class="avatar-preview">
                                    <img src="https://58.tl/assets/images/<?= htmlspecialchars($userData['avatar'] ?? 'default.jpg') ?>" 
                                         id="avatarPreview" class="avatar-img">
                                </div>
                                <div class="avatar-upload-controls">
                                    <input type="file" id="avatar" name="avatar" accept="image/*" 
                                           class="form-control-file" onchange="previewImage(this)">
                                    <small class="form-text text-muted">建议上传 200x200 像素的方形图片</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h4><i class="glyphicon glyphicon-lock"></i> 密码修改</h4>
                        <p class="text-muted">如不需要修改密码，请留空以下字段</p>
                        
                        <div class="form-group">
                            <label for="new_password">新密码</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">确认新密码</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="glyphicon glyphicon-floppy-disk"></i> 保存更改
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 右侧 - 账户信息 -->
        <div class="col-md-4">
            <!-- 账户概览 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-info-sign"></i> 账户概览</h3>
                </div>
                <div class="card-body">
                    <div class="account-summary">
                        <div class="account-avatar">
                            <img src="https://58.tl/assets/images/<?= htmlspecialchars($userData['avatar'] ?? 'default.jpg') ?>" 
                                 class="avatar-img-large">
                        </div>
                        <div class="account-details">
                            <h4><?= htmlspecialchars($userData['username']) ?></h4>
                            <p>
                                <i class="glyphicon glyphicon-envelope"></i>
                                <?= htmlspecialchars($userData['email']) ?>
                            </p>
                            <p>
                                <i class="glyphicon glyphicon-map-marker"></i>
                                <?= htmlspecialchars($userData['city']) ?>
                            </p>
                            <p>
                                <i class="glyphicon glyphicon-time"></i>
                                注册于: <?= date('Y-m-d', strtotime($userData['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- BCT资产概览 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-credit-card"></i> BCT资产</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($userAccounts)): ?>
                        <div class="empty-state-sm">
                            <i class="glyphicon glyphicon-warning-sign"></i>
                            <p>暂无BCT资产</p>
                            <a href="../market.php" class="btn btn-sm btn-primary">去交易</a>
                        </div>
                    <?php else: ?>
                        <div class="bct-summary">
                            <div class="bct-total">
                                <h4>总估值</h4>
                                <p class="total-value">
                                    <?php 
                                    $totalValuation = 0;
                                    foreach ($userAccounts as $account) {
                                        $totalValuation += $account['balance'] * $account['current_price'];
                                    }
                                    echo number_format($totalValuation, 2);
                                    ?> 元
                                </p>
                            </div>
                            <div class="bct-cities">
                                <h5>持有城市</h5>
                                <ul class="city-list">
                                    <?php foreach ($userAccounts as $account): ?>
                                    <li>
                                        <span><?= htmlspecialchars($account['city']) ?></span>
                                        <span><?= number_format($account['balance']) ?> BCT</span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="text-center mt-3">
                                <a href="../market.php" class="btn btn-sm btn-default">查看行情</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 账户安全 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-lock"></i> 账户安全</h3>
                </div>
                <div class="card-body">
                    <div class="security-status">
                        <div class="security-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>密码强度: 强</span>
                        </div>
                        <div class="security-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>邮箱已验证</span>
                        </div>
                        <div class="security-item">
                            <i class="glyphicon glyphicon-remove text-danger"></i>
                            <span>手机未绑定</span>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-sm btn-default">安全设置</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 页面特定样式 -->
<style>

</style>

<!-- 页面特定脚本 -->
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// 表单提交确认
document.querySelector('form').addEventListener('submit', function(e) {
    var newPass = document.getElementById('new_password').value;
    var confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('两次输入的密码不一致，请重新输入');
        document.getElementById('new_password').focus();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>