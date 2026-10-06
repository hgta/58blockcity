<?php
/**
 * Block 子站 — 管理后台看板
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
require_once '../../classes/City.php';

// 使用统一后台框架
$admin_site_config = [
    'site'       => 'block',
    'page_title' => '区块管理看板',
];
require_once '../../shared/admin/admin-header.php';

$block = new Block($pdo);
$city = new City($pdo);

// 统计数据
$totalBlocksSold = $pdo->query("SELECT COUNT(*) FROM blocks WHERE status = 'sold'")->fetchColumn() ?: 0;
$totalBlocksAvail = $pdo->query("SELECT COUNT(*) FROM blocks WHERE status = 'available'")->fetchColumn() ?: 0;
$todayClaims = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE status = 'sold' AND DATE(updated_at) = ?");
$todayClaims->execute([date('Y-m-d')]);
$todayClaimsCount = $todayClaims->fetchColumn() ?: 0;

$totalCities = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn() ?: 0;
$totalMerged = $pdo->query("SELECT COUNT(*) FROM merged_blocks")->fetchColumn() ?: 0;

// 城市认领排行 Top 10
// 口径说明：
//   sold_blocks      = 本站 blocks 表中 status='sold' 的区块（本地已认领）
//   activated_blocks = 官方榜单同步的「已开启区块数」（classes/Block.php 约定：
//                      该字段由 sync-cities.php / 手动维护，不被本站认领操作覆盖）
//   渗透率            = 本地已认领 / 官方已开启区块（本地占官方开启块的比例）
// 注意：不要用 sold_blocks / COUNT(blocks) 当「激活率」——分母只是本站留下过记录
//      的块数，并非城市容量，且取消认领只减分子不改分母，比率无业务含义。
$cityStats = $pdo->query("
    SELECT c.id, c.name, c.pinyin, c.activated_blocks,
           COUNT(b.id) as total_blocks,
           SUM(CASE WHEN b.status = 'sold' THEN 1 ELSE 0 END) as sold_blocks
    FROM cities c
    LEFT JOIN blocks b ON c.id = b.city_id
    GROUP BY c.id
    HAVING total_blocks > 0
    ORDER BY sold_blocks DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 最新交易（通过 blocks 表间接关联 cities）
$recentTx = $pdo->query("
    SELECT t.*, u.username, c.name as city_name
    FROM transactions t
    JOIN users u ON t.buyer_id = u.id
    JOIN blocks b ON t.block_id = b.id
    JOIN cities c ON b.city_id = c.id
    ORDER BY t.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- 统计卡片 -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-cubes"></i></div>
        <div class="stat-value"><?= number_format($totalBlocksSold) ?></div>
        <div class="stat-label">已售区块</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= number_format($todayClaimsCount) ?></div>
        <div class="stat-label">今日认领</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-city"></i></div>
        <div class="stat-value"><?= number_format($totalCities) ?></div>
        <div class="stat-label">开通城市</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-object-group"></i></div>
        <div class="stat-value"><?= number_format($totalMerged) ?></div>
        <div class="stat-label">合并区块</div>
    </div>
</div>

<!-- 双栏布局 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- 城市认领排行 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-trophy" style="margin-right:8px;color:var(--admin-accent);"></i>城市认领排行 Top 10</span>
            <span style="font-size:12px;color:var(--admin-text-muted);">本地已认领 / 官方已开启区块</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>排名</th>
                        <th>城市</th>
                        <th>本地已认领</th>
                        <th>官方开启区块</th>
                        <th>渗透率</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cityStats as $i => $cs):
                        $sold     = (int)$cs['sold_blocks'];
                        $official = (int)($cs['activated_blocks'] ?? 0);
                        // 官方开启区块为 0（未同步）时不计算比率，避免显示误导性的 0%
                        $rate     = $official > 0 ? round($sold / $official * 100, 1) : null;
                    ?>
                    <tr>
                        <td><span style="font-weight:700;color:<?= $i < 3 ? 'var(--admin-accent)' : 'var(--admin-text-muted)' ?>;">#<?= $i + 1 ?></span></td>
                        <td><?= htmlspecialchars($cs['name']) ?></td>
                        <td><?= number_format($sold) ?></td>
                        <td><?= $official > 0 ? number_format($official) : '<span class="admin-text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($rate === null): ?>
                                <span style="font-size:12px;color:var(--admin-text-muted);">未同步</span>
                            <?php else: ?>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:var(--admin-bg);border-radius:3px;overflow:hidden;">
                                    <div style="width:<?= $rate ?>%;min-width:2px;height:100%;background:linear-gradient(90deg,var(--admin-accent),var(--admin-accent-light));border-radius:3px;"></div>
                                </div>
                                <span style="font-size:12px;min-width:40px;text-align:right;"><?= $rate ?>%</span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 最新交易 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-exchange-alt" style="margin-right:8px;color:var(--admin-accent);"></i>最新交易记录</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentTx)): ?>
                <div class="admin-empty-state" style="padding:40px 20px;">
                    <i class="fas fa-inbox"></i>
                    <p>暂无交易记录</p>
                </div>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>买家</th>
                            <th>城市</th>
                            <th>金额</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTx as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['username'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($tx['city_name'] ?? '-') ?></td>
                            <td>¥<?= number_format($tx['price'] ?? 0) ?></td>
                            <td>
                                <?php if ($tx['status'] === 'completed'): ?>
                                    <span class="admin-badge success">已完成</span>
                                <?php elseif ($tx['status'] === 'pending'): ?>
                                    <span class="admin-badge warning">待处理</span>
                                <?php else: ?>
                                    <span class="admin-badge default"><?= htmlspecialchars($tx['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
