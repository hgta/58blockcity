<?php
/**
 * Block 子站 — 同步城市数据
 * 从 blockcity.vip 同步排名、开启区块、居民数等基础数据到 cities 表
 * 支持自动抓取（可能需要内部网络）和手动 JSON 粘贴两种方式
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';

$admin_site_config = [
    'site'       => 'block',
    'page_title' => '同步城市数据',
];
require_once '../../shared/admin/admin-header.php';

$msg = '';
$err = '';
$stats = [];

// 处理手动 JSON 粘贴
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'manual') {
        $json = trim($_POST['json_data'] ?? '');
        if (empty($json)) {
            $err = '请粘贴城市数据 JSON';
        } else {
            $data = json_decode($json, true);
            if (!$data || !is_array($data)) {
                $err = 'JSON 格式解析失败';
            } else {
                $updated = 0;
                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE cities SET rank = ?, activated_blocks = ?, resident_count = ?, updated_at = NOW() WHERE name = ?");
                    foreach ($data as $item) {
                        $name = trim($item['name'] ?? $item['city'] ?? '');
                        if (empty($name)) continue;
                        $rank    = intval($item['rank']     ?? $item['ranking'] ?? 0);
                        $actived = intval($item['activated_blocks'] ?? $item['activated'] ?? $item['blocks'] ?? 0);
                        $resident= intval($item['resident_count']  ?? $item['resident'] ?? $item['population'] ?? 0);
                        $upd->execute([$rank, $actived, $resident, $name]);
                        if ($upd->rowCount() > 0) $updated++;
                    }
                    $pdo->commit();
                    $msg = "同步完成：更新了 {$updated} 个城市";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $err = '更新失败：' . $e->getMessage();
                }
            }
        }
    }
}

// 尝试从 blockcity.vip 获取原始数据（直接请求可能因网络/权限失败）
$rawData = null;
if (isset($_GET['fetch']) && $_GET['fetch'] === '1') {
    $urls = [
        'https://www.blockcity.vip/api/area/top200',
        'https://www.blockcity.vip/api/city/rank',
        'https://api.blockcity.vip/v1/cities',
    ];
    foreach ($urls as $url) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'timeout' => 8,
            'header'  => "Accept: application/json\r\nUser-Agent: BlockCity-Sync/1.0\r\n",
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false && !empty($raw)) {
            $rawData = $raw;
            break;
        }
    }
    if ($rawData) {
        $stats['raw_length'] = strlen($rawData);
    } else {
        $err = '自动抓取失败（blockcity.vip 接口不可达或未开放）。请使用下方 "手动粘贴 JSON" 方式。';
    }
}

// 当前 cities 表统计
$cityTotal = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
$cityWithRank = $pdo->query("SELECT COUNT(*) FROM cities WHERE rank > 0")->fetchColumn();
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
        <div class="stat-icon accent"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= $rawData ? strlen($rawData).'B' : '—' ?></div>
        <div class="stat-label">数据源状态</div>
    </div>
</div>

<!-- 方式一：自动抓取 -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-cloud-download-alt"></i> 方式一：从 blockcity.vip 抓取</h3>
    </div>
    <div class="admin-card-body">
        <p style="font-size:13px;color:#888;margin-bottom:12px;">
            尝试通过 HTTP 请求从 blockcity.vip 获取城市排行数据（排名、开启区块、居民数）。<br>
            ⚠️ 原始站为微信小程序，API 可能仅内网可达。抓取失败请用方式二。
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="?fetch=1" class="admin-btn admin-btn-primary"><i class="fas fa-sync"></i> 尝试抓取</a>
            <?php if ($rawData): ?>
                <span style="color:#2e7d32;font-size:13px;align-self:center;">
                    <i class="fas fa-check-circle"></i> 已获取数据（<?= strlen($rawData) ?> 字节）
                </span>
            <?php endif; ?>
        </div>
        <?php if ($rawData): ?>
            <div style="margin-top:12px;">
                <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">原始返回（仅供参考）</label>
                <textarea readonly style="width:100%;height:120px;font-family:monospace;font-size:12px;border:1px solid #ddd;border-radius:6px;padding:8px;"><?= htmlspecialchars(substr($rawData, 0, 2000)) ?></textarea>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 方式二：手动 JSON 粘贴 -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-paste"></i> 方式二：手动粘贴 JSON</h3>
    </div>
    <div class="admin-card-body">
        <p style="font-size:13px;color:#888;margin-bottom:8px;">
            将城市数据以 JSON 格式粘贴到下方。每条记录包含：<code>name</code>（城市名）、<code>rank</code>（排名）、<code>activated_blocks</code>（开启区块数）、<code>resident_count</code>（居民数）。
        </p>
        <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;font-size:13px;color:#3498db;">JSON 格式参考</summary>
            <pre style="background:#f8f9fa;padding:12px;border-radius:6px;font-size:12px;overflow:auto;margin-top:8px;">[
  {"name":"北京","rank":1,"activated_blocks":9999,"resident_count":2154},
  {"name":"上海","rank":2,"activated_blocks":8736,"resident_count":2487},
  {"name":"广州","rank":3,"activated_blocks":8221,"resident_count":1867}
]</pre>
        </details>
        <form method="POST">
            <input type="hidden" name="action" value="manual">
            <textarea name="json_data" style="width:100%;height:260px;font-family:monospace;font-size:13px;border:1px solid #ddd;border-radius:8px;padding:12px;" placeholder='粘贴城市 JSON 数据'></textarea>
            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 批量更新</button>
                <span style="font-size:12px;color:#999;">将按 <code>name</code> 匹配更新对应城市的 rank、activated_blocks、resident_count</span>
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
