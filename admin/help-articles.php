<?php
/**
 * 帮助文章管理（richtext/steps 双模式 + 置顶/发布/下架 + AI 草稿）
 * change: help-center-ai-assistant (task 5.2 / 5.7)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/SecureCrypto.php';
require_once '../classes/AiProvider.php';
require_once '../classes/EmbeddingProvider.php';
require_once '../classes/HelpChunker.php';
require_once '../classes/HelpChunkSync.php';

checkAdmin();

// 语义检索块同步（help-semantic-rag 任务 3.2）：
// 嵌入渠道未配置或调用失败只记日志，绝不阻断后台保存/发布
function hsr_sync_article($articleId)
{
    global $pdo;
    try {
        if ($emb = EmbeddingProvider::pick($pdo)) {
            HelpChunkSync::syncArticle($pdo, $emb, $articleId);
        }
    } catch (Exception $ex) {
        error_log('[help-semantic-rag] 文章块同步失败 #' . $articleId . ': ' . $ex->getMessage());
    }
}

$actionMsg = '';

// ---------- 操作处理 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        switch ($_POST['action']) {
            case 'save':
                $title = trim($_POST['title'] ?? '');
                $slug  = trim($_POST['slug'] ?? '');
                $catId = (int)($_POST['category_id'] ?? 0);
                $summary = trim($_POST['summary'] ?? '');
                $ctype = $_POST['content_type'] === 'steps' ? 'steps' : 'richtext';
                $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived']) ? $_POST['status'] : 'draft';
                $pinned = isset($_POST['is_pinned']) ? 1 : 0;
                $cover  = trim($_POST['cover_image'] ?? '');

                $steps = [];
                if ($ctype === 'steps' && is_array($_POST['steps'] ?? null)) {
                    foreach ($_POST['steps'] as $s) {
                        $t = trim((string)($s['title'] ?? ''));
                        $x = trim((string)($s['text'] ?? ''));
                        $img = trim((string)($s['image'] ?? ''));
                        if ($t === '' && $x === '' && $img === '') continue;
                        $steps[] = ['title' => $t, 'text' => $x, 'image' => $img];
                    }
                }

                if ($title === '' || $slug === '' || $catId <= 0) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">标题、slug、分类不能为空</div>';
                    break;
                }
                if ($ctype === 'steps' && !$steps) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">步骤模式至少需要一个步骤</div>';
                    break;
                }

                $fields = [
                    ':title' => $title, ':slug' => $slug, ':cat' => $catId, ':summary' => $summary,
                    ':ctype' => $ctype,
                    ':rt'    => $ctype === 'richtext' ? trim((string)$_POST['content_richtext']) : null,
                    ':steps' => $ctype === 'steps' ? json_encode($steps, JSON_UNESCAPED_UNICODE) : null,
                    ':cover' => $cover, ':status' => $status, ':pinned' => $pinned,
                ];
                if ($id > 0) {
                    $pdo->prepare("UPDATE help_articles SET title=:title, slug=:slug, category_id=:cat, summary=:summary,
                                   content_type=:ctype, content_richtext=:rt, content_steps=:steps, cover_image=:cover,
                                   status=:status, is_pinned=:pinned WHERE id=:id")
                        ->execute($fields + [':id' => $id]);
                    // 人工编辑后清除 AI 草稿待审核标记
                    $pdo->prepare("UPDATE help_articles SET is_ai_generated = 0 WHERE id = ? AND status = 'published'")->execute([$id]);
                    $actionMsg = '<div class="admin-alert admin-alert-success">文章已保存</div>';
                } else {
                    $pdo->prepare("INSERT INTO help_articles (title, slug, category_id, summary, content_type, content_richtext,
                                   content_steps, cover_image, status, is_pinned, created_by)
                                   VALUES (:title, :slug, :cat, :summary, :ctype, :rt, :steps, :cover, :status, :pinned, :uid)")
                        ->execute($fields + [':uid' => $_SESSION['user_id'] ?? null]);
                    $id = (int)$pdo->lastInsertId();
                    $actionMsg = '<div class="admin-alert admin-alert-success">文章已创建</div>';
                }
                hsr_sync_article($id); // 保存/发布后同步知识块（published 才生成块）
                break;

            case 'toggle_pin':
                $pdo->prepare("UPDATE help_articles SET is_pinned = 1 - is_pinned WHERE id = ?")->execute([$id]);
                $actionMsg = '<div class="admin-alert admin-alert-success">置顶状态已切换</div>';
                break;

            case 'set_status':
                $st = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived']) ? $_POST['status'] : 'draft';
                $pdo->prepare("UPDATE help_articles SET status = ? WHERE id = ?")->execute([$st, $id]);
                hsr_sync_article($id); // 发布/下架都会改变块的存留
                $actionMsg = '<div class="admin-alert admin-alert-success">状态已更新</div>';
                break;

            case 'delete':
                $pdo->prepare("DELETE FROM help_articles WHERE id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM help_article_feedback WHERE article_id = ?")->execute([$id]);
                try { HelpChunkSync::dropSource($pdo, 'article', $id); } catch (Exception $ex) {}
                $actionMsg = '<div class="admin-alert admin-alert-success">文章已删除</div>';
                break;

            case 'ai_draft': // task 5.7：AI 生成草稿（落库 draft + 标记，强制人工审核）
                $topic = trim($_POST['topic'] ?? '');
                $catId = (int)($_POST['category_id'] ?? 0);
                if ($topic === '' || $catId <= 0) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">请选择分类并填写主题</div>';
                    break;
                }
                $catStmt = $pdo->prepare("SELECT name FROM help_categories WHERE id = ?");
                $catStmt->execute([$catId]);
                $catName = $catStmt->fetchColumn();
                if (!$catName) { $actionMsg = '<div class="admin-alert admin-alert-error">分类不存在</div>'; break; }

                $prompt = "你是\"58区块城市\"平台（元宇宙虚拟地块+同城生活社区）的帮助内容编辑。"
                    . "请为「{$catName}」板块写一篇面向新手的帮助教程，主题：{$topic}。\n"
                    . "要求：1) 输出 JSON，格式 {\"title\":\"...\",\"summary\":\"...\",\"steps\":[{\"title\":\"...\",\"text\":\"...\"}]}；"
                    . "2) 5-8 个步骤，每步文字 50-120 字，面向不熟悉互联网产品的用户；"
                    . "3) 步骤中涉及价格、规则等不确定事实时用【待核实】标注；"
                    . "4) 只输出 JSON，不要其他文字。";
                $res = null;
                foreach (AiProvider::routeList($pdo) as $p) {
                    $r = $p->chatOnce([
                        ['role' => 'system', 'content' => '你是专业的帮助文档编辑，只输出合法 JSON。'],
                        ['role' => 'user', 'content' => $prompt],
                    ], 60.0);
                    if ($r['ok']) { $res = $r; break; }
                }
                if (!$res) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">AI 生成失败（请先在「AI渠道配置」添加可用渠道）</div>';
                    break;
                }
                $json = trim($res['answer']);
                $json = preg_replace('/^```(json)?|```$/m', '', $json);
                $obj = json_decode($json, true);
                if (!is_array($obj) || empty($obj['title'])) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">AI 返回内容无法解析，请换个说法重试</div>';
                    break;
                }
                $slug = 'ai-' . date('md') . '-' . substr(md5(uniqid('', true)), 0, 6);
                $steps = [];
                foreach ((array)($obj['steps'] ?? []) as $s) {
                    if (!empty($s['title']) || !empty($s['text'])) {
                        $steps[] = ['title' => (string)($s['title'] ?? ''), 'text' => (string)($s['text'] ?? ''), 'image' => ''];
                    }
                }
                $pdo->prepare("INSERT INTO help_articles (category_id, title, slug, summary, content_type, content_steps, status, is_ai_generated, created_by)
                               VALUES (:cat, :title, :slug, :summary, 'steps', :steps, 'draft', 1, :uid)")
                    ->execute([
                        ':cat' => $catId, ':title' => mb_substr((string)$obj['title'], 0, 100), ':slug' => $slug,
                        ':summary' => mb_substr((string)($obj['summary'] ?? ''), 0, 200),
                        ':steps' => json_encode($steps, JSON_UNESCAPED_UNICODE),
                        ':uid' => $_SESSION['user_id'] ?? null,
                    ]);
                $actionMsg = '<div class="admin-alert admin-alert-success">AI 草稿已生成（草稿状态，需人工审核编辑后发布）</div>';
                break;
        }
    } catch (Exception $ex) {
        // slug 唯一键冲突：给出可操作的提示（原文是裸 MySQL 报错，看不懂）
        $isDup = false;
        if ($ex instanceof PDOException) {
            $info = $ex->errorInfo ?? null;
            $isDup = (isset($info[1]) && (int)$info[1] === 1062) || stripos($ex->getMessage(), 'Duplicate entry') !== false;
        }
        if ($isDup) {
            $actionMsg = '<div class="admin-alert admin-alert-error">Slug 已被其他文章占用，请在 Slug 后加个后缀（如 -2）再保存</div>';
        } else {
            $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . htmlspecialchars($ex->getMessage()) . '</div>';
        }
    }
}

// ---------- 编辑数据 ----------
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM help_articles WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if ($editing && $editing['content_type'] === 'steps') {
        $editing['steps_arr'] = json_decode($editing['content_steps'], true) ?: [];
    }
}

$cats = $pdo->query("SELECT id, name FROM help_categories ORDER BY sort_order, id")->fetchAll();

// ---------- 列表 ----------
$fltCat = (int)($_GET['cat'] ?? 0);
$fltStatus = (string)($_GET['status'] ?? '');
$fltQ = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 15;

$where = '1=1'; $params = [];
if ($fltCat > 0) { $where .= " AND a.category_id = :cat"; $params[':cat'] = $fltCat; }
if ($fltStatus !== '') { $where .= " AND a.status = :st"; $params[':st'] = $fltStatus; }
if ($fltQ !== '') { $where .= " AND a.title LIKE :q"; $params[':q'] = '%' . $fltQ . '%'; }

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM help_articles a WHERE $where");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);

$listStmt = $pdo->prepare(
    "SELECT a.*, c.name AS cat_name FROM help_articles a JOIN help_categories c ON c.id = a.category_id
     WHERE $where ORDER BY a.updated_at DESC LIMIT " . (($page - 1) * $per) . ", $per"
);
$listStmt->execute($params);
$arts = $listStmt->fetchAll();

$statusMap = ['draft' => '草稿', 'published' => '已发布', 'archived' => '已下架'];

// wangEditor 自托管在 admin/assets/wangeditor/（不依赖 CDN，国内访问稳定）
$admin_site_config = [
    'site' => 'main',
    'page_title' => '帮助文章管理',
    'extra_head' => '<link rel="stylesheet" href="assets/wangeditor/style.css">',
];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-file-lines"></i> 帮助文章 (共 <?= $total ?> 篇)</span>
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="q" placeholder="搜索标题..." value="<?= htmlspecialchars($fltQ) ?>"
                   style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;width:180px;">
            <select name="cat" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部分类</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $fltCat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部状态</option>
                <?php foreach ($statusMap as $k => $v): ?>
                <option value="<?= $k ?>" <?= $fltStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">筛选</button>
            <a href="help-articles.php?edit=0" class="admin-btn admin-btn-success admin-btn-sm">+ 新建文章</a>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead>
                <tr><th>ID</th><th>标题</th><th>分类</th><th>类型</th><th>状态</th><th>浏览</th><th>更新时间</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($arts as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td>
                        <?= $a['is_pinned'] ? '<i class="fas fa-thumbtack" style="color:#f59e0b" title="置顶"></i> ' : '' ?>
                        <?= $a['is_ai_generated'] ? '<i class="fas fa-wand-magic-sparkles" style="color:#818cf8" title="AI草稿·待人工审核"></i> ' : '' ?>
                        <?= htmlspecialchars(mb_substr($a['title'], 0, 30)) ?>
                    </td>
                    <td><?= htmlspecialchars($a['cat_name']) ?></td>
                    <td><?= $a['content_type'] === 'steps' ? '步骤图文' : '富文本' ?></td>
                    <td><span style="color:<?= $a['status'] === 'published' ? '#22c55e' : ($a['status'] === 'draft' ? '#f59e0b' : '#64748b') ?>"><?= $statusMap[$a['status']] ?></span></td>
                    <td><?= $a['view_count'] ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars(date('m-d H:i', strtotime($a['updated_at']))) ?></td>
                    <td style="white-space:nowrap;">
                        <a href="help-articles.php?edit=<?= $a['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">编辑</a>
                        <?php if ($a['status'] === 'published'): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="archived"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="admin-btn admin-btn-secondary admin-btn-sm">下架</button></form>
                        <?php else: ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="published"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="admin-btn admin-btn-success admin-btn-sm">发布</button></form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_pin"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="admin-btn admin-btn-secondary admin-btn-sm">置顶</button></form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('确认删除？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="admin-btn admin-btn-danger admin-btn-sm">删</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$arts): ?>
                <tr><td colspan="8" style="text-align:center;color:#64748b;padding:24px;">暂无文章</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($pages > 1): ?>
        <div style="padding:12px;text-align:center;">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>&cat=<?= $fltCat ?>&status=<?= urlencode($fltStatus) ?>&q=<?= urlencode($fltQ) ?>" class="admin-btn admin-btn-sm <?= $i == $page ? 'admin-btn-primary' : 'admin-btn-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-robot"></i> AI 生成教程草稿</span>
    </div>
    <div class="admin-card-body">
        <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="action" value="ai_draft">
            <select name="category_id" required style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">选择分类…</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="topic" required placeholder="主题，如：如何给店铺上架新商品" style="flex:1;min-width:240px;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-wand-magic-sparkles"></i> 生成草稿</button>
        </form>
        <div style="font-size:12px;color:#64748b;margin-top:8px;">生成的草稿为「草稿 + AI标记」状态，必须人工编辑审核并发布后才会在前台显示。涉及价格/规则的【待核实】内容务必替换为准确信息。</div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-<?= $editing ? 'pen' : 'plus' ?>"></i> <?= $editing ? '编辑文章 #' . $editing['id'] : '新建文章' ?></span>
    </div>
    <div class="admin-card-body">
        <form method="POST" id="artForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;">
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">标题 *</label>
                    <input name="title" id="titleInput" required value="<?= htmlspecialchars($editing['title'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">Slug *（URL标识）
                    <span style="font-weight:400;color:#64748b;">标题填好后自动生成，可手动改</span></label>
                    <div style="display:flex;gap:6px;">
                        <input name="slug" id="slugInput" required pattern="[a-z0-9-]+" value="<?= htmlspecialchars($editing['slug'] ?? '') ?>" style="flex:1;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;">
                        <button type="button" id="slugAiBtn" title="用 AI 把标题翻译成英文 slug（如 mall-shopping-guide）" class="admin-btn admin-btn-secondary admin-btn-sm" style="white-space:nowrap;">AI 英文</button>
                    </div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:14px;">
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">分类 *</label>
                    <select name="category_id" required style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                        <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($editing['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">内容类型</label>
                    <select name="content_type" id="ctypeSel" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                        <option value="richtext" <?= ($editing['content_type'] ?? '') === 'richtext' ? 'selected' : '' ?>>富文本</option>
                        <option value="steps" <?= ($editing['content_type'] ?? '') === 'steps' ? 'selected' : '' ?>>步骤化图文</option>
                    </select></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">状态</label>
                    <select name="status" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                        <?php foreach ($statusMap as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($editing['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div style="display:flex;align-items:flex-end;"><label style="font-size:13px;"><input type="checkbox" name="is_pinned" <?= !empty($editing['is_pinned']) ? 'checked' : '' ?>> 置顶显示</label></div>
            </div>
            <div style="margin-top:14px;"><label style="display:block;font-size:13px;margin-bottom:4px;">摘要（搜索与列表展示）
                <span style="font-weight:400;color:#64748b;">写完正文会自动取开头生成，也可点 AI 重写</span></label>
                <div style="display:flex;gap:6px;">
                    <input name="summary" id="summaryInput" value="<?= htmlspecialchars($editing['summary'] ?? '') ?>" style="flex:1;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                    <button type="button" id="sumAiBtn" title="用 AI 根据正文重写一段 80~120 字的摘要" class="admin-btn admin-btn-secondary admin-btn-sm" style="white-space:nowrap;">AI 摘要</button>
                </div></div>

            <!-- 富文本模式：所见即所得编辑器（wangEditor 自托管）+ 可切回 HTML 源码 -->
            <div id="rtBox" style="margin-top:14px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                    <label style="font-size:13px;">正文</label>
                    <button type="button" id="rtToggleSrc" class="admin-btn admin-btn-secondary admin-btn-sm">HTML 源码</button>
                    <span style="font-size:12px;color:#64748b;">图片可直接粘贴/拖入上传（≤3MB，走 help-upload.php）；需要手改标签时点「HTML 源码」</span>
                </div>
                <!-- 可视化编辑区（内容提交前同步进下方 textarea） -->
                <div id="rtEditorWrap">
                    <div id="rtToolbar" style="background:#fff;border-radius:6px 6px 0 0;border-bottom:1px solid #e5e7eb;"></div>
                    <div id="rtEditor" style="background:#fff;border-radius:0 0 6px 6px;min-height:320px;"></div>
                </div>
                <!-- 源码视图 & 实际提交字段 -->
                <textarea name="content_richtext" id="rtArea" rows="14" placeholder="支持 HTML：h2/p/ul/img/table 等标签" style="display:none;width:100%;padding:10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;font-size:13px;"><?= htmlspecialchars($editing['content_richtext'] ?? '') ?></textarea>
            </div>

            <!-- 步骤模式 -->
            <div id="stepsBox" style="margin-top:14px;display:none;">
                <label style="display:block;font-size:13px;margin-bottom:4px;">教程步骤（每步：标题 + 说明 + 截图）</label>
                <div id="stepsList"></div>
                <button type="button" id="addStep" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-top:8px;">+ 添加步骤</button>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? '保存修改' : '创建文章' ?></button>
                <?php if ($editing): ?>
                <a href="https://help.58.tl/article/<?= htmlspecialchars($editing['slug']) ?>" target="_blank" class="admin-btn admin-btn-secondary">前台预览</a>
                <a href="help-articles.php" class="admin-btn admin-btn-secondary">返回列表</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- wangEditor（自托管）必须先于下方内联脚本加载 -->
<script src="assets/wangeditor/index.js"></script>
<!-- pinyin-pro（自托管）：标题 → 拼音 slug -->
<script src="assets/pinyin-pro/index.js"></script>
<script>
(function () {
  var ctype = document.getElementById('ctypeSel');
  var rtBox = document.getElementById('rtBox');
  var stepsBox = document.getElementById('stepsBox');
  var stepsList = document.getElementById('stepsList');

  function syncType() {
    rtBox.style.display = ctype.value === 'richtext' ? '' : 'none';
    stepsBox.style.display = ctype.value === 'steps' ? '' : 'none';
  }
  ctype.addEventListener('change', syncType);

  function uploadImage(file, cb) {
    var fd = new FormData();
    fd.append('image', file);
    fetch('help-upload.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) { cb(j.ok, j.url || '', j.msg || ''); })
      .catch(function () { cb(false, '', '网络异常'); });
  }

  // 步骤行
  function addStep(data) {
    data = data || {};
    var idx = stepsList.children.length;
    var div = document.createElement('div');
    div.style.cssText = 'border:1px solid #334155;border-radius:8px;padding:12px;margin-bottom:10px;position:relative;';
    div.innerHTML =
      '<button type="button" class="admin-btn admin-btn-danger admin-btn-sm" style="position:absolute;top:8px;right:8px;">删除</button>' +
      '<input name="steps[' + idx + '][title]" placeholder="步骤标题，如：打开商城" value="' + (data.title || '').replace(/"/g, '&quot;') + '" style="width:60%;padding:6px 10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">' +
      '<textarea name="steps[' + idx + '][text]" placeholder="步骤说明文字" rows="2" style="width:100%;margin-top:8px;padding:6px 10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">' + (data.text || '') + '</textarea>' +
      '<div style="display:flex;align-items:center;gap:8px;margin-top:8px;">' +
      '<button type="button" class="admin-btn admin-btn-secondary admin-btn-sm up-btn">上传截图</button>' +
      '<input type="file" accept="image/*" hidden>' +
      '<input name="steps[' + idx + '][image]" placeholder="或直接填图片URL" value="' + (data.image || '') + '" class="img-url" style="flex:1;padding:6px 10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:12px;font-family:monospace;">' +
      '<img class="img-prev" style="height:40px;border-radius:4px;display:none;">' +
      '</div>';
    var fileInput = div.querySelector('input[type=file]');
    var urlInput = div.querySelector('.img-url');
    var prev = div.querySelector('.img-prev');
    function refreshPrev() { if (urlInput.value) { prev.src = urlInput.value; prev.style.display = ''; } else prev.style.display = 'none'; }
    urlInput.addEventListener('change', refreshPrev);
    div.querySelector('.up-btn').onclick = function () { fileInput.click(); };
    fileInput.onchange = function () {
      if (!fileInput.files[0]) return;
      var btn = div.querySelector('.up-btn'); btn.textContent = '上传中…';
      uploadImage(fileInput.files[0], function (ok, url, msg) {
        btn.textContent = '上传截图';
        if (ok) { urlInput.value = url; refreshPrev(); } else { alert('上传失败：' + msg); }
      });
    };
    div.querySelector('button[style*="position:absolute"]').onclick = function () { div.remove(); reindex(); };
    stepsList.appendChild(div);
    refreshPrev();
  }

  // 重建索引（删除中间步骤后 name 序号重排，避免 PHP 数组断档——实际断档无碍，保持整洁）
  function reindex() {
    var rows = stepsList.children;
    for (var i = 0; i < rows.length; i++) {
      var els = rows[i].querySelectorAll('[name^="steps["]');
      els.forEach(function (el) {
        el.name = el.name.replace(/steps\[\d+\]/, 'steps[' + i + ']');
      });
    }
  }

  document.getElementById('addStep').onclick = function () { addStep(); };

  // 初始数据
  var initSteps = <?= json_encode($editing['steps_arr'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  if (Array.isArray(initSteps) && initSteps.length) initSteps.forEach(addStep);

  // ---- Slug 自动生成：标题 → 拼音（SEO 友好），手动改过就不再覆盖 ----
  var titleInput = document.getElementById('titleInput');
  var slugInput = document.getElementById('slugInput');
  var slugManual = <?= !empty($editing['slug']) ? 'true' : 'false' ?>; // 已有 slug 视为人工值

  function slugify(text) {
    var s = text || '';
    if (window.pinyinPro && window.pinyinPro.pinyin) {
      // 中文 → 无声调拼音；英文/数字/其他字符原样保留
      s = window.pinyinPro.pinyin(s, { toneType: 'none', type: 'string', nonZh: 'consecutive', separator: ' ' });
    }
    s = s.toLowerCase()
         .replace(/[^a-z0-9]+/g, '-')   // 非字母数字统一为连字符
         .replace(/-{2,}/g, '-')
         .replace(/^-+|-+$/g, '')
         .slice(0, 60)                   // 控制 URL 长度，保留关键词
         .replace(/-+$/g, '');
    return s;
  }

  function autoSlug() {
    if (slugManual) return;
    var v = slugify(titleInput.value);
    if (v) slugInput.value = v;
  }

  titleInput.addEventListener('input', autoSlug);
  titleInput.addEventListener('blur', autoSlug);
  slugInput.addEventListener('input', function () { slugManual = true; }); // 手动改过 → 停止自动
  if (!slugInput.value) autoSlug(); // 编辑老文章但 slug 为空时补一次

  // AI 生成英文 slug（复用后台已配置的聊天渠道）
  var slugAiBtn = document.getElementById('slugAiBtn');
  slugAiBtn.onclick = function () {
    if (!titleInput.value.trim()) { alert('先填标题'); return; }
    aiAdminCall(this, 'help-slug-ai.php',
      { title: titleInput.value, id: <?= (int)($editing['id'] ?? 0) ?> },
      function (j) { slugInput.value = j.slug; slugManual = true; }, // AI 结果视为人工确认值
      'AI 英文 slug 失败（已保留当前 slug）');
  };

  // ---- 摘要自动生成：正文 → 本地提取（即时）+ AI 按钮（更优） ----
  var summaryInput = document.getElementById('summaryInput');
  var sumManual = <?= !empty($editing['summary']) ? 'true' : 'false' ?>; // 已有摘要视为人工值

  function plainText() {
    if (editor && !srcMode) {
      try { return editor.getText() || ''; } catch (e) { /* 忽略 */ }
    }
    var t = rtArea.value || '';
    var d = document.createElement('div');
    d.innerHTML = t;
    return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
  }

  function buildSummary() {
    var t = plainText().replace(/^[\s，,。.、]+/, '');
    if (!t) return '';
    var max = 120;
    if (t.length <= max) return t;
    // 优先在句子边界收尾，避免截在半句话上
    var cut = t.slice(0, max);
    var p = Math.max(cut.lastIndexOf('。'), cut.lastIndexOf('！'), cut.lastIndexOf('？'), cut.lastIndexOf('；'), cut.lastIndexOf(';'));
    return (p >= Math.floor(max * 0.5) ? cut.slice(0, p + 1) : cut + '…');
  }

  function autoSummary() {
    if (sumManual) return;
    var s = buildSummary();
    if (s) summaryInput.value = s;
  }

  summaryInput.addEventListener('input', function () { sumManual = true; }); // 手动改过 → 停止自动

  // 后台 AI 小任务公共调用：实时秒数提示 + 80s 兜底 + 显示"用了哪个渠道/耗时/为何切换"
  // （后台短任务非流式，单渠道最多 30s、两个渠道合计可能 60s+，故兜底设 80s）
  function aiAdminCall(btn, url, body, onOk, failTip) {
    var old = btn.textContent;
    var started = Date.now();
    btn.textContent = '生成中… 0s'; btn.disabled = true;
    var tick = setInterval(function () {
      btn.textContent = '生成中… ' + Math.round((Date.now() - started) / 1000) + 's';
    }, 1000);
    var stopTick = function () { clearInterval(tick); };
    var ac = (window.AbortController) ? new AbortController() : null;
    var timer = setTimeout(function () { if (ac) ac.abort(); }, 80000); // 前端兜底，避免一直转圈
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      signal: ac ? ac.signal : undefined
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        clearTimeout(timer); stopTick();
        if (j.ok) {
          onOk(j);
          var m = j.meta || {};
          var sec = m.ms ? (m.ms / 1000).toFixed(1) + 's' : '';
          btn.textContent = '✓ ' + sec;
          btn.title = '渠道：' + (m.provider || '未知') + (m.attempts && m.attempts.length > 1 ? '（已跳过 ' + (m.attempts.length - 1) + ' 个失败渠道）' : '');
          setTimeout(function () { btn.textContent = old; btn.disabled = false; }, 1500);
        } else {
          var detail = '';
          if (j.meta && j.meta.attempts) {
            detail = '\n\n尝试明细：\n' + j.meta.attempts.map(function (a) {
              return '· ' + a.name + '：' + (a.ok ? '成功' : '失败(' + (a.ms / 1000).toFixed(1) + 's) ' + (a.error || '').slice(0, 80));
            }).join('\n');
          }
          alert(failTip + '：' + (j.msg || '未知错误') + detail);
          btn.textContent = old; btn.disabled = false;
        }
      })
      .catch(function (err) {
        clearTimeout(timer); stopTick();
        var aborted = err && err.name === 'AbortError';
        alert(failTip + '：' + (aborted ? '超过 80 秒无响应（已放弃）' : '网络异常') + '\n请检查后台 AI 渠道是否可用（AI渠道配置页可点「测试」）');
        btn.textContent = old; btn.disabled = false;
      });
  }

  document.getElementById('sumAiBtn').onclick = function () {
    var btn = this;
    var content = (editor && !srcMode) ? (function () { try { return editor.getHtml(); } catch (e) { return rtArea.value; } })() : rtArea.value;
    if (!plainText().trim()) { alert('先写正文，再生成摘要'); return; }
    aiAdminCall(btn, 'help-summary-ai.php', { title: titleInput.value, content: content }, function (j) {
      summaryInput.value = j.summary;
      sumManual = true;
    }, 'AI 摘要失败（已保留当前摘要）');
  };

  // ---- 富文本：wangEditor 可视化编辑 + HTML 源码切换 ----
  var rtArea = document.getElementById('rtArea');
  var rtEditorBox = document.getElementById('rtEditorWrap');
  var editor = null;
  var srcMode = false;

  function createEditor() {
    var E = window.wangEditor;
    if (!E) return null;
    var ed = E.createEditor({
      selector: '#rtEditor',
      html: rtArea.value || '',
      config: {
        placeholder: '在这里撰写正文…支持标题、列表、加粗、链接、图片、表格',
        MENU_CONF: {
          uploadImage: {
            // 复用后台已有的 help-upload.php（管理员鉴权、≤3MB、落盘 uploads/help/）
            customUpload: function (file, insertFn) {
              uploadImage(file, function (ok, url, msg) {
                if (ok) insertFn(url, '配图', url);
                else alert('上传失败：' + msg);
              });
            }
          }
        },
        onChange: function (ed) { rtArea.value = ed.getHtml(); autoSummary(); }
      }
    });
    E.createToolbar({
      editor: ed,
      selector: '#rtToolbar',
      config: {}
    });
    return ed;
  }

  function syncFromEditor() { if (editor) rtArea.value = editor.getHtml(); }

  document.getElementById('rtToggleSrc').onclick = function () {
    srcMode = !srcMode;
    if (srcMode) {
      syncFromEditor();                       // 可视化 → 源码
      rtEditorBox.style.display = 'none';
      rtArea.style.display = '';
      this.textContent = '可视化编辑';
    } else {
      if (editor) editor.setHtml(rtArea.value || '');   // 源码 → 可视化
      rtArea.style.display = 'none';
      rtEditorBox.style.display = '';
      this.textContent = 'HTML 源码';
    }
  };

  // 源码模式下手打正文也更新摘要
  rtArea.addEventListener('input', function () { if (srcMode) autoSummary(); });

  // 提交前确保拿到最新内容（可视化模式下 textarea 是隐藏的）
  var form = rtArea.closest('form');
  if (form) form.addEventListener('submit', syncFromEditor);

  if (window.wangEditor) {
    editor = createEditor();
  } else {
    // 资源没加载出来时退回纯 textarea，保证文章仍可编辑
    rtEditorBox.style.display = 'none';
    rtArea.style.display = '';
    document.getElementById('rtToggleSrc').style.display = 'none';
  }

  syncType();
})();
</script>

<?php require_once '../shared/admin/admin-footer.php'; ?>
