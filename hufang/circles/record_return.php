<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Visit.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Notification.php';

// 验证用户登录
checkLogin();

// 获取访问记录ID
$visitId = $_GET['id'] ?? 0;

// 初始化类
$visit = new Visit($pdo);
$circle = new Circle($pdo);
$notification = new Notification($pdo);

// 获取访问记录详情
$visitDetails = $visit->getVisitById($visitId);
if (!$visitDetails) {
    header("Location: ../user/dashboard.php?error=访问记录不存在");
    exit;
}

// 验证当前用户是否有权限记录回访
if ($visitDetails['visitor_id'] != $_SESSION['user_id']) {
    header("Location: ../user/dashboard.php?error=无权操作此记录");
    exit;
}

// 检查访问状态是否允许记录回访
if ($visitDetails['status'] != 'confirmed') {
    header("Location: ../user/dashboard.php?error=当前状态不能记录回访");
    exit;
}

// 处理表单提交
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnDate = $_POST['return_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';
    
    // 验证回访日期
    if (strtotime($returnDate) === false) {
        $error = "无效的日期格式";
    } else {
        // 处理文件上传
        $screenshotPath = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/return_screenshots/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一文件名
            $fileExt = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('return_') . '.' . $fileExt;
            $uploadPath = $uploadDir . $fileName;
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['screenshot']['type'], $allowedTypes)) {
                if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadPath)) {
                    $screenshotPath = 'uploads/return_screenshots/' . $fileName;
                } else {
                    $error = "文件上传失败";
                }
            } else {
                $error = "只允许上传JPG, PNG或GIF图片";
            }
        }
        
        if (!$error) {
            // 记录回访
            if ($visit->recordReturn($visitId, $returnDate, $notes, $screenshotPath)) {
                // 更新互访圈区块数
                //$circle->incrementBlockCount($visitDetails['circle_id']);
                
                // 发送通知给圈主
                $message = "用户 {$_SESSION['username']} 已完成对您圈子 '{$visitDetails['circle_name']}' 的回访";
                $notification->create(
                    $visitDetails['circle_owner_id'],
                    'return_confirm',
                    $visitId,
                    $message
                );
                
                // 重定向到用户仪表盘
                header("Location: ../user/dashboard.php?success=回访记录已保存");
                exit;
            } else {
                $error = "保存回访记录失败";
            }
        }
    }
}

// 获取互访圈信息
$circleInfo = $circle->getCircleById($visitDetails['circle_id']);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-undo"></i> 记录回访</h1>
        <p>请填写您对 <strong><?= htmlspecialchars($circleInfo['name']) ?></strong> 的回访信息</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="return_date">回访日期 *</label>
                    <input type="date" class="form-control" id="return_date" name="return_date" 
                           value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="screenshot">回访截图证明</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="screenshot" name="screenshot" accept="image/*">
                        <label class="custom-file-label" for="screenshot">选择图片文件...</label>
                    </div>
                    <small class="form-text text-muted">请上传能证明您已完成回访的截图（如页面截图、合影等）</small>
                </div>
                
                <div class="form-group">
                    <label for="notes">回访备注</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="请简要描述您的回访情况..."></textarea>
                </div>
                
                <div class="visit-info mb-4">
                    <h5><i class="fas fa-info-circle"></i> 原访问信息</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>访问日期:</strong> <?= htmlspecialchars($visitDetails['visit_date']) ?></p>
                            <p><strong>建议下次访问:</strong> <?= htmlspecialchars($visitDetails['next_suggest_date']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>互访圈:</strong> <?= htmlspecialchars($circleInfo['name']) ?></p>
                            <p><strong>所在城市:</strong> <?= htmlspecialchars($circleInfo['city']) ?></p>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> 提交回访记录
                </button>
                <a href="../user/dashboard.php" class="btn btn-outline-secondary">取消</a>
            </form>
        </div>
    </div>
</div>

<script>
// 显示选择的文件名
document.querySelector('.custom-file-input').addEventListener('change', function(e) {
    var fileName = e.target.files[0] ? e.target.files[0].name : "选择图片文件...";
    var nextSibling = e.target.nextElementSibling;
    nextSibling.innerText = fileName;
});
</script>

<?php require_once '../includes/footer.php'; ?>