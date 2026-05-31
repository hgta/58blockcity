<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

$circleId = intval($_GET['id'] ?? 0);

// Get circle info
$stmt = $pdo->prepare("SELECT c.*, u.username FROM circles c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
$stmt->execute([$circleId]);
$circle = $stmt->fetch();

if (!$circle || $circle['user_id'] != $_SESSION['user_id']) {
    header("Location: ../index.php");
    exit();
}

// Get visit requests
$stmt = $pdo->prepare("SELECT v.*, c2.name as from_circle_name, u.username 
    FROM visits v 
    JOIN circles c2 ON v.from_circle_id = c2.id 
    LEFT JOIN users u ON v.user_id = u.id 
    WHERE v.to_circle_id = ? 
    ORDER BY v.created_at DESC");
$stmt->execute([$circleId]);
$visits = $stmt->fetchAll();

// Get return visits
$stmt = $pdo->prepare("SELECT * FROM visits WHERE from_circle_id = ? ORDER BY created_at DESC");
$stmt->execute([$circleId]);
$returns = $stmt->fetchAll();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visit_id'], $_POST['status'])) {
    $vid = intval($_POST['visit_id']);
    $status = $_POST['status'];
    $validStatuses = ['confirmed', 'cancelled', 'completed'];
    if (in_array($status, $validStatuses)) {
        $upd = $pdo->prepare("UPDATE visits SET status = ? WHERE id = ? AND to_circle_id = ?");
        $upd->execute([$status, $vid, $circleId]);
    }
    header("Location: manage_visits.php?id={$circleId}");
    exit();
}

// Status map
$statusMap = ['pending'=>'待确认','confirmed'=>'已确认','cancelled'=>'已取消','completed'=>'已完成'];
$statusColors = ['pending'=>'#fff3cd','confirmed'=>'#d1ecf1','cancelled'=>'#f8d7da','completed'=>'#d4edda'];
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:900px; margin:0 auto; padding:20px; }
.page-title { font-size:22px; font-weight:bold; margin-bottom:20px; color:#333; }
.card { background:white; border-radius:8px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:20px; }
.card-title { font-size:16px; font-weight:bold; border-bottom:2px solid #f0f0f0; padding-bottom:10px; margin-bottom:15px; }
.visit-item { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f5f5f5; }
.visit-item:last-child { border-bottom:none; }
.visit-info { flex:1; }
.visit-circle { font-weight:bold; }
.visit-user { font-size:12px; color:#666; }
.visit-date { font-size:12px; color:#999; }
.status-badge { padding:3px 10px; border-radius:12px; font-size:12px; }
.actions { display:flex; gap:6px; }
.btn-sm { padding:5px 12px; border:1px solid #ddd; border-radius:4px; font-size:12px; cursor:pointer; background:white; }
.btn-sm.btn-confirm { border-color:#27ae60; color:#27ae60; }
.btn-sm.btn-cancel { border-color:#e74c3c; color:#e74c3c; }
.btn-sm.btn-complete { border-color:#3498db; color:#3498db; }
.empty-state { text-align:center; padding:40px; color:#999; }
</style>

<div class="container">
    <h1 class="page-title">
        <i class="fas fa-calendar-check"></i> 管理访问 - <?= htmlspecialchars($circle['name']) ?>
    </h1>
    
    <div class="card">
        <div class="card-title">收到的互访请求</div>
        <?php if (empty($visits)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>暂无待处理的互访请求</p>
            </div>
        <?php else: ?>
            <?php foreach ($visits as $v): ?>
                <div class="visit-item">
                    <div class="visit-info">
                        <div class="visit-circle"><?= htmlspecialchars($v['from_circle_name']) ?></div>
                        <div class="visit-user">来自: <?= htmlspecialchars($v['username'] ?? '匿名') ?></div>
                        <div class="visit-date"><?= date('Y-m-d H:i', strtotime($v['created_at'])) ?></div>
                    </div>
                    <span class="status-badge" style="background:<?= $statusColors[$v['status']] ?? '#f0f0f0' ?>"><?= $statusMap[$v['status']] ?? $v['status'] ?></span>
                    <?php if ($v['status'] === 'pending'): ?>
                    <div class="actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="status" value="confirmed">
                            <button type="submit" class="btn-sm btn-confirm">确认</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn-sm btn-cancel">拒绝</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div style="text-align:center;margin-top:15px;">
        <a href="view.php?id=<?= $circleId ?>" style="color:#3498db;font-size:14px;">
            <i class="fas fa-arrow-left"></i> 返回互访圈详情
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
