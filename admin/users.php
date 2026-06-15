<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/User.php';
require_once '../classes/Shop.php';

checkAdmin();

$user = new User($pdo);
$shop = new Shop($pdo);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT id, username, email, role, status, created_at, last_login FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $v) { $stmt->bindValue($i+1, $v); }
$stmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'users';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>用户管理 - 58区块城市</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background:#f5f7fa; color:#333; }
.admin-layout { display:flex; min-height:100vh; }
.sidebar { width:260px; background:#1a1a2e; color:#fff; position:fixed; height:100vh; overflow-y:auto; z-index:100; transition:transform .3s; }
.sidebar-header { padding:20px; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar-header h2 { font-size:18px; font-weight:700; }
.sidebar-header p { font-size:12px; opacity:0.6; margin-top:4px; }
.nav-menu { padding:15px 0; }
.nav-item { display:flex; align-items:center; gap:12px; padding:12px 20px; color:rgba(255,255,255,0.8); text-decoration:none; font-size:14px; transition:all .2s; border-left:3px solid transparent; }
.nav-item:hover { background:rgba(255,255,255,0.05); color:#fff; }
.nav-item.active { background:rgba(255,107,0,0.15); color:#ff6b00; border-left-color:#ff6b00; }
.nav-item i { width:20px; text-align:center; }
.sidebar-footer { padding:15px 20px; border-top:1px solid rgba(255,255,255,0.1); font-size:12px; opacity:0.5; }
.main-content { flex:1; margin-left:260px; min-height:100vh; }
.topbar { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.05); position:sticky; top:0; z-index:50; }
.topbar h1 { font-size:20px; font-weight:600; }
.topbar-right { display:flex; align-items:center; gap:15px; font-size:14px; }
.topbar-right a { color:#666; text-decoration:none; }
.topbar-right a:hover { color:#ff6b00; }
.menu-toggle { display:none; background:none; border:none; font-size:20px; cursor:pointer; color:#333; }
.content { padding:25px 30px; }
.search-bar { background:#fff; padding:15px 20px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:20px; display:flex; gap:10px; align-items:center; }
.search-bar input { flex:1; padding:10px 15px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; }
.search-bar button { padding:10px 20px; background:#ff6b00; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:14px; }
.section-card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden; }
.section-header { padding:15px 20px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
.section-header h3 { font-size:16px; font-weight:600; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th { text-align:left; padding:12px 20px; font-size:12px; color:#888; font-weight:500; text-transform:uppercase; border-bottom:1px solid #f0f0f0; }
.data-table td { padding:12px 20px; font-size:14px; border-bottom:1px solid #f8f9fa; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover { background:#fafbfc; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
.badge-success { background:#e8f5e9; color:#388e3c; }
.badge-default { background:#f5f5f5; color:#666; }
.badge-admin { background:#fff3e0; color:#f57c00; }
.pagination { display:flex; justify-content:center; gap:8px; padding:20px; }
.pagination a, .pagination span { padding:8px 14px; border-radius:6px; text-decoration:none; font-size:14px; color:#333; border:1px solid #e0e0e0; }
.pagination a:hover { background:#ff6b00; color:#fff; border-color:#ff6b00; }
.pagination .active { background:#ff6b00; color:#fff; border-color:#ff6b00; }
@media (max-width:768px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main-content { margin-left:0; }
    .menu-toggle { display:block; }
    .data-table { display:block; overflow-x:auto; }
}
.overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99; }
@media (max-width:768px) { .overlay.show { display:block; } }
</style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-cube"></i> 58区块城市</h2>
            <p>管理后台</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item <?= $currentPage=='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
            <a href="users.php" class="nav-item <?= $currentPage=='users'?'active':'' ?>"><i class="fas fa-users"></i> 用户管理</a>
            <a href="shops.php" class="nav-item <?= $currentPage=='shops'?'active':'' ?>"><i class="fas fa-store"></i> 店铺管理</a>
            <a href="orders.php" class="nav-item <?= $currentPage=='orders'?'active':'' ?>"><i class="fas fa-shopping-cart"></i> 订单管理</a>
            <a href="products.php" class="nav-item <?= $currentPage=='products'?'active':'' ?>"><i class="fas fa-box"></i> 商品管理</a>
            <a href="bct_orders.php" class="nav-item <?= $currentPage=='bct_orders'?'active':'' ?>"><i class="fas fa-coins"></i> BCT交易</a>
            <a href="../index.php" class="nav-item"><i class="fas fa-home"></i> 返回前台</a>
        </nav>
        <div class="sidebar-footer">
            管理员: <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
        </div>
    </aside>

    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="main-content">
        <div class="topbar">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1>用户管理</h1>
            <div class="topbar-right">
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> 退出</a>
            </div>
        </div>

        <div class="content">
            <div class="search-bar">
                <form method="GET" style="display:flex;gap:10px;width:100%;">
                    <input type="text" name="search" placeholder="搜索用户名或邮箱..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="fas fa-search"></i> 搜索</button>
                    <?php if ($search): ?><a href="users.php" style="padding:10px 15px;color:#666;text-decoration:none;">重置</a><?php endif; ?>
                </form>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3>用户列表 (共 <?= $total ?> 人)</h3>
                </div>
                <table class="data-table">
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>邮箱</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th>最后登录</th>
                    </tr>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge <?= $u['role']=='admin'?'badge-admin':'badge-default' ?>"><?= $u['role'] ?></span></td>
                        <td><span class="badge <?= $u['status']=='active'?'badge-success':'badge-default' ?>"><?= $u['status'] ?></span></td>
                        <td><?= $u['created_at'] ? date('Y-m-d', strtotime($u['created_at'])) : '-' ?></td>
                        <td><?= $u['last_login'] ? date('m-d H:i', strtotime($u['last_login'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">未找到用户</td></tr>
                    <?php endif; ?>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">上一页</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPages || abs($i-$page) <= 2): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
                        <?php elseif (abs($i-$page) == 3): ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
</script>
</body>
</html>
