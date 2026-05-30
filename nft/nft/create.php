<?php
require_once  '../../config/database.php';
require_once  '../includes/auth.php';
require_once  '../../classes/NFT.php';
require_once  '../../classes/UserPopularity.php';

// 验证用户登录
checkLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 初始化类
$nft = new NFT($pdo);
$popularity = new UserPopularity($pdo);

// 获取用户在当前城市的人气值
$userCity = $_SESSION['city'] ?? '北京'; // 默认城市
$userPopularity = $popularity->getUserPopularity($userId, $userCity);

// 处理表单提交
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证表单数据
    $code = trim($_POST['code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $rarity = trim($_POST['rarity'] ?? 'common');
    $description = trim($_POST['description'] ?? '');
    
    // 验证NFT编号格式 (2字母+2数字)
    if (!preg_match('/^[A-Z]{2}\d{2}$/', $code)) {
        $errors['code'] = '编号格式不正确，必须为2个大写字母加2个数字 (例如: AB12)';
    }
    
    // 验证城市是否有效
    $validCities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '苏州', '天津', '南京'];
    if (!in_array($city, $validCities)) {
        $errors['city'] = '请选择有效的城市';
    }
    
    // 验证稀有度
    $validRarities = ['common', 'rare', 'epic', 'legendary'];
    if (!in_array($rarity, $validRarities)) {
        $errors['rarity'] = '请选择有效的稀有度';
    }
    
    // 验证描述长度
    if (strlen($description) > 500) {
        $errors['description'] = '描述不能超过500个字符';
    }
    
    // 处理图片上传
    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES['image']['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors['image'] = '只允许上传JPEG, PNG或GIF图片';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors['image'] = '图片大小不能超过2MB';
        } else {
            // 生成唯一文件名
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = uniqid('nft_') . '.' . $extension;
            $uploadDir = __DIR__ . '/../assets/nfts/';
            $imagePath = 'assets/nfts/' . $imageName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
                $errors['image'] = '图片上传失败';
            }
        }
    } else {
        $errors['image'] = '请上传头像图片';
    }
    
    // 检查用户是否已有10个NFT
    $userCollection = $nft->getUserCollection($userId);
    if (count($userCollection) >= 10) {
        $errors['general'] = '每个用户最多只能持有10个NFT头像';
    }
    
    // 如果没有错误，创建NFT
    if (empty($errors)) {
        if ($nft->create($userId, $code, $city, $rarity, $description, $imagePath)) {
            $success = true;
            
            // 清空表单值
            $_POST = [];
        } else {
            $errors['general'] = '创建NFT失败，请稍后再试';
            
            // 删除已上传的图片（如果创建失败）
            if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                unlink(__DIR__ . '/../' . $imagePath);
            }
        }
    } else {
        // 删除已上传的图片（如果有验证错误）
        if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
            unlink(__DIR__ . '/../' . $imagePath);
        }
    }
}

// 获取城市列表
$cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '苏州', '天津', '南京'];
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-plus-circle"></i> 创建NFT头像</h3>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> NFT头像创建成功！
                            <a href="../nft/view.php?id=<?= $nft->lastInsertId ?>" class="alert-link">查看详情</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- 图片上传 -->
                                <div class="form-group">
                                    <label for="image">头像图片 *</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input <?= isset($errors['image']) ? 'is-invalid' : '' ?>" 
                                               id="image" name="image" accept="image/*" required>
                                        <label class="custom-file-label" for="image">选择图片...</label>
                                        <?php if (isset($errors['image'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['image']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-text text-muted">支持JPEG, PNG或GIF，最大2MB</small>
                                </div>
                                
                                <!-- 图片预览 -->
                                <div class="form-group">
                                    <div class="image-preview-container">
                                        <img id="imagePreview" src="../assets/images/default-nft.jpg" 
                                             class="img-thumbnail" alt="NFT预览">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- NFT编号 -->
                                <div class="form-group">
                                    <label for="code">NFT编号 *</label>
                                    <input type="text" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" 
                                           id="code" name="code" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" 
                                           placeholder="例如: AB12" required>
                                    <?php if (isset($errors['code'])): ?>
                                        <div class="invalid-feedback">
                                            <?= htmlspecialchars($errors['code']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">2个大写字母+2个数字，例如: AB12</small>
                                </div>
                                
                                <!-- 城市选择 -->
                                <div class="form-group">
                                    <label for="city">所属城市 *</label>
                                    <select class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>" 
                                            id="city" name="city" required>
                                        <option value="">选择城市</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?= htmlspecialchars($city) ?>" 
                                                <?= ($_POST['city'] ?? $userCity) === $city ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($city) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['city'])): ?>
                                        <div class="invalid-feedback">
                                            <?= htmlspecialchars($errors['city']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 稀有度选择 -->
                                <div class="form-group">
                                    <label for="rarity">稀有度 *</label>
                                    <select class="form-control <?= isset($errors['rarity']) ? 'is-invalid' : '' ?>" 
                                            id="rarity" name="rarity" required>
                                        <option value="common" <?= ($_POST['rarity'] ?? 'common') === 'common' ? 'selected' : '' ?>>普通</option>
                                        <option value="rare" <?= ($_POST['rarity'] ?? '') === 'rare' ? 'selected' : '' ?>>稀有</option>
                                        <option value="epic" <?= ($_POST['rarity'] ?? '') === 'epic' ? 'selected' : '' ?>>史诗</option>
                                        <option value="legendary" <?= ($_POST['rarity'] ?? '') === 'legendary' ? 'selected' : '' ?>>传说</option>
                                    </select>
                                    <?php if (isset($errors['rarity'])): ?>
                                        <div class="invalid-feedback">
                                            <?= htmlspecialchars($errors['rarity']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 描述 -->
                        <div class="form-group">
                            <label for="description">描述</label>
                            <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" 
                                      id="description" name="description" rows="3"
                                      placeholder="描述你的NFT头像..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['description']) ?>
                                </div>
                            <?php endif; ?>
                            <small class="form-text text-muted">最多500个字符</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" name="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    我确认此NFT头像符合平台规定，且我有权使用此图片
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> 创建NFT头像
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                当前在<strong><?= htmlspecialchars($userCity) ?></strong>的人气值: 
                                <span class="text-primary"><?= number_format($userPopularity) ?></span>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 图片预览功能
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('imagePreview').src = event.target.result;
        };
        reader.readAsDataURL(file);
        
        // 更新文件标签
        const fileName = file.name.length > 20 ? 
            file.name.substring(0, 10) + '...' + file.name.substring(file.name.length - 7) : 
            file.name;
        document.querySelector('.custom-file-label').textContent = fileName;
    }
});

// NFT编号输入格式验证
document.getElementById('code').addEventListener('input', function(e) {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (this.value.length > 4) {
        this.value = this.value.substring(0, 4);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>