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

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="admin-alert success"><i class="fas fa-check-circle"></i> <?= $_SESSION['flash_message'] ?></div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<style>
.admin-info-item { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--admin-border); display:flex; gap:12px; }
.admin-info-item:last-child { border-bottom: none; }
.admin-info-item label { font-weight: 600; color: var(--admin-text-muted); min-width: 100px; flex-shrink:0; }
.admin-notes { background: var(--admin-bg); padding: 10px 14px; border-radius: var(--admin-radius-sm); border-left: 3px solid var(--admin-accent); margin: 4px 0 0; }
</style>

<!-- 基本信息 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-info-circle"></i> 基本信息</span></div>
    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:0 30px;">
        <div>
            <div class="admin-info-item">
                <label>互访圈</label>
                <span><a href="../circles/view.php?id=<?= $visitInfo['circle_id'] ?>" style="color:var(--admin-accent);"><?= htmlspecialchars($visitInfo['circle_name']) ?></a></span>
            </div>
            <div class="admin-info-item">
                <label>圈主</label>
                <span style="display:flex;align-items:center;gap:8px;">
                    <img src="../../assets/images/<?= htmlspecialchars($ownerInfo['avatar'] ?? 'default.jpg') ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
                    <?= htmlspecialchars($ownerInfo['username']) ?>
                </span>
            </div>
            <div class="admin-info-item">
                <label>所在城市</label>
                <span><?= htmlspecialchars($visitInfo['circle_city']) ?></span>
            </div>
        </div>
        <div>
            <div class="admin-info-item">
                <label>访问者</label>
                <span style="display:flex;align-items:center;gap:8px;">
                    <img src="../../assets/images/<?= htmlspecialchars($visitorInfo['avatar'] ?? 'default.jpg') ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
                    <?= htmlspecialchars($visitorInfo['username']) ?>
                </span>
            </div>
            <div class="admin-info-item">
                <label>访问状态</label>
                <span>
                    <?php
                    $statusMap = ['completed'=>['已完成','success'],'confirmed'=>['已确认','info'],'pending'=>['待确认','warning'],'cancelled'=>['已取消','default']];
                    $s = $statusMap[$visitInfo['status']] ?? [$visitInfo['status'], 'default'];
                    ?>
                    <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
                </span>
            </div>
            <div class="admin-info-item">
                <label>申请时间</label>
                <span><?= date('Y-m-d H:i', strtotime($visitInfo['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- 访问时间信息 -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-calendar-alt"></i> 访问时间信息</span></div>
        <div class="admin-card-body">
            <div class="admin-info-item"><label>申请访问日期</label><span><?= $visitInfo['created_at'] ? date('Y-m-d', strtotime($visitInfo['created_at'])) : '-' ?></span></div>
            <div class="admin-info-item"><label>实际访问日期</label><span><?= $visitInfo['visit_date'] ?? '-' ?></span></div>
            <div class="admin-info-item"><label>建议下次访问</label><span><?= $visitInfo['next_suggest_date'] ?? '-' ?></span></div>
            <div class="admin-info-item"><label>实际回访日期</label><span><?= $visitInfo['return_date'] ?? '-' ?></span></div>
        </div>
    </div>
    
    <!-- 备注信息 -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-comments"></i> 备注信息</span></div>
        <div class="admin-card-body">
            <div class="admin-info-item" style="flex-direction:column;align-items:flex-start;">
                <label>访问者备注</label>
                <p class="admin-notes"><?= $visitInfo['notes'] ? nl2br(htmlspecialchars($visitInfo['notes'])) : '无' ?></p>
            </div>
            <div class="admin-info-item" style="flex-direction:column;align-items:flex-start;border-bottom:none;">
                <label>管理员备注</label>
                <p class="admin-notes"><?= $visitInfo['admin_notes'] ? nl2br(htmlspecialchars($visitInfo['admin_notes'])) : '无' ?></p>
            </div>
        </div>
    </div>
</div>

<?php if ($visitInfo['screenshot_path']): ?>
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-camera"></i> 访问证明</span></div>
    <div class="admin-card-body" style="text-align:center;">
        <img src="../../<?= htmlspecialchars($visitInfo['screenshot_path']) ?>" style="max-height:400px;max-width:100%;border-radius:var(--admin-radius-sm);" alt="访问证明截图">
    </div>
</div>
<?php endif; ?>

<!-- 管理操作 -->
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-cog"></i> 管理操作</span></div>
    <div class="admin-card-body">
        <?php if ($visitInfo['status'] == 'pending'): ?>
            <form method="post" style="margin-bottom:24px;">
                <h4 style="margin:0 0 16px;"><i class="fas fa-check"></i> 确认访问</h4>
                <div class="admin-form-group">
                    <label class="admin-form-label">实际访问日期</label>
                    <input type="date" class="admin-form-input" name="visit_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">管理员备注</label>
                    <textarea class="admin-form-input" name="admin_notes" rows="2"></textarea>
                </div>
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-check-circle"></i> 确认访问</button>
            </form>
            
            <form method="post">
                <h4 style="margin:0 0 16px;"><i class="fas fa-times"></i> 取消访问</h4>
                <div class="admin-form-group">
                    <label class="admin-form-label">取消原因</label>
                    <textarea class="admin-form-input" name="admin_notes" rows="2" required></textarea>
                </div>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="admin-btn admin-btn-danger"><i class="fas fa-ban"></i> 取消访问</button>
            </form>
        
        <?php elseif ($visitInfo['status'] == 'confirmed'): ?>
            <form method="post">
                <h4 style="margin:0 0 16px;"><i class="fas fa-undo"></i> 记录回访</h4>
                <div class="admin-form-group">
                    <label class="admin-form-label">回访日期</label>
                    <input type="date" class="admin-form-input" name="return_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">管理员备注</label>
                    <textarea class="admin-form-input" name="admin_notes" rows="2"></textarea>
                </div>
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-check-double"></i> 记录回访完成</button>
            </form>
        
        <?php else: ?>
            <div class="admin-alert" style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);color:var(--admin-info);">
                此访问记录已处于最终状态（<?= $visitInfo['status'] ?>），无法再进行操作
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
