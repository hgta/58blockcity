<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/City.php';
require_once '../../classes/Visit.php';

$circle = new Circle($pdo);
$city = new City($pdo);

$selectedCity = $_GET['city'] ?? '北京';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;

$circles = $circle->getCirclesByCityPaginated($selectedCity, $page, $perPage, $search);
$totalCount = $circle->getCircleCountByCity($selectedCity, $search);
$totalPages = ceil($totalCount / $perPage);

// 获取当前用户的访问状态
$visitedMap = [];
if (isset($_SESSION['user_id'])) {
    $visit = new Visit($pdo);
    $visitedMap = $visit->getUserVisitedCircleIds($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($selectedCity) ?>全部互访圈 - 58互访圈</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#f5f7fa; color:#333; }
.container { max-width:1200px; margin:0 auto; padding:20px; }
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.page-header h1 { font-size:22px; }
.page-header a { color:#ff6b00; text-decoration:none; font-size:14px; }
table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
th { background:#f8f9fa; text-align:left; padding:12px 16px; font-size:13px; color:#666; font-weight:500; }
td { padding:10px 16px; font-size:14px; border-bottom:1px solid #f0f0f0; }
tr:hover { background:#fafbfc; }
.badge { padding:2px 8px; border-radius:10px; font-size:11px; }
.badge-completed { background:#d4edda; color:#155724; }
.badge-visited { background:#d1ecf1; color:#0c5460; }
.badge-pending { background:#fff3cd; color:#856404; }
.btn { display:inline-block; padding:4px 12px; background:#ff6b00; color:#fff; text-decoration:none; border-radius:4px; font-size:12px; }
.search-bar { display:flex; gap:8px; margin-bottom:16px; }
.search-bar input { flex:1; padding:8px 14px; border:1px solid #e0e0e0; border-radius:20px; font-size:14px; outline:none; }
.pagination { display:flex; gap:6px; justify-content:center; padding:20px 0; }
.pagination a { padding:8px 14px; border:1px solid #e0e0e0; border-radius:6px; text-decoration:none; color:#333; font-size:14px; }
.pagination a.active { background:#ff6b00; color:#fff; border-color:#ff6b00; }
</style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($selectedCity) ?> 的互访圈 (<?= $totalCount ?>)</h1>
        <a href="../index.php?city=<?= urlencode($selectedCity) ?>"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>

    <form method="GET" class="search-bar">
        <input type="hidden" name="city" value="<?= htmlspecialchars($selectedCity) ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索圈子名称或描述...">
        <button type="submit" class="btn"><i class="fas fa-search"></i> 搜索</button>
    </form>

    <?php if (empty($circles)): ?>
    <div style="text-align:center;padding:60px;color:#999;">
        <i class="fas fa-users-slash" style="font-size:48px;margin-bottom:16px;"></i>
        <h3>暂无互访圈</h3>
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>互访圈</th>
                <th>城市</th>
                <th>区块数</th>
                <th>圈主</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($circles as $c):
                $vs = $visitedMap[$c['id']] ?? null;
                $badge = '';
                if ($vs === 'completed') $badge = '<span class="badge badge-completed">已互访</span>';
                elseif (in_array($vs, ['visited','returned'])) $badge = '<span class="badge badge-visited">已访</span>';
                elseif (in_array($vs, ['pending','confirmed'])) $badge = '<span class="badge badge-pending">已访问</span>';
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><?= htmlspecialchars($c['city']) ?></td>
                <td><?= $c['block_count'] ?></td>
                <td><?= htmlspecialchars($c['username']) ?></td>
                <td><?= $badge ?: '<span style="color:#ccc;">-</span>' ?></td>
                <td><a href="view.php?id=<?= $c['id'] ?>" class="btn">详情</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?city=<?= urlencode($selectedCity) ?>&page=<?= $page-1 ?><?php echo $search?'&search='.urlencode($search):'' ?>">上一页</a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?city=<?= urlencode($selectedCity) ?>&page=<?= $i ?><?php echo $search?'&search='.urlencode($search):'' ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?city=<?= urlencode($selectedCity) ?>&page=<?= $page+1 ?><?php echo $search?'&search='.urlencode($search):'' ?>">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
