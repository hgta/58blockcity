<?php
/**
 * Block 子站 — 同步城市数据（匿名通道版）
 * 从 blockcity.vip 排行接口拉取最新 城市排名/居民数/开启区块 并更新 cities 表
 *
 * 说明：
 *   同步核心逻辑统一在 classes/CitySyncer.php（匿名签名请求，无需 token/登录态），
 *   本页为 block 子站的管理入口薄壳；互访圈后台（hufang/admin/cities.php）的一键
 *   同步按钮复用同一类。签名配方若变更只需改 CitySyncer 一处。
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/CitySyncer.php';

checkAdmin();

$admin_site_config = [
    'site'       => 'block',
    'page_title' => '同步城市数据',
];
require_once '../../shared/admin/admin-header.php';

$msg = '';
$err = '';

// ---------- 处理请求 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 一键自动同步（匿名签名通道，无需 token）
    if (($_POST['action'] ?? '') === 'auto_sync') {
        try {
            $list = CitySyncer::fetchRankList();
            $stat = CitySyncer::apply($list, $pdo);
            $missedTxt = '';
            if (!empty($stat['missed'])) {
                $missedTxt = '；未匹配：' . implode('、', array_slice($stat['missed'], 0, 10))
                           . (count($stat['missed']) > 10 ? ' 等' : '');
            }
            $unchangedTxt = $stat['unchanged'] > 0 ? "，{$stat['unchanged']} 个无变化" : '';
            $demotedTxt   = !empty($stat['demoted']) ? "，{$stat['demoted']} 个掉榜城市排名已清零" : '';
            $msg = "同步完成：解析 {$stat['fetched']} 个城市，更新 {$stat['updated']} 个{$unchangedTxt}{$demotedTxt}{$missedTxt}";
        } catch (Exception $e) {
            $err = '同步失败：' . $e->getMessage() . '。可改用下方「手动粘贴 JSON」兜底。';
        }
    }

    // 手动 JSON 粘贴（兜底通道）
    if (($_POST['action'] ?? '') === 'manual') {
        try {
            $stat = CitySyncer::applyManualJson($_POST['json_data'] ?? '', $pdo);
            $missedTxt = '';
            if (!empty($stat['missed'])) {
                $missedTxt = '；未匹配：' . implode('、', array_slice($stat['missed'], 0, 10))
                           . (count($stat['missed']) > 10 ? ' 等' : '');
            }
            $msg = "同步完成：解析 {$stat['fetched']} 个城市，更新 {$stat['updated']} 个{$missedTxt}";
        } catch (Exception $e) {
            $err = '更新失败：' . $e->getMessage();
        }
    }
}

// ---------- 当前 cities 表统计（长耗时操作后先探活重连） ----------
$pdo = CitySyncer::ensurePdo($pdo);
$cityTotal    = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
$cityWithRank = $pdo->query("SELECT COUNT(*) FROM cities WHERE rank > 0")->fetchColumn();
$lastUpdate   = $pdo->query("SELECT MAX(updated_at) FROM cities")->fetchColumn();
$lastUpdate   = $lastUpdate ? date('Y-m-d H:i', strtotime((string)$lastUpdate)) : '—';
?>

<?php if ($msg): ?><div style="background:#d4edda;color:#155724;padding:14px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div style="background:#f8d7da;color:#721c24;padding:14px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- 当前状态 -->
<div class="admin-stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="admin-stat-card">
        <div class="stat-icon primary"><i class="fas fa-city"></i></div>
        <div class="stat-value"><?= number_format($cityTotal) ?></div>
        <div class="stat-label">城市总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-sort-numeric-up"></i></div>
        <div class="stat-value"><?= number_format($cityWithRank) ?></div>
        <div class="stat-label">已设置排名</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-history"></i></div>
        <div class="stat-value" style="font-size:18px;"><?= htmlspecialchars($lastUpdate) ?></div>
        <div class="stat-label">数据最近更新</div>
    </div>
</div>

<!-- 方式一：一键自动同步（匿名通道） -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-cloud-download-alt"></i> 一键同步（无需 token）</h3>
    </div>
    <div class="admin-card-body">
        <p style="font-size:13px;color:#888;margin-bottom:12px;">
            内置与 blockcity.vip 官方 H5 一致的匿名签名请求，点击即可拉取最新
            <a href="https://www.blockcity.vip/pages/block/area" target="_blank">城市排行榜</a>
            （约 200 城），自动更新本地 <strong>排名 / 居民数 / 开启区块数</strong>。整个过程一次请求、单事务写入，失败时不会写库。
        </p>
        <form method="POST" onsubmit="return confirm('立即从 blockcity.vip 拉取最新榜单并更新？');">
            <input type="hidden" name="action" value="auto_sync">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-sync"></i> 立即同步（排名/开启区块/居民数）</button>
        </form>
    </div>
</div>

<!-- 方式二：手动粘贴 JSON 兜底 -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-paste"></i> 手动粘贴 JSON（兜底）</h3>
    </div>
    <div class="admin-card-body">
        <p style="font-size:13px;color:#888;margin-bottom:8px;">
            自动通道异常时使用。每条记录需含 <code>name</code>，数值字段兼容
            <code>rank/ranking</code>、<code>resident_count/userNum</code>、<code>activated_blocks/num</code>。
        </p>
        <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;font-size:13px;color:#3498db;">JSON 格式参考</summary>
            <pre style="background:#f8f9fa;padding:12px;border-radius:6px;font-size:12px;overflow:auto;margin-top:8px;">[
  {"name":"北京","rank":1,"activated_blocks":4970,"resident_count":3993},
  {"name":"杭州","rank":2,"activated_blocks":3773,"resident_count":2806},
  {"name":"广州","rank":6,"activated_blocks":1948,"resident_count":1419}
]</pre>
        </details>
        <form method="POST">
            <input type="hidden" name="action" value="manual">
            <textarea name="json_data" style="width:100%;height:260px;font-family:monospace;font-size:13px;border:1px solid #ddd;border-radius:8px;padding:12px;" placeholder='粘贴城市 JSON 数据'></textarea>
            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 批量更新</button>
                <span style="font-size:12px;color:#999;">将按城市名匹配更新 rank、activated_blocks、resident_count（未匹配条目会列出）</span>
            </div>
        </form>
    </div>
</div>

<!-- 最近更新记录 -->
<div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title"><i class="fas fa-history"></i> 最近更新</h3></div>
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead><tr><th>排名</th><th>城市</th><th>开启区块</th><th>居民数</th><th>人气值</th><th>更新时间</th></tr></thead>
            <tbody>
                <?php
                $recent = $pdo->query("SELECT name, rank, activated_blocks, resident_count, popularity, updated_at FROM cities WHERE rank IS NOT NULL AND rank > 0 ORDER BY rank ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($recent as $c):
                ?>
                <tr>
                    <td><strong><?= $c['rank'] ?></strong></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= number_format($c['activated_blocks']) ?></td>
                    <td><?= number_format($c['resident_count']) ?></td>
                    <td><?= number_format($c['popularity']) ?></td>
                    <td class="admin-text-muted"><?= date('Y-m-d H:i', strtotime($c['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
