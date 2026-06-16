<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';

// 检查管理员权限
checkAdmin();

$city = new City($pdo);

// 处理搜索和分页
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 获取数据
$cities = $city->searchAllCities($page, $perPage, $search);
$totalCities = $city->getTotalCitiesCount($search);
$totalPages = ceil($totalCities / $perPage);

// 处理删除操作
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $cityId = (int)$_GET['id'];
    if ($city->deleteCity($cityId)) {
        $_SESSION['success'] = '城市删除成功！';
    } else {
        $_SESSION['error'] = '城市删除失败！';
    }
    header('Location: cities.php');
    exit;
}
?>

$admin_site_config = ['site' => 'hufang', 'page_title' => '城市管理'];
require_once '../../shared/admin/admin-header.php';

<style>
/* 简洁清晰的分页样式 */
.pagination-clean {
    margin: 1.5rem 0;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pagination-clean .page-item {
    margin: 0;
}

.pagination-clean .page-link {
    min-width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    color: #495057;
    background: white;
    text-decoration: none;
    transition: all 0.2s ease;
}

.pagination-clean .page-link:hover {
    border-color: #007bff;
    color: #007bff;
    background-color: #f8f9fa;
}

.pagination-clean .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

.pagination-clean .page-item.disabled .page-link {
    color: #6c757d;
    background-color: #f8f9fa;
    border-color: #dee2e6;
    opacity: 0.6;
}

.pagination-clean .page-link i {
    font-size: 0.8rem;
}

/* 分页信息 */
.pagination-info-clean {
    text-align: center;
    margin: 1rem 0;
    padding: 0.75rem;
    background-color: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.pagination-info-clean .total-count {
    font-size: 0.95rem;
    color: #495057;
    font-weight: 500;
}

.pagination-info-clean .page-indicator {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

/* 省略号样式 */
.page-ellipsis {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    color: #6c757d;
    font-weight: 500;
}

/* 移动端适配 */
@media (max-width: 768px) {
    .pagination-clean {
        gap: 0.25rem;
    }
    
    .pagination-clean .page-link {
        min-width: 34px;
        height: 34px;
        font-size: 0.85rem;
    }
    
    .page-ellipsis {
        min-width: 34px;
        height: 34px;
    }
}

/* 紧凑型表格 */
.table-compact th,
.table-compact td {
    padding: 0.75rem 0.5rem;
    font-size: 0.9rem;
}

.table-compact .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}
</style>

<div class="container-fluid">
    <!-- 页面标题和操作按钮 -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-city me-2"></i>城市管理</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="city_add.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus me-1"></i>添加城市
            </a>
        </div>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- 搜索框 -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="搜索城市名称、拼音或区域代码..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>搜索
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="cities.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-sync me-1"></i>重置
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- 城市列表 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">城市列表 (共 <?= $totalCities ?> 个城市)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($cities)): ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-city fa-2x mb-3"></i>
                    <h5 class="mb-2">暂无城市数据</h5>
                    <p class="text-muted mb-0">尝试修改搜索条件或添加新城市</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-compact">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>城市名称</th>
                                <th>拼音</th>
                                <th>区域代码</th>
                                <th>排名</th>
                                <th>居民数</th>
                                <th>区块数</th>
                                <th>人气值</th>
                                <th>热门</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $cityItem): ?>
                                <tr>
                                    <td><?= $cityItem['id'] ?></td>
                                    <td><?= htmlspecialchars($cityItem['name']) ?></td>
                                    <td><?= htmlspecialchars($cityItem['pinyin']) ?></td>
                                    <td><?= htmlspecialchars($cityItem['area_code']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $cityItem['rank'] <= 3 ? 'primary' : 'secondary' ?>">
                                            <?= $cityItem['rank'] ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($cityItem['resident_count']) ?></td>
                                    <td><?= number_format($cityItem['activated_blocks']) ?></td>
                                    <td><?= number_format($cityItem['popularity']) ?></td>
                                    <td>
                                        <?php if ($cityItem['is_hot']): ?>
                                            <span class="badge bg-success">是</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">否</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="city_edit.php?id=<?= $cityItem['id'] ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="cities.php?delete=1&id=<?= $cityItem['id'] ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('确定要删除这个城市吗？此操作不可恢复！')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页信息和导航 -->
                <?php if ($totalPages > 1): ?>
                    <!-- 分页信息 -->
                    <div class="pagination-info-clean">
                        <div class="total-count">共 <?= $totalCities ?> 条记录</div>
                        <div class="page-indicator">第 <?= $page ?> 页 / 共 <?= $totalPages ?> 页</div>
                    </div>

                    <!-- 分页导航 -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-clean justify-content-center">
                            <!-- 上一页 -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <!-- 首页 -->
                            <?php if ($page > 3): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>">1</a>
                                </li>
                                <?php if ($page > 4): ?>
                                    <li class="page-item">
                                        <span class="page-ellipsis">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- 中间页码 -->
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- 末页 -->
                            <?php if ($page < $totalPages - 2): ?>
                                <?php if ($page < $totalPages - 3): ?>
                                    <li class="page-item">
                                        <span class="page-ellipsis">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>">
                                        <?= $totalPages ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- 下一页 -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>