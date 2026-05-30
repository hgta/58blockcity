<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';

checkLogin();

$circleId = $_GET['id'] ?? 0;
$circle = new Circle($pdo);
$circleInfo = $circle->getCircleById($circleId);

// 验证当前用户是否有权限编辑该互访圈
if (!$circleInfo || $circleInfo['user_id'] != $_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $block_count = intval($_POST['block_count'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');

    if (empty($name) || empty($city)) {
        $error = '互访圈名称和所在城市是必填项';
    } else {
        $stmt = $pdo->prepare("UPDATE circles SET name = ?, description = ?, city = ?, category = 'BlockCity', block_count = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $city, $block_count, $status, $circleId])) {
            $success = '互访圈信息已更新！';
            // 刷新数据
            $circleInfo = $circle->getCircleById($circleId);
        } else {
            $error = '更新互访圈时出错，请稍后再试';
        }
    }
}

$cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '苏州', '天津', '南京', '合肥', '无锡', '中国数藏'];
$statuses = ['active' => '活跃', 'inactive' => '停用'];
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> 编辑互访圈</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../user/circles.php">我的互访圈</a></li>
                <li class="breadcrumb-item"><a href="view.php?id=<?= $circleId ?>"><?= htmlspecialchars($circleInfo['name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">编辑</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" class="circle-form">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> 基本信息</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">互访圈名称 *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($circleInfo['name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="description">互访圈描述</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= 
                                htmlspecialchars($circleInfo['description']) ?></textarea>
                            <small class="form-text text-muted">描述您的互访圈特色或访问要求</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="city">所在城市 *</label>
                                    <select class="form-control" id="city" name="city" required>
                                        <option value="">-- 请选择城市 --</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?= htmlspecialchars($city) ?>" <?= 
                                                $city === $circleInfo['city'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($city) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="block_count">区块数量</label>
                                    <input type="number" class="form-control" id="block_count" name="block_count" 
                                           min="0" value="<?= htmlspecialchars($circleInfo['block_count'] ?? 0) ?>">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="category" value="BlockCity">

                        <div class="form-group">
                            <label for="status">互访圈状态</label>
                            <select class="form-control" id="status" name="status">
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= 
                                        $key === $circleInfo['status'] ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> 保存更改
                    </button>
                    <a href="view.php?id=<?= $circleId ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> 取消
                    </a>
                    <button type="button" class="btn btn-outline-danger float-right" data-toggle="modal" data-target="#deleteModal">
                        <i class="fas fa-trash"></i> 删除互访圈
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 删除确认模态框 -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">确认删除互访圈</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>您确定要删除这个互访圈吗？此操作无法撤销。</p>
                <p class="text-danger"><strong>注意：</strong>所有相关的访问记录也将被删除。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <a href="delete.php?id=<?= $circleId ?>" class="btn btn-danger">确认删除</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// 表单提交确认
document.querySelector('.circle-form').addEventListener('submit', function(e) {
    const statusSelect = document.getElementById('status');
    if (statusSelect.value === 'inactive') {
        if (!confirm('将互访圈设为"停用"状态后，其他用户将无法申请访问。确定要继续吗？')) {
            e.preventDefault();
        }
    }
});
</script>