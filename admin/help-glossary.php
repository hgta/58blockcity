<?php
/**
 * 术语表管理
 * change: help-center-ai-assistant (task 5.4)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($_POST['action'] === 'save') {
            $term = trim($_POST['term'] ?? '');
            $pinyin = strtolower(trim($_POST['pinyin'] ?? ''));
            $def = trim($_POST['definition'] ?? '');
            $artId = (int)($_POST['related_article_id'] ?? 0) ?: null;
            $sort = (int)($_POST['sort_order'] ?? 0);
            if ($term === '' || $def === '') {
                $actionMsg = '<div class="admin-alert admin-alert-error">术语与释义不能为空</div>';
            } elseif ($id > 0) {
                $pdo->prepare("UPDATE help_glossary SET term=?, pinyin=?, definition=?, related_article_id=?, sort_order=? WHERE id=?")
                    ->execute([$term, $pinyin, $def, $artId, $sort, $id]);
                $actionMsg = '<div class="admin-alert admin-alert-success">术语已更新</div>';
            } else {
                $pdo->prepare("INSERT INTO help_glossary (term, pinyin, definition, related_article_id, sort_order) VALUES (?,?,?,?,?)")
                    ->execute([$term, $pinyin, $def, $artId, $sort]);
                $actionMsg = '<div class="admin-alert admin-alert-success">术语已创建</div>';
            }
        } elseif ($_POST['action'] === 'delete' && $id > 0) {
            $pdo->prepare("DELETE FROM help_glossary WHERE id = ?")->execute([$id]);
            $actionMsg = '<div class="admin-alert admin-alert-success">术语已删除</div>';
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . htmlspecialchars($ex->getMessage()) . '</div>';
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM help_glossary WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$arts = $pdo->query("SELECT id, title FROM help_articles WHERE status = 'published' ORDER BY title")->fetchAll();
$terms = $pdo->query("SELECT g.*, a.title AS art_title FROM help_glossary g LEFT JOIN help_articles a ON a.id = g.related_article_id ORDER BY g.sort_order, g.pinyin, g.id LIMIT 300")->fetchAll();

$admin_site_config = ['site' => 'main', 'page_title' => '术语表管理'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-book-bookmark"></i> 术语表 (<?= count($terms) ?> 条)</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead><tr><th>ID</th><th>术语</th><th>拼音/字母</th><th>释义</th><th>关联教程</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($terms as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td><b><?= htmlspecialchars($t['term']) ?></b></td>
                    <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($t['pinyin']) ?></td>
                    <td><?= htmlspecialchars(mb_substr($t['definition'], 0, 30)) ?>…</td>
                    <td style="font-size:12px;"><?= $t['art_title'] ? htmlspecialchars(mb_substr($t['art_title'], 0, 16)) : '-' ?></td>
                    <td style="white-space:nowrap;">
                        <a href="help-glossary.php?edit=<?= $t['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">编辑</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('确认删除？')">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="admin-btn admin-btn-danger admin-btn-sm">删</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$terms): ?><tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px;">暂无术语</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-<?= $editing ? 'pen' : 'plus' ?>"></i> <?= $editing ? '编辑术语' : '新增术语' ?></span></div>
    <div class="admin-card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">术语 *</label>
                    <input name="term" required value="<?= htmlspecialchars($editing['term'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">拼音（字母序排布用）</label>
                    <input name="pinyin" value="<?= htmlspecialchars($editing['pinyin'] ?? '') ?>" placeholder="英文术语可留空自动取原词" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">排序</label>
                    <input name="sort_order" type="number" value="<?= $editing['sort_order'] ?? 0 ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
            </div>
            <div style="margin-top:12px;"><label style="display:block;font-size:13px;margin-bottom:4px;">释义 *</label>
                <textarea name="definition" required rows="3" style="width:100%;padding:10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"><?= htmlspecialchars($editing['definition'] ?? '') ?></textarea></div>
            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;">
                <select name="related_article_id" style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                    <option value="">关联教程（可选）</option>
                    <?php foreach ($arts as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= ($editing['related_article_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars(mb_substr($a['title'], 0, 24)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? '保存' : '创建' ?></button>
                <?php if ($editing): ?><a href="help-glossary.php" class="admin-btn admin-btn-secondary">取消</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
