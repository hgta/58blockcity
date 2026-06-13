<?php
/**
 * 主站总控后台 — 数据汇总看板
 */

require_once '../config/database.php';
require_once '../shared/admin/admin-header.php';

// ========== 查询各子站核心汇总数据 ==========

// 1. 平台总用户数
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;

// 2. 今日活跃（以今日注册或今日有交易的用户估算）
$today = date('Y-m-d');
$todayActive = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM (
    SELECT user_id FROM blocks WHERE DATE(updated_at) = ?
    UNION
    SELECT buyer_id FROM transactions WHERE DATE(created_at) = ?
    UNION
    SELECT buyer_id FROM orders WHERE DATE(created_at) = ?
    UNION
    SELECT user_id FROM nft_transactions WHERE DATE(created_at) = ?
) t");
$todayActive->execute([$today, $today, $today, $today]);
$todayActiveUsers = $todayActive->fetchColumn() ?: 0;

// 3. 区块数据
$totalBlocks = $pdo->query("SELECT COUNT(*) FROM blocks WHERE status = 'sold'")->fetchColumn() ?: 0;
$todayBlocks = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE status = 'sold' AND DATE(updated_at) = ?");
$todayBlocks->execute([$today]);
$todayBlockClaims = $todayBlocks->fetchColumn() ?: 0;

// 4. 总交易金额（transactions 表）
$totalTransactionAmount = $pdo->query("SELECT COALESCE(SUM(price), 0) FROM transactions WHERE status = 'completed'")->fetchColumn() ?: 0;

// 5. 商城数据
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'canceled'")->fetchColumn() ?: 0;
$todayOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
$todayOrders->execute([$today]);
$todayOrderCount = $todayOrders->fetchColumn() ?: 0;

// 6. NFT 交易
$totalNftTransactions = $pdo->query("SELECT COUNT(*) FROM nft_transactions WHERE status = 'completed'")->fetchColumn() ?: 0;

// 7. 互访圈数据
$totalCircles = $pdo->query("SELECT COUNT(*) FROM circles")->fetchColumn() ?: 0;
$totalVisits = $pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn() ?: 0;

// 8. 城市数
$totalCities = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn() ?: 0;

// 9. 最新事件（跨站点汇总）
$recentEvents = $pdo->query("
    (SELECT 'block' as type, CONCAT('区块 ', block_number, ' 被认领') as event, created_at
     FROM blocks WHERE status = 'sold' ORDER BY created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'order' as type, CONCAT('订单 #', id, ' 已创建') as event, created_at
     FROM orders ORDER BY created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'nft' as type, CONCAT('NFT 交易 #', id) as event, created_at
     FROM nft_transactions ORDER BY created_at DESC LIMIT 3)
    ORDER BY created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 10. 各子站近7天数据趋势（简化版：每日区块认领数）
$dailyBlocks = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE status = 'sold' AND DATE(updated_at) = ?");
    $stmt->execute([$d]);
    $dailyBlocks[] = ['date' => $d, 'count' => $stmt->fetchColumn() ?: 0];
}

?>

<!-- 统计卡片 -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">平台总用户</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-bolt"></i></div>
        <div class="stat-value"><?= number_format($todayActiveUsers) ?></div>
        <div class="stat-label">今日活跃用户</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-cubes"></i></div>
        <div class="stat-value"><?= number_format($totalBlocks) ?></div>
        <div class="stat-label">已激活区块</div>
        <div class="stat-change up">+<?= $todayBlockClaims ?> 今日</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-value"><?= number_format($totalOrders) ?></div>
        <div class="stat-label">商城订单总数</div>
        <div class="stat-change up">+<?= $todayOrderCount ?> 今日</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-coins"></i></div>
        <div class="stat-value"><?= number_format($totalTransactionAmount) ?></div>
        <div class="stat-label">区块交易总额</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-image"></i></div>
        <div class="stat-value"><?= number_format($totalNftTransactions) ?></div>
        <div class="stat-label">NFT 成交数</div>
    </div>
</div>

<!-- 下方双栏布局 -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <!-- 左侧：各子站活跃度 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-chart-line" style="margin-right:8px;color:var(--admin-accent);"></i>近7天区块激活趋势</span>
        </div>
        <div class="admin-card-body">
            <div style="display:flex;align-items:flex-end;gap:12px;height:200px;padding:20px 0;">
                <?php
                $maxCount = max(array_column($dailyBlocks, 'count')) ?: 1;
                foreach ($dailyBlocks as $day):
                    $height = ($day['count'] / $maxCount) * 100;
                    $label = date('m-d', strtotime($day['date']));
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;">
                    <div style="width:100%;background:linear-gradient(180deg,var(--admin-accent),var(--admin-accent-light));border-radius:8px 8px 0 0;transition:height 0.5s ease;position:relative;" title="<?= $day['count'] ?> 个">
                        <div style="height:<?= $height ?>px;min-height:4px;"></div>
                    </div>
                    <span style="font-size:11px;color:var(--admin-text-muted);"><?= $label ?></span>
                    <span style="font-size:12px;font-weight:600;"><?= $day['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 右侧：最新动态 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-bell" style="margin-right:8px;color:var(--admin-accent);"></i>最新动态</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentEvents)): ?>
                <div class="admin-empty-state" style="padding:40px 20px;">
                    <i class="fas fa-inbox"></i>
                    <p>暂无动态</p>
                </div>
            <?php else: ?>
                <div style="padding:8px 0;">
                    <?php foreach ($recentEvents as $event): ?>
                        <?php
                        $typeColors = [
                            'block' => 'var(--admin-accent)',
                            'order' => 'var(--admin-success)',
                            'nft'   => 'var(--admin-info)',
                        ];
                        $color = $typeColors[$event['type']] ?? 'var(--admin-text-muted)';
                        ?>
                        <div style="display:flex;align-items:center;gap:12px;padding:12px 24px;border-bottom:1px solid var(--admin-border);">
                            <span style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;"></span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($event['event']) ?></div>
                                <div style="font-size:12px;color:var(--admin-text-muted);margin-top:2px;"><?= date('m-d H:i', strtotime($event['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 子站快捷入口 -->
<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-th-large" style="margin-right:8px;color:var(--admin-accent);"></i>子站管理入口</span>
    </div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
            <?php
            $siteCards = [
                ['key'=>'block',  'name'=>'区块交易', 'icon'=>'fa-cubes',        'color'=>'#ff6b00', 'stat'=>number_format($totalBlocks).' 已激活'],
                ['key'=>'bct',    'name'=>'BCT市场',  'icon'=>'fa-coins',        'color'=>'#f59e0b', 'stat'=>'人气值交易'],
                ['key'=>'hufang', 'name'=>'互访圈',   'icon'=>'fa-users',        'color'=>'#3b82f6', 'stat'=>number_format($totalCircles).' 个圈子'],
                ['key'=>'mall',   'name'=>'人气商城', 'icon'=>'fa-shopping-bag', 'color'=>'#22c55e', 'stat'=>number_format($totalOrders).' 个订单'],
                ['key'=>'nft',    'name'=>'NFT头像',  'icon'=>'fa-image',        'color'=>'#8b5cf6', 'stat'=>number_format($totalNftTransactions).' 笔交易'],
            ];
            foreach ($siteCards as $card):
                $siteInfo = getAdminSiteConfig($card['key']);
                if (!$siteInfo) continue;
            ?>
            <a href="<?= $siteInfo['url'] ?>" style="display:flex;align-items:center;gap:16px;padding:20px;background:var(--admin-bg);border:1px solid var(--admin-border);border-radius:var(--admin-radius);text-decoration:none;color:var(--admin-text);transition:var(--admin-transition);" onmouseover="this.style.borderColor='<?= $card['color'] ?>';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.2)'" onmouseout="this.style.borderColor='var(--admin-border)';this.style.transform='';this.style.boxShadow=''">
                <div style="width:48px;height:48px;border-radius:var(--admin-radius-sm);background:<?= $card['color'] ?>20;color:<?= $card['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:20px;">
                    <i class="fas <?= $card['icon'] ?>"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:15px;"><?= $card['name'] ?></div>
                    <div style="font-size:12px;color:var(--admin-text-muted);margin-top:2px;"><?= $card['stat'] ?></div>
                </div>
                <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--admin-text-muted);font-size:12px;"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
