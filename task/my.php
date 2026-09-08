<?php
require_once '../config/database.php';
require_once '../classes/Task.php';
require_once '../classes/TaskClaim.php';
require_once '../includes/auth.php';
require_once 'includes/helpers.php';
checkLogin();

$uid   = (int)$_SESSION['user_id'];
$taskM = new Task($pdo);
$tc    = new TaskClaim($pdo);

$taskM->expireOverdue();

$tab = (string)($_GET['tab'] ?? '');
if (!in_array($tab, ['publish', 'claim'], true)) $tab = 'publish';

// 我的发布：任务 + 其下认领（可内联验收/结算/关闭）
$myTasks = $taskM->listByEmployer($uid);
$claimsByTask = [];
foreach ($myTasks as $t) {
    $claimsByTask[(int)$t['id']] = $tc->claimsByTask((int)$t['id']);
}

// 我的承接：认领列表（可按状态筛）
$claimStatus = (string)($_GET['claim_status'] ?? '');
$myClaims = $tab === 'claim' ? $tc->myClaims($uid, $claimStatus) : [];

$csrf = generateCsrfToken();
$statusName = [
    'accepted' => '已领取待交付', 'submitted' => '待验收', 'rejected' => '被驳回',
    'settling' => '结算中', 'completed' => '已完成', 'cancelled' => '已取消', 'disputed' => '争议中',
];

$site_config['title'] = '我的任务 - 任务广场';
$site_config['description'] = '我发布的任务 / 我承接的任务管理：交付凭证、验收、结算、评价与争议。';
require_once 'includes/header.php';
?>
<style>
.my-wrap { max-width: 1000px; margin: 22px auto; padding: 0 15px; }
.my-tabs { display: flex; gap: 8px; margin-bottom: 18px; }
.my-tab { padding: 9px 22px; border-radius: 10px; text-decoration: none; font-size: 14px; color: #7a7f87; background: #fff; border: 1px solid #e6e8ec; }
.my-tab.active { background: #fff2e8; color: #ff6b00; border-color: #ffb37f; font-weight: 600; }
.my-card { background: #fff; border: 1px solid #f0ece6; border-radius: 14px; padding: 18px 20px; margin-bottom: 14px; }
.my-card-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
.my-card-title { font-size: 16px; font-weight: 700; color: #1f2430; text-decoration: none; }
.my-card-title:hover { color: #ff6b00; }
.my-badge { display: inline-block; font-size: 11px; padding: 2px 9px; border-radius: 10px; background: #f4f6fa; color: #6a7481; margin-left: 6px; }
.my-badge.open { background: #e8f6ef; color: #0a9d62; }
.my-badge.ended { background: #f1f2f4; color: #979ba1; }
.my-reward { color: #ff3b3b; font-weight: 800; font-size: 15px; }
.my-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.my-btn { border: 1px solid #dde1e6; background: #fff; color: #56606b; border-radius: 8px; padding: 6px 12px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; }
.my-btn.primary { background: #ff6b00; border-color: #ff6b00; color: #fff; }
.my-btn.success { background: #0a9d62; border-color: #0a9d62; color: #fff; }
.my-btn.danger { background: #fee9e7; border-color: #f5c6c2; color: #d4380d; }
.my-claims { margin-top: 12px; border-top: 1px dashed #e9ecf0; padding-top: 6px; }
.my-claim { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 8px 2px; border-bottom: 1px solid #f6f7f9; font-size: 13px; }
.my-claim:last-child { border-bottom: none; }
.my-claim .who { font-weight: 600; color: #2b303b; min-width: 90px; }
.st { font-size: 12px; padding: 1px 9px; border-radius: 10px; background: #f1f3f6; color: #58606b; display:inline-block; }
.st.submitted { background: #e8f1ff; color: #2f6fe4; }
.st.rejected { background: #fee9e7; color: #d4380d; }
.st.settling { background: #fff2e8; color: #ff7a18; }
.st.completed { background: #e8f6ef; color: #0a9d62; }
.st.disputed { background: #f3e8ff; color: #8b2fd4; }
.my-claim .st { font-size: 12px; padding: 1px 9px; border-radius: 10px; background: #f1f3f6; color: #58606b; }
.my-claim .st.submitted { background: #e8f1ff; color: #2f6fe4; }
.my-claim .st.rejected { background: #fee9e7; color: #d4380d; }
.my-claim .st.settling { background: #fff2e8; color: #ff7a18; }
.my-claim .st.completed { background: #e8f6ef; color: #0a9d62; }
.my-claim .st.disputed { background: #f3e8ff; color: #8b2fd4; }
.my-claim .note { color: #8b929a; font-size: 12px; flex: 1; min-width: 120px; }
.my-claim .ops { display: flex; gap: 6px; }
.my-claim .proof { color: #5a626d; font-size: 12px; flex-basis: 100%; }
.my-claim .proof img { max-width: 120px; border-radius: 6px; margin-top: 4px; }
.reject-box { background: #fafbfc; border-radius: 8px; padding: 10px 12px; margin-top: 6px; flex-basis: 100%; display: none; }
.reject-box textarea { width: 100%; border: 1px solid #dde1e6; border-radius: 6px; padding: 8px 10px; font-size: 13px; }
.my-empty { text-align: center; color: #9aa1a8; padding: 60px 0; font-size: 14px; }
.my-empty a { color: #ff6b00; }
.my-subnav { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
.my-subnav a { font-size: 13px; color: #7a7f87; text-decoration: none; padding: 5px 12px; border-radius: 16px; }
.my-subnav a.active { background: #fff2e8; color: #ff6b00; }
.my-subnav select { border: 1px solid #e3e6ea; border-radius: 16px; padding: 5px 12px; font-size: 13px; color: #555; }
.alert-inline { font-size: 13px; margin-bottom: 10px; }
</style>

<div class="my-wrap">
    <h1 style="font-size: 23px; margin: 0 0 14px;">👤 我的任务</h1>
    <?php displayFlashMessages(); ?>

    <div class="my-tabs">
        <a class="my-tab <?= $tab === 'publish' ? 'active' : '' ?>" href="my.php?tab=publish">我发布的（<?= count($myTasks) ?>）</a>
        <a class="my-tab <?= $tab === 'claim' ? 'active' : '' ?>" href="my.php?tab=claim">我承接的（<?= $tc->countByWorker($uid) ?>）</a>
    </div>

    <?php if ($tab === 'publish'): ?>
        <?php if (empty($myTasks)): ?>
            <div class="my-empty">你还没有发布过任务。<br><a href="create.php">＋ 去发布一个悬赏任务</a></div>
        <?php else: foreach ($myTasks as $t): ?>
            <?php
            $label = task_status_label($t);
            $claims = $claimsByTask[(int)$t['id']] ?? [];
            $pendingCount = 0;
            foreach ($claims as $c) { if ($c['status'] === 'submitted') $pendingCount++; }
            ?>
            <div class="my-card">
                <div class="my-card-head">
                    <div>
                        <span class="my-badge <?= $label === '进行中' ? 'open' : 'ended' ?>"><?= $label ?></span>
                        <a class="my-card-title" href="view.php?id=<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a>
                    </div>
                    <div class="my-reward"><?= task_reward_text($t) ?>
                        <span style="color:#9aa1a8;font-weight:400;font-size:12px;margin-left:6px;">已领 <?= (int)$t['claimed_count'] ?>/<?= (int)$t['quota'] ?><?= $pendingCount ? ' · 待验收 ' . $pendingCount : '' ?></span>
                    </div>
                </div>
                <?php if ($t['status'] === 'open'): ?>
                    <div class="my-actions" style="margin-top:10px;">
                        <a class="my-btn" href="view.php?id=<?= (int)$t['id'] ?>">查看详情</a>
                        <form method="post" action="action.php" onsubmit="return confirm('确认关闭该任务？未交付认领将被取消。');">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="close">
                            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                            <button class="my-btn danger" type="submit">关闭任务</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="margin-top:8px;color:#8b929a;font-size:13px;">任务已<?= $label ?>，已提交的认领仍可在详情页验收/发起争议。</div>
                <?php endif; ?>

                <?php if ($claims): ?>
                <div class="my-claims">
                    <?php foreach ($claims as $cl):
                        $clSt = $cl['status']; ?>
                        <div class="my-claim">
                            <span class="who"><?= htmlspecialchars($cl['worker_name'] ?? ('#' . $cl['worker_id'])) ?></span>
                            <span class="st <?= $clSt ?>"><?= htmlspecialchars($statusName[$clSt] ?? $clSt) ?></span>
                            <span class="note">
                                <?php if ($cl['proof_text']): ?>📎 <?= htmlspecialchars(mb_substr((string)$cl['proof_text'], 0, 60)) ?><?php endif; ?>
                                <?php if ($clSt === 'rejected' && $cl['employer_note']): ?><b style="color:#d4380d;">已驳回</b><?php endif; ?>
                                <?php if ($clSt === 'settling'): ?>等待结算…<?php endif; ?>
                            </span>
                            <span class="ops">
                                <?php if ($clSt === 'submitted'): ?>
                                    <?php if (!empty($cl['review_due_at']) && strtotime($cl['review_due_at']) < time()): ?>
                                        <span style="color:#d4380d;font-size:12px;">已逾期</span>
                                    <?php endif; ?>
                                    <form method="post" action="action.php">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="review">
                                        <input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>">
                                        <input type="hidden" name="decision" value="pass">
                                        <button class="my-btn success" type="submit">验收通过</button>
                                    </form>
                                    <button class="my-btn danger" type="button" onclick="document.getElementById('rej-<?= (int)$cl['id'] ?>').style.display='block'">驳回</button>
                                <?php elseif ($clSt === 'settling'): ?>
                                    <form method="post" action="action.php">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="settle">
                                        <input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>">
                                        <button class="my-btn primary" type="submit">结算 / 重试</button>
                                    </form>
                                <?php else: ?>
                                    <a class="my-btn" href="view.php?id=<?= (int)$t['id'] ?>">查看</a>
                                <?php endif; ?>
                            </span>
                            <?php if ($clSt === 'submitted' && $cl['proof_image']): ?>
                                <div class="proof"><a href="/<?= ltrim($cl['proof_image'], '/') ?>" target="_blank"><img src="/<?= ltrim($cl['proof_image'], '/') ?>" alt="凭证"></a></div>
                            <?php endif; ?>
                            <div class="reject-box" id="rej-<?= (int)$cl['id'] ?>">
                                <form method="post" action="action.php">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="claim_id" value="<?= (int)$cl['id'] ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <textarea name="employer_note" rows="2" required placeholder="驳回原因（必填，将展示给接单人）"></textarea>
                                    <div style="margin-top:6px;"><button class="my-btn danger" type="submit">确认驳回</button></div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

    <?php else: ?>
        <div class="my-subnav">
            <a class="<?= $claimStatus === '' ? 'active' : '' ?>" href="my.php?tab=claim">全部</a>
            <?php foreach ($statusName as $k => $nm): ?>
                <a class="<?= $claimStatus === $k ? 'active' : '' ?>" href="my.php?tab=claim&claim_status=<?= $k ?>"><?= $nm ?></a>
            <?php endforeach; ?>
            <select style="margin-left:auto;" onchange="if(this.value){location='my.php?tab=claim&claim_status='+this.value}">
                <option value="">按状态筛选…</option>
                <?php foreach ($statusName as $k => $nm): ?>
                    <option value="<?= $k ?>" <?= $claimStatus === $k ? 'selected' : '' ?>><?= $nm ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (empty($myClaims)): ?>
            <div class="my-empty"><?= $claimStatus === '' ? '你还没有承接任务，去 <a href="index.php">任务广场</a> 逛逛吧' : '该状态下暂无记录' ?></div>
        <?php else: foreach ($myClaims as $cl): ?>
            <?php $taskOpen = $cl['task_status'] === 'open'; ?>
            <div class="my-card">
                <div class="my-card-head">
                    <div>
                        <a class="my-card-title" href="view.php?id=<?= (int)$cl['task_id'] ?>"><?= htmlspecialchars($cl['task_title']) ?></a>
                        <span class="my-badge"><?= htmlspecialchars($cl['category_name'] ?? '') ?></span>
                    </div>
                    <div>
                        <span class="my-reward"><?= task_reward_text($cl) ?></span>
                        <span class="st <?= $cl['status'] ?>" style="margin-left:8px;"><?= htmlspecialchars($statusName[$cl['status']] ?? $cl['status']) ?></span>
                    </div>
                </div>
                <div style="font-size:12px;color:#8b929a;margin-top:6px;">
                    👤 雇主：<?= htmlspecialchars($cl['employer_name'] ?? '') ?>
                    <?php if (!empty($cl['task_city'])): ?> · 🏙 <?= htmlspecialchars($cl['task_city']) ?><?php endif; ?>
                    <?php if ($cl['submitted_at']): ?> · 提交于 <?= date('m-d H:i', strtotime($cl['submitted_at'])) ?><?php endif; ?>
                </div>
                <?php if ($cl['status'] === 'rejected' && $cl['employer_note']): ?>
                    <div style="background:#fee9e7;color:#d4380d;font-size:13px;border-radius:8px;padding:8px 12px;margin-top:8px;">被驳回原因：<?= htmlspecialchars($cl['employer_note']) ?></div>
                <?php endif; ?>
                <?php if ($cl['status'] === 'settling'): ?>
                    <div style="background:#fff2e8;color:#96611b;font-size:13px;border-radius:8px;padding:8px 12px;margin-top:8px;">验收已通过，结算处理中（人气值任务由雇主结算划转；现金任务请与雇主线下完成付款）。</div>
                <?php endif; ?>
                <div class="my-actions" style="margin-top:10px;">
                    <a class="my-btn primary" href="view.php?id=<?= (int)$cl['task_id'] ?>">进入任务处理</a>
                    <?php if (in_array($cl['status'], ['accepted', 'rejected'], true) && $taskOpen): ?>
                        <a class="my-btn" href="view.php?id=<?= (int)$cl['task_id'] ?>"><?= $cl['status'] === 'rejected' ? '补充凭证重新提交' : '提交交付凭证' ?></a>
                    <?php endif; ?>
                    <?php if (in_array($cl['status'], ['accepted', 'submitted', 'rejected'], true)): ?>
                        <a class="my-btn danger" href="view.php?id=<?= (int)$cl['task_id'] ?>#dispute">发起争议</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
