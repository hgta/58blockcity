<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../../classes/City.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$user = new User($pdo);

// 获取当前用户数据
$userData = $user->getUserById($userId);

// 处理表单提交
$errors = [];
$success = false;

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

$city = new City($pdo);
$cities = $city->getAllCities();
?>
<?php include '../includes/header.php'; ?>

<div class="container profile-container">
    <div class="row">
        <div class="col-md-3">
            <!-- 用户侧边栏 -->
            <div class="user-sidebar">
                <div class="user-profile-card">
                    <img src="../assets/images/<?php echo htmlspecialchars($userData['avatar']); ?>" 
                         alt="<?php echo htmlspecialchars($userData['username']); ?>" class="user-avatar">
                    <h4><?php echo htmlspecialchars($userData['username']); ?></h4>
                    <p><?php echo htmlspecialchars($userData['city']); ?></p>
                </div>
                
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> 仪表盘</a></li>
                    <li class="active"><a href="profile.php"><i class="fas fa-user"></i> 个人资料</a></li>
                    <li><a href="circles.php"><i class="fas fa-users"></i> 我的互访圈</a></li>
                    <li><a href="visits.php"><i class="fas fa-exchange-alt"></i> 互访记录</a></li>
                </ul>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="profile-content">
                <h2><i class="fas fa-user"></i> 个人资料</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">个人资料已成功更新！</div>
                <?php endif; ?>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general']); ?></div>
                <?php endif; ?>
                
                <form method="post" action="profile.php" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="username">用户名</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <small class="text-danger"><?php echo htmlspecialchars($errors['username']); ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="email">邮箱</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <small class="text-danger"><?php echo htmlspecialchars($errors['email']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="phone">手机号码</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="city">所在城市</label>
                            <select id="city" name="city" class="form-control" required>                                								
									<option value="">-- 请选择城市 --</option>
									<?php foreach ($cities as $city): ?>
										<option value="<?= htmlspecialchars($city['name']) ?>" <?= 
											(isset($userData['city']) && $userData['city'] === $city['name']) ? 'selected' : '' ?>>
											<?= htmlspecialchars($city['name']) ?>
										</option>
									<?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="password">新密码 (留空则不修改)</label>
                            <input type="password" id="password" name="password" class="form-control">
                            <?php if (isset($errors['password'])): ?>
                                <small class="text-danger"><?php echo htmlspecialchars($errors['password']); ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="password_confirm">确认新密码</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control">
                            <?php if (isset($errors['password_confirm'])): ?>
                                <small class="text-danger"><?php echo htmlspecialchars($errors['password_confirm']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                
                <!-- 头像上传 -->
                <div class="avatar-upload-section">
                    <h4>头像设置</h4> 
                        <div class="current-avatar">
                            <img src="../../assets/images/<?php echo htmlspecialchars($userData['avatar']); ?>" 
                                 alt="当前头像" class="avatar-preview">
                        </div>
                        <div class="form-group">
                            <label for="avatar">选择新头像</label>
                            <input type="file" id="avatar" name="avatar" class="form-control-file" accept="image/*">
                            <small class="form-text text-muted">支持JPG, PNG格式，大小不超过2MB</small>
                        </div>
                   
                </div>
				
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">更新资料</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>