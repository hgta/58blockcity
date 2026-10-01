<?php
/**
 * 用户排行榜（change: block-user-ranking）
 * 数据层：classes/BlockRanking.php（七维度口径集中维护）
 * 互切入口：top200city.php（城市排行 ↔ 用户排行）
 */
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../config/block_prices.php';
require_once '../classes/Block.php';          // BlockRanking 依赖（normalizeBlockKey 口径）
require_once '../classes/BlockRanking.php';
require_once '../classes/User.php';           // avatarUrl 静态头像 URL

$sort    = BlockRanking::normalizeSort($_GET['sort'] ?? '');
$ranking = new BlockRanking($pdo);
$defs    = BlockRanking::sortDefs();

$rows      = $ranking->getTopUsers($sort, BlockRanking::TOP_LIMIT);
$boardSize = $ranking->getParticipantCount($sort);

$currentUserId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
$myRank = $currentUserId ? $ranking->getUserRank($currentUserId, $sort) : null;

/** 维度值格式化：计数用整数，价值用金额 */
function ur_fmt($metric, $val)
{
    if ($metric === 'value') {
        return '¥' . number_format((float)$val);
    }
    return number_format((int)$val);
}
?>
<?php require_once 'includes/header.php'; ?>

<style>
.rank-wrap { max-width:1000px; margin:0 auto; padding:20px 15px; }
.rank-header { margin-bottom:20px; display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.rank-title { font-size:24px; font-weight:800; color:#1a1a2e; margin-bottom:4px; }
.rank-title i { color:#ff6b00; }
.rank-sub { font-size:13px; color:#999; }
.rank-switch { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:18px; font-size:13px; font-weight:600; color:#666; background:#f5f5f5; text-decoration:none; transition:.2s; white-space:nowrap; }
.rank-switch:hover { background:#fff0e6; color:#ff6b00; }

/* 标签栏 */
.rank-tabs { display:flex; flex-wrap:wrap; gap:6px; margin:16px 0; }
.rank-tab { padding:8px 16px; border-radius:20px; font-size:13px; font-weight:600; text-decoration:none; color:#666; background:#f5f5f5; transition:.2s; white-space:nowrap; }
.rank-tab:hover { background:#fff0e6; color:#ff6b00; }
.rank-tab.active { background:linear-gradient(135deg,#ff6b00,#ff9500); color:#fff; box-shadow:0 3px 10px rgba(255,107,0,.25); }

/* 我的名次条 */
.my-rank-bar { display:flex; align-items:center; gap:18px; flex-wrap:wrap; padding:14px 18px; margin-bottom:14px; background:linear-gradient(135deg,#fff8f5,#fff); border:1px solid #ffe3d0; border-radius:12px; }
.my-rank-rank { font-size:20px; font-weight:800; color:#ff6b00; }
.my-rank-rank small { font-size:12px; font-weight:400; color:#999; }
.my-rank-meta { font-size:13px; color:#666; line-height:1.6; }
.my-rank-meta b { color:#333; }

/* 表格（横向滚动，避免移动端砍列） */
.rank-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.rank-table { width:100%; border-collapse:collapse; background:#fff; min-width:720px; }
.rank-table th, .rank-table td { padding:12px 14px; text-align:center; font-size:14px; border-bottom:1px solid #f2f2f2; }
.rank-table th { background:#fafbfc; font-size:12px; color:#999; font-weight:600; white-space:nowrap; }
.rank-table th.sort-active { color:#ff6b00; background:#fff8f5; }
.rank-table th small { display:block; font-weight:400; color:#bbb; font-size:11px; }
.rank-table td { color:#555; }
.rank-table tr:hover td { background:#fafbfc; }
.rank-table tr.me td { background:#fff8f5; }

/* 排名数字 */
.rank-num { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:8px; font-size:13px; font-weight:700; color:#999; background:#f5f5f5; }
.rank-num.r1, .rank-num.r2, .rank-num.r3 { color:#fff; width:30px; height:30px; border-radius:10px; font-size:14px; }
.rank-num.r1 { background:linear-gradient(135deg,#f59e0b,#fbbf24); }
.rank-num.r2 { background:linear-gradient(135deg,#94a3b8,#b0bec5); }
.rank-num.r3 { background:linear-gradient(135deg,#d97706,#b45309); }

/* 用户列 */
.rank-user { text-align:left; font-weight:600; }
.rank-user-inner { display:flex; align-items:center; gap:10px; }
.rank-user img { width:34px; height:34px; border-radius:50%; object-fit:cover; background:#eee; flex-shrink:0; }
.rank-user-name { font-size:14px; color:#1a1a2e; line-height:1.3; }
.rank-user-name .me-tag { font-size:11px; color:#ff6b00; font-weight:600; margin-left:4px; }

/* 数据列 */
.rank-val { font-weight:600; color:#333; }
.rank-val.highlight { color:#ff6b00; font-size:15px; }

/* 口径脚注 */
.rank-footnote { margin-top:16px; font-size:12px; color:#999; line-height:1.8; }

/* 空态 */
.empty-state { text-align:center; padding:60px 20px; color:#aaa; background:#fff; border-radius:12px; }
.empty-state i { font-size:40px; display:block; margin-bottom:12px; }

@media(max-width:768px) {
    .rank-table td, .rank-table th { padding:10px 8px; font-size:13px; }
}
</style>

<div class="rank-wrap">
    <div class="rank-header">
        <div>
            <h1 class="rank-title"><i class="fas fa-trophy"></i> 用户排行</h1>
            <div class="rank-sub">TOP <?= BlockRanking::TOP_LIMIT ?> · 当前维度共 <?= number_format($boardSize) ?> 位上榜</div>
        </div>
        <a class="rank-switch" href="top200city.php"><i class="fas fa-city"></i> 城市排行</a>
    </div>

    <!-- 排序标签 -->
    <div class="rank-tabs">
        <?php foreach ($defs as $k => $t): ?>
            <a href="?sort=<?= $k ?>" class="rank-tab <?= $sort===$k?'active':'' ?>">
                <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($myRank): ?>
        <!-- 我的名次条 -->
        <div class="my-rank-bar">
            <div class="my-rank-rank">
                <?php if ($myRank['in_board']): ?>
                    第 <?= number_format($myRank['rank']) ?> <small>名</small>
                <?php else: ?>
                    <small>未上榜</small>
                <?php endif; ?>
            </div>
            <div class="my-rank-meta">
                当前维度：<b><?= ur_fmt(BlockRanking::metricOf($sort), $myRank['value']) ?></b>
                <?php if ($myRank['in_board'] && $myRank['gap_prev'] !== null): ?>
                    · 距上一名还差 <b><?= ur_fmt(BlockRanking::metricOf($sort), $myRank['gap_prev']) ?></b>
                <?php elseif ($myRank['in_board']): ?>
                    · 已是当前维度第一
                <?php elseif ($myRank['gap_board'] !== null): ?>
                    · 距上榜还差 <b><?= ur_fmt(BlockRanking::metricOf($sort), $myRank['gap_board']) ?></b>
                <?php else: ?>
                    · 暂无上榜者
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($currentUserId): ?>
        <!-- 登录了但账号未被统计（如被 HIDDEN_USER_IDS 隐藏）：明确告知，避免"我明明有区块却没上榜"的困惑 -->
        <div class="my-rank-bar">
            <div class="my-rank-meta">当前账号未纳入排行统计。</div>
        </div>
    <?php endif; ?>

    <?php if (empty($rows) && !($myRank && !$myRank['in_board'])): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>当前维度暂无上榜用户</p>
        </div>
    <?php else: ?>
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th style="width:60px;">排名</th>
                        <th style="text-align:left;">用户</th>
                        <th class="<?= $sort==='blocks'?'sort-active':'' ?>">区块数<small>合并按 1 块计</small></th>
                        <th class="<?= $sort==='votes'?'sort-active':'' ?>">投票数<small>合并拆开计</small></th>
                        <th class="<?= $sort==='cities'?'sort-active':'' ?>">城市数</th>
                        <th class="<?= $sort==='value'?'sort-active':'' ?>">总价值</th>
                        <th class="<?= $sort==='merged'?'sort-active':'' ?>">合并组</th>
                        <th class="<?= $sort==='listed'?'sort-active':'' ?>">挂牌中</th>
                        <th class="<?= $sort==='wanted'?'sort-active':'' ?>">求购中</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr<?= $currentUserId && $r['user_id'] === $currentUserId ? ' class="me"' : '' ?>>
                        <td><span class="rank-num <?= $r['rank']<=3?'r'.$r['rank']:'' ?>"><?= $r['rank'] ?></span></td>
                        <td class="rank-user">
                            <span class="rank-user-inner">
                                <img src="<?= htmlspecialchars(User::avatarUrl($r['avatar'])) ?>" alt="" loading="lazy">
                                <span class="rank-user-name">
                                    <?= htmlspecialchars(mb_substr($r['username'], 0, 12)) ?>
                                    <?php if ($currentUserId && $r['user_id'] === $currentUserId): ?><span class="me-tag">我</span><?php endif; ?>
                                </span>
                            </span>
                        </td>
                        <td><span class="rank-val <?= $sort==='blocks'?'highlight':'' ?>"><?= number_format($r['blocks']) ?></span></td>
                        <td><span class="rank-val <?= $sort==='votes'?'highlight':'' ?>"><?= number_format($r['votes']) ?></span></td>
                        <td><span class="rank-val <?= $sort==='cities'?'highlight':'' ?>"><?= number_format($r['cities']) ?></span></td>
                        <td><span class="rank-val <?= $sort==='value'?'highlight':'' ?>">¥<?= number_format($r['value']) ?></span></td>
                        <td><span class="rank-val <?= $sort==='merged'?'highlight':'' ?>"><?= number_format($r['merged']) ?></span></td>
                        <td><span class="rank-val <?= $sort==='listed'?'highlight':'' ?>"><?= number_format($r['listed']) ?></span></td>
                        <td><span class="rank-val <?= $sort==='wanted'?'highlight':'' ?>"><?= number_format($r['wanted']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if ($myRank && !$myRank['in_board']): ?>
                        <?php
                        // 榜外追补"我"的行（复用聚合数据，保证口径一致）
                        $meRow = null;
                        foreach ($ranking->getTopUsers($sort, PHP_INT_MAX) as $u) {
                            if ($u['user_id'] === $currentUserId) { $meRow = $u; break; }
                        }
                        ?>
                        <?php if ($meRow): ?>
                        <tr class="me">
                            <td>—</td>
                            <td class="rank-user">
                                <span class="rank-user-inner">
                                    <img src="<?= htmlspecialchars(User::avatarUrl($meRow['avatar'])) ?>" alt="">
                                    <span class="rank-user-name"><?= htmlspecialchars(mb_substr($meRow['username'], 0, 12)) ?><span class="me-tag">我</span></span>
                                </span>
                            </td>
                            <td><span class="rank-val"><?= number_format($meRow['blocks']) ?></span></td>
                            <td><span class="rank-val"><?= number_format($meRow['votes']) ?></span></td>
                            <td><span class="rank-val"><?= number_format($meRow['cities']) ?></span></td>
                            <td><span class="rank-val">¥<?= number_format($meRow['value']) ?></span></td>
                            <td><span class="rank-val"><?= number_format($meRow['merged']) ?></span></td>
                            <td><span class="rank-val"><?= number_format($meRow['listed']) ?></span></td>
                            <td><span class="rank-val"><?= number_format($meRow['wanted']) ?></span></td>
                        </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="rank-footnote">
            口径说明：仅统计已认领（sold）区块；「区块数」按实际口径（合并组整组计 1 块），「投票数」按拆开口径（合并组子块逐块各计 1 票）；
            「总价值」按区块官方计价汇总；全体持有账号均参与排名（含管理员）<?= count(BlockRanking::$hiddenUserIds) ? '，另有 ' . count(BlockRanking::$hiddenUserIds) . ' 个测试账号未纳入' : '' ?>；同名次为并列（后续名次跳号）。
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
