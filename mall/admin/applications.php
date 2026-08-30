<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Application.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$app = new Application($pdo);

$type = $_GET['type'] ?? '';
if (!in_array($type, ['', 'model', 'author'])) $type = '';
$status = $_GET['status'] ?? '';
if (!in_array($status, ['', 'pending', 'contacted', 'approved', 'rejected'])) $status = '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$stats = $app->getStats($type);
$result = $app->getList($type, $status, $page, $perPage);
$list = $result['list'];
$total = $result['total'];
$totalPages = $result['pages'];

$statusColors = [
    'pending'   => ['#f59e0b', '#fef3c7'],
    'contacted' => ['#2563eb', '#dbeafe'],
    'approved'  => ['#16a34a', '#dcfce7'],
    'rejected'  => ['#dc2626', '#fee2e2'],
];

$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '申请管理 - 58商城后台',
];
require_once '../../shared/admin/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-page-header">
        <h1>申请管理</h1>
        <span style="color:#64748b;font-size:13px;">模特申请 / 作者合作申请审核</span>
    </div>

    <!-- 类型 tab -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <a href="applications.php" class="admin-btn <?= $type===''?'admin-btn-primary':'admin-btn-secondary' ?>">全部 (<?= array_sum($stats) ?>)</a>
        <a href="applications.php?type=model" class="admin-btn <?= $type==='model'?'admin-btn-primary':'admin-btn-secondary' ?>">模特申请 (<?= $stats['pending'] ?>)</a>
        <a href="applications.php?type=author" class="admin-btn <?= $type==='author'?'admin-btn-primary':'admin-btn-secondary' ?>">作者合作申请 (<?= $stats['pending'] ?>)</a>
    </div>

    <!-- 状态筛选 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <span style="font-size:13px;color:#94a3b8;">状态：</span>
        <?php
        $statusLabels = ['pending' => '待处理', 'contacted' => '已联系', 'approved' => '已通过', 'rejected' => '已驳回'];
        $baseQuery = $type !== '' ? 'type=' . $type . '&' : '';
        ?>
        <a href="applications.php?<?= $baseQuery ?>" class="admin-btn <?= $status===''?'admin-btn-primary':'admin-btn-secondary' ?> admin-btn-sm">全部</a>
        <?php foreach ($statusLabels as $sk => $sl): ?>
        <a href="applications.php?<?= $baseQuery ?>status=<?= $sk ?>" class="admin-btn <?= $status===$sk?'admin-btn-primary':'admin-btn-secondary' ?> admin-btn-sm"><?= $sl ?> (<?= $stats[$sk] ?>)</a>
        <?php endforeach; ?>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">申请列表 (<?= $total ?>)</span>
        </div>
        <div class="admin-card-body">
            <?php if (empty($list)): ?>
            <div style="text-align:center;padding:40px;color:#64748b;">
                <i class="fas fa-file-signature" style="font-size:48px;display:block;margin-bottom:12px;"></i>
                暂无申请数据
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;color:#94a3b8;border-bottom:1px solid #1e293b;">
                        <th style="padding:10px 8px;">ID</th>
                        <th style="padding:10px 8px;">类型</th>
                        <th style="padding:10px 8px;">昵称</th>
                        <th style="padding:10px 8px;">联系方式</th>
                        <th style="padding:10px 8px;">照片</th>
                        <th style="padding:10px 8px;">状态</th>
                        <th style="padding:10px 8px;">提交用户</th>
                        <th style="padding:10px 8px;">提交时间</th>
                        <th style="padding:10px 8px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row):
                        $sc = $statusColors[$row['status']] ?? $statusColors['pending'];
                    ?>
                    <tr style="border-bottom:1px solid #0f172a;color:#e2e8f0;">
                        <td style="padding:10px 8px;"><?= $row['id'] ?></td>
                        <td style="padding:10px 8px;"><?= Application::typeLabel($row['type']) ?></td>
                        <td style="padding:10px 8px;font-weight:600;"><?= htmlspecialchars($row['nickname']) ?></td>
                        <td style="padding:10px 8px;color:#94a3b8;">
                            <?php
                            $contacts = [];
                            if (!empty($row['phone'])) $contacts[] = '📞 ' . htmlspecialchars($row['phone']);
                            if (!empty($row['weixin'])) $contacts[] = '微信:' . htmlspecialchars($row['weixin']);
                            if (!empty($row['qq'])) $contacts[] = 'QQ:' . htmlspecialchars($row['qq']);
                            echo $contacts ? implode('<br>', array_slice($contacts, 0, 2)) : '-';
                            ?>
                        </td>
                        <td style="padding:10px 8px;">
                            <?php if (!empty($row['photos_arr'][0])): ?>
                            <img src="../<?= htmlspecialchars($row['photos_arr'][0]) ?>" style="width:40px;height:40px;border-radius:6px;object-fit:cover;">
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 8px;">
                            <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;color:<?= $sc[0] ?>;background:<?= $sc[1] ?>;"><?= Application::statusLabel($row['status']) ?></span>
                        </td>
                        <td style="padding:10px 8px;color:#94a3b8;"><?= htmlspecialchars($row['username'] ?? '-') ?></td>
                        <td style="padding:10px 8px;color:#94a3b8;"><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                        <td style="padding:10px 8px;">
                            <a href="application-view.php?id=<?= $row['id'] ?>" class="admin-btn admin-btn-sm admin-btn-primary" style="font-size:12px;">查看</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <div style="text-align:center;margin-top:20px;">
        <div class="admin-pagination" style="display:inline-flex;gap:8px;align-items:center;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&type=<?= $type ?>&status=<?= $status ?>" class="admin-btn admin-btn-sm admin-btn-secondary">上一页</a>
            <?php endif; ?>
            <span class="admin-page-info" style="padding:0 12px;color:#94a3b8;"><?= $page ?> / <?= $totalPages ?> (<?= $total ?>条)</span>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>&type=<?= $type ?>&status=<?= $status ?>" class="admin-btn admin-btn-sm admin-btn-secondary">下一页</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
