<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Visit.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

// 检查管理员权限
checkAdmin();

$visitId = $_GET['id'] ?? 0;
$visit = new Visit($pdo);
$circle = new Circle($pdo);
$user = new User($pdo);

// 获取访问记录详情
$visitInfo = $visit->getVisitDetailForAdmin($visitId);
if (!$visitInfo) {
    header('Location: visits.php?error=访问记录不存在');
    exit;
}

// 处理状态变更
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = $_POST['admin_notes'] ?? '';
    
    switch ($action) {
        case 'confirm':
            $visitDate = $_POST['visit_date'] ?? date('Y-m-d');
            if ($visit->adminConfirmVisit($visitId, $visitDate, $notes)) {
                $_SESSION['flash_message'] = '访问已成功确认';
                header("Location: visit_detail.php?id=$visitId");
                exit;
            }
            break;
            
        case 'complete':
            $returnDate = $_POST['return_date'] ?? date('Y-m-d');
            if ($visit->adminCompleteVisit($visitId, $returnDate, $notes)) {
                // 更新区块计数
                $circle->incrementBlockCount($visitInfo['circle_id']);
                $_SESSION['flash_message'] = '回访已成功记录';
                header("Location: visit_detail.php?id=$visitId");
                exit;
            }
            break;
            
        case 'cancel':
            if ($visit->adminCancelVisit($visitId, $notes)) {
                $_SESSION['flash_message'] = '访问已取消';
                header("Location: visit_detail.php?id=$visitId");
                exit;
            }
            break;
    }
    
    $error = '操作失败，请稍后再试';
}

// 获取相关用户信息
$visitorInfo = $user->getUserById($visitInfo['visitor_id']);
$ownerInfo = $user->getUserById($visitInfo['owner_id']);

$admin_site_config = ['site' => 'hufang', 'page_title' => '访问详情'];
require_once '../../shared/admin/admin-header.php';
?>

<div class="container admin-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> 访问记录详情</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">管理面板</a></li>
                <li class="breadcrumb-item"><a href="visits.php">访问记录管理</a></li>
                <li class="breadcrumb-item active" aria-current="page">访问详情</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_message'] ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-info-circle"></i> 基本信息</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item">
                        <label>互访圈:</label>
                        <span>
                            <a href="../circles/view.php?id=<?= $visitInfo['circle_id'] ?>">
                                <?= htmlspecialchars($visitInfo['circle_name']) ?>
                            </a>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>圈主:</label>
                        <span>
                            <img src="../../assets/images/<?= htmlspecialchars($ownerInfo['avatar'] ?? 'default.jpg') ?>" 
                                 class="avatar-xs rounded-circle">
                            <?= htmlspecialchars($ownerInfo['username']) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>所在城市:</label>
                        <span><?= htmlspecialchars($visitInfo['circle_city']) ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>访问者:</label>
                        <span>
                            <img src="../../assets/images/<?= htmlspecialchars($visitorInfo['avatar'] ?? 'default.jpg') ?>" 
                                 class="avatar-xs rounded-circle">
                            <?= htmlspecialchars($visitorInfo['username']) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>访问状态:</label>
                        <span class="status-badge <?= $visitInfo['status'] ?>">
                            <?= $visitInfo['status'] ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>申请时间:</label>
                        <span><?= date('Y-m-d H:i', strtotime($visitInfo['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-calendar-alt"></i> 访问时间信息</h3>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <label>申请访问日期:</label>
                        <span><?= $visitInfo['created_at'] ? date('Y-m-d', strtotime($visitInfo['created_at'])) : '-' ?></span>
                    </div>
                    <div class="info-item">
                        <label>实际访问日期:</label>
                        <span><?= $visitInfo['visit_date'] ?? '-' ?></span>
                    </div>
                    <div class="info-item">
                        <label>建议下次访问:</label>
                        <span><?= $visitInfo['next_suggest_date'] ?? '-' ?></span>
                    </div>
                    <div class="info-item">
                        <label>实际回访日期:</label>
                        <span><?= $visitInfo['return_date'] ?? '-' ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-comments"></i> 备注信息</h3>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <label>访问者备注:</label>
                        <p class="notes"><?= $visitInfo['notes'] ? nl2br(htmlspecialchars($visitInfo['notes'])) : '无' ?></p>
                    </div>
                    <div class="info-item">
                        <label>管理员备注:</label>
                        <p class="notes"><?= $visitInfo['admin_notes'] ? nl2br(htmlspecialchars($visitInfo['admin_notes'])) : '无' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($visitInfo['screenshot_path']): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-camera"></i> 访问证明</h3>
        </div>
        <div class="card-body text-center">
            <img src="../../<?= htmlspecialchars($visitInfo['screenshot_path']) ?>" 
                 class="img-fluid rounded" style="max-height: 400px;" alt="访问证明截图">
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-cog"></i> 管理操作</h3>
        </div>
        <div class="card-body">
            <?php if ($visitInfo['status'] == 'pending'): ?>
                <form method="post" class="mb-4">
                    <h5><i class="fas fa-check"></i> 确认访问</h5>
                    <div class="form-group">
                        <label for="visit_date">实际访问日期</label>
                        <input type="date" class="form-control" id="visit_date" name="visit_date" 
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="admin_notes">管理员备注</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="2"></textarea>
                    </div>
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> 确认访问
                    </button>
                </form>
                
                <form method="post">
                    <h5><i class="fas fa-times"></i> 取消访问</h5>
                    <div class="form-group">
                        <label for="cancel_notes">取消原因</label>
                        <textarea class="form-control" id="cancel_notes" name="admin_notes" rows="2" required></textarea>
                    </div>
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> 取消访问
                    </button>
                </form>
            
            <?php elseif ($visitInfo['status'] == 'confirmed'): ?>
                <form method="post">
                    <h5><i class="fas fa-undo"></i> 记录回访</h5>
                    <div class="form-group">
                        <label for="return_date">回访日期</label>
                        <input type="date" class="form-control" id="return_date" name="return_date" 
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="complete_notes">管理员备注</label>
                        <textarea class="form-control" id="complete_notes" name="admin_notes" rows="2"></textarea>
                    </div>
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-double"></i> 记录回访完成
                    </button>
                </form>
            
            <?php else: ?>
                <div class="alert alert-info">
                    此访问记录已处于最终状态（<?= $visitInfo['status'] ?>），无法再进行操作
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>

<style>
.info-item {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}
.info-item label {
    font-weight: bold;
    color: #666;
    min-width: 120px;
    display: inline-block;
}
.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: bold;
    text-transform: capitalize;
}
.status-badge.pending {
    background-color: #fff3cd;
    color: #856404;
}
.status-badge.confirmed {
    background-color: #cce5ff;
    color: #004085;
}
.status-badge.completed {
    background-color: #d4edda;
    color: #155724;
}
.status-badge.cancelled {
    background-color: #f8d7da;
    color: #721c24;
}
.notes {
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.25rem;
    border-left: 3px solid #007bff;
}
</style>

<script>
// 初始化日期选择器
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('visit_date').max = today;
    document.getElementById('return_date').max = today;
});
</script>