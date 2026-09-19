<?php
/**
 * FAQ 管理（含未命中对话一键转 FAQ 入口）
 * change: help-center-ai-assistant (task 5.4 / 5.6)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($_POST['action'] === 'save') {
            $q = trim($_POST['question'] ?? '');
            $a = trim($_POST['answer'] ?? '');
            $catId = (int)($_POST['category_id'] ?? 0);
            $artId = (int)($_POST['related_article_id'] ?? 0) ?: null;
            $sort = (int)($_POST['sort_order'] ?? 0);
            $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
            $source = in_array($_POST['source'] ?? '', ['manual', 'ai_draft', 'from_chat']) ? $_POST['source'] : 'manual';
            if ($q === '' || $a === '' || $catId <= 0) {
                $actionMsg = '<div class="admin-alert admin-alert-error">问题、答案、分类不能为空</div>';
            } elseif ($id > 0) {
                $pdo->prepare("UPDATE help_faq SET question=?, answer=?, category_id=?, related_article_id=?, sort_order=?, status=?, source=? WHERE id=?")
                    ->execute([$q, $a, $catId, $artId, $sort, $status, $source, $id]);
                $actionMsg = '<div class="admin-alert admin-alert-success">FAQ 已更新</div>';
            } else {
                $pdo->prepare("INSERT INTO help_faq (question, answer, category_id, related_article_id, sort_order, status, source) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$q, $a, $catId, $artId, $sort, $status, $source]);
                $actionMsg = '<div class="admin-alert admin-alert-success">FAQ 已创建</div>';
            }
        } elseif ($_POST['action'] === 'delete' && $id > 0) {
            $pdo->prepare("DELETE FROM help_faq WHERE id = ?")->execute([$id]);
            $actionMsg = '<div class="admin-alert admin-alert-success">FAQ 已删除</div>';
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . htmlspecialchars($ex->getMessage()) . '</div>';
    }
}

// 未命中对话转 FAQ（来自对话记录页）
if (isset($_GET['from_chat'])) {
    $stmt = $pdo->prepare("SELECT question FROM ai_chat_logs WHERE id = ?");
    $stmt->execute([(int)$_GET['from_chat']]);
    $preQ = (string)$stmt->fetchColumn();
    $preFill = ['question' => $preQ, 'answer' => '', 'category_id' => 11, 'status' => 'draft', 'source' => 'from_chat', 'sort_order' => 0, 'related_article_id' => ''];
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM help_faq WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
$f = $editing ?: (isset($preFill) ? $preFill : null);

$cats = $pdo->query("SELECT id, name FROM help_categories ORDER BY sort_order, id")->fetchAll();
$arts = $pdo->query("SELECT id, title FROM help_articles WHERE status = 'published' ORDER BY title")->fetchAll();

$fltCat = (int)($_GET['cat'] ?? 0);
$where = $fltCat > 0 ? "WHERE f.category_id = " . $fltCat : '';
$faqs = $pdo->query("SELECT f.*, c.name AS cat_name FROM help_faq f JOIN help_categories c ON c.id = f.category_id $where ORDER BY f.category_id, f.sort_order, f.id LIMIT 200")->fetchAll();

$admin_site_config = ['site' => 'main', 'page_title' => 'FAQ 管理'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-circle-question"></i> FAQ 管理 (<?= count($faqs) ?>)</span>
        <form method="GET" style="display:flex;gap:8px;">
            <select name="cat" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部分类</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $fltCat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="admin-btn admin-btn-primary admin-btn-sm">筛选</button>
            <a href="help-faq.php" class="admin-btn admin-btn-success admin-btn-sm">+ 新建</a>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead><tr><th>ID</th><th>问题</th><th>分类</th><th>来源</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($faqs as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars(mb_substr($row['question'], 0, 40)) ?></td>
                    <td><?= htmlspecialchars($row['cat_name']) ?></td>
                    <td style="font-size:12px;"><?= ['manual' => '手动', 'ai_draft' => 'AI草稿', 'from_chat' => '对话转来'][$row['source']] ?></td>
                    <td><span style="color:<?= $row['status'] === 'published' ? '#22c55e' : '#f59e0b' ?>"><?= $row['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
                    <td style="white-space:nowrap;">
                        <a href="help-faq.php?edit=<?= $row['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">编辑</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('确认删除？')">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button class="admin-btn admin-btn-danger admin-btn-sm">删</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$faqs): ?><tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px;">暂无 FAQ</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-<?= $editing ? 'pen' : 'plus' ?>"></i> <?= $editing ? '编辑 FAQ #' . $editing['id'] : '新建 FAQ' ?></span></div>
    <div class="admin-card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $f['id'] ?? 0 ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($f['source'] ?? 'manual') ?>">
            <div><label style="display:block;font-size:13px;margin-bottom:4px;">问题 *</label>
                <input name="question" required value="<?= htmlspecialchars($f['question'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
            <div style="margin-top:12px;"><label style="display:block;font-size:13px;margin-bottom:4px;">答案 *</label>
                <textarea name="answer" required rows="4" style="width:100%;padding:10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"><?= htmlspecialchars($f['answer'] ?? '') ?></textarea></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px;">
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">分类 *</label>
                    <select name="category_id" required style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                        <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($f['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">关联教程（可选）</label>
                    <select name="related_article_id" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                        <option value="">不关联</option>
                        <?php foreach ($arts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($f['related_article_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars(mb_substr($a['title'], 0, 24)) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">排序</label>
                    <input name="sort_order" type="number" value="<?= $f['sort_order'] ?? 0 ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">状态</label>
                    <select name="status" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                        <option value="draft" <?= ($f['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>草稿</option>
                        <option value="published" <?= ($f['status'] ?? '') === 'published' ? 'selected' : '' ?>>已发布</option>
                    </select></div>
            </div>
            <div style="margin-top:14px;">
                <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? '保存' : '创建' ?></button>
                <a href="help-faq.php" class="admin-btn admin-btn-secondary">清空表单</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
