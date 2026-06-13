<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';
require_once '../../classes/Visit.php';

checkLogin();

$circleId = $_GET['id'] ?? 0;
$circle = new Circle($pdo);
$user = new User($pdo);
$visit = new Visit($pdo);

$circleInfo = $circle->getCircleById($circleId);
if (!$circleInfo) {
    header('Location: index.php');
    exit;
}

$ownerInfo = $user->getUserById($circleInfo['user_id']);
$visits = $visit->getCircleVisitsById($circleId);//getCircleVisits
$canRequest = false;

// 检查当前用户是否可以申请访问
$currentUser = $user->getUserById($_SESSION['user_id']);
//$canRequest = ($currentUser['city'] === $circleInfo['city'] && 
//              $_SESSION['user_id'] != $circleInfo['user_id']);
$canRequest = (1 && $_SESSION['user_id'] != $circleInfo['user_id']);

// 检查是否已有待处理或已确认的访问请求
foreach ($visits as $v) {
    if ($v['visitor_id'] == $_SESSION['user_id'] && 
        ($v['status'] == 'pending' || $v['status'] == 'confirmed')) {
        $canRequest = false;
        break;
    }
}

// 处理访问申请
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_visit']) && $canRequest) {
    $applicantCircleId = $_POST['applicant_circle_id'] ?? 0;
    
    if (empty($applicantCircleId)) {
        $_SESSION['flash_message'] = '请选择您的互访圈';
    } elseif ($visit->requestVisit($circleId, $_SESSION['user_id'], $applicantCircleId)) {
        $_SESSION['flash_message'] = '互访申请已提交，等待对方确认';
        header("Location: view.php?id=$circleId");
        exit;
    } else {
        $_SESSION['flash_message'] = '申请提交失败，请重试';
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container circle-view-container">
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success">
			<?= htmlspecialchars($_SESSION['flash_message']) ?>
			<button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="circle-view-header">
        <div class="circle-basic-info">
            <img src="../assets/images/<?= htmlspecialchars($ownerInfo['avatar'] ?? 'default.jpg') ?>"
                 class="circle-owner-avatar" alt="<?= htmlspecialchars($ownerInfo['username']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:50%;">
            
            <div class="circle-title">
                <h1><?= htmlspecialchars($circleInfo['name']) ?></h1>
                <div class="circle-meta">
                    <span class="circle-owner">
                        <i class="fas fa-user"></i><a href="../circles/circles.php?user_id=<?= $ownerInfo['id'] ?>"> <?= htmlspecialchars($ownerInfo['username']) ?></a>
                    </span>
                    <span class="circle-location">
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($circleInfo['city']) ?>
                    </span>
                    <?php if ($circleInfo['category']): ?>
                        <span class="circle-category"><?= htmlspecialchars($circleInfo['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="circle-actions">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> 返回列表
            </a>
            <?php if ($_SESSION['user_id'] == $circleInfo['user_id']): ?>
                <a href="edit.php?id=<?= $circleInfo['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> 编辑信息
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-8">
            <!-- 互访圈详情卡片 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> 互访圈详情</h3>
                </div>
                <div class="card-body">
                    <div class="circle-description">
                        <?= nl2br(htmlspecialchars($circleInfo['description'] ?? '暂无详细描述')) ?>
                    </div>
                </div>
            </div>
            
            <!-- 访问记录卡片 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exchange-alt"></i> 访问记录</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($visits)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <h4>暂无访问记录</h4>
                            <p>此互访圈还没有任何访问记录</p>
                        </div>
                    <?php else: ?>
                        <div class="visit-list">
                            <?php foreach ($visits as $visit): ?>
                                <div class="visit-item status-<?= $visit['status'] ?>">
                                    <div class="visit-user">
                                        <img src="../assets/images/<?= htmlspecialchars($visit['avatar'] ?? 'default.jpg') ?>"
                                             class="avatar" alt="<?= htmlspecialchars($visit['username']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:50%;flex-shrink:0;">
                                        <div class="user-info">
                                            <span class="username"><?= htmlspecialchars($visit['applicant_circle_name']) ?></span>
                                            <span class="visit-date"><?= date('Y-m-d', strtotime($visit['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="visit-details">
                                        <div class="status-badge <?= $visit['status'] ?>">
                                            <?= $visit['status'] ?>
                                        </div>
                                        <?php if ($visit['visit_date']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-calendar-day"></i>
                                                <span>访问日期: <?= $visit['visit_date'] ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($visit['return_date']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-calendar-check"></i>
                                                <span>回访日期: <?= $visit['return_date'] ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="visit-actions">
                                        <?php if ($visit['status'] == 'pending' && $_SESSION['user_id'] == $circleInfo['user_id']): ?>
                                            <a href="../user/confirm_visit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-check"></i> 确认
                                            </a>
                                        <?php endif; ?>
                                        <a href="../user/visit_detail.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-info-circle"></i> 详情
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- 统计信息卡片 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> 互访统计</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?= $circleInfo['block_count'] ?></div>
                            <div class="stat-label">区块数量</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= count($visits) ?></div>
                            <div class="stat-label">总访问数</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= 
                                count(array_filter($visits, fn($v) => $v['status'] == 'completed')) ?></div>
                            <div class="stat-label">已完成</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 操作卡片 -->
            <?php if ($canRequest): ?>
				<?php 
				// 获取用户在同城的互访圈
				$userCircles = $circle->getCirclesByConditions([
					'user_id' => $_SESSION['user_id'],
					'city' => $circleInfo['city']
				]);
				?>
				
				<div class="card mb-4" id="request">
					<div class="card-header">
						<h3><i class="fas fa-handshake"></i> 申请互访</h3>
					</div>
					<div class="card-body">
						<form method="post">
							<!--<div class="form-group">
								<label>您的城市</label>
								<input type="text" class="form-control" value="<?= 
									htmlspecialchars($currentUser['city']) ?>" readonly>
							</div>-->
							
							<div class="form-group">
								<label>选择你 <?= htmlspecialchars($circleInfo['city']) ?> 的互访圈</label>
								<?php if (!empty($userCircles)): ?>
									<select name="applicant_circle_id" class="form-control" required>
										<option value="">-- 请选择 --</option>
										<?php foreach ($userCircles as $uc): ?>
											<option value="<?= $uc['id'] ?>">
												<?= htmlspecialchars($uc['name']) ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else: ?>
									<div class="alert alert-warning">
										<p>你在 <?= htmlspecialchars($circleInfo['city']) ?> 还没有创建互访圈</p>
										<a href="../circles/create.php?city=<?= urlencode($circleInfo['city']) ?>" 
										   class="btn btn-primary btn-sm">
											<i class="fas fa-plus"></i> 立即创建
										</a>
									</div>
								<?php endif; ?>
							</div>
							
							<?php if (!empty($userCircles)): ?>
								<button type="submit" name="request_visit" class="btn btn-primary btn-block">
									<i class="fas fa-paper-plane"></i> 提交互访申请
								</button>
							<?php endif; ?>
						</form>
					</div>
				</div>
 
            <?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $circleInfo['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> 访问状态</h3>
                    </div>
                    <div class="card-body">
                        <?php 
                        $userVisit = null;
                        foreach ($visits as $v) {
                            if ($v['visitor_id'] == $_SESSION['user_id']) {
                                $userVisit = $v;
                                break;
                            }
                        }
                        ?>
                        
                        <?php if ($userVisit): ?>
                            <div class="visit-status">
                                <div class="status-badge <?= $userVisit['status'] ?>">
                                    <?= $userVisit['status'] ?>
                                </div>
                                
                                <?php if ($userVisit['visit_date']): ?>
                                    <div class="detail-item mt-3">
                                        <i class="fas fa-calendar-day"></i>
                                        <span>访问日期: <?= $userVisit['visit_date'] ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($userVisit['status'] == 'confirmed' && !$userVisit['return_date']): ?>
                                    <a href="record_return.php?id=<?= $userVisit['id'] ?>" 
                                       class="btn btn-success btn-block mt-3">
                                        <i class="fas fa-check-double"></i> 记录回访
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <?php if ($currentUser['city'] != $circleInfo['city']): ?>
                                    <p><i class="fas fa-info-circle"></i> 您与互访圈不在同一城市，无法申请访问</p>
                                <?php else: ?>
                                    <p><i class="fas fa-info-circle"></i> 您已申请过访问此互访圈</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 所有者管理卡片 -->
            <?php if ($_SESSION['user_id'] == $circleInfo['user_id']): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> 管理选项</h3>
                    </div>
                    <div class="card-body">
                        <a href="edit.php?id=<?= $circleInfo['id'] ?>" class="btn btn-outline-primary btn-block mb-2">
                            <i class="fas fa-edit"></i> 编辑互访圈
                        </a>
                        <a href="../user/visits.php?circle_id=<?= $circleInfo['id'] ?>" class="btn btn-outline-secondary btn-block mb-2">
                            <i class="fas fa-list"></i> 管理访问记录
                        </a>
                        <button class="btn btn-outline-danger btn-block" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i> 删除互访圈
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 删除确认模态框 -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">确认删除</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                您确定要删除这个互访圈吗？此操作无法撤销，所有相关数据将被永久删除。
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <a href="delete.php?id=<?= $circleInfo['id'] ?>" class="btn btn-danger">确认删除</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>