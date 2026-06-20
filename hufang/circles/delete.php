<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

// 验证登录状态
checkLogin();

// 获取互访圈ID
$circleId = intval(\$_GET['id']) ?? 0;
if (!$circleId) {
    $_SESSION['error_message'] = '无效的互访圈ID';
    header('Location: index.php');
    exit;
}

// 初始化类
$circle = new Circle($pdo);
$visit = new Visit($pdo);

// 获取互访圈信息
$circleInfo = $circle->getCircleById($circleId);
if (!$circleInfo) {
    $_SESSION['error_message'] = '互访圈不存在';
    header('Location: index.php');
    exit;
}

// 验证当前用户是否有权限删除
if ($_SESSION['user_id'] != $circleInfo['user_id']) {
    $_SESSION['error_message'] = '无权删除此互访圈';
    header('Location: view.php?id='.$circleId);
    exit;
}

// 处理删除请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    try {
        // 开启事务
        $pdo->beginTransaction();
        
        // 1. 删除相关访问记录
        $visit->deleteVisitsByCircle($circleId);
        
        // 2. 删除互访圈
        $success = $circle->deleteCircle($circleId);
        
        if ($success) {
            $pdo->commit();
            $_SESSION['success_message'] = '互访圈已成功删除';
            header('Location: index.php');
            exit;
        } else {
            throw new Exception('删除互访圈失败');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = '删除过程中出错: '.$e->getMessage();
        header('Location: view.php?id='.$circleId);
        exit;
    }
}

// 显示确认页面
$pageTitle = '确认删除互访圈';
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>确认删除</h4>
        </div>
        
        <div class="card-body">
            <p class="lead">您确定要删除以下互访圈吗？此操作不可撤销！</p>
            
            <div class="alert alert-danger">
                <h5 class="alert-heading"><?= htmlspecialchars($circleInfo['name']) ?></h5>
                <p class="mb-1"><strong>城市:</strong> <?= htmlspecialchars($circleInfo['city']) ?></p>
                <p class="mb-1"><strong>区块数:</strong> <?= $circleInfo['block_count'] ?></p>
                <?php if ($circleInfo['description']): ?>
                <hr>
                <p class="mb-0"><strong>描述:</strong> <?= nl2br(htmlspecialchars($circleInfo['description'])) ?></p>
                <?php endif; ?>
            </div>
            
            <p class="text-danger"><i class="fas fa-info-circle me-1"></i>将同时删除该互访圈的所有访问记录！</p>
            
            <form method="post">
                            <?= csrfField() ?>
                         class="mt-4">
                <div class="d-flex justify-content-between">
                    <a href="view.php?id=<?= $circleId ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> 取消
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> 确认删除
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>