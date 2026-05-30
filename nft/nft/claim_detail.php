<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';
require_once '../../classes/Comment.php';
require_once '../../classes/Block.php';

checkLogin();

$nftId = $_GET['id'] ?? 0;
$nft = new NFT($pdo);
$city = new City($pdo);
$comment = new Comment($pdo);
$block = new Block($pdo);

// 获取NFT基础信息
$nftInfo = $nft->getNftById($nftId);
if (!$nftInfo) {
    header("Location: /nft/claim_list.php");
    exit;
}

// 获取所有城市列表
$cities = $city->getAllCities();

// 获取最近认领记录
$recentClaims = $nft->getRecentClaims($nftId, 10);

// 处理认领请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $cityId = $_POST['city_id'];
    $blockId = $_POST['block_id'];
    
    // 处理评论提交
    if (isset($_POST['comment_text'])) {
        $commentText = trim($_POST['comment_text']);
        if (!empty($commentText)) {
            if ($comment->addComment($userId, $nftId, $commentText)) {
                $_SESSION['message'] = "评论发布成功！";
            } else {
                $_SESSION['error'] = "评论发布失败";
            }
        }
        header("Location: claim_detail.php?id=$nftId");
        exit;
    }
    
    // 正常认领流程
    if (empty($blockId)) {
        $_SESSION['error_message'] = '请输入区块ID';            
    } else {
        // 检查区块是否存在，如果不存在则自动创建
		$blockInfo = $block->getBlockByCityAndNumber($cityId, $blockId);
		if (!$blockInfo) {
			// 根据区块ID判断所属区域和价格
			$zone = $block->determineZoneByBlockId($blockId);
			$basePrice = $block->calculateBasePrice($blockId, $zone);
			
			// 自动创建区块记录
			$blockInfo = $block->createBlock($cityId, $zone, $blockId, $userId, $basePrice);
			
			if (!$blockInfo) {
				$_SESSION['error_message'] = '创建区块记录失败，请重试';
				// 可以在这里记录日志或处理错误
			}
		}

		if ($blockInfo && $nft->claimNft($nftId, $userId, $cityId, $blockId)) {
			$_SESSION['success_message'] = '认领成功！';            
			header("Location: /user/collection.php");
			exit;
		} else {
			$_SESSION['error_message'] = '认领失败，请重试';    
		}
        
        if ($blockInfo && $nft->claimNft($nftId, $userId, $cityId, $blockId)) {
            $_SESSION['success_message'] = '认领成功！';            
            header("Location: /user/collection.php");
            exit;
        } else {
            $_SESSION['error_message'] = '认领失败，请重试';    
        }
    }
}  

// 获取NFT标签
$tags = $nft->getNftTags($nftId);

// 获取当前NFT的所有评论
$comments = $comment->getCommentsByNft($nftId);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h4>NFT头像详情</h4>
                </div>
                <div class="card-body text-center">
                    <img src="../avatar/<?= htmlspecialchars($nftInfo['base_image']) ?>" 
                         class="img-fluid rounded mb-3" style="max-height: 300px;" 
                         alt="NFT <?= htmlspecialchars($nftInfo['code']) ?>">
                    <h5><?= htmlspecialchars($nftInfo['code']) ?></h5>
                    
                    <!-- 标签显示 -->
                    <?php if (!empty($tags)): ?>
                        <div class="mb-3">
                            <?php foreach ($tags as $tag): ?>
                                <?php if (isset($tag['name'])): ?>
                                <a href="claim_list.php?tag=<?= urlencode($tag['name']) ?>" 
                                   class="badge bg-secondary text-decoration-none me-1">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 认领播报模块 -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-broadcast-tower"></i> 认领播报
                </div>
                <div class="card-body p-0">
                    <div id="claimBroadcast" style="max-height: 200px; overflow-y: auto;">
                        <?php if (!empty($recentClaims)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recentClaims as $claim): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="fw-bold"><?= htmlspecialchars($claim['block_id']) ?></span>
                                                <span class="text-muted">区块已认领</span>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('m-d H:i', strtotime($claim['claimed_at'])) ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($claim['city_name'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($claim['city_name']) ?></small>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center p-3 text-muted">
                                <i class="fas fa-info-circle"></i> 暂无认领记录
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h4>认领NFT</h4>
                </div>
                <div class="card-body">
                    <form id="claimForm" method="post">
                        <div class="form-group">
                            <label for="citySelect">选择城市</label>
                            <select class="form-control" id="citySelect" name="city_id" required>
                                <option value="">-- 请选择城市 --</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= $city['id'] ?>">
                                        <?= htmlspecialchars($city['name'] .' '. $city['rank']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="claimStatus" class="mb-3" style="display: none;">
                            <!-- 动态显示认领状态 -->
                        </div>
                        
                        <div id="claimFormFields" style="display: none;">
                            <div class="form-group">
                                <label for="blockId">选择您的区块ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="blockId" name="block_id" 
                                           placeholder="例如: 0101" required
                                           list="userBlocks">
                                    <button type="button" class="btn btn-outline-secondary" id="refreshBlocks">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <datalist id="userBlocks">
                                    <!-- 动态加载用户区块 -->
                                </datalist>
                                <small class="form-text text-muted">
                                    输入或选择您在该城市的区块ID，如果不存在将自动创建
                                </small>
                            </div>
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-hand-holding-heart"></i> 确认认领
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 评论区域 -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-comments"></i> 用户评价
                </div>
                <div class="card-body">
                    <!-- 评论表单 -->
                    <form method="post" class="mb-4">
                        <div class="mb-3">
                            <label for="commentText" class="form-label">发表您的评价</label>
                            <textarea class="form-control" id="commentText" name="comment_text" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-info">提交评价</button>
                    </form>
                    
                    <!-- 评论列表 -->
                    <div class="comments-section">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $commentItem): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="card-title"><?= htmlspecialchars($commentItem['username']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($commentItem['created_at']) ?></small>
                                        </div>
                                        <p class="card-text"><?= htmlspecialchars($commentItem['content']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">暂无评价，快来发表第一条评论吧！</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 使用jQuery兼容性写法确保$可用
(function($) {
    $(document).ready(function() {
        // 城市选择变化时检查认领状态
        $('#citySelect').on('change', function() {
            const cityId = $(this).val();
            const nftId = <?= $nftId ?>;  // 确保这里输出的是有效的数字
            
            // 隐藏状态和表单区域
            const $statusDiv = $('#claimStatus').hide();
            const $claimFields = $('#claimFormFields').hide();
            
            if (!cityId) {
                return; // 未选择城市，直接返回
            }
            
            // 显示加载状态
            $statusDiv.html('<div class="alert alert-info">检查 ' + cityId + ' ' + nftId +' 认领状态中...</div>').show();
            
            // 加载用户在该城市的区块
            loadUserBlocks(cityId);
            
            // 使用更健壮的AJAX请求
            $.ajax({
                url: '/api/nft_claim_status.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    nft_id: nftId,
                    city_id: cityId
                },
                timeout: 5000, // 5秒超时
                success: function(response) {
                    // 清空状态区域
                    $statusDiv.empty();
                    
                    if (response && response.claimed > 0) {
                        // 已被认领
                        $statusDiv.html(
                            '<div class="alert alert-warning">' +
                            '<p>该NFT已被 <a href="/block/' + (response.block_id || '') + '" target="_blank">' +
                            (response.username || '未知用户') + '的' + (response.city_name || '未知城市') + (response.block_id || '') + '区块</a> 认领</p>' +
                            '<button type="button" class="btn btn-warning btn-block" data-toggle="modal" data-target="#appealModal">' +
                            '<i class="fas fa-gavel"></i> 申诉取回</button>' +
                            '</div>'
                        ).show();
                        $claimFields.hide();
                    } else if (response) {
                        // 可认领
                        $statusDiv.html('<div class="alert alert-success">该NFT在此城市尚未被认领</div>').show();
                        $claimFields.show();
                    } else {
                        // 无效响应
                        showError('无效的服务器响应');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = '获取认领状态失败';
                    if (status === 'timeout') {
                        errorMsg = '请求超时，请重试';
                    } else if (error) {
                        errorMsg += ': ' + error;
                    }
                    showError(errorMsg);
                }
            });
            
            // 显示错误信息的辅助函数
            function showError(message) {
                $statusDiv.html('<div class="alert alert-danger">' + message + '</div>').show();
                $claimFields.hide();
            }
        });
        
        // 刷新区块列表
        $('#refreshBlocks').on('click', function() {
            const cityId = $('#citySelect').val();
            if (cityId) {
                loadUserBlocks(cityId);
            }
        });
        
        // 加载用户区块函数
        function loadUserBlocks(cityId) {
            $.ajax({
                url: '/api/get_user_blocks.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    city_id: cityId
                },
                success: function(blocks) {
                    const $datalist = $('#userBlocks');
                    $datalist.empty();
                    
                    if (blocks && blocks.length > 0) {
                        blocks.forEach(function(block) {
                            $datalist.append('<option value="' + block.block_number + '">');
                        });
                    }
                },
                error: function() {
                    console.log('加载用户区块失败');
                }
            });
        }
        
        // 触发一次change事件，如果有默认选中的城市
        $('#citySelect').trigger('change');
    });
})(jQuery); // 传递jQuery对象确保$可用
</script>

<?php require_once '../includes/footer.php'; ?>