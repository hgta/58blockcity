<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';

// 检查管理员权限
checkAdmin();

$city = new City($pdo);

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
    
    if ($city->addCity($data)) {
        $_SESSION['success'] = '城市添加成功！';
        header('Location: cities.php');
        exit;
    } else {
        $error = '城市添加失败！';
    }
}

$admin_site_config = ['site' => 'hufang', 'page_title' => '添加城市'];
require_once '../../shared/admin/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <a href="cities.php" class="admin-btn admin-btn-default"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>

<?php if (isset($error)): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-plus-circle"></i> 添加城市</span></div>
    <div class="admin-card-body">
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
                <div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">城市名称 *</label>
                        <input type="text" name="name" class="admin-form-input" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">拼音名称 *</label>
                        <input type="text" name="pinyin" class="admin-form-input" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">区域代码</label>
                        <input type="text" name="area_code" class="admin-form-input">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">当前排名</label>
                        <input type="number" name="rank" class="admin-form-input" value="0" min="0">
                    </div>
                </div>
                <div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">居民数量</label>
                        <input type="number" name="resident_count" class="admin-form-input" value="0" min="0">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">开启区块数</label>
                        <input type="number" name="activated_blocks" class="admin-form-input" value="0" min="0">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">人气值</label>
                        <input type="number" name="popularity" class="admin-form-input" value="0" min="0">
                    </div>
                    <div class="admin-form-group">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="is_hot" id="is_hot">
                            <span>设为热门城市</span>
                        </label>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;display:flex;gap:12px;justify-content:center;">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 保存城市</button>
                <a href="cities.php" class="admin-btn admin-btn-default">取消</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
