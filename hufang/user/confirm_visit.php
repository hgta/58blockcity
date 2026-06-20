<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';
require_once '../../classes/User.php';
require_once '../../classes/Notification.php';

checkLogin();

$visitId = intval($_GET['id']) ?? 0;
$visit = new Visit($pdo);
$circle = new Circle($pdo);
$user = new User($pdo);
$notification = new Notification($pdo);

// 获取访问记录详情
$visitInfo = $visit->getVisitById($visitId);
if (!$visitInfo) {
    header('Location: visits.php');
    exit;
}

// 验证当前用户是否有权限确认此访问
$currentUserId = $_SESSION['user_id'];
if ($currentUserId != $visitInfo['owner_id']) {
    header('Location: visits.php');
    exit;
}

// 处理表单提交
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $visitDate = $_POST['visit_date'] ?? date('Y-m-d');
    $nextSuggestDate = date('Y-m-d', strtotime($visitDate . ' +6 months'));
    $notes = $_POST['notes'] ?? '';
    
    // 处理文件上传
    $screenshotPath = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/visit_screenshots/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 生成唯一文件名
        $fileExt = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('visit_') . '.' . $fileExt;
        $uploadPath = $uploadDir . $fileName;
        
        // 验证文件类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['screenshot']['type'], $allowedTypes)) {
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadPath)) {
                $screenshotPath = 'uploads/visit_screenshots/' . $fileName;
            } else {
                $error = "文件上传失败";
            }
        } else {
            $error = "只允许上传JPG, PNG或GIF图片";
        }
    }
    
    if (!$error) {
        if ($visit->confirmVisit($visitId, $visitDate, $nextSuggestDate, $notes, $screenshotPath)) {
            // 发送通知给访问者
            $message = "您的访问请求已被确认 - {$visitInfo['circle_name']}";
            $notification->create(
                $visitInfo['visitor_id'],
                'visit_confirm',
                $visitId,
                $message
            );
            
            $_SESSION['flash_message'] = '访问已成功确认';
            header("Location: visits.php");
            exit;
        } else {
            $error = '确认访问时出错，请稍后再试';
        }
    }
}

// 计算默认建议日期
$defaultNextDate = date('Y-m-d', strtotime('+6 months'));
?>

<?php require_once '../includes/header.php'; ?>

<div class="container confirm-visit-container">
    <div class="page-header">
        <h1><i class="fas fa-check-circle"></i> 确认访问</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="visits.php">访问记录</a></li>
                <li class="breadcrumb-item"><a href="visit_detail.php?id=<?= $visitId ?>">访问详情</a></li>
                <li class="breadcrumb-item active" aria-current="page">确认访问</li>
            </ol>
        </nav>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> 访问信息</h3>
                </div>
                <div class="card-body">
                    <div class="visit-info-summary">
                        <div class="info-row">
                            <label>互访圈:</label>
                            <span><?= htmlspecialchars($visitInfo['circle_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <label>访问者:</label>
                            <span>
                                <img src="../assets/images/<?= htmlspecialchars($visitInfo['visitor_avatar'] ?? 'default.jpg') ?>" 
                                     class="avatar-sm" alt="<?= htmlspecialchars($visitInfo['visitor_name']) ?>">
                                <?= htmlspecialchars($visitInfo['visitor_name']) ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <label>申请时间:</label>
                            <span><?= date('Y-m-d H:i', strtotime($visitInfo['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <form method="post">
                            <?= csrfField() ?>
                         class="mt-4" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="visit_date">实际访问日期 *</label>
                            <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                            <small class="form-text text-muted">请填写访问实际发生的日期</small>
                        </div>
                        
                        <div class="form-group">
                            <label>下次建议互访时间</label>
                            <input type="text" class="form-control" value="<?= $defaultNextDate ?>" readonly>
                            <small class="form-text text-muted">系统自动计算 (6个月后)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="screenshot">访问证明截图</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="screenshot" name="screenshot" accept="image/*">
                                <label class="custom-file-label" for="screenshot">选择图片文件...</label>
                            </div>
                            <small class="form-text text-muted">请上传能证明访问发生的截图</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">备注 (可选)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= 
                                htmlspecialchars($visitInfo['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check"></i> 确认访问
                            </button>
                            <a href="visit_detail.php?id=<?= $visitId ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> 取消
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// 初始化日期选择器和文件上传显示
document.addEventListener('DOMContentLoaded', function() {
    // 设置日期最大值
    const visitDateInput = document.getElementById('visit_date');
    if (visitDateInput) {
        visitDateInput.max = new Date().toISOString().split('T')[0];
    }
    
    // 显示选择的文件名
    document.querySelector('.custom-file-input').addEventListener('change', function(e) {
        var fileName = e.target.files[0] ? e.target.files[0].name : "选择图片文件...";
        var nextSibling = e.target.nextElementSibling;
        nextSibling.innerText = fileName;
    });
});
</script>