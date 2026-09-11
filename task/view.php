<?php
require_once '../config/database.php';
require_once '../classes/Task.php';
require_once '../classes/TaskClaim.php';
require_once '../classes/TaskReview.php';
require_once '../includes/auth.php';
require_once 'includes/helpers.php';

$taskObj = new Task($pdo);
$tc      = new TaskClaim($pdo);
$tr      = new TaskReview($pdo);

$taskId = (int)($_GET['id'] ?? 0);
$taskObj->expireOverdue(); // 惰性关闭已到期任务，保证任务状态为最新
$task = $taskObj->getById($taskId);
if (!$task) {
    header('HTTP/1.0 404 Not Found');
    echo '任务不存在或已删除';
    exit;
}

$uid      = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$isEmployer = $uid > 0 && (int)$task['employer_id'] === $uid;
$myClaim    = $uid > 0 ? $tc->userClaimOn($taskId, $uid) : null;

$claims   = $tc->claimsByTask($taskId); // 雇主视角需名单；访客仅用于计数展示
$claimable = $taskObj->isClaimable($task) && !$isEmployer && !$myClaim;
$target    = task_target_link($task);
$cityLink  = task_city_link($pdo, (string)($task['city'] ?? ''));
$label     = task_status_label($task);

// 我的待办标签
$myStatusText = [
    'accepted' => '待交付', 'submitted' => '待雇主验收', 'rejected' => '被驳回 · 待补交',
    'settling' => '结算中', 'completed' => '已完成', 'cancelled' => '已取消', 'disputed' => '争议中',
];
// 雇主视角下的认领状态文案（rejected 是雇主主动驳回，submitted 直接待验收）
$claimStatusText = $myStatusText;
if ($isEmployer) {
    $claimStatusText['submitted'] = '待验收';
    $claimStatusText['rejected']  = '已驳回';
}
$csrf = generateCsrfToken();

$site_config['title'] = $task['title'] . ' - 任务广场';
$site_config['description'] = mb_substr((string)$task['description'], 0, 150);
require_once 'includes/header.php';
?>
<style>
.tk-wrap { max-width: 980px; margin: 22px auto; padding: 0 15px; }
.tk-crumb { font-size: 13px; color: #9aa1a8; margin-bottom: 12px; }
.tk-crumb a { color: #9aa1a8; text-decoration: none; }
.tk-card { background: #fff; border: 1px solid #f0ece6; border-radius: 14px; padding: 22px 24px; margin-bottom: 16px; }
.tk-title { font-size: 21px; font-weight: 800; color: #1f2430; margin: 8px 0 6px; }
.tk-badge { display: inline-block; font-size: 12px; padding: 3px 10px; border-radius: 12px; background: #f1f3f6; color: #58606b; }
.tk-badge.open { background: #e8f6ef; color: #0a9d62; }
.tk-badge.full { background: #fff2e8; color: #ff7a18; }
.tk-badge.ended { background: #f1f2f4; color: #979ba1; }
.tk-meta { color: #8a929b; font-size: 13px; margin: 10px 0 0; line-height: 2; }
.tk-meta b { color: #4a515a; font-weight: 600; }
.tk-sec { color: #1f2430; font-weight: 700; font-size: 15px; margin: 18px 0 8px; }
.tk-text { color: #3f4550; font-size: 14px; line-height: 1.85; white-space: pre-wrap; word-break: break-word; }
.tk-tip { background: #fff8ec; border: 1px solid #f5e3bf; color: #96611b; border-radius: 10px; padding: 12px 16px; font-size: 13px; margin: 12px 0; }
.tk-ext { display: inline-block; background: #eef3ff; color: #3d63dd !important; border-radius: 8px; padding: 7px 14px; font-size: 13px; text-decoration: none; margin-top: 6px; }
.tk-btn { border: none; border-radius: 8px; padding: 10px 20px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
.tk-btn.primary { background: #ff6b00; color: #fff; }
.tk-btn.ghost { background: #fff; border: 1px solid #d8dce1; color: #56606b; }
.tk-btn.danger { background: #fee9e7; color: #d4380d; }
.tk-btn.success { background: #0a9d62; color: #fff; }
.tk-btn.small { padding: 6px 12px; font-size: 13px; }
.tk-form { background: #fafbfc; border-radius: 10px; padding: 14px 16px; margin-top: 12px; }
.tk-form label { display: block; font-size: 13px; color: #56606b; font-weight: 600; margin: 10px 0 4px; }
.tk-form textarea, .tk-form input[type=text], .tk-form input[type=number] { width: 100%; border: 1px solid #dde1e6; border-radius: 8px; padding: 9px 12px; font-size: 14px; }
.tk-claim { border-top: 1px dashed #eceff3; padding: 14px 2px; }
.tk-claim:first-of-type { border-top: none; }
.tk-claim-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }
.tk-who { font-size: 14px; font-weight: 600; color: #2b303b; }
.tk-st { font-size: 12px; padding: 2px 10px; border-radius: 10px; background: #f1f3f6; color: #58606b; }
.tk-st.submitted { background: #e8f1ff; color: #2f6fe4; }
.tk-st.rejected { background: #fee9e7; color: #d4380d; }
.tk-st.settling { background: #fff2e8; color: #ff7a18; }
.tk-st.completed { background: #e8f6ef; color: #0a9d62; }
.tk-st.disputed { background: #f3e8ff; color: #8b2fd4; }
.tk-proof { font-size: 13px; color: #5a626d; margin: 8px 0 0; white-space: pre-wrap; }
.tk-img { margin-top: 8px; max-width: 320px; border-radius: 8px; border: 1px solid #eceff3; }
.tk-note { font-size: 12px; color: #d4380d; margin-top: 6px; }
.tk-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.tk-review { border-top: 1px solid #eef0f3; padding: 10px 2px 0; margin-top: 10px; font-size: 13px; color: #6a7481; }
.tk-stars { color: #ff9f2e; letter-spacing: 2px; }
.tk-empty { color: #9aa1a8; font-size: 13px; text-align: center; padding: 30px 0; }
</style>

<div class="tk-wrap">
    <div class="tk-crumb"><a href="index.php">任务广场</a> › 任务详情</div>
    <?php displayFlashMessages(); ?>

    <div class="tk-card">
        <span class="tk-badge <?= $label === '进行中' ? 'open' : ($label === '已满' ? 'full' : 'ended') ?>"><?= $label ?></span>
        <span class="tk-badge"><?= htmlspecialchars($task['category_name'] ?? '未分类') ?></span>
        <?php if ($task['reward_type'] === 'cash'): ?><span class="tk-badge">现金 · 线下结算</span><?php endif; ?>
        <h1 class="tk-title"><?= htmlspecialchars($task['title']) ?></h1>
        <div class="tk-meta">
            <div>👤 发布者：<b><?= htmlspecialchars($task['employer_name'] ?? '') ?></b></div>
            <div>💰 赏金：<b style="color:#ff3b3b;font-size:16px;"><?= task_reward_text($task) ?></b></div>
            <div>👥 名额：<b><?= (int)$task['quota'] ?> 份</b> · 已领取 <?= (int)$task['claimed_count'] ?> / <?= (int)$task['quota'] ?></div>
            <?php if (!empty($task['city'])): ?>
                <div>🏙 关联城市：<?php if ($cityLink): ?><a class="tk-ext" style="padding:0 2px;" href="<?= htmlspecialchars($cityLink['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($task['city']) ?> ↗</a><?php else: ?><b><?= htmlspecialchars($task['city']) ?></b><?php endif; ?><?= $task['reward_type'] === 'popularity' ? '（人气值结算城市）' : '' ?></div>
            <?php endif; ?>
            <div>🕒 领取截止：<b><?= $task['expire_at'] ? date('Y-m-d H:i', strtotime($task['expire_at'])) : '长期有效' ?></b></div>
            <div>📋 验收期限：交付后 <b><?= (int)$task['review_days'] ?> 天</b>内</div>
        </div>

        <?php if ($task['reward_type'] === 'cash'): ?>
            <div class="tk-tip">⚠️ 本任务为<strong>现金悬赏、线下结算</strong>：平台不托管资金、不代收代付。验收通过后请双方线下完成付款，风险自担，保留沟通与凭证记录。</div>
        <?php endif; ?>

        <div class="tk-sec">📝 任务说明</div>
        <div class="tk-text"><?= htmlspecialchars((string)$task['description']) ?></div>

        <div class="tk-sec">✅ 验收说明</div>
        <div class="tk-text"><?= htmlspecialchars((string)($task['accept_desc'] ?? '')) ?></div>

        <?php if ($target): ?>
            <div class="tk-sec">🔗 关联对象</div>
            <a class="tk-ext" href="<?= htmlspecialchars($target['url']) ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars($target['label']) ?>（<?= htmlspecialchars($target['name']) ?>）↗
            </a>
        <?php endif; ?>
    </div>

    <?php if ($isEmployer): ?>
        <div class="tk-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div class="tk-sec" style="margin:0;">📥 认领进展（<?= count($claims) ?> 份）</div>
                <?php if ($task['status'] === 'open'): ?>
                    <form method="post" action="action.php" onsubmit="return confirm('确认关闭该任务？已交付认领仍可验收，未交付认领将被取消');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <button class="tk-btn danger small" type="submit">关闭任务</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($claims)): ?>
                <div class="tk-empty">还没有人领取该任务，去广场把它推荐给更多人吧</div>
            <?php endif; ?>

            <?php foreach ($claims as $cl):
                $clStatus = $cl['status'];
                $stClass = $clStatus; ?>
                <div class="tk-claim">
                    <div class="tk-claim-head">
                        <span class="tk-who">👤 <?= htmlspecialchars($cl['worker_name'] ?? ('#' . $cl['worker_id'])) ?></span>
                        <span class="tk-st <?= $stClass ?>"><?= htmlspecialchars($claimStatusText[$clStatus] ?? $clStatus) ?></span>
                    </div>

                    <?php if ($cl['proof_text']): ?><div class="tk-proof">📎 <?= htmlspecialchars($cl['proof_text']) ?></div><?php endif; ?>
                    <?php if ($cl['proof_image']): ?>
                        <a href="/<?= ltrim($cl['proof_image'], '/') ?>" target="_blank">
                            <img class="tk-img" src="/<?= ltrim($cl['proof_image'], '/') ?>" alt="交付凭证" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <?php if ($cl['employer_note'] && $clStatus === 'rejected'): ?><div class="tk-note">你的驳回意见：<?= htmlspecialchars($cl['employer_note']) ?></div><?php endif; ?>
                    <?php if ($cl['submitted_at']): ?><div class="tk-meta" style="margin-top:4px;">提交于 <?= date('m-d H:i', strtotime($cl['submitted_at'])) ?></div><?php endif; ?>
                    <?php if ($clStatus === 'submitted' && !empty($cl['review_due_at'])): ?>
                        <?php if (strtotime($cl['review_due_at']) < time()): ?>
                            <div class="tk-note">⚠️ 已超过验收期限（<?= date('m-d H:i', strtotime($cl['review_due_at'])) ?>），请尽快处理，避免被发起争议</div>
                        <?php else: ?>
                            <div class="tk-meta" style="margin-top:4px;">⏳ 请在 <?= date('m-d H:i', strtotime($cl['review_due_at'])) ?> 前验收</div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($clStatus === 'submitted'): ?>
                        <div class="tk-actions">
                            <form method="post" action="action.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="review">
                                <input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>">
                                <input type="hidden" name="decision" value="pass">
                                <button class="tk-btn success small" type="submit">✓ 验收通过</button>
                            </form>
                            <button class="tk-btn danger small" type="button" onclick="document.getElementById('reject-<?= (int)$cl['id'] ?>').style.display='block'">✕ 驳回</button>
                        </div>
                        <div id="reject-<?= (int)$cl['id'] ?>" style="display:none;" class="tk-form">
                            <form method="post" action="action.php">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="review">
                                <input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>">
                                <input type="hidden" name="decision" value="reject">
                                <label>驳回原因（必填，将展示给接单人）</label>
                                <textarea name="employer_note" rows="2" required placeholder="如：凭证截图不清晰 / 缺少关键信息"></textarea>
                                <div style="margin-top:8px;"><button class="tk-btn ghost small" type="submit">确认驳回</button></div>
                            </form>
                        </div>
                    <?php elseif ($clStatus === 'settling'): ?>
                        <div class="tk-actions">
                            <form method="post" action="action.php">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="settle">
                                <input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>">
                                <button class="tk-btn primary small" type="submit">🔄 结算 / 重试结算</button>
                            </form>
                            <?php if ($task['reward_type'] === 'popularity'): ?>
                                <span style="font-size:12px;color:#8b929a;">人气值任务：结算仅推进状态，不扣减/划转人气值</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // 认领评价展示
                    $claimReviews = $tr->listByClaim((int)$cl['id']);
                    if (!empty($claimReviews)):
                        foreach ($claimReviews as $r): ?>
                            <div class="tk-review">
                                <span class="tk-stars"><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?></span>
                                <b><?= htmlspecialchars($r['from_name'] ?? '') ?></b>：
                                <?= htmlspecialchars($r['content']) ?>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$isEmployer): ?>
        <div class="tk-card" style="text-align:center;">
            <?php if (!$uid): ?>
                <p style="color:#7a8087;margin-bottom:12px;">登录后可领取任务或查看进度</p>
                <a class="tk-btn primary" href="auth/login.php?redirect=<?= urlencode('view.php?id=' . $taskId) ?>">登录后领取</a>
            <?php elseif (!$myClaim): ?>
                <?php if ($claimable): ?>
                    <p style="color:#7a8087;margin-bottom:12px;">本任务共 <?= (int)$task['quota'] ?> 份名额，剩 <?= max(0, (int)$task['quota'] - (int)$task['claimed_count']) ?> 份，动作完成后提交凭证，验收通过即得赏金</p>
                    <form method="post" action="action.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="claim">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <button class="tk-btn primary" type="submit">立即领取</button>
                    </form>
                <?php else: ?>
                    <p style="color:#9aa1a8;">该任务当前不可领取<?= $label === '已满' ? '（名额已满）' : '（已结束）' ?></p>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $ms = $myClaim['status'];
                $stName = $myStatusText[$ms] ?? $ms; ?>
                <p style="color:#7a8087;margin-bottom:6px;">你已领取该任务，当前状态：<b><?= htmlspecialchars($stName) ?></b></p>
                <a class="tk-btn ghost small" href="my.php">前往「我的承接」管理</a>
            <?php endif; ?>
        </div>

        <?php if ($myClaim): ?>
        <div class="tk-card">
            <div class="tk-sec" style="margin-top:0;">🧾 我的认领（#<?= (int)$myClaim['id'] ?>）</div>

            <?php if (in_array($myClaim['status'], ['accepted', 'rejected'], true) && $task['status'] === 'open'): ?>
                <?php if ($myClaim['status'] === 'rejected'): ?>
                    <div class="tk-tip">你的交付被驳回。驳回意见：<?= htmlspecialchars((string)$myClaim['employer_note']) ?>。请根据意见补充凭证后重新提交。</div>
                <?php endif; ?>
                <div class="tk-form">
                    <form method="post" action="action.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="claim_id" value="<?= (int)$myClaim['id'] ?>">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <label>交付凭证说明（必填）</label>
                        <textarea name="proof_text" rows="3" required placeholder="描述你完成该动作的时间、地点与经过，便于雇主核验"></textarea>
                        <label>凭证截图（选填，jpg/png/gif ≤ 8MB）</label>
                        <input type="file" name="proof_image" accept="image/jpeg,image/png,image/gif">
                        <div style="margin-top:10px;"><button class="tk-btn primary" type="submit">提交凭证，等待验收</button></div>
                    </form>
                </div>
            <?php elseif ($myClaim['status'] === 'submitted'): ?>
                <p class="tk-text" style="margin:0;">凭证已提交，等待雇主验收（<?= $myClaim['review_due_at'] ? '截止 ' . date('Y-m-d H:i', strtotime($myClaim['review_due_at'])) : '' ?>）。若雇主逾期未验收，你可发起争议。</p>
                <?php if (!empty($myClaim['review_due_at']) && strtotime($myClaim['review_due_at']) < time()): ?>
                    <form method="post" action="action.php" class="tk-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="dispute">
                        <input type="hidden" name="claim_id" value="<?= (int)$myClaim['id'] ?>">
                        <label>雇主逾期未验收，发起争议（平台仲裁）</label>
                        <textarea name="reason" rows="2" required placeholder="说明争议事由"></textarea>
                        <div style="margin-top:8px;"><button class="tk-btn danger small" type="submit">提交争议</button></div>
                    </form>
                <?php endif; ?>
            <?php elseif ($myClaim['status'] === 'settling'): ?>
                <p class="tk-text" style="margin:0;">已通过验收，结算处理中（现金任务请与雇主线下完成付款）。</p>
            <?php elseif ($myClaim['status'] === 'completed'): ?>
                <p class="tk-text" style="margin:0;">🎉 认领已完成。<?= $task['reward_type'] === 'cash' ? '请与雇主完成线下付款。' : '人气值任务已完成结算。' ?>完成后可与对方互评。</p>
            <?php elseif ($myClaim['status'] === 'cancelled'): ?>
                <p class="tk-text" style="margin:0;">该认领已取消。</p>
            <?php endif; ?>

            <?php if (in_array($myClaim['status'], ['accepted', 'submitted', 'rejected'], true) && $task['status'] === 'closed'): ?>
                <div class="tk-tip">任务已被雇主关闭。如对关闭有异议（例如你已交付），可发起争议交由平台仲裁。</div>
            <?php endif; ?>

            <?php if (in_array($myClaim['status'], ['accepted', 'submitted', 'rejected'], true)): ?>
                <details class="tk-form" style="margin-top:14px;">
                    <summary style="cursor:pointer;color:#56606b;font-weight:600;">遇到问题？发起争议（违约 / 僵持）</summary>
                    <form method="post" action="action.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="dispute">
                        <input type="hidden" name="claim_id" value="<?= (int)$myClaim['id'] ?>">
                        <label>争议事由</label>
                        <textarea name="reason" rows="2" required placeholder="说明对方违约或僵持情况"></textarea>
                        <div style="margin-top:8px;"><button class="tk-btn danger small" type="submit">提交争议</button></div>
                    </form>
                </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    // 详情页展示我的评价资格与已得评价（双方）
    if ($myClaim && $myClaim['status'] === 'completed'):
        $toUser = $isEmployer ? (int)$myClaim['worker_id'] : (int)$task['employer_id'];
        // 判断我是否已评
        $already = false;
        foreach ($tr->listByClaim((int)$myClaim['id']) as $r) {
            if ((int)$r['from_user_id'] === $uid) { $already = true; break; }
        }
        if (!$already): ?>
        <div class="tk-card">
            <div class="tk-sec" style="margin-top:0;">⭐ 评价对方（每人每单一次，提交后不可修改）</div>
            <div class="tk-form">
                <form method="post" action="action.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="review_add">
                    <input type="hidden" name="claim_id" value="<?= (int)$myClaim['id'] ?>">
                    <input type="hidden" name="to_user_id" value="<?= (int)$toUser ?>">
                    <label>星级</label>
                    <select name="rating" style="width:auto;">
                        <?php for ($s = 5; $s >= 1; $s--): ?>
                            <option value="<?= $s ?>"><?= $s ?> ★<?= str_repeat('★', $s - 1) ?></option>
                        <?php endfor; ?>
                    </select>
                    <label>评价内容</label>
                    <textarea name="content" rows="2" required placeholder="合作体验如何？"></textarea>
                    <div style="margin-top:8px;"><button class="tk-btn primary small" type="submit">提交评价</button></div>
                </form>
            </div>
        </div>
        <?php endif;
    endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
