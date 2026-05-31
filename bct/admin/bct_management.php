<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/User.php';

//checkAdminLogin(); // 需要管理员权限
checkAdmin();

$account = new UserBCTAccount($pdo);
$user = new User($pdo);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId = $_POST['user_id'];
        $city = $_POST['city'];
        $amount = (int)$_POST['amount'];
        $operation = $_POST['operation'];
        $reason = $_POST['reason'] ?? '管理员调整';

        // 验证输入
        if (empty($userId) || empty($city) || $amount <= 0) {
            throw new Exception("请填写完整的有效信息");
        }

        // 检查用户是否存在
        $userData = $user->getUserById($userId);
        if (!$userData) {
            throw new Exception("用户不存在");
        }

        // 执行余额更新
        $isFrozen = ($operation === 'freeze');
        $adjustAmount = ($operation === 'deduct' || $operation === 'unfreeze') ? -$amount : $amount;
        
        $result = $account->updateBalance($userId, $city, $adjustAmount, $isFrozen);
        
        if ($result) {
            // 记录操作日志
            $logMessage = sprintf(
                "管理员 %s %s了用户 %s 在 %s 的BCT：%d个，原因：%s",
                $_SESSION['admin_username'],
                getOperationName($operation),
                $userData['username'],
                $city,
                $amount,
                $reason
            );
            logOperation($logMessage);
            
            $_SESSION['message'] = "BCT余额更新成功";
            header("Location: bct_management.php");
            exit();
        } else {
            throw new Exception("BCT余额更新失败");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// 获取操作类型名称
function getOperationName($operation) {
    $operations = [
        'add' => '增加',
        'deduct' => '扣除',
        'freeze' => '冻结',
        'unfreeze' => '解冻'
    ];
    return $operations[$operation] ?? $operation;
}

// 记录操作日志
function logOperation($message) {
    $logFile = '../logs/bct_operations.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 显示消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}

// 获取最近操作记录
$recentLogs = [];
$logFile = '../logs/bct_operations.log';
if (file_exists($logFile)) {
    $recentLogs = array_slice(array_reverse(file($logFile)), 0, 10);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <!-- 页面标题 -->
    <div class="page-header">
        <h1>
            <i class="glyphicon glyphicon-piggy-bank"></i>
            BCT人气值管理
        </h1>
        <p class="text-muted">管理系统用户的人气值(BCT)余额</p>
    </div>

    <div class="row">
        <!-- 左侧 - 余额调整表单 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-edit"></i> 调整BCT余额</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="user_id">用户ID *</label>
                            <input type="text" class="form-control" id="user_id" name="user_id" required>
                            <small class="form-text text-muted">输入要调整的用户ID</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="city">城市 *</label>
                            <select class="form-control" id="city" name="city" required>
                                <option value="">选择城市</option>
                                <option value="北京">北京</option>
                                <option value="上海">上海</option>
                                <option value="广州">广州</option>
                                <option value="深圳">深圳</option>
                                <option value="杭州">杭州</option>
                                <option value="成都">成都</option>
                                <!-- 其他城市... -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="operation">操作类型 *</label>
                            <select class="form-control" id="operation" name="operation" required>
                                <option value="add">增加余额</option>
                                <option value="deduct">扣除余额</option>
                                <option value="freeze">冻结余额</option>
                                <option value="unfreeze">解冻余额</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">数量 *</label>
                            <input type="number" class="form-control" id="amount" name="amount" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason">调整原因</label>
                            <textarea class="form-control" id="reason" name="reason" rows="2"></textarea>
                            <small class="form-text text-muted">简要说明调整原因(可选)</small>
                        </div>
                        
                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="glyphicon glyphicon-ok"></i> 确认调整
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 右侧 - 最近操作记录 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-list-alt"></i> 最近操作记录</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recentLogs)): ?>
                        <div class="empty-state">
                            <i class="glyphicon glyphicon-info-sign"></i>
                            <p>暂无操作记录</p>
                        </div>
                    <?php else: ?>
                        <div class="operation-logs">
                            <?php foreach ($recentLogs as $log): ?>
                            <div class="log-item">
                                <p><?= htmlspecialchars(trim($log)) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 用户搜索 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-search"></i> 用户搜索</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <input type="text" class="form-control" id="user_search" placeholder="输入用户名或ID搜索">
                    </div>
                    <div id="search_results" class="search-results"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 页面特定样式 -->
<style>
/* 操作日志 */
.operation-logs {
    max-height: 300px;
    overflow-y: auto;
}

.log-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

.log-item:last-child {
    border-bottom: none;
}

/* 搜索区域 */
.search-results {
    max-height: 200px;
    overflow-y: auto;
    margin-top: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
    padding: 10px;
}

.search-result-item {
    padding: 8px;
    cursor: pointer;
    border-bottom: 1px solid #f5f5f5;
}

.search-result-item:hover {
    background-color: #f9f9f9;
}

.search-result-item h5 {
    margin: 0 0 3px;
    font-size: 14px;
}

.search-result-item p {
    margin: 0;
    font-size: 12px;
    color: #666;
}

/* 空状态 */
.empty-state {
    text-align: center;
    padding: 20px;
    color: #666;
}

.empty-state i {
    font-size: 30px;
    margin-bottom: 10px;
    color: #ccc;
}
</style>

<!-- 页面特定脚本 -->
<script>
$(document).ready(function() {
    // 用户搜索功能
    $('#user_search').on('keyup', function() {
        var query = $(this).val().trim();
        if (query.length < 2) {
            $('#search_results').empty();
            return;
        }
        
        $.ajax({
            url: '../api/search_users.php',
            type: 'GET',
            data: { q: query },
            success: function(data) {
                var results = $('#search_results');
                results.empty();
                
                if (data.length === 0) {
                    results.append('<div class="empty-state-sm">未找到匹配用户</div>');
                    return;
                }
                
                data.forEach(function(user) {
                    var item = $('<div class="search-result-item"></div>');
                    item.append('<h5>' + user.username + ' (ID: ' + user.id + ')</h5>');
                    item.append('<p>城市: ' + user.city + ' | 注册时间: ' + user.created_at + '</p>');
                    
                    item.click(function() {
                        $('#user_id').val(user.id);
                        $('#city').val(user.city);
                        $('#search_results').empty();
                    });
                    
                    results.append(item);
                });
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>