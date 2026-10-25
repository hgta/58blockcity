<?php
/**
 * 58拍卖子站 — 个人资料编辑（含头像上传）
 * 与 block / mall / bct 等子站保持一致：头像统一落盘主仓库 assets/images/uploads/avatars/
 */
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';
checkLogin();

$userId = $_SESSION['user_id'];
$user   = new User($pdo);
$userData = $user->getUserById($userId);

// 拍卖侧统计（个人中心同款）
$stats = ['my_auctions' => 0, 'my_bids' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auctions WHERE seller_id = ?");
    $stmt->execute([$userId]);
    $stats['my_auctions'] = intval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT auction_id) FROM auction_bids WHERE bidder_id = ?");
    $stmt->execute([$userId]);
    $stats['my_bids'] = intval($stmt->fetchColumn());
} catch (Exception $e) {
    // 表未创建时忽略
}

// ---- POST 处理（必须在任何 HTML 输出之前，redirect 才有效）----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requiredFields = ['username', 'email', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("请填写所有必填字段");
            }
        }

        // 头像上传（与 block/bct 子站一致：统一落盘主仓库 assets/images/uploads/avatars/）
        $avatarPath = $userData['avatar'] ?? '';   // 默认沿用原头像

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 2) . '/assets/images/uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("只允许上传 JPG、PNG、GIF 或 WebP 格式的图片");
            }
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                throw new Exception("头像图片大小不能超过 2MB");
            }

            // 先落临时文件，再统一缩放为 200x200 内的 JPEG
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
                case 'image/webp': $src = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpFile) : false; break;
                default: $src = false;
            }

            if (!$src) {
                imagedestroy($thumb);
                unlink($tmpFile);
                throw new Exception("图片处理失败，请换一张图片重试");
            }

            imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            $avatarFilename = $tmpName . '.jpg';
            $avatarPath = 'uploads/avatars/' . $avatarFilename;
            imagejpeg($thumb, $uploadDir . $avatarFilename, 90);
            imagedestroy($src);
            imagedestroy($thumb);
            unlink($tmpFile);

            // 删除旧头像文件（非默认头像时）
            $old = (string)($userData['avatar'] ?? '');
            if ($old !== '' && $old !== 'default.jpg'
                && file_exists(dirname(__DIR__, 2) . '/assets/images/' . $old)) {
                unlink(dirname(__DIR__, 2) . '/assets/images/' . $old);
            }
        }

        $updateData = [
            'username' => trim($_POST['username']),
            'email'    => trim($_POST['email']),
            'phone'    => trim($_POST['phone'] ?? '') ?: null,
            'city'     => trim($_POST['city']),
            'avatar'   => $avatarPath,
            'id'       => $userId,
        ];

        // 密码变更（留空表示不修改）
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
                throw new Exception("两次输入的密码不一致");
            }
            if (strlen($_POST['new_password']) < 6) {
                throw new Exception("新密码长度至少 6 位");
            }
            $updateData['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }

        if (!$user->updateUser($updateData)) {
            throw new Exception("资料更新失败");
        }

        // 同步 session，避免顶部导航仍显示旧昵称/头像
        $_SESSION['username'] = $updateData['username'];
        $_SESSION['avatar']   = $updateData['avatar'];

        $_SESSION['message'] = "资料更新成功";
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: profile.php");
        exit();
    }
}

// ---- 提示消息 ----
$pageMsg = '';
if (isset($_SESSION['message'])) {
    $pageMsg = '<div class="dash-alert dash-alert-ok">' . htmlspecialchars($_SESSION['message']) . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $pageMsg = '<div class="dash-alert dash-alert-err">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

$site_config['title'] = '编辑个人资料 - 58拍卖';
require_once '../includes/header.php';
?>
<style>
.dash-wrap { max-width: 760px; margin: 24px auto; padding: 0 15px; }
.dash-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 24px; box-shadow: var(--shadow); margin-bottom: 16px; }
.dash-card-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.dash-alert { padding: 10px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
.dash-alert-ok { background: rgba(34,197,94,.12); color: #16a34a; border: 1px solid rgba(34,197,94,.3); }
.dash-alert-err { background: rgba(239,68,68,.12); color: #dc2626; border: 1px solid rgba(239,68,68,.3); }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; font-weight: 600; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px; box-sizing: border-box; background: var(--surface-2); color: var(--text); }
.form-control:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 2px var(--brand-dim); }
.form-text { font-size: 12px; color: var(--muted); margin-top: 6px; }

.avatar-upload { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.avatar-preview { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; border: 3px solid var(--brand); flex-shrink: 0; background: var(--surface-2); }
.avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.btn-save { display: inline-block; background: var(--brand); color: #fff; border: none; border-radius: 8px; padding: 11px 28px; font-size: 15px; cursor: pointer; font-weight: 600; }
.btn-save:hover { filter: brightness(1.05); }
.btn-back { display: inline-block; padding: 10px 20px; background: var(--surface-2); color: var(--text); border: 1px solid var(--line); border-radius: 8px; text-decoration: none; }
</style>

<div class="dash-wrap">
    <h1 style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:18px;">编辑个人资料</h1>
    <?= $pageMsg ?>

    <div class="dash-card">
        <div class="dash-card-title"><i class="fas fa-user-circle"></i> 基本信息与头像</div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="avatar">头像</label>
                <div class="avatar-upload">
                    <div class="avatar-preview">
                        <img src="<?= htmlspecialchars(User::avatarUrl($userData['avatar'] ?? '')) ?>"
                             id="avatarPreview" alt="头像"
                             onerror="this.onerror=null;this.src='https://www.58.tl/assets/images/default.jpg'">
                    </div>
                    <div>
                        <input type="file" id="avatar" name="avatar" accept="image/*" onchange="previewImage(this)">
                        <div class="form-text">支持 JPG / PNG / GIF / WebP，不超过 2MB，建议方形图片，上传后自动缩放</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="username">用户名 *</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= htmlspecialchars($userData['username'] ?? '') ?>" required>
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

            <hr style="border:none;border-top:1px solid var(--line);margin:20px 0;">

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

    <div class="dash-card">
        <div class="dash-card-title"><i class="fas fa-chart-bar"></i> 我的拍卖概览</div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
            <div style="text-align:center;padding:16px;background:var(--surface-2);border-radius:10px;">
                <div style="font-size:22px;font-weight:800;color:var(--brand);"><?= $stats['my_auctions'] ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px;">我发起的拍卖</div>
            </div>
            <div style="text-align:center;padding:16px;background:var(--surface-2);border-radius:10px;">
                <div style="font-size:22px;font-weight:800;color:var(--brand);"><?= $stats['my_bids'] ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px;">我参与的拍卖</div>
            </div>
        </div>
    </div>

    <a class="btn-back" href="dashboard.php"><i class="fas fa-arrow-left"></i> 返回个人中心</a>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
document.querySelector('form').addEventListener('submit', function (e) {
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
