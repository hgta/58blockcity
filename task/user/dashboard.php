<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';
checkLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$avatar = $_SESSION['avatar'] ?? 'default.jpg';

// 统计（表未创建时忽略，待 G2 建表后正常返回）
$stats = ['published' => 0, 'claimed' => 0, 'skill' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE employer_id = ?");
    $stmt->execute([$userId]);
    $stats['published'] = intval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_claims WHERE worker_id = ?");
    $stmt->execute([$userId]);
    $stats['claimed'] = intval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_skills WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $stats['skill'] = intval($stmt->fetchColumn());
} catch (Exception $e) {
    // 任务相关表可能尚未创建（migrate-task.sql 未执行），忽略
}

$site_config['title'] = '个人中心 - 任务广场';
require_once '../includes/header.php';
?>
<style>
.dash-wrap { max-width: 760px; margin: 24px auto; padding: 0 15px; }
.dash-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 16px; }
.dash-head { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.dash-avatar { width: 64px; height: 64px; border-radius: 50%; overflow: hidden; background: #f0f0f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.dash-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dash-name { font-size: 20px; font-weight: bold; color: #222; }
.dash-sub { font-size: 13px; color: #999; }
.dash-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.dash-stat { text-align: center; padding: 16px; background: #fafafa; border-radius: 8px; }
.dash-stat .num { font-size: 22px; font-weight: bold; color: #ff6b00; }
.dash-stat .lbl { font-size: 12px; color: #999; margin-top: 4px; }
.dash-links { display: flex; flex-direction: column; gap: 0; }
.dash-link { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid #f2f2f2; text-decoration: none; color: #333; font-size: 14px; }
.dash-link:hover { background: #fff9f5; }
.dash-link:last-child { border-bottom: none; }
.dash-link .arrow { color: #ccc; }
</style>

<div class="dash-wrap">
    <div class="dash-card">
        <div class="dash-head">
            <div class="dash-avatar">
                <img src="<?= htmlspecialchars(User::avatarUrl($avatar)) ?>" alt="" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'">
            </div>
            <div>
                <div class="dash-name"><?= htmlspecialchars($username) ?></div>
                <div class="dash-sub">任务广场 · 个人中心</div>
            </div>
        </div>
        <div class="dash-stats">
            <div class="dash-stat"><div class="num"><?= $stats['published'] ?></div><div class="lbl">我发布的任务</div></div>
            <div class="dash-stat"><div class="num"><?= $stats['claimed'] ?></div><div class="lbl">我承接的单</div></div>
            <div class="dash-stat"><div class="num"><?= $stats['skill'] ?></div><div class="lbl">展示中技能卡</div></div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-links">
            <a class="dash-link" href="../my.php">📋 我的发布 / 我的承接 <span class="arrow">›</span></a>
            <a class="dash-link" href="../user/skills.php">🧰 我的技能卡（求职侧：我能承接什么） <span class="arrow">›</span></a>
            <a class="dash-link" href="../create.php">➕ 发布悬赏任务 <span class="arrow">›</span></a>
            <a class="dash-link" href="../index.php">🏠 返回任务广场 <span class="arrow">›</span></a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
