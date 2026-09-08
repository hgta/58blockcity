<?php
require_once '../config/database.php';
require_once '../classes/TaskCategory.php';
require_once '../classes/TaskSkill.php';
require_once '../classes/TaskReview.php';
require_once '../classes/User.php';
require_once '../includes/auth.php';

$catM   = new TaskCategory($pdo);
$skillM = new TaskSkill($pdo);
$revM   = new TaskReview($pdo);

$catId = max(0, (int)($_GET['category'] ?? 0));
$page  = max(1, (int)($_GET['page'] ?? 1));

$result    = $skillM->listActive($catId, $page, 12);
$list      = $result['list'];
$pages     = $result['pages'];
$categories = $catM->listActive();

// 批量信誉摘要（收到评价 + 完成单数）
$workerIds = [];
foreach ($list as $s) { $workerIds[] = (int)$s['user_id']; }
$sumByUser = [];
$doneByUser = [];
if ($workerIds) {
    $holders = implode(',', array_fill(0, count($workerIds), '?'));
    try {
        $stmt = $pdo->prepare(
            "SELECT to_user_id, COUNT(*) AS cnt, AVG(rating) AS avg_rating
             FROM task_reviews WHERE to_user_id IN ($holders) GROUP BY to_user_id"
        );
        $stmt->execute($workerIds);
        foreach ($stmt->fetchAll() as $r) {
            $sumByUser[(int)$r['to_user_id']] = ['count' => (int)$r['cnt'], 'avg' => round((float)$r['avg_rating'], 1)];
        }
    } catch (Exception $e) { $sumByUser = []; }
    try {
        $stmt = $pdo->prepare(
            "SELECT worker_id, COUNT(*) AS cnt FROM task_claims
             WHERE worker_id IN ($holders) AND status = 'completed' GROUP BY worker_id"
        );
        $stmt->execute($workerIds);
        foreach ($stmt->fetchAll() as $r) {
            $doneByUser[(int)$r['worker_id']] = (int)$r['cnt'];
        }
    } catch (Exception $e) { $doneByUser = []; }
}

$isLogin = isLoggedIn();

$site_config['title'] = $catId ? '找承接人 · ' . (function () use ($categories, $catId) {
    foreach ($categories as $c) { if ((int)$c['id'] === $catId) return $c['name']; }
    return '全部';
})() . ' - 任务广场' : '找承接人 - 任务广场';
$site_config['description'] = '在展示中的求职技能卡里找到能承接代互访 / 代打卡 / 代做市长等任务的活跃承接人，查看其说明与历史评价并联系。';
require_once 'includes/header.php';
?>
<style>
.fd-wrap { max-width: 1000px; margin: 22px auto; padding: 0 15px; }
.fd-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.fd-head h1 { font-size: 23px; margin: 0; }
.fd-my { background: #fff; border: 1px solid #f0ece6; border-radius: 10px; padding: 10px 16px; font-size: 13px; color: #56606b; text-decoration: none; display:inline-block; }
.fd-my:hover { border-color: #ff6b00; color: #ff6b00; }
.fd-cats { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0 18px; }
.fd-cat { padding: 6px 14px; border-radius: 18px; border: 1px solid #e3e6ea; background: #fff; color: #555; font-size: 13px; text-decoration: none; }
.fd-cat.active { background: #fff2e8; color: #ff6b00; border-color: #ffb37f; }
.fd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
.fd-card { background: #fff; border: 1px solid #f0ece6; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 10px; }
.fd-top { display: flex; align-items: center; gap: 12px; }
.fd-avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; background: #f2f3f5; flex-shrink: 0; }
.fd-avatar img { width: 100%; height: 100%; object-fit: cover; }
.fd-name { font-weight: 700; color: #1f2430; font-size: 15px; }
.fd-stats { font-size: 12px; color: #8b929a; margin-top: 2px; }
.fd-cats-tag { display: flex; flex-wrap: wrap; gap: 6px; }
.fd-tag { font-size: 11px; background: #eef3ff; color: #3d63dd; border-radius: 10px; padding: 2px 9px; }
.fd-desc { font-size: 13px; color: #4a515a; line-height: 1.7; flex: 1; }
.fd-price { font-size: 13px; color: #ff6b00; font-weight: 600; }
.fd-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; border-top: 1px solid #f3f4f6; padding-top: 10px; }
.fd-msg { background: #ff6b00; color: #fff; text-decoration: none; font-size: 13px; padding: 7px 14px; border-radius: 8px; }
.fd-empty { text-align: center; padding: 70px 20px; color: #9aa1a8; }
.pager { text-align: center; margin: 22px 0 6px; }
.pager a, .pager span { display: inline-block; padding: 6px 14px; margin: 0 3px; border-radius: 8px; border: 1px solid #e7e9ed; font-size: 13px; color: #666; text-decoration: none; }
</style>

<div class="fd-wrap">
    <div class="fd-head">
        <h1>🧑‍🔧 找承接人</h1>
        <a class="fd-my" href="user/skills.php">＋ 我是承接人：创建/编辑我的技能卡</a>
    </div>

    <div class="fd-cats">
        <a class="fd-cat <?= $catId === 0 ? 'active' : '' ?>" href="find.php">全部</a>
        <?php foreach ($categories as $c): ?>
            <a class="fd-cat <?= $catId === (int)$c['id'] ? 'active' : '' ?>" href="find.php?category=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($list)): ?>
        <div class="fd-empty">
            <div style="font-size:42px;opacity:.35;">🧑‍🔧</div>
            <p style="margin-top:12px;">暂无展示中的技能卡<?= $catId ? '（换个类别试试）' : '' ?>。<a href="user/skills.php" style="color:#ff6b00;">成为第一个挂牌的承接人</a></p>
        </div>
    <?php else: ?>
    <div class="fd-grid">
        <?php foreach ($list as $s):
            $sum   = $sumByUser[(int)$s['user_id']] ?? null;
            $done  = $doneByUser[(int)$s['user_id']] ?? 0;
            $stars = $sum ? str_repeat('★', (int)round($sum['avg'])) . str_repeat('☆', 5 - (int)round($sum['avg'])) : ''; ?>
            <div class="fd-card">
                <div class="fd-top">
                    <div class="fd-avatar"><img src="<?= htmlspecialchars(User::avatarUrl((string)($s['avatar'] ?? ''))) ?>" alt="avatar" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'"></div>
                    <div>
                        <div class="fd-name"><?= htmlspecialchars($s['username'] ?? ('用户' . $s['user_id'])) ?></div>
                        <div class="fd-stats">
                            <?php if ($sum && $sum['count'] > 0): ?><span style="color:#ff9f2e;"><?= $stars ?></span> <?= $sum['avg'] ?> 分（<?= $sum['count'] ?> 评）<?php else: ?>暂无评价<?php endif; ?>
                            <?php if ($done > 0): ?> · ✅ 完成 <?= $done ?> 单<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="fd-cats-tag">
                    <?php foreach (explode('、', (string)$s['cat_names']) as $cn): if ($cn === '') continue; ?>
                        <span class="fd-tag"><?= htmlspecialchars($cn) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="fd-desc"><?= htmlspecialchars((string)$s['description']) ?></div>
                <?php if (!empty($s['ref_price'])): ?><div class="fd-price">参考：<?= htmlspecialchars($s['ref_price']) ?></div><?php endif; ?>
                <div class="fd-foot">
                    <span style="font-size:12px;color:#b3b9c1;">承接类别以技能卡为准</span>
                    <a class="fd-msg" href="https://www.58.tl/messages/index.php?with=<?= (int)$s['user_id'] ?>" target="_blank" rel="noopener">✉️ 发消息聊聊</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pager">
        <?php if ($page > 1): ?><a href="find.php?category=<?= $catId ?>&page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
        <span><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="find.php?category=<?= $catId ?>&page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
