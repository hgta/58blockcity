<?php
/**
 * Block 子站 — 同步城市数据
 * 从 blockcity.vip 同步排名、开启区块、居民数等基础数据到 cities 表
 *
 * 重要说明：
 *   blockcity.vip 的区块排名数据由 mini-program 通过 /api/area/* 接口加载，
 *   接口要求「签名 + 登录态 Authorization token」。匿名请求一律返回 500。
 *   因此本页支持两种方式：
 *     1) token 化自动同步：填入你在 blockcity.vip 登录后的 token，PHP 端用签名算法请求接口并入库
 *     2) 手动粘贴 JSON（无需 token）
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

// token 持久化（存数据库 settings 表，避免在 web 目录写文件导致权限/泄露问题）
$settingKey = 'blockcity_api_token';
function bc_load_token($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? '' : (string)$v;
}
function bc_save_token($pdo, $key, $val) {
    $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    )->execute([$key, $val]);
}
function bc_clear_token($pdo, $key) {
    $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$key]);
}
$savedToken = bc_load_token($pdo, $settingKey);

// ---------- 签名算法（与 blockcity.vip 前端一致）----------
function bc_rand_chars($n) {
    $cs = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $cs[random_int(0, 35)];
    return $s;
}
function bc_sign_headers() {
    $ak = (string) (int) (microtime(true) * 1000) . bc_rand_chars(6);
    $t  = (string) (int) (microtime(true) * 1000);
    $a  = bc_rand_chars(8);
    $n  = $ak . $t . bc_rand_chars(8);
    $s  = md5('blockcity_blockcity' . $t . $n . 'Blockcity153#abc#123');
    return [
        'ak' => $ak, 'a' => $a, 't' => $t, 'n' => $n, 's' => $s,
    ];
}

/**
 * 用签名 + token 请求 blockcity.vip 接口，返回解码后的数组或 null
 */
function bc_fetch($path, $token, $payload = []) {
    $hdr = bc_sign_headers();
    $body = http_build_query($payload);
    $ch = curl_init('https://www.blockcity.vip' . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: https://www.blockcity.vip/pages/block/area',
            'User-Agent: Mozilla/5.0',
            'ak: ' . $hdr['ak'],
            'a: '  . $hdr['a'],
            't: '  . $hdr['t'],
            'n: '  . $hdr['n'],
            's: '  . $hdr['s'],
            'u: '  . '',
            'Authorization: ' . $token,
        ],
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $http != 200) return null;
    $arr = json_decode($resp, true);
    return is_array($arr) ? $arr : null;
}

/** 从接口返回中尽量提取城市列表 */
function bc_extract_list($resp) {
    if (!$resp || !isset($resp['code'])) return [];
    // 成功码可能是 0 / 200 / 1
    $ok = in_array($resp['code'], [0, 1, 200, '0', '1', '200'], true);
    if (!$ok) return [];
    $data = $resp['data'] ?? [];
    if (isset($data['list']) && is_array($data['list'])) return $data['list'];
    if (isset($data['data']) && is_array($data['data'])) return $data['data'];
    if (is_array($data)) return $data;
    return [];
}

/** 从单条记录取城市字段（兼容驼峰/下划线）*/
function bc_pick_city($item) {
    $name = $item['name'] ?? $item['cityName'] ?? $item['city_name'] ?? $item['city'] ?? '';
    if (!$name) return null;
    return [
        'name'            => trim($name),
        'rank'            => intval($item['rank'] ?? $item['ranking'] ?? $item['orderNum'] ?? 0),
        'activated_blocks'=> intval($item['activatedBlocks'] ?? $item['activated_blocks'] ?? $item['openBlocks'] ?? $item['blocks'] ?? 0),
        'resident_count'  => intval($item['residentCount'] ?? $item['resident_count'] ?? $item['population'] ?? $item['resident'] ?? 0),
    ];
}

// ---------- 处理请求 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 保存 token
    if (($_POST['action'] ?? '') === 'save_token') {
        $tk = trim($_POST['token'] ?? '');
        if ($tk === '') {
            bc_clear_token($pdo, $settingKey);
            $savedToken = '';
            $msg = '已清除 token';
        } else {
            bc_save_token($pdo, $settingKey, $tk);
            $savedToken = $tk;
            $msg = 'token 已保存（存于数据库，不会外泄到 web 目录）';
        }
    }

    // token 化自动同步
    if (($_POST['action'] ?? '') === 'auto_sync') {
        $token = trim($_POST['token'] ?? $savedToken);
        if (empty($token)) {
            $err = '请先填写并保存 blockcity.vip 的登录 token';
        } else {
            // 依次尝试排名接口与列表接口
            $cities = [];
            foreach (['/api/area/rankList', '/api/area/list'] as $ep) {
                $resp = bc_fetch($ep, $token, ['page' => 1, 'limit' => 300]);
                if ($resp && ($resp['code'] ?? -1) == 0) {
                    foreach (bc_extract_list($resp) as $it) {
                        $c = bc_pick_city($it);
                        if ($c) $cities[$c['name']] = $c;
                    }
                }
            }
            if (empty($cities)) {
                $err = '自动同步未取到数据：token 可能已失效，或接口返回结构变化。请改用「手动粘贴 JSON」。';
            } else {
                $updated = 0;
                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE cities SET rank = ?, activated_blocks = ?, resident_count = ?, updated_at = NOW() WHERE name = ?");
                    foreach ($cities as $c) {
                        $upd->execute([$c['rank'], $c['activated_blocks'], $c['resident_count'], $c['name']]);
                        if ($upd->rowCount() > 0) $updated++;
                    }
                    $pdo->commit();
                    $msg = "自动同步完成：共解析 " . count($cities) . " 个城市，更新了 {$updated} 个";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $err = '更新失败：' . $e->getMessage();
                }
            }
        }
    }

    // 手动 JSON 粘贴
    if (($_POST['action'] ?? '') === 'manual') {
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
                        $rank     = intval($item['rank']    ?? $item['ranking'] ?? 0);
                        $actived  = intval($item['activated_blocks'] ?? $item['activated'] ?? $item['blocks'] ?? 0);
                        $resident = intval($item['resident_count']  ?? $item['resident'] ?? $item['population'] ?? 0);
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

// 当前 cities 表统计
$cityTotal     = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
$cityWithRank  = $pdo->query("SELECT COUNT(*) FROM cities WHERE rank > 0")->fetchColumn();
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
        <div class="stat-icon accent"><i class="fas fa-key"></i></div>
        <div class="stat-value"><?= $savedToken ? '已配置' : '未配置' ?></div>
        <div class="stat-label">Token 状态</div>
    </div>
</div>

<!-- 方式一：token 化自动同步 -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-cloud-download-alt"></i> 方式一：用 blockcity.vip token 自动同步</h3>
    </div>
    <div class="admin-card-body">
        <p style="font-size:13px;color:#888;margin-bottom:12px;">
            blockcity.vip 的排名接口需要登录态 token（匿名请求一律失败）。请按下面步骤拿到 token，粘贴保存后即可一键同步排名 / 开启区块 / 居民数。<br>
            <strong>获取 token 步骤：</strong>用浏览器打开
            <a href="https://www.blockcity.vip/pages/block/area" target="_blank">blockcity.vip</a> 并<strong>登录</strong>
            → 按 F12 打开开发者工具 → <code>Application</code> / <code>本地存储(Local Storage)</code> → 找到键 <code>token</code>（或 <code>access_token</code>）→ 复制其值。
        </p>
        <form method="POST" style="margin-bottom:12px;">
            <input type="hidden" name="action" value="save_token">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="token" value="<?= htmlspecialchars($savedToken) ?>"
                       placeholder="粘贴 blockcity.vip 的 token"
                       style="flex:1;min-width:280px;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                <button type="submit" class="admin-btn"><i class="fas fa-save"></i> 保存 Token</button>
            </div>
        </form>
        <form method="POST">
            <input type="hidden" name="action" value="auto_sync">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-sync"></i> 立即同步（排名/开启区块/居民数）</button>
        </form>
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
