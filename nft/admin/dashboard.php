<?php
/**
 * NFT 头像交易平台 — 管理后台看板
 * (已迁移至统一后台框架)
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';
require_once '../../classes/User.php';
require_once '../../classes/Transaction.php';
require_once '../../classes/NFTRanking.php';

$nft = new NFT($pdo);
$user = new User($pdo);
$transaction = new Transaction($pdo);
$nftRanking = new NFTRanking($pdo);

$totalNfts = $nft->getTotalNftCount();
$totalUsers = $user->getTotalUsersCount();
$totalTransactions = $transaction->getTotalTransactionCount();
$recentTransactions = $transaction->getRecentTransactions(10);
$recentlyListedNfts = $nft->getRecentlyListedNfts(5);
$popularCities = $nft->getPopularCities(5);

$today = date('Y-m-d');
$todayTransactions = $transaction->getTodayTransactionCount($today);
$todayRevenue = $transaction->getTodayRevenue($today);

$topCitiesByClaims = $nftRanking->getTopCitiesByClaims(3) ?: [];
$topNftsByTransactions = $nftRanking->getTopNftsByTransactions(3) ?: [];

// 统一后台框架配置
$admin_site_config = [
    'site'       => 'nft',
    'page_title' => 'NFT 管理看板',
];
require_once '../../shared/admin/admin-header.php';
?>

<!-- 统计卡片 -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-image"></i></div>
        <div class="stat-value"><?= number_format($totalNfts) ?></div>
        <div class="stat-label">NFT 总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">注册用户</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-exchange-alt"></i></div>
        <div class="stat-value"><?= number_format($totalTransactions) ?></div>
        <div class="stat-label">总交易数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?= number_format($todayTransactions) ?></div>
        <div class="stat-label">今日交易</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-value">¥<?= number_format($todayRevenue, 2) ?></div>
        <div class="stat-label">今日收入</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-map-marker-alt"></i></div>
        <div class="stat-value"><?= count($popularCities) ?></div>
        <div class="stat-label">活跃城市</div>
    </div>
</div>

<!-- 双栏: 排行榜 + 快捷操作 -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
    <!-- 左侧: 排行榜 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-trophy" style="margin-right:8px;color:var(--admin-accent);"></i>今日排行</span>
        </div>
        <div class="admin-card-body">
            <div style="font-size:12px;color:var(--admin-text-muted);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px;">认领最多城市</div>
            <?php foreach ($topCitiesByClaims as $i => $city): ?>
                <div style="display:flex;align-items:center;padding:8px 0;border-bottom:1px solid var(--admin-border);">
                    <span style="width:28px;font-weight:700;color:<?= $i < 3 ? 'var(--admin-accent)' : 'var(--admin-text-muted)' ?>;">#<?= $i + 1 ?></span>
                    <span style="flex:1;font-size:14px;"><?= htmlspecialchars($city['name'] ?? '未知') ?></span>
                    <span style="font-size:13px;color:var(--admin-accent);font-weight:600;"><?= $city['claim_count'] ?? 0 ?> 次</span>
                </div>
            <?php endforeach; ?>

            <div style="font-size:12px;color:var(--admin-text-muted);margin:16px 0 12px;text-transform:uppercase;letter-spacing:0.5px;">成交最多头像</div>
            <?php foreach ($topNftsByTransactions as $i => $nftItem): ?>
                <div style="display:flex;align-items:center;padding:8px 0;border-bottom:1px solid var(--admin-border);gap:10px;">
                    <span style="width:28px;font-weight:700;color:<?= $i < 3 ? 'var(--admin-accent)' : 'var(--admin-text-muted)' ?>;">#<?= $i + 1 ?></span>
                    <img src="../avatar/<?= htmlspecialchars($nftItem['base_image'] ?? 'default.jpg') ?>"
                         style="width:32px;height:32px;border-radius:6px;object-fit:cover;"
                         onerror="this.src='../assets/images/default-nft.jpg'">
                    <span style="flex:1;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($nftItem['code'] ?? '未知') ?></span>
                    <span style="font-size:13px;color:var(--admin-accent);font-weight:600;"><?= $nftItem['transaction_count'] ?? 0 ?> 笔</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 右侧: 最近交易 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-exchange-alt" style="margin-right:8px;color:var(--admin-accent);"></i>最近交易</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentTransactions)): ?>
                <div class="admin-empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>暂无交易</h4>
                </div>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>NFT</th>
                            <th>买家</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $tx): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <img src="../avatar/<?= htmlspecialchars($tx['nft_image'] ?? 'default.jpg') ?>"
                                         style="width:32px;height:32px;border-radius:6px;object-fit:cover;"
                                         onerror="this.src='../assets/images/default-nft.jpg'">
                                    <span style="font-size:13px;"><?= htmlspecialchars($tx['nft_code'] ?? '未知') ?></span>
                                </div>
                            </td>
                            <td>用户<?= $tx['buyer_id'] ?? $tx['seller_id'] ?></td>
                            <td><?= number_format($tx['price'] ?? 0, 2) ?> <?= ($tx['currency'] === 'popularity') ? '人气值' : '¥' ?></td>
                            <td>
                                <?php
                                $statusMap = [
                                    'pending'   => ['待处理', 'warning'],
                                    'completed' => ['已完成', 'success'],
                                    'canceled'  => ['已取消', 'default'],
                                ];
                                $s = $statusMap[$tx['status']] ?? [$tx['status'], 'default'];
                                ?>
                                <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
                            </td>
                            <td style="font-size:12px;color:var(--admin-text-muted);"><?= date('m-d H:i', strtotime($tx['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 最近上架 NFT -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-images" style="margin-right:8px;color:var(--admin-accent);"></i>最近上架 NFT</span>
    </div>
    <div class="admin-card-body">
        <?php if (empty($recentlyListedNfts)): ?>
            <div class="admin-empty-state">
                <i class="fas fa-inbox"></i>
                <h4>暂无上架 NFT</h4>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:20px;">
                <?php foreach ($recentlyListedNfts as $nftItem): ?>
                <div style="background:var(--admin-bg);border:1px solid var(--admin-border);border-radius:var(--admin-radius-sm);padding:16px;text-align:center;transition:var(--admin-transition);"
                     onmouseover="this.style.borderColor='var(--admin-border-light)'" onmouseout="this.style.borderColor='var(--admin-border)'">
                    <img src="../avatar/<?= htmlspecialchars($nftItem['base_image'] ?? 'default.jpg') ?>"
                         style="width:80px;height:80px;border-radius:var(--admin-radius-sm);object-fit:cover;margin-bottom:12px;"
                         onerror="this.src='../assets/images/default-nft.jpg'">
                    <div style="font-weight:600;font-size:14px;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($nftItem['code']) ?></div>
                    <div style="color:var(--admin-accent);font-weight:700;font-size:15px;margin-bottom:4px;">
                        <?= number_format($nftItem['price'] ?? 0, 2) ?> <?= ($nftItem['currency'] === 'popularity') ? '人气值' : '¥' ?>
                    </div>
                    <div style="font-size:12px;color:var(--admin-text-muted);"><?= htmlspecialchars($nftItem['city_name'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../../shared/admin/admin-footer.php';

// Helper function
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('MONTH_IN_SECONDS', 2592000);
define('YEAR_IN_SECONDS', 31536000);

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = ['y' => '年', 'm' => '月', 'w' => '周', 'd' => '天', 'h' => '小时', 'i' => '分钟', 's' => '秒'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . $v; } else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . '前' : '刚刚';
}
