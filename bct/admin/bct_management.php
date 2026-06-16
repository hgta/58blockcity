<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/User.php';

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

        if (empty($userId) || empty($city) || $amount <= 0) {
            throw new Exception("请填写完整的有效信息");
        }

        $userData = $user->getUserById($userId);
        if (!$userData) {
            throw new Exception("用户不存在");
        }

        $isFrozen = ($operation === 'freeze');
        $adjustAmount = ($operation === 'deduct' || $operation === 'unfreeze') ? -$amount : $amount;
        $result = $account->updateBalance($userId, $city, $adjustAmount, $isFrozen);

        if ($result) {
            $logMessage = sprintf(
                "管理员 %s %s了用户 %s 在 %s 的BCT：%d个，原因：%s",
                $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'Admin'),
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
        header("Location: bct_management.php");
        exit();
    }
}

function getOperationName($operation) {
    $operations = ['add' => '增加', 'deduct' => '扣除', 'freeze' => '冻结', 'unfreeze' => '解冻'];
    return $operations[$operation] ?? $operation;
}

function logOperation($message) {
    $logFile = '../logs/bct_operations.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 显示消息
$successMessage = '';
$errorMessage = '';
if (isset($_SESSION['message'])) { $successMessage = $_SESSION['message']; unset($_SESSION['message']); }
if (isset($_SESSION['error'])) { $errorMessage = $_SESSION['error']; unset($_SESSION['error']); }

// 获取最近操作记录
$recentLogs = [];
$logFile = '../logs/bct_operations.log';
if (file_exists($logFile)) {
    $recentLogs = array_slice(array_reverse(file($logFile)), 0, 10);
}

$admin_site_config = ['site' => 'bct', 'page_title' => 'BCT 余额管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if ($successMessage): ?>
<div class="admin-card" style="border-left:4px solid #22c55e; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#86efac;"><?= htmlspecialchars($successMessage) ?></div>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="admin-card" style="border-left:4px solid #ef4444; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#fca5a5;"><?= htmlspecialchars($errorMessage) ?></div>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
    <!-- 余额调整表单 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-edit"></i> 调整 BCT 余额</span>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">用户ID *</label>
                    <input type="text" name="user_id" id="user_id" required
                           style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">城市 *</label>
                    <select name="city" id="city" required style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                        <option value="">选择城市</option>
                        <option value="北京">北京</option><option value="上海">上海</option>
                        <option value="广州">广州</option><option value="深圳">深圳</option>
                        <option value="杭州">杭州</option><option value="成都">成都</option>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">操作类型 *</label>
                    <select name="operation" required style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                        <option value="add">增加余额</option>
                        <option value="deduct">扣除余额</option>
                        <option value="freeze">冻结余额</option>
                        <option value="unfreeze">解冻余额</option>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">数量 *</label>
                    <input type="number" name="amount" min="1" required
                           style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">调整原因</label>
                    <textarea name="reason" rows="2" style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;resize:vertical;"></textarea>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary" style="width:100%;justify-content:center;">
                    <i class="fas fa-check"></i> 确认调整
                </button>
            </form>
        </div>
    </div>

    <!-- 最近操作记录 + 用户搜索 -->
    <div>
        <div class="admin-card" style="margin-bottom:20px;">
            <div class="admin-card-header">
                <span class="admin-card-title"><i class="fas fa-list-alt"></i> 最近操作记录</span>
            </div>
            <div class="admin-card-body" style="max-height:300px;overflow-y:auto;">
                <?php if (empty($recentLogs)): ?>
                    <div class="admin-empty-state" style="padding:24px;">
                        <i class="fas fa-info-circle"></i>
                        <p>暂无操作记录</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                    <div style="padding:10px;border-bottom:1px solid #1e293b;font-size:13px;color:#94a3b8;">
                        <?= htmlspecialchars(trim($log)) ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title"><i class="fas fa-search"></i> 用户搜索</span>
            </div>
            <div class="admin-card-body">
                <input type="text" id="user_search" placeholder="输入用户名或ID搜索"
                       style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;margin-bottom:10px;">
                <div id="search_results"></div>
            </div>
        </div>
    </div>
</div>

<style>
#search_results { max-height:200px; overflow-y:auto; }
.search-result-item { padding:10px; cursor:pointer; border-bottom:1px solid #1e293b; color:#94a3b8; font-size:13px; }
.search-result-item:hover { background:#0f172a; color:#e2e8f0; }
.search-result-item strong { color:#f1f5f9; display:block; margin-bottom:3px; }
</style>

<script>
document.getElementById('user_search').addEventListener('keyup', function() {
    var q = this.value.trim();
    var res = document.getElementById('search_results');
    if (q.length < 2) { res.innerHTML = ''; return; }

    fetch('../api/search_users.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            res.innerHTML = '';
            if (!data.length) { res.innerHTML = '<div style="padding:16px;text-align:center;color:#64748b;">未找到匹配用户</div>'; return; }
            data.forEach(function(u) {
                var d = document.createElement('div');
                d.className = 'search-result-item';
                d.innerHTML = '<strong>' + u.username + ' (ID: ' + u.id + ')</strong>城市: ' + (u.city || '-');
                d.onclick = function() {
                    document.getElementById('user_id').value = u.id;
                    document.getElementById('city').value = u.city || '';
                    res.innerHTML = '';
                };
                res.appendChild(d);
            });
        });
});
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
