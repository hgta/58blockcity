<?php
require_once '../config/database.php';
require_once '../classes/Task.php';
require_once '../classes/TaskCategory.php';
require_once '../includes/auth.php';
require_once 'includes/helpers.php';

$task = new Task($pdo);
$taskCat = new TaskCategory($pdo);
$task->expireOverdue(); // 惰性关闭到期任务

// 筛选与排序
$tab    = in_array($_GET['tab'] ?? '', ['open', 'full', 'ended'], true) ? $_GET['tab'] : 'open';
$catId  = max(0, intval($_GET['category'] ?? 0));
$city   = trim((string)($_GET['city'] ?? ''));
$rtype  = in_array($_GET['reward_type'] ?? '', ['popularity', 'cash'], true) ? $_GET['reward_type'] : '';
$sort   = ($_GET['sort'] ?? '') === 'reward' ? 'reward' : 'new';
$page   = max(1, intval($_GET['page'] ?? 1));

$result = $task->listPlaza([
    'tab' => $tab, 'category' => $catId, 'city' => $city,
    'reward_type' => $rtype, 'sort' => $sort,
], $page, 12);
$list  = $result['list'];
$pages = $result['pages'];
$page  = $result['page'];

$categories = $taskCat->listActive();

// 广场中出现的城市（去重，供筛选下拉）
$cityOptions = [];
try {
    $cityOptions = $pdo->query(
        "SELECT DISTINCT city FROM tasks WHERE city IS NOT NULL AND city <> '' ORDER BY city"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $cityOptions = []; }

// 当前页任务城市 → pinyin（卡片“城市目标”导流）
$cityPinyinMap = [];
$cityNamesOnPage = [];
foreach ($list as $t) {
    if (!empty($t['city'])) $cityNamesOnPage[$t['city']] = true;
}
if ($cityNamesOnPage) {
    $cityPinyinMap = task_city_pinyin_map($pdo, array_keys($cityNamesOnPage));
}

$site_config['title'] = $tab === 'ended' ? '已结束任务 - 任务广场'
    : ($tab === 'full' ? '名额已满任务 - 任务广场' : '任务广场 - 发布悬赏 · 众包领取');
$site_config['description'] = '任务广场：找人代互访、代打卡、代做市长，或领取悬赏赚人气值/现金。';
require_once 'includes/header.php';
?>
<style>
.task-wrap { max-width: 1100px; margin: 20px auto 0; padding: 0 15px; }
.task-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 4px; }
.task-head h1 { font-size: 23px; margin: 0; }
.task-publish { background: #ff6b00; color: #fff !important; text-decoration: none !important; padding: 8px 16px; border-radius: 8px; font-size: 14px; }
.task-tabs { display: flex; gap: 6px; border-bottom: 1px solid #eef0f3; margin: 14px 0 10px; flex-wrap: wrap; }
.task-tab { padding: 8px 16px; border-radius: 8px 8px 0 0; text-decoration: none; color: #7a7f87; font-size: 14px; }
.task-tab.active { color: #ff6b00; font-weight: 600; border-bottom: 2px solid #ff6b00; }
.task-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
.task-filter { padding: 6px 12px; border-radius: 18px; border: 1px solid #e3e6ea; background: #fff; color: #555; font-size: 13px; text-decoration: none; }
.task-filter.active { background: #fff2e8; color: #ff6b00; border-color: #ffb37f; }
.task-filter-select { padding: 6px 12px; border-radius: 18px; border: 1px solid #e3e6ea; background: #fff; font-size: 13px; color: #555; }
.task-list { display: flex; flex-direction: column; gap: 12px; }
.task-card { background: #fff; border: 1px solid #f0ece6; border-radius: 12px; padding: 16px 18px; display: flex; gap: 14px; justify-content: space-between; align-items: flex-start; text-decoration: none; color: inherit; transition: box-shadow .2s; }
.task-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); border-color: #ffc9a0; }
.task-main { flex: 1; min-width: 0; }
.task-title { font-size: 16px; font-weight: 700; color: #222; margin: 0 0 6px; }
.task-desc { color: #7a8087; font-size: 13px; line-height: 1.6; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.task-meta { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 12px; color: #9aa1a8; }
.task-meta b { color: #666; font-weight: 600; }
.task-meta a.ext { color: #4f6ef7; text-decoration: none; }
.task-side { text-align: right; flex-shrink: 0; }
.task-reward { color: #ff3b3b; font-weight: 800; font-size: 18px; white-space: nowrap; }
.task-reward .unit { font-size: 12px; color: #b9bfc7; font-weight: 400; margin-left: 2px; }
.task-side .slots { font-size: 12px; color: #8b929a; margin-top: 4px; }
.task-badge { display: inline-block; font-size: 11px; padding: 2px 9px; border-radius: 10px; background: #f4f6fa; color: #6a7481; margin-bottom: 8px; margin-right: 6px; }
.task-badge.open { background: #e8f6ef; color: #0a9d62; }
.task-badge.full { background: #fff2e8; color: #ff7a18; }
.task-badge.ended { background: #f1f2f4; color: #979ba1; }
.task-empty { text-align: center; padding: 70px 20px; color: #9aa1a8; }
.pager { text-align: center; margin: 22px 0 6px; }
.pager a, .pager span { display: inline-block; padding: 6px 14px; margin: 0 3px; border-radius: 8px; border: 1px solid #e7e9ed; font-size: 13px; color: #666; text-decoration: none; }
.pager a:hover { border-color: #ff6b00; color: #ff6b00; }
</style>

<div class="task-wrap">
    <?php displayFlashMessages(); ?>
    <div class="task-head">
        <h1>🧩 任务广场<?= $tab === 'ended' ? ' · 已结束' : ($tab === 'full' ? ' · 已满' : '') ?></h1>
        <a class="task-publish" href="create.php">＋ 发布悬赏任务</a>
    </div>

    <div class="task-tabs">
        <a class="task-tab <?= $tab === 'open' ? 'active' : '' ?>"
           href="index.php?<?= http_build_query(array_merge($_GET, ['tab' => 'open', 'page' => 1])) ?>">进行中</a>
        <a class="task-tab <?= $tab === 'full' ? 'active' : '' ?>"
           href="index.php?<?= http_build_query(array_merge($_GET, ['tab' => 'full', 'page' => 1])) ?>">已满</a>
        <a class="task-tab <?= $tab === 'ended' ? 'active' : '' ?>"
           href="index.php?<?= http_build_query(array_merge($_GET, ['tab' => 'ended', 'page' => 1])) ?>">已结束</a>
    </div>

    <form class="task-filters" method="get" action="index.php">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <a class="task-filter <?= $catId === 0 ? 'active' : '' ?>"
           href="index.php?<?= http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])) ?>">全部分类</a>
        <?php foreach ($categories as $c): ?>
            <a class="task-filter <?= $catId === (int)$c['id'] ? 'active' : '' ?>"
               href="index.php?<?= http_build_query(array_merge($_GET, ['category' => $c['id'], 'page' => 1])) ?>"><?= htmlspecialchars($c['name']) ?></a>
        <?php endforeach; ?>

        <select name="city" class="task-filter-select" onchange="this.form.submit()">
            <option value="">全部城市</option>
            <?php foreach ($cityOptions as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="reward_type" class="task-filter-select" onchange="this.form.submit()">
            <option value="">全部赏金</option>
            <option value="popularity" <?= $rtype === 'popularity' ? 'selected' : '' ?>>人气值</option>
            <option value="cash" <?= $rtype === 'cash' ? 'selected' : '' ?>>现金</option>
        </select>

        <a class="task-filter <?= $sort === 'new' ? 'active' : '' ?>"
           href="index.php?<?= http_build_query(array_merge($_GET, ['sort' => 'new', 'page' => 1])) ?>">最新</a>
        <a class="task-filter <?= $sort === 'reward' ? 'active' : '' ?>"
           href="index.php?<?= http_build_query(array_merge($_GET, ['sort' => 'reward', 'page' => 1])) ?>">赏金从高到低</a>
    </form>

    <?php if (empty($list)): ?>
        <div class="task-empty">
            <div style="font-size:42px;opacity:.35;">🧩</div>
            <p style="margin-top:12px;">暂无匹配任务，换个筛选条件试试，或<a href="create.php" style="color:#ff6b00;">发布一个</a></p>
        </div>
    <?php else: ?>
    <div class="task-list">
        <?php foreach ($list as $t):
            $label = task_status_label($t);
            $target = task_target_link($t); ?>
            <a class="task-card" href="view.php?id=<?= (int)$t['id'] ?>">
                <div class="task-main">
                    <span class="task-badge <?= $label === '进行中' ? 'open' : ($label === '已满' ? 'full' : 'ended') ?>"><?= $label ?></span>
                    <span class="task-badge"><?= htmlspecialchars($t['category_name'] ?? '未分类') ?></span>
                    <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
                    <p class="task-desc"><?= htmlspecialchars(mb_substr((string)$t['description'], 0, 120)) ?></p>
                    <div class="task-meta">
                        <span>👤 <b><?= htmlspecialchars($t['employer_name'] ?? '') ?></b></span>
                        <?php if (!empty($t['city'])): ?>
                            <span>🏙 <?php if (!empty($cityPinyinMap[$t['city']] ?? '')): ?><a class="ext" href="https://block.58.tl/city.php?name=<?= rawurlencode($cityPinyinMap[$t['city']]) ?>" target="_blank" rel="noopener" title="前往该城市"><?= htmlspecialchars($t['city']) ?> ↗</a><?php else: ?><b><?= htmlspecialchars($t['city']) ?></b><?php endif; ?></span>
                        <?php endif; ?>
                        <span>🕒 截止 <?= date('m-d H:i', strtotime($t['expire_at'])) ?></span>
                        <?php if ($target): ?><span>🔗 <b><?= htmlspecialchars($target['name']) ?></b></span><?php endif; ?>
                    </div>
                </div>
                <div class="task-side">
                    <div class="task-reward"><?= task_reward_text($t) ?></div>
                    <div class="slots"><?= task_remaining_text($t) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pager">
        <?php if ($page > 1): ?><a href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">上一页</a><?php endif; ?>
        <span><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">下一页</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
