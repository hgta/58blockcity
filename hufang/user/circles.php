<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

checkLogin();

$userId = $_SESSION['user_id'];
$circle = new Circle($pdo);
$visit = new Visit($pdo);

$userCircles = $circle->getUserCircles($userId);
$circleStats = [];

foreach ($userCircles as $c) {
    $circleStats[$c['id']] = [
        'pending' => count($visit->getCircleVisits($c['id'], 'pending')),
        'confirmed' => count($visit->getCircleVisits($c['id'], 'confirmed')),
        'completed' => count($visit->getCircleVisits($c['id'], 'completed'))
    ];
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container user-container">
    <div class="user-header">
        <h2><i class="fas fa-users"></i> 我的互访圈管理</h2>
        <div>
            <button id="shareProfileBtn" class="btn btn-outline-primary mr-2" data-toggle="tooltip" title="复制分享链接">
                <i class="fas fa-share-alt"></i> 分享我的互访圈
            </button>
            <a href="../circles/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 创建新互访圈
            </a>
        </div>
    </div>
     
	<!-- 
    <div class="share-section">
        <button id="generatePoster" class="btn btn-primary">
            <i class="fas fa-share-alt"></i> 生成分享海报
        </button>
        <div id="posterPreview" style="display:none; margin-top:20px; text-align:center;">
            <img id="generatedPoster" src="" alt="我的互访圈海报" style="max-width:100%; border:1px solid #eee;">
            <div>
                <a id="downloadPoster" class="btn btn-success mt-3" download="我的互访圈海报.png">
                    <i class="fas fa-download"></i> 下载海报
                </a>
            </div>
        </div>
    </div>
	-->

    <?php if (empty($userCircles)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-users-slash"></i>
            </div>
            <h3>您还没有创建任何互访圈</h3>
            <p>创建一个互访圈，开始与其他用户互动吧</p>
            <a href="../circles/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 创建第一个互访圈
            </a>
        </div>
    <?php else: ?>
        <div class="circle-grid">
            <?php foreach ($userCircles as $circle): ?>
                <div class="circle-card">
                    <div class="circle-header">
						<h3><?= htmlspecialchars($circle['name']) ?></h3>
						<div class="circle-header-info">
							<span class="circle-location">
								<i class="fas fa-map-marker-alt" style="margin-right:5px;"></i>
								<?= htmlspecialchars($circle['city']) ?>
							</span>
							<!-- 优化后的区块数显示 -->
							<span class="block-count-badge">
								<i class="fas fa-cube"></i>
								<span><?= $circle['block_count'] ?> 区块</span>
							</span>
						</div>
					</div>
                    
                    <div class="circle-body">
                        <p><?= nl2br(htmlspecialchars($circle['description'])) ?></p>
                    </div>
                    
                    <div class="circle-stats">
                        <div class="stat-item pending">
                            <span class="stat-count"><?= $circleStats[$circle['id']]['pending'] ?></span>
                            <span class="stat-label">待处理</span>
                        </div>
                        <div class="stat-item confirmed">
                            <span class="stat-count"><?= $circleStats[$circle['id']]['confirmed'] ?></span>
                            <span class="stat-label">已确认</span>
                        </div>
                        <div class="stat-item completed">
                            <span class="stat-count"><?= $circleStats[$circle['id']]['completed'] ?></span>
                            <span class="stat-label">已完成</span>
                        </div>
                    </div>
                    
                    <div class="circle-actions">
                        <button class="btn btn-sm btn-outline-secondary share-circle-btn mr-2" 
                                data-circle-id="<?= $circle['id'] ?>" 
                                data-toggle="tooltip" title="复制分享链接">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> 查看详情
                        </a>
                        <a href="visits.php?circle_id=<?= $circle['id'] ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-list"></i> 访问记录
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // 初始化工具提示
    $('[data-toggle="tooltip"]').tooltip();
    
    // 分享用户个人主页
    $('#shareProfileBtn').click(function() {
        const shareUrl = `https://v.58.tl/circles/circles.php?user_id=<?= $userId ?>`;
        copyToClipboard(shareUrl);
        $(this).attr('data-original-title', '链接已复制').tooltip('show');
        setTimeout(() => {
            $(this).attr('data-original-title', '复制分享链接');
        }, 2000);
    });
    
    // 分享单个互访圈
    $('.share-circle-btn').click(function() {
        const circleId = $(this).data('circle-id');
        const shareUrl = `https://v.58.tl/circles/view.php?id=${circleId}`;
        copyToClipboard(shareUrl);
        $(this).attr('data-original-title', '链接已复制').tooltip('show');
        setTimeout(() => {
            $(this).attr('data-original-title', '复制分享链接');
        }, 2000);
    });
    
    // 复制到剪贴板函数
    function copyToClipboard(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

    // 海报生成功能
    document.getElementById('generatePoster').addEventListener('click', function() {
        // 显示加载状态
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
        
        // 获取用户ID
        const userId = <?php echo $_SESSION['user_id']; ?>;
        
        // 调用API生成海报
        fetch(`share_poster.php?user_id=${userId}`)
            .then(response => response.blob())
            .then(blob => {
                const posterUrl = URL.createObjectURL(blob);
                document.getElementById('generatedPoster').src = posterUrl;
                document.getElementById('downloadPoster').href = posterUrl;
                document.getElementById('posterPreview').style.display = 'block';
                this.innerHTML = '<i class="fas fa-share-alt"></i> 生成分享海报';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('海报生成失败，请重试');
                this.innerHTML = '<i class="fas fa-share-alt"></i> 生成分享海报';
            });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>