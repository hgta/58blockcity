<?php
require_once '../../config/database.php';
require_once '../../classes/TaskSkill.php';
require_once '../../classes/TaskCategory.php';
require_once '../includes/auth.php';
checkLogin();

$uid     = (int)$_SESSION['user_id'];
$skillM  = new TaskSkill($pdo);
$catM    = new TaskCategory($pdo);

$skill = $skillM->getByUser($uid);
$categories = $catM->listActive();
$chosen = [];
if ($skill) {
    foreach (($skill['categories'] ?? []) as $c) {
        $chosen[] = (int)$c['id'];
    }
}

// ---------- 保存 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $description = trim((string)($_POST['description'] ?? ''));
    $refPrice    = trim((string)($_POST['ref_price'] ?? ''));
    $cats        = array_map('intval', (array)($_POST['category_ids'] ?? []));
    $active      = (string)($_POST['status_active'] ?? '1') !== '0'; // 未勾选/值为1=展示中；值为0=下架

    [$ok, $res] = $skillM->save($uid, $description, $cats, $refPrice, $active);
    if ($ok) {
        setFlashMessage('success', $active ? '技能卡已保存并展示中（求职者可在「找承接人」看到你）' : '技能卡已保存（当前为下架状态）');
        header('Location: skills.php');
        exit;
    }
    setFlashMessage('error', $res);
    header('Location: skills.php');
    exit;
}

// 重新读取以便展示最新状态
$skill = $skillM->getByUser($uid);
$chosen = [];
if ($skill) {
    foreach (($skill['categories'] ?? []) as $c) {
        $chosen[] = (int)$c['id'];
    }
}

$csrf = generateCsrfToken();

$site_config['title'] = '我的技能卡 - 任务广场';
$site_config['description'] = '维护求职侧技能卡：声明你能承接的任务类别、说明与参考价，让雇主在「找承接人」发现你。';
require_once '../includes/header.php';
?>
<style>
.sk-wrap { max-width: 760px; margin: 22px auto; padding: 0 15px; }
.sk-card { background: #fff; border: 1px solid #f0ece6; border-radius: 14px; padding: 22px 26px; }
.sk-card h1 { font-size: 21px; margin: 0 0 6px; }
.sk-tip { color: #8b929a; font-size: 13px; margin-bottom: 8px; }
.sk-form label { display: block; font-size: 13px; color: #56606b; font-weight: 600; margin: 16px 0 6px; }
.sk-form label .sub { font-weight: 400; color: #9aa1a8; margin-left: 4px; }
.sk-form input[type=text], .sk-form textarea { width: 100%; border: 1px solid #dde1e6; border-radius: 8px; padding: 10px 12px; font-size: 14px; box-sizing: border-box; }
.sk-form textarea { line-height: 1.7; }
.sk-cats { display: flex; flex-wrap: wrap; gap: 8px; }
.sk-cat { position: relative; }
.sk-cat input { display: none; }
.sk-cat label { display: block; margin: 0; padding: 7px 14px; border-radius: 18px; border: 1px solid #dde1e6; background: #fff; color: #56606b; font-weight: 400; font-size: 13px; cursor: pointer; }
.sk-cat input:checked + label { background: #fff2e8; border-color: #ffb37f; color: #ff6b00; font-weight: 600; }
.sk-status { display: flex; gap: 14px; margin-top: 8px; }
.sk-status label { margin: 0; font-weight: 400; }
.sk-btn { border: none; border-radius: 10px; padding: 11px 30px; font-size: 15px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 22px; }
.sk-btn.primary { background: #ff6b00; color: #fff; }
.sk-note { background: #f4f6fa; border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #5a626d; margin-top: 14px; }
.sk-note a { color: #3d63dd; }
</style>

<div class="sk-wrap">
    <div class="sk-card">
        <h1>🧰 我的技能卡</h1>
        <div class="sk-tip">求职侧名片：告诉雇主「你能承接什么、参考什么价位」。每人一张，可随时编辑上下架；雇主在任务广场「找承接人」页能看到展示中的技能卡。</div>

        <?php displayFlashMessages(); ?>

        <form class="sk-form" method="post" action="skills.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <label>可承接类别 <span class="req" style="color:#ff4d4f;">*</span> <span class="sub">多选（最多 20 个）</span></label>
            <div class="sk-cats">
                <?php foreach ($categories as $c): ?>
                    <div class="sk-cat">
                        <input type="checkbox" name="category_ids[]" id="cat-<?= (int)$c['id'] ?>" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $chosen, true) ? 'checked' : '' ?>>
                        <label for="cat-<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <label>「我能承接」说明 <span class="req" style="color:#ff4d4f;">*</span> <span class="sub">500 字内</span></label>
            <textarea name="description" rows="4" required placeholder="如：可每天代互访/代打卡，位于北京，行动前会和你确认时间地点…"><?= htmlspecialchars((string)($skill['description'] ?? '')) ?></textarea>

            <label>参考价 / 备注 <span class="sub">自由文本，如「互访 1 元/次 · 打卡 5 元/天」或「一次 2 人气值」</span></label>
            <input type="text" name="ref_price" maxlength="50" value="<?= htmlspecialchars((string)($skill['ref_price'] ?? '')) ?>" placeholder="例：代互访 2 人气值/次（可议）">

            <label>卡片状态</label>
            <div class="sk-status">
                <label><input type="radio" name="status_active" value="1" <?= !$skill || ($skill['status'] ?? '') === 'active' ? 'checked' : '' ?>> 展示中（雇主可见）</label>
                <label><input type="radio" name="status_active" value="0" <?= $skill && ($skill['status'] ?? '') === 'inactive' ? 'checked' : '' ?>> 下架（暂不接单）</label>
            </div>

            <button class="sk-btn primary" type="submit">保存技能卡</button>
        </form>

        <div class="sk-note">
            💡 保存后可在<a href="../find.php">「找承接人」页面</a>查看你的公开名片效果；收到合适悬赏记得去<a href="../index.php">任务广场</a>领取。
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
