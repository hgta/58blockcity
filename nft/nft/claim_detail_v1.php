<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';
require_once '../../classes/Comment.php';

checkLogin();

$nftId = $_GET['id'] ?? 0;
$nft = new NFT($pdo);
$city = new City($pdo);
$comment = new Comment($pdo);

// 获取NFT基础信息
$nftInfo = $nft->getNftById($nftId);
if (!$nftInfo) {
    header("Location: /nft/claim_list.php");
    exit;
}

// 获取所有城市列表
$cities = $city->getAllCities();

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
	
    /* if (!checkLogin(false)) {
        $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
        header("Location: /auth/login.php");
        exit;
    } */
    
	//echo '0'.$userId . ' '. $cityId .' '.$blockId;
	//exit;
    // 检查是否为申诉请求
    if (isset($_POST['appeal'])) {
        header("Location: /nft/appeal.php?nft_id=$nftId&city_id=$cityId");
        exit;
    } else {		
        // 正常认领流程
        if (empty($blockId)) {
            $_SESSION['error_message'] = '请输入区块ID';			
        } elseif ($nft->claimNft($nftId, $userId, $cityId, $blockId)) {
            $_SESSION['success_message'] = '认领成功！';			
            header("Location: /user/collection.php");
            exit;
        } else {
            $_SESSION['error_message'] = '认领失败，请重试';	
			//echo($nftId .' '. $userId .' '. $cityId .' '. $blockId);
			//exit;
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
        </div>
        
        <div class="col-md-6">
            <div class="card">
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
                                        <?= htmlspecialchars($city['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="claimStatus" class="mb-3" style="display: none;">
                            <!-- 动态显示认领状态 -->
                        </div>
                        
                        <div id="claimFormFields" style="display: none;">
							<div class="form-group">
								<label for="blockId">输入您的区块ID</label>
								<input type="text" class="form-control" id="blockId" name="block_id" 
									   placeholder="例如: 0101" required>
								<small class="form-text text-muted">请输入您在该城市的区块ID</small>
							</div>
							<button type="submit" class="btn btn-success btn-block">
								<i class="fas fa-hand-holding-heart"></i> 确认认领
							</button>
						</div>
                    </form>
                </div>
            </div>
			
			<!-- 评论区域 -->
                <div class="col-12">
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
        
        // 触发一次change事件，如果有默认选中的城市
        $('#citySelect').trigger('change');
    });
})(jQuery); // 传递jQuery对象确保$可用
</script>

<!-- 申诉模态框 - 移除了show类和style="display: block" -->

<!---
<div class="modal fade" id="appealModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="city_id" id="modalCityId">
                <input type="hidden" name="appeal" value="1">
                
                <div class="modal-header">
                    <h5 class="modal-title">申诉取回NFT</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>请说明您为什么应该拥有这个NFT的所有权：</p>
                    <div class="form-group">
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>上传证据图片</label>
                        <input type="file" name="evidence" class="form-control-file" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-warning">提交申诉</button>
                </div>
            </form>
        </div>
    </div>
</div>
-->

<script>
// 设置申诉城市ID
$('#appealModal').on('show.bs.modal', function(event) {
    var cityId = $('#citySelect').val();
    $('#modalCityId').val(cityId);
});
</script>

<?php require_once '../includes/footer.php'; ?>