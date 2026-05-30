<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/User.php';
require_once '../../classes/City.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$nft = new NFT($pdo);
$user = new User($pdo);

// 获取用户收藏的NFT及城市关联信息
$collection = $nft->getUserCollection($userId);//$nft->getUserCollectionWithCity($userId);

// 获取用户当前头像信息
$userData = $user->getUserById($userId);
$currentAvatar = $userData['avatar'];

// 分页设置
$page = $_GET['page'] ?? 1;
$perPage = 30;
$totalNfts = count($collection);
$totalPages = ceil($totalNfts / $perPage);
$offset = ($page - 1) * $perPage;
$paginatedCollection = array_slice($collection, $offset, $perPage);
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-user-circle me-2"></i>我持有的NFT头像</h2>
        <span class="badge bg-primary"><?= count($collection) ?> 个头像</span>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- NFT收藏网格 -->
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3">
        <?php if (empty($paginatedCollection)): ?>
            <div class="col-12">
                <div class="empty-state text-center py-5">
                    <div class="empty-icon text-muted mb-3">
                        <i class="fas fa-image fa-3x"></i>
                    </div>
                    <h3 class="h5">您还没有NFT收藏</h3>
                    <p class="text-muted">快去认领或购买NFT头像吧！</p>
                    <a href="/nft/claim_list.php" class="btn btn-primary mt-3">
                        <i class="fas fa-hand-holding-heart me-2"></i>去认领NFT
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($paginatedCollection as $item): ?>
                <!-- 在循环中修改NFT卡片部分 -->
				<div class="col">
					<div class="card h-100 border-0 shadow-sm position-relative">
						<!-- 当前头像标记 -->
						<?php if ($currentAvatar == $item['base_image']): ?>
							<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
								<i class="fas fa-check"></i>
								<span class="visually-hidden">当前头像</span>
							</span>
						<?php endif; ?>
						
						<!-- 圆形头像容器 - 移除了城市标记 -->
						<div class="avatar-container mx-auto mt-3">
							<img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" 
								 class="nft-avatar-img" 
								 alt="NFT <?= htmlspecialchars($item['code']) ?>"
								 loading="lazy">
						</div>
						
						<div class="card-body text-center pt-1 pb-3">
							<!-- 编号和城市名称 -->
							<h6 class="card-title mb-1"><?= htmlspecialchars($item['code']) ?></h6>
							<div class="city-name mb-2">
								<i class="fas fa-map-marker-alt text-muted me-1"></i>
								<?= htmlspecialchars($item['city_name']) ?>
							</div>
														
							<!-- 操作按钮 -->
							<div class="d-flex justify-content-center gap-2">
								<a href="/user/set_profile_avatar.php?id=<?= $item['nft_id'] ?>" 
								   class="btn btn-sm btn-outline-primary rounded-circle"
								   title="设为头像"
								   data-bs-toggle="tooltip">
									<i class="fas fa-user-circle"></i>
								</a>
								<a href="/nft/sell.php?id=<?= $item['nft_id'] ?>&city_id=<?= $item['city_id'] ?>" 
								   class="btn btn-sm btn-outline-success rounded-circle"
								   title="出售"
								   data-bs-toggle="tooltip">
									<i class="fas fa-tag"></i>
								</a>
								<a href="/nft/view.php?id=<?= $item['nft_id'] ?>" 
								   class="btn btn-sm btn-outline-info rounded-circle"
								   title="查看详情"
								   data-bs-toggle="tooltip">
									<i class="fas fa-eye"></i>
								</a>
							</div>
						</div>
					</div>
				</div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// 启用工具提示
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>