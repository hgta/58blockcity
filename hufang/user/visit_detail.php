<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';
require_once '../../classes/User.php';
require_once '../../classes/Notification.php';

checkLogin();

$visitId = intval(\$_GET['id']) ?? 0;
$visit = new Visit($pdo);
$circle = new Circle($pdo);
$user = new User($pdo);
$notification = new Notification($pdo);

// 获取访问记录详情
$visitInfo = $visit->getVisitById($visitId);
if (!$visitInfo) {
    header('Location: ../user/visits.php');
    exit;
}

$visitCircleInfo = $visit->getVisitCircleById($visitId);//vt


// 获取相关数据
$circleInfo = $circle->getCircleById($visitInfo['circle_id']);
$visitorInfo = $user->getUserById($visitInfo['visitor_id']);
$ownerInfo = $user->getUserById($circleInfo['user_id']);

// 检查当前用户是否有权限查看此记录
$currentUserId = $_SESSION['user_id'];
if ($currentUserId != $visitInfo['visitor_id'] && $currentUserId != $circleInfo['user_id']) {
    header('Location: ../user/visits.php');
    exit;
}

// 处理表单提交（记录回访或更新笔记）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (isset($_POST['record_return']) && $currentUserId == $visitInfo['visitor_id']) {
        $returnDate = $_POST['return_date'] ?? date('Y-m-d');
        if ($visit->recordReturn($visitId, $returnDate)) {
            $message = "{$visitInfo['visitor_name']} 已记录对「{$visitInfo['circle_name']}」的回访";
            $notification->create(
                $visitInfo['owner_id'],
                'return_confirm',
                $visitId,
                $message
            );
            $_SESSION['flash_message'] = '回访记录已更新';
            header("Location: visit_detail.php?id=$visitId");
            exit;
        }
    } elseif (isset($_POST['update_notes']) && $currentUserId == $circleInfo['user_id']) {
        $notes = trim($_POST['notes'] ?? '');
        if ($visit->updateNotes($visitId, $notes)) {
            $_SESSION['flash_message'] = '备注已更新';
            header("Location: visit_detail.php?id=$visitId");
            exit;
        }
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container visit-detail-container">
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="visit-header">
        <div class="breadcrumb">
            <a href="../user/visits.php">我的访问记录</a>
            <span>/</span>
            <span>访问详情</span>
        </div>
        
        <h1><i class="fas fa-exchange-alt"></i> 互访详情</h1>
        <?php $statusInfo = getVisitStatusLabel($visitInfo['status']); ?>
        <div class="status-badge large badge-<?= $statusInfo['class'] ?>">
            <?= $statusInfo['label'] ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- 基本信息卡片 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> 基本信息</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>互访圈</label>
                            <div class="value">
                                <a href="../circles/view.php?id=<?= $circleInfo['id'] ?>">
                                    <?= htmlspecialchars($circleInfo['name']) ?>
                                </a>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>所在城市</label>
                            <div class="value">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($circleInfo['city']) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label><?= $currentUserId == $visitInfo['visitor_id'] ? '圈主' : '访问者' ?></label>
                            <div class="value">
                                <div class="user-info">
                                    <img src="../assets/images/<?= htmlspecialchars($currentUserId == $visitInfo['visitor_id'] ? $ownerInfo['avatar'] : $visitorInfo['avatar'] ?? 'default.jpg') ?>" 
                                         class="avatar-sm" alt="用户头像">
                                    <span><?= htmlspecialchars($currentUserId == $visitInfo['visitor_id'] ? $ownerInfo['username'] : $visitorInfo['username']) ?></span>
                                </div>
                            </div>
                        </div>
						<div class="info-item">
                            <label>申请互访圈</label>
                            <div class="value">
                                <?= htmlspecialchars($visitCircleInfo['circle_name']) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>申请时间</label>
                            <div class="value">
                                <?= date('Y-m-d H:i', strtotime($visitInfo['created_at'])) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>访问日期</label>
                            <div class="value">
                                <?= $visitInfo['visit_date'] ? date('Y-m-d', strtotime($visitInfo['visit_date'])) : '待确认' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>回访日期</label>
                            <div class="value">
                                <?= $visitInfo['return_date'] ? date('Y-m-d', strtotime($visitInfo['return_date'])) : '待记录' ?>
                            </div>
                        </div>
                        <?php if ($visitInfo['next_suggest_date']): ?>
                        <div class="info-item">
                            <label>下次建议互访时间</label>
                            <div class="value">
                                <?= date('Y-m-d', strtotime($visitInfo['next_suggest_date'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
			
			<?php if ($visitInfo['status'] === 'confirmed' || $visitInfo['status'] === 'completed'): ?>
				<div class="card mt-4">
					<div class="card-header">
						<h5><i class="fas fa-camera"></i> 访问证据</h5>
					</div>
					<div class="card-body">
						<?php if ($visitInfo['screenshot_path']): ?>
							<img src="../<?= htmlspecialchars($visitInfo['screenshot_path']) ?>" class="img-fluid rounded mb-3" alt="访问截图">
						<?php else: ?>
							<p class="text-muted">未上传访问截图</p>
						<?php endif; ?>
						
						<p><strong>访问日期:</strong> <?= htmlspecialchars($visitInfo['visit_date']) ?></p>
						<p><strong>下次建议访问:</strong> <?= htmlspecialchars($visitInfo['next_suggest_date']) ?></p>
						<?php if ($visitInfo['notes']): ?>
							<p><strong>备注:</strong> <?= nl2br(htmlspecialchars($visitInfo['notes'])) ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
            
            <!-- 备注卡片 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> 备注</h3>
                </div>
                <div class="card-body">
                    <?php if ($currentUserId == $circleInfo['user_id']): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <div class="form-group">
                                <textarea class="form-control" name="notes" rows="4"><?= 
                                    htmlspecialchars($visitInfo['notes'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="update_notes" class="btn btn-primary">
                                <i class="fas fa-save"></i> 更新备注
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="notes-content">
                            <?= nl2br(htmlspecialchars($visitInfo['notes'] ?? '暂无备注')) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- 操作卡片 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> 操作</h3>
                </div>
                <div class="card-body">
                    <?php if ($currentUserId == $circleInfo['user_id'] && $visitInfo['status'] == 'pending'): ?>
                        <a href="../user/confirm_visit.php?id=<?= $visitId ?>" class="btn btn-primary btn-block mb-3">
                            <i class="fas fa-check"></i> 确认访问
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($currentUserId == $visitInfo['visitor_id'] && $visitInfo['status'] == 'confirmed' && !$visitInfo['return_date']): ?>
                        <form method="post">
                            <?= csrfField() ?>
                         class="mb-3">
                            <div class="form-group">
                                <label>回访日期</label>
                                <input type="date" class="form-control" name="return_date" 
                                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <button type="submit" name="record_return" class="btn btn-success btn-block">
                                <i class="fas fa-check-double"></i> 记录回访
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="../user/visits.php" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-arrow-left"></i> 返回列表
                    </a>
                </div>
            </div>
            
            <!-- 时间线卡片 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> 互访进度</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php $s = $visitInfo['status']; $completed = in_array($s, ['confirmed','completed']); $done = $s === 'completed'; ?>
                        <div class="timeline-item active">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h4>申请提交</h4>
                                <p><?= date('Y-m-d H:i', strtotime($visitInfo['created_at'])) ?></p>
                            </div>
                        </div>
                        <div class="timeline-item <?= $completed ? 'active' : '' ?>">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h4>访问确认</h4>
                                <p><?= $visitInfo['visit_date'] ? date('Y-m-d', strtotime($visitInfo['visit_date'])) : '待确认' ?></p>
                            </div>
                        </div>
                        <div class="timeline-item <?= $done ? 'active' : '' ?>">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h4>回访完成</h4>
                                <p><?= $visitInfo['return_date'] ? date('Y-m-d', strtotime($visitInfo['return_date'])) : '待完成' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// 初始化日期选择器
document.addEventListener('DOMContentLoaded', function() {
    const returnDateInput = document.querySelector('input[name="return_date"]');
    if (returnDateInput) {
        returnDateInput.max = new Date().toISOString().split('T')[0];
    }
});
</script>