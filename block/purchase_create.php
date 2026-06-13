<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/City.php';

// 未登录跳转
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$city = new City($pdo);
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cityId = intval($_POST['city_id'] ?? 0);
    $zone = strtoupper($_POST['zone'] ?? '');
    $blockNumber = trim($_POST['block_number'] ?? '');
    $maxPrice = $_POST['max_price'] ?? '';

    if ($cityId <= 0) {
        $errors[] = '请选择城市';
    }
    if (!preg_match('/^[A-HZ]$/', $zone)) {
        $errors[] = '请选择有效区域';
    }
    if ($blockNumber !== '' && !preg_match('/^\d{4}$/', $blockNumber)) {
        $errors[] = '区块编号格式不正确（4位数字）';
    }
    if ($maxPrice !== '' && (!is_numeric($maxPrice) || floatval($maxPrice) < 0)) {
        $errors[] = '最高出价必须是有效数字';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO purchase_requests (user_id, city_id, zone, block_number, max_price, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $cityId,
            $zone,
            $blockNumber ?: null,
            $maxPrice !== '' ? floatval($maxPrice) : null
        ]);
        if ($result) {
            $success = true;
        } else {
            $errors[] = '发布失败，请稍后重试';
        }
    }
}

$allCities = $city->getAllCities();
?>
<?php require_once 'includes/header.php'; ?>

<div class="container page-container" style="max-width:600px;">
    <h1 class="page-title"><i class="fas fa-plus-circle"></i> 发布求购</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 求购发布成功！
            <a href="purchase_list.php" class="btn-primary" style="margin-left:15px;">查看求购列表</a>
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?>
                    <div><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-card">
            <div class="form-group">
                <label>目标城市 <span class="required">*</span></label>
                <select name="city_id" required>
                    <option value="">请选择城市</option>
                    <?php foreach ($allCities as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>目标区域 <span class="required">*</span></label>
                <select name="zone" required>
                    <option value="">请选择区域</option>
                    <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                    <option value="<?= $z ?>"><?= $z ?>区</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>区块编号（可选）</label>
                <input type="text" name="block_number" placeholder="如：0101，留空表示任意区块" maxlength="4" pattern="\d{4}">
                <small>4位数字，前两位为列，后两位为行</small>
            </div>

            <div class="form-group">
                <label>最高出价（可选）</label>
                <input type="number" name="max_price" placeholder="¥" min="0" step="0.01">
                <small>留空表示面议</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">发布求购</button>
                <a href="purchase_list.php" class="btn-secondary">取消</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
