<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
require_once '../../classes/User.php';

$blockId = $_GET['id'] ?? 0;

$block = new Block($pdo);
$user = new User($pdo);

$blockInfo = $block->getBlockById($blockId);
$ownerInfo = $blockInfo['owner_id'] ? $user->getUserById($blockInfo['owner_id']) : null;

if (!$blockInfo) {
    header("Location: ../city/");
    exit();
}

$isOwner = isLoggedIn() && $_SESSION['user_id'] == $blockInfo['owner_id'];
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="block-detail">
        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <?= htmlspecialchars($blockInfo['city_name']) ?> - <?= $blockInfo['zone'] ?>区 - 区块 <?= $blockInfo['block_number'] ?>
                            <?php if ($isOwner): ?>
                                <span class="label label-primary">我的区块</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <?php if ($blockInfo['name']): ?>
                            <h2><?= htmlspecialchars($blockInfo['name']) ?></h2>
                        <?php endif; ?>
                        
                        <?php if ($blockInfo['description']): ?>
                            <div class="block-description">
                                <?= nl2br(htmlspecialchars($blockInfo['description'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="block-meta">
                            <div class="meta-item">
                                <span class="meta-label">状态:</span>
                                <span class="meta-value">
                                    <?php if ($blockInfo['status'] == 'sold'): ?>
                                        <span class="text-danger">已售</span>
                                    <?php else: ?>
                                        <span class="text-success">可购买</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="meta-item">
                                <span class="meta-label">价格:</span>
                                <span class="meta-value"><?= number_format($blockInfo['price'], 2) ?> 元</span>
                            </div>
                            
                            <?php if ($ownerInfo): ?>
                                <div class="meta-item">
                                    <span class="meta-label">拥有者:</span>
                                    <span class="meta-value">
                                        <a href="../user/profile.php?id=<?= $ownerInfo['id'] ?>">
                                            <?= htmlspecialchars($ownerInfo['username']) ?>
                                        </a>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <?php if ($isOwner): ?>
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">区块管理</h3>
                        </div>
                        <div class="panel-body">
                            <a href="edit.php?id=<?= $blockInfo['id'] ?>" class="btn btn-default btn-block">编辑区块信息</a>
                            <a href="sell.php?id=<?= $blockInfo['id'] ?>" class="btn btn-warning btn-block">出售此区块</a>
                        </div>
                    </div>
                <?php elseif (isLoggedIn() && $blockInfo['status'] == 'available'): ?>
                    <div class="panel panel-success">
                        <div class="panel-heading">
                            <h3 class="panel-title">购买区块</h3>
                        </div>
                        <div class="panel-body">
                            <form action="buy.php" method="post">
                                <input type="hidden" name="block_id" value="<?= $blockInfo['id'] ?>">
                                <div class="form-group">
                                    <label>区块价格</label>
                                    <p class="form-control-static"><?= number_format($blockInfo['price'], 2) ?> 元</p>
                                </div>
                                <button type="submit" class="btn btn-success btn-block">确认购买</button>
                            </form>
                        </div>
                    </div>
                <?php elseif (isLoggedIn() && $blockInfo['status'] == 'sold'): ?>
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h3 class="panel-title">联系拥有者</h3>
                        </div>
                        <div class="panel-body">
                            <p>此区块已有拥有者，您可以联系他们协商购买。</p>
                            <a href="../messages/new.php?to=<?= $ownerInfo['id'] ?>" class="btn btn-primary btn-block">发送消息</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">操作区块</h3>
                        </div>
                        <div class="panel-body">
                            <p>请<a href="../auth/login.php">登录</a>后购买或管理区块。</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>