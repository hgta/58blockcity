<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../classes/User.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
checkLogin();

$post = new Post($pdo);
$userObj = new User($pdo);
$userId = $_SESSION['user_id'];

$type = in_array($_GET['type'] ?? '', ['post', 'moment'], true) ? $_GET['type'] : 'post';

$msg = '';
$err = '';

// 用户城市
$myCity = '';
$u = $userObj->getUserById($userId);
$myCity = $u['city'] ?? '';

// 城市列表
$hotCities = [];
try {
    $stmt = $pdo->query("SELECT name FROM cities WHERE status='active' ORDER BY rank ASC LIMIT 100");
    $hotCities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $hotCities = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = in_array($_POST['type'] ?? '', ['post', 'moment'], true) ? $_POST['type'] : 'post';
    $images = [];

    // 处理图片上传（多图，数组形式）
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/assets/uploads/posts';
        $relPrefix = 'assets/uploads/posts/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            $fname = uniqid() . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . '/' . $fname)) {
                $images[] = $relPrefix . $fname;
            }
        }
    }

    $data = [
        'city'    => $_POST['city'] ?? '',
        'title'   => $_POST['title'] ?? '',
        'content' => $_POST['content'] ?? '',
        'images'  => $images,
        'topic'   => $_POST['topic'] ?? '',
    ];

    $result = $post->create($userId, $type, $data);
    if (is_int($result)) {
        header('Location: post.php?id=' . $result);
        exit;
    }
    $err = $result;
}

$site_config['title'] = ($type === 'moment' ? '发心情' : '发帖') . ' - 58区块社区';
require_once 'includes/header.php';
?>
<style>
.create-wrap { max-width: 680px; margin: 24px auto; padding: 0 15px; }
.create-card { background: #fff; border-radius: 12px; padding: 26px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.type-switch { display: flex; gap: 0; background: #f5f5f5; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
.type-btn { flex: 1; padding: 10px; text-align: center; font-size: 14px; font-weight: 600; color: #555; cursor: pointer; text-decoration: none; }
.type-btn.active { background: #ff6b00; color: #fff; }
.form-group { margin: 16px 0; }
.form-label { display: block; font-size: 14px; color: #555; margin-bottom: 6px; }
.form-input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.form-textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; min-height: 120px; resize: vertical; }
.form-select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: #fff; }
.btn-primary { background: #ff6b00; color: #fff; border: none; border-radius: 8px; padding: 12px 24px; font-size: 15px; cursor: pointer; font-weight: bold; }
.btn-primary:hover { background: #e05d00; }
.alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
.alert-err { background: #f8d7da; color: #721c24; }
.img-preview { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.img-preview img { width: 70px; height: 70px; object-fit: cover; border-radius: 6px; }
.hint { font-size: 12px; color: #999; margin-top: 4px; }
</style>

<div class="create-wrap">
    <h1 style="font-size: 24px; margin: 0 0 16px;"><?= $type === 'moment' ? '😊 发心情' : '📝 发帖' ?></h1>
    <?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="type-switch">
        <a class="type-btn <?= $type === 'post' ? 'active' : '' ?>" href="create.php?type=post">发帖</a>
        <a class="type-btn <?= $type === 'moment' ? 'active' : '' ?>" href="create.php?type=moment">发心情</a>
    </div>

    <div class="create-card">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="type" value="<?= $type ?>">

            <?php if ($type === 'post'): ?>
            <div class="form-group">
                <label class="form-label">标题</label>
                <input type="text" name="title" class="form-input" maxlength="100" required placeholder="一句话概括主题">
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label"><?= $type === 'moment' ? '此刻的想法' : '正文内容' ?></label>
                <textarea name="content" class="form-textarea" required placeholder="<?= $type === 'moment' ? '分享你此刻的心情...' : '分享你的想法...' ?>"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">配图<?= $type === 'moment' ? '（心情最多 1 张）' : '（可多图）' ?></label>
                <input type="file" name="images[]" accept="image/*" <?= $type === 'moment' ? '' : 'multiple' ?> class="form-input" id="image-input">
                <div class="img-preview" id="img-preview"></div>
                <?php if ($type === 'moment'): ?><div class="hint">心情最多上传 1 张图片</div><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">城市</label>
                <select name="city" class="form-select">
                    <option value="">不指定</option>
                    <?php foreach ($hotCities as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $c === $myCity ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">话题（可选）</label>
                <select name="topic" class="form-select">
                    <option value="">不指定话题</option>
                    <option value="block">聊区块</option>
                    <option value="nft">聊头像</option>
                    <option value="bct">聊人气值</option>
                    <option value="city">聊城市</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">发布</button>
        </form>
    </div>
</div>

<script>
// 图片预览 + 心情单图限制
var input = document.getElementById('image-input');
var preview = document.getElementById('img-preview');
input.addEventListener('change', function() {
    preview.innerHTML = '';
    var files = input.files;
    for (var i = 0; i < files.length; i++) {
        var f = files[i];
        if (!f.type.match(/^image\//)) continue;
        var reader = new FileReader();
        reader.onload = (function(file) {
            return function(e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            };
        })(f);
        reader.readAsDataURL(f);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
