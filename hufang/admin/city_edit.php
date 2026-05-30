<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';

//checkAdminLogin();
checkLogin();

$userId = $_SESSION['user_id'];
if ($userId != 1) {
    header('Location: ../user/dashboard.php');
    exit();
}

$city = new City($pdo);

// 获取城市信息
$cityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cityData = $city->getCityById($cityId);

if (!$cityData) {
    $_SESSION['error'] = '城市不存在！';
    header('Location: cities.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name']),
        'pinyin' => trim($_POST['pinyin']),
        'is_hot' => isset($_POST['is_hot']) ? 1 : 0,
        'area_code' => trim($_POST['area_code']),
        'rank' => (int)$_POST['rank'],
        'resident_count' => (int)$_POST['resident_count'],
        'activated_blocks' => (int)$_POST['activated_blocks'],
        'popularity' => (int)$_POST['popularity']
    ];
    
    if ($city->updateCity($cityId, $data)) {
        $_SESSION['success'] = '城市更新成功！';
        header('Location: cities.php');
        exit;
    } else {
        $error = '城市更新失败！';
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php //require_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">编辑城市</h1>
                <a href="cities.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> 返回列表
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">城市名称 *</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cityData['name']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">拼音名称 *</label>
                                    <input type="text" name="pinyin" class="form-control" value="<?= htmlspecialchars($cityData['pinyin']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">区域代码</label>
                                    <input type="text" name="area_code" class="form-control" value="<?= htmlspecialchars($cityData['area_code']) ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">当前排名</label>
                                    <input type="number" name="rank" class="form-control" value="<?= $cityData['rank'] ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">居民数量</label>
                                    <input type="number" name="resident_count" class="form-control" value="<?= $cityData['resident_count'] ?>" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">开启区块数</label>
                                    <input type="number" name="activated_blocks" class="form-control" value="<?= $cityData['activated_blocks'] ?>" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">人气值</label>
                                    <input type="number" name="popularity" class="form-control" value="<?= $cityData['popularity'] ?>" min="0">
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_hot" class="form-check-input" id="is_hot" <?= $cityData['is_hot'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_hot">设为热门城市</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 更新城市
                            </button>
                            <a href="cities.php" class="btn btn-secondary">取消</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>