<?php
/**
 * AI Provider 渠道配置（多渠道 + 掩码 + 测试连接 + 用量统计）
 * change: help-center-ai-assistant (task 5.5 / 3.3)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/SecureCrypto.php';
require_once '../classes/AiProvider.php';

checkAdmin();

// 预置渠道模板（OpenAI 兼容端点）
$presets = [
    'deepseek' => ['name' => 'DeepSeek',      'endpoint' => 'https://api.deepseek.com/v1',   'model' => 'deepseek-chat',        'hint' => 'platform.deepseek.com 获取'],
    'qwen'     => ['name' => '通义千问',       'endpoint' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'model' => 'qwen-plus', 'hint' => '阿里云 DashScope 获取'],
    'kimi'     => ['name' => 'Kimi',          'endpoint' => 'https://api.moonshot.cn/v1',    'model' => 'moonshot-v1-8k',       'hint' => 'platform.moonshot.cn 获取'],
    'zhipu'    => ['name' => '智谱 GLM',       'endpoint' => 'https://open.bigmodel.cn/api/paas/v4', 'model' => 'glm-4-flash',   'hint' => 'bigmodel.cn 获取'],
    'openai'   => ['name' => 'OpenAI',        'endpoint' => 'https://api.openai.com/v1',     'model' => 'gpt-4o-mini',          'hint' => '需海外网络环境'],
    'hermes'   => ['name' => 'Hermes Agent（本机）', 'endpoint' => 'http://127.0.0.1:8642/v1', 'model' => 'hermes-agent',     'hint' => '服务器已部署的 Hermes 智能体，端口以 api-server 实际配置为准'],
    'custom'   => ['name' => '自定义（OpenAI 兼容）', 'endpoint' => '', 'model' => '', 'hint' => '任何 OpenAI 兼容端点'],
];

$actionMsg = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($_POST['action'] === 'save') {
            $name = trim($_POST['name'] ?? '');
            $endpoint = trim($_POST['endpoint'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 0);
            $limit = (int)($_POST['daily_limit'] ?? 0);
            $enabled = isset($_POST['is_enabled']) ? 1 : 0;
            $apiKeyPlain = trim($_POST['api_key'] ?? ''); // 留空 = 不修改

            if ($name === '' || $endpoint === '') {
                $actionMsg = '<div class="admin-alert admin-alert-error">名称与端点不能为空</div>';
            } else {
                if ($id > 0) {
                    if ($apiKeyPlain !== '') {
                        $pdo->prepare("UPDATE ai_providers SET name=?, endpoint=?, model=?, sort_order=?, daily_limit=?, is_enabled=?, api_key_cipher=? WHERE id=?")
                            ->execute([$name, $endpoint, $model, $sort, $limit, $enabled, SecureCrypto::encrypt($apiKeyPlain), $id]);
                    } else {
                        $pdo->prepare("UPDATE ai_providers SET name=?, endpoint=?, model=?, sort_order=?, daily_limit=?, is_enabled=? WHERE id=?")
                            ->execute([$name, $endpoint, $model, $sort, $limit, $enabled, $id]);
                    }
                    $actionMsg = '<div class="admin-alert admin-alert-success">渠道已更新</div>';
                } else {
                    if ($apiKeyPlain === '') {
                        $actionMsg = '<div class="admin-alert admin-alert-error">新增渠道必须填写 API Key</div>';
                    } else {
                        $pdo->prepare("INSERT INTO ai_providers (name, endpoint, api_key_cipher, model, sort_order, daily_limit, is_enabled) VALUES (?,?,?,?,?,?,?)")
                            ->execute([$name, $endpoint, SecureCrypto::encrypt($apiKeyPlain), $model, $sort, $limit, $enabled]);
                        $actionMsg = '<div class="admin-alert admin-alert-success">渠道已创建（Key 已加密存储）</div>';
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete' && $id > 0) {
            $pdo->prepare("DELETE FROM ai_providers WHERE id = ?")->execute([$id]);
            $actionMsg = '<div class="admin-alert admin-alert-success">渠道已删除</div>';
        } elseif ($_POST['action'] === 'set_default' && $id > 0) {
            $pdo->exec("UPDATE ai_providers SET is_default = 0");
            $pdo->prepare("UPDATE ai_providers SET is_default = 1 WHERE id = ?")->execute([$id]);
            $actionMsg = '<div class="admin-alert admin-alert-success">默认渠道已切换</div>';
        } elseif ($_POST['action'] === 'toggle' && $id > 0) {
            $pdo->prepare("UPDATE ai_providers SET is_enabled = 1 - is_enabled WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'test' && $id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $res = AiProvider::testConnection($row);
                $p = new AiProvider($row);
                $testResult = [
                    'name' => $row['name'],
                    'url' => $p->endpointUrl(),
                    'ok' => $res['ok'],
                    'msg' => $res['ok'] ? '连接成功，模型响应正常：' . mb_substr($res['answer'], 0, 60)
                                       : '失败：' . $res['error'],
                ];
            }
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . htmlspecialchars($ex->getMessage()) . '</div>';
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if ($editing) {
        $editing['key_mask'] = SecureCrypto::mask(SecureCrypto::decrypt($editing['api_key_cipher']));
    }
}

$rows = $pdo->query("SELECT * FROM ai_providers ORDER BY is_default DESC, sort_order, id")->fetchAll();

// 今日用量统计（日志表）
$usage = [];
try {
    $usage = $pdo->query(
        "SELECT p.id, p.name,
                COUNT(l.id) AS calls,
                SUM(l.status = 'error') AS errs,
                SUM(l.status = 'unmatched') AS unmatched
         FROM ai_providers p LEFT JOIN ai_chat_logs l ON l.provider_id = p.id AND l.created_at > CURDATE()
         GROUP BY p.id, p.name"
    )->fetchAll(PDO::FETCH_UNIQUE);
} catch (Exception $ex) {}

$admin_site_config = ['site' => 'main', 'page_title' => 'AI 渠道配置'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>
<?php if ($testResult): ?>
<div class="admin-alert <?= $testResult['ok'] ? 'admin-alert-success' : 'admin-alert-error' ?>">
    <b>测试 [<?= htmlspecialchars($testResult['name']) ?>]</b>：<?= htmlspecialchars($testResult['msg']) ?><br>
    <span style="font-family:monospace;font-size:11px;color:#94a3b8;">请求地址：<?= htmlspecialchars($testResult['url']) ?></span>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-robot"></i> AI 渠道配置（路由顺序：默认渠道优先，失败自动切换）</span>
        <a href="ai-providers.php?edit=0" class="admin-btn admin-btn-success admin-btn-sm">+ 新增渠道</a>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead><tr><th>顺序</th><th>名称</th><th>端点</th><th>模型</th><th>今日调用</th><th>限额</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): $u = $usage[$r['id']] ?? null; ?>
                <tr>
                    <td><?= $r['sort_order'] ?><?= $r['is_default'] ? ' <i class="fas fa-star" style="color:#f59e0b" title="默认"></i>' : '' ?></td>
                    <td><b><?= htmlspecialchars($r['name']) ?></b></td>
                    <td style="font-family:monospace;font-size:11px;color:#94a3b8;"><?= htmlspecialchars(mb_substr($r['endpoint'], 0, 34)) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($r['model']) ?></td>
                    <td>
                        <?php if ($u): ?><?= (int)$u['calls'] ?> 次<?= $u['errs'] ? ' <span style="color:#ef4444">(' . (int)$u['errs'] . '失败)</span>' : '' ?><?= $u['unmatched'] ? ' <span style="color:#f59e0b">(' . (int)$u['unmatched'] . '未命中)</span>' : '' ?><?php else: ?>0 次<?php endif; ?>
                    </td>
                    <td><?= $r['daily_limit'] > 0 ? $r['daily_limit'] . '/天' : '不限' ?><?= $r['count_date'] === date('Y-m-d') ? "（已用 {$r['call_count_today']}）" : '' ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="admin-btn admin-btn-sm <?= $r['is_enabled'] ? 'admin-btn-success' : 'admin-btn-secondary' ?>"><?= $r['is_enabled'] ? '启用' : '停用' ?></button>
                        </form>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="ai-providers.php?edit=<?= $r['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">编辑</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="test"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="admin-btn admin-btn-primary admin-btn-sm">测试</button>
                        </form>
                        <?php if (!$r['is_default']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="admin-btn admin-btn-secondary admin-btn-sm">设默认</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('确认删除该渠道？')">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="admin-btn admin-btn-danger admin-btn-sm">删</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                <tr><td colspan="8" style="text-align:center;color:#64748b;padding:24px;">还没有配置渠道——先在下方新增一个（推荐将本机 Hermes Agent 设为默认，直连大模型作为备用）</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-<?= $editing ? 'pen' : 'plus' ?>"></i> <?= $editing ? '编辑渠道 #' . $editing['id'] : '新增渠道' ?></span></div>
    <div class="admin-card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:13px;margin-bottom:4px;">快速预置</label>
                <select id="presetSel" style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                    <option value="">— 选择预置模板自动填充 —</option>
                    <?php foreach ($presets as $k => $p): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($p['name']) ?><?= $k === 'hermes' ? ' ★推荐' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <span id="presetHint" style="font-size:12px;color:#64748b;margin-left:8px;"></span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">名称 *</label>
                    <input name="name" id="fName" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">API 端点 *（OpenAI 兼容）</label>
                    <input name="endpoint" id="fEndpoint" required placeholder="https://api.deepseek.com/v1" value="<?= htmlspecialchars($editing['endpoint'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;font-size:12px;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">默认模型</label>
                    <input name="model" id="fModel" value="<?= htmlspecialchars($editing['model'] ?? '') ?>" placeholder="deepseek-chat" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;font-size:12px;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">API Key<?= $editing ? '（留空=不修改，当前：' . htmlspecialchars($editing['key_mask']) . '）' : ' *' ?></label>
                    <input name="api_key" type="password" <?= $editing ? '' : 'required' ?> placeholder="<?= $editing ? '留空保持不变' : 'sk-…' ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;font-size:12px;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">切换顺序（小优先）</label>
                    <input name="sort_order" type="number" value="<?= $editing['sort_order'] ?? 0 ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">每日限额（0=不限）</label>
                    <input name="daily_limit" type="number" value="<?= $editing['daily_limit'] ?? 0 ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
            </div>
            <div style="margin-top:14px;display:flex;gap:14px;align-items:center;">
                <label style="font-size:13px;"><input type="checkbox" name="is_enabled" <?= !$editing || $editing['is_enabled'] ? 'checked' : '' ?>> 启用</label>
                <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? '保存修改' : '创建渠道' ?></button>
                <span style="font-size:12px;color:#64748b;">Key 以 AES-256-GCM 加密存储，不会明文落库</span>
                <?php if ($editing): ?><a href="ai-providers.php" class="admin-btn admin-btn-secondary">取消</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
  var presets = <?= json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var sel = document.getElementById('presetSel');
  sel.addEventListener('change', function () {
    var p = presets[sel.value];
    if (!p) return;
    if (!document.getElementById('fName').value) document.getElementById('fName').value = p.name;
    if (!document.getElementById('fEndpoint').value) document.getElementById('fEndpoint').value = p.endpoint;
    if (!document.getElementById('fModel').value) document.getElementById('fModel').value = p.model;
    document.getElementById('presetHint').textContent = p.hint;
  });
})();
</script>

<?php require_once '../shared/admin/admin-footer.php'; ?>
