<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Block.php';
require_once '../../classes/UserHoldings.php';
require_once '../../config/block_prices.php';

checkLogin();
$userId = $_SESSION['user_id'];
$user = new User($pdo);
$block = new Block($pdo);

$userData = $user->getUserById($userId);
$actualStats = $block->getUserActualBlockStats($userId);
$blockCount = $actualStats['block_count'];
$totalValue = $actualStats['total_value'];
try {
    $totalPopularity = array_sum(array_values((new UserHoldings($pdo))->getUserPopularityMap($userId)));
} catch (Exception $e) {
    error_log('block profile popularity error: ' . $e->getMessage());
    $totalPopularity = 0;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requiredFields = ['username', 'email', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("请填写所有必填字段");
            }
        }

        // 处理头像上传（与 bct 子站一致：统一落盘主仓库 assets/images/uploads/avatars/）
        $avatarPath = $userData['avatar']; // 默认为原头像

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 2) . '/assets/images/uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // 验证文件类型与大小
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("只允许上传 JPG, PNG 或 GIF 格式的图片");
            }
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                throw new Exception("头像图片大小不能超过2MB");
            }

            // 生成唯一文件名，先落临时文件再缩放为 200x200 内的 JPEG
            $tmpName = 'avatar_' . $userId . '_' . time();
            $tmpFile = $uploadDir . 'tmp_' . $tmpName . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $tmpFile)) {
                throw new Exception("头像上传失败");
            }

            list($origWidth, $origHeight) = getimagesize($tmpFile);
            $maxSize = 200;
            $ratio = min($maxSize / $origWidth, $maxSize / $origHeight);
            $newWidth  = max(1, (int)($origWidth * $ratio));
            $newHeight = max(1, (int)($origHeight * $ratio));

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
                $avatarFilename = $tmpName . '.jpg';
                $avatarPath = 'uploads/avatars/' . $avatarFilename;
                imagejpeg($thumb, $uploadDir . $avatarFilename, 90);
                imagedestroy($src);
                imagedestroy($thumb);
            }
            unlink($tmpFile);

            // 删除旧头像文件（非默认头像时）
            if ($userData['avatar'] && $userData['avatar'] !== 'default.jpg'
                && file_exists(dirname(__DIR__, 2) . '/assets/images/' . $userData['avatar'])) {
                unlink(dirname(__DIR__, 2) . '/assets/images/' . $userData['avatar']);
            }
        }

        // 更新用户信息
        $updateData = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'city' => trim($_POST['city']),
            'avatar' => $avatarPath,
            'id' => $userId
        ];

        // 密码变更（留空表示不修改）
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("两次输入的密码不一致");
            }
            if (strlen($_POST['new_password']) < 6) {
                throw new Exception("新密码长度至少 6 位");
            }
            $updateData['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }

        $result = $user->updateUser($updateData);
        if (!$result) {
            throw new Exception("资料更新失败");
        }

        $_SESSION['message'] = "资料更新成功";
        $_SESSION['username'] = $updateData['username'];
        $_SESSION['avatar'] = $updateData['avatar'];
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: profile.php");
        exit();
    }
}

// 显示消息
$pageMsg = '';
if (isset($_SESSION['message'])) {
    $pageMsg = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['message']) . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $pageMsg = '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:800px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; }
.card { background:white; border-radius:8px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px; }
.card-title { font-size:18px; font-weight:bold; border-bottom:2px solid #f0f0f0; padding-bottom:10px; margin-bottom:15px; }
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:15px; }
.stat-item { text-align:center; padding:14px 6px; background:#f8f9fa; border-radius:8px; }
.stat-value { font-size:20px; font-weight:bold; color:#ff6b00; font-variant-numeric:tabular-nums; }
.stat-label { font-size:12px; color:#666; margin-top:4px; }
@media(max-width:600px){ .stat-grid { grid-template-columns:repeat(3,1fr); gap:8px; } .stat-value { font-size:16px; } }

/* 表单 */
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-size:14px; color:#555; margin-bottom:6px; font-weight:500; }
.form-control { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box; }
.form-control:focus { outline:none; border-color:#ff6b00; box-shadow:0 0 0 2px rgba(255,107,0,.12); }
.form-text { font-size:12px; color:#999; margin-top:4px; }

/* 头像上传 */
.avatar-upload { display:flex; align-items:center; gap:18px; }
.avatar-preview { width:84px; height:84px; border-radius:50%; overflow:hidden; border:3px solid #ff6b00; flex-shrink:0; background:#f5f5f5; }
.avatar-preview img { width:100%; height:100%; object-fit:cover; }
.avatar-file { font-size:13px; }
.btn-save { display:inline-block; background:#ff6b00; color:#fff; border:none; border-radius:6px; padding:11px 28px; font-size:15px; cursor:pointer; }
.btn-save:hover { background:#e55a00; }
</style>

<div class="container">
    <h1 class="page-title">编辑个人资料</h1>
    <?= $pageMsg ?>

    <div class="card">
        <div class="card-title"><i class="fas fa-user"></i> 基本信息与头像</div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="avatar">头像</label>
                <div class="avatar-upload">
                    <div class="avatar-preview">
                        <img src="<?= htmlspecialchars(User::avatarUrl($userData['avatar'] ?? '')) ?>"
                             id="avatarPreview" alt="头像"
                             onerror="this.onerror=null;this.src='https://www.58.tl/assets/images/default.jpg'">
                    </div>
                    <div class="avatar-file">
                        <input type="file" id="avatar" name="avatar" accept="image/*" onchange="previewImage(this)">
                        <div class="form-text">支持 JPG / PNG / GIF，不超过 2MB，建议方形图片，上传后自动缩放</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="username">用户名 *</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= htmlspecialchars($userData['username']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">电子邮箱 *</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">手机号码</label>
                <input type="tel" class="form-control" id="phone" name="phone"
                       value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="city">所在城市 *</label>
                <input type="text" class="form-control" id="city" name="city"
                       value="<?= htmlspecialchars($userData['city'] ?? '') ?>" required>
            </div>

            <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0;">

            <div class="form-group">
                <label for="new_password">新密码（不需要修改请留空）</label>
                <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
            </div>

            <button type="submit" class="btn-save"><i class="fas fa-save"></i> 保存更改</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-chart-bar"></i> 资产概览</div>
        <div class="stat-grid">
            <div class="stat-item">
                <div class="stat-value"><?= $blockCount ?></div>
                <div class="stat-label">拥有区块</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">¥<?= number_format($totalValue) ?></div>
                <div class="stat-label">区块价值</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($totalPopularity) ?></div>
                <div class="stat-label">人气值</div>
            </div>
        </div>
    </div>

    <a href="dashboard.php" style="display:inline-block;padding:10px 20px;background:#3498db;color:white;border-radius:6px;text-decoration:none;">
        <i class="fas fa-arrow-left"></i> 返回仪表盘
    </a>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
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
