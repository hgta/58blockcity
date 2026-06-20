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
            <?php foreach ($userCircles as $circle):
                $stats = $circleStats[$circle['id']] ?? [];
                $statsHtml = '<div class="circle-stats" style="margin-top:10px;">' .
                    '<div class="stat-item pending"><span class="stat-count">' . ($stats['pending'] ?? 0) . '</span><span class="stat-label">待处理</span></div>' .
                    '<div class="stat-item confirmed"><span class="stat-count">' . ($stats['confirmed'] ?? 0) . '</span><span class="stat-label">已确认</span></div>' .
                    '<div class="stat-item completed"><span class="stat-count">' . ($stats['completed'] ?? 0) . '</span><span class="stat-label">已完成</span></div>' .
                    '</div>';
                $actions = '<button class="btn btn-sm btn-outline-secondary share-circle-btn mr-2" data-circle-id="' . (int)$circle['id'] . '" data-toggle="tooltip" title="复制分享链接"><i class="fas fa-share-alt"></i></button>' .
                    '<a href="visits.php?circle_id=' . (int)$circle['id'] . '" class="btn btn-sm btn-secondary"><i class="fas fa-list"></i> 访问记录</a>';
                echo renderCircleCard($circle, null, $actions, $statsHtml, true);
            endforeach; ?>
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

    // 海报生成功能
    var posterBtn = document.getElementById('generatePoster');
    if (posterBtn) {
        posterBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
            const userId = <?php echo $_SESSION['user_id']; ?>;
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
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>