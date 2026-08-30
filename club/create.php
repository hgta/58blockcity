<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../classes/User.php';
require_once '../classes/SeoHelper.php';
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
    validateCsrfToken();
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
        // 跳转伪静态详情页
        $title = $type === 'post' ? $data['title'] : $data['content'];
        header('Location: ' . SeoHelper::postUrl($result, $title));
        exit;
    }
    $err = $result;
}

$site_config['title'] = ($type === 'moment' ? '发心情' : '发帖') . ' - 58区块社区';
$site_config['description'] = SeoHelper::description(($type === 'moment' ? '发布你的心情' : '发布帖子') . '，分享见闻与观点。');
$site_config['canonical_url'] = 'https://club.58.tl/create.php?type=' . $type;
$site_config['og_url'] = $site_config['canonical_url'];
require_once 'includes/header.php';
?>

<div class="club-layout">

  <!-- ============ 主内容 ============ -->
  <main class="club-main">
    <div class="club-header-bar">
      <h1><?= $type === 'moment' ? '发心情' : '发帖' ?></h1>
    </div>

    <div class="club-form-card">
      <?php if ($err): ?><div class="club-alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <div class="club-type-switch" style="margin-bottom:8px;">
        <a href="create.php?type=post" class="<?= $type === 'post' ? 'active' : '' ?>"><i class="fas fa-pen"></i> 发帖</a>
        <a href="create.php?type=moment" class="<?= $type === 'moment' ? 'active' : '' ?>"><i class="fas fa-smile"></i> 发心情</a>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="type" value="<?= $type ?>">

        <?php if ($type === 'post'): ?>
        <div class="club-form-group">
          <label class="club-form-label">标题</label>
          <input type="text" name="title" class="club-form-input" maxlength="100" required placeholder="一句话概括主题">
        </div>
        <?php endif; ?>

        <div class="club-form-group">
          <label class="club-form-label"><?= $type === 'moment' ? '此刻的想法' : '正文内容' ?></label>
          <textarea name="content" class="club-form-textarea" required placeholder="<?= $type === 'moment' ? '分享你此刻的心情...' : '分享你的想法...' ?>"></textarea>
        </div>

        <div class="club-form-group">
          <label class="club-form-label">配图<?= $type === 'moment' ? '（心情最多 1 张）' : '（可多图）' ?></label>
          <input type="file" name="images[]" accept="image/*" <?= $type === 'moment' ? '' : 'multiple' ?> class="club-form-input" id="image-input">
          <div class="club-img-preview" id="img-preview"></div>
          <?php if ($type === 'moment'): ?><div class="hint" style="font-size:12px;color:#999;margin-top:4px;">心情最多上传 1 张图片</div><?php endif; ?>
        </div>

        <div class="club-form-group">
          <label class="club-form-label">城市</label>
          <select name="city" class="club-form-input">
            <option value="">不指定</option>
            <?php foreach ($hotCities as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $c === $myCity ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="club-form-group">
          <label class="club-form-label">话题（可选）</label>
          <select name="topic" class="club-form-input">
            <option value="">不指定话题</option>
            <option value="block">聊区块</option>
            <option value="nft">聊头像</option>
            <option value="bct">聊人气值</option>
          </select>
        </div>

        <button type="submit" class="club-btn primary"><i class="fas fa-paper-plane"></i> 发布</button>
      </form>
    </div>
  </main>

  <!-- ============ 右侧栏 ============ -->
  <?php require_once 'includes/sidebar.php'; ?>

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
