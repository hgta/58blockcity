<?php
/**
 * 帮助分类管理
 * change: help-center-ai-assistant (task 5.1)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($_POST['action'] === 'save') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $icon = trim($_POST['icon'] ?? 'book');
            $desc = trim($_POST['description'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 0);
            $vis  = isset($_POST['is_visible']) ? 1 : 0;
            if ($name === '' || $slug === '') {
                $actionMsg = '<div class="admin-alert admin-alert-error">名称与 slug 不能为空</div>';
            } else {
                if ($id > 0) {
                    $pdo->prepare("UPDATE help_categories SET name=?, slug=?, icon=?, description=?, sort_order=?, is_visible=? WHERE id=?")
                        ->execute([$name, $slug, $icon, $desc, $sort, $vis, $id]);
                    $actionMsg = '<div class="admin-alert admin-alert-success">分类已更新</div>';
                } else {
                    $pdo->prepare("INSERT INTO help_categories (name, slug, icon, description, sort_order, is_visible) VALUES (?,?,?,?,?,?)")
                        ->execute([$name, $slug, $icon, $desc, $sort, $vis]);
                    $actionMsg = '<div class="admin-alert admin-alert-success">分类已创建</div>';
                }
            }
        } elseif ($_POST['action'] === 'delete' && $id > 0) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM help_articles WHERE category_id = ?");
            $cnt->execute([$id]);
            $faqCnt = $pdo->prepare("SELECT COUNT(*) FROM help_faq WHERE category_id = ?");
            $faqCnt->execute([$id]);
            if ((int)$cnt->fetchColumn() > 0 || (int)$faqCnt->fetchColumn() > 0) {
                $actionMsg = '<div class="admin-alert admin-alert-error">分类下仍有文章/FAQ，请先移除或转移</div>';
            } else {
                $pdo->prepare("DELETE FROM help_categories WHERE id = ?")->execute([$id]);
                $actionMsg = '<div class="admin-alert admin-alert-success">分类已删除</div>';
            }
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . htmlspecialchars($ex->getMessage()) . '</div>';
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM help_categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$cats = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM help_articles a WHERE a.category_id = c.id) AS art_cnt,
            (SELECT COUNT(*) FROM help_faq f WHERE f.category_id = c.id AND f.status = 'published') AS faq_cnt
     FROM help_categories c ORDER BY c.sort_order, c.id"
)->fetchAll();

$admin_site_config = ['site' => 'main', 'page_title' => '帮助分类管理'];
require_once '../shared/admin/admin-header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-book"></i> 帮助分类管理 (共 <?= count($cats) ?> 个)</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?= $actionMsg ?>
        <table class="admin-data-table">
            <thead>
                <tr><th>ID</th><th>名称</th><th>Slug</th><th>图标</th><th>排序</th><th>文章</th><th>FAQ</th><th>可见</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($cats as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td style="font-family:monospace;font-size:12px;color:#94a3b8;"><?= htmlspecialchars($c['slug']) ?></td>
                    <td><i class="fas fa-<?= htmlspecialchars($c['icon']) ?>"></i></td>
                    <td><?= $c['sort_order'] ?></td>
                    <td><?= $c['art_cnt'] ?></td>
                    <td><?= $c['faq_cnt'] ?></td>
                    <td><?= $c['is_visible'] ? '<span style="color:#22c55e">✓</span>' : '<span style="color:#64748b">隐藏</span>' ?></td>
                    <td>
                        <a href="help-categories.php?edit=<?= $c['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">编辑</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('确认删除该分类？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-<?= $editing ? 'pen' : 'plus' ?>"></i> <?= $editing ? '编辑分类' : '新增分类' ?></span>
    </div>
    <div class="admin-card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">名称 *</label>
                    <input name="name" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">Slug *（URL标识）</label>
                    <input name="slug" required pattern="[a-z0-9-]+" value="<?= htmlspecialchars($editing['slug'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">图标（Font Awesome 名称）</label>
                    <input name="icon" value="<?= htmlspecialchars($editing['icon'] ?? 'book') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
                <div><label style="display:block;font-size:13px;margin-bottom:4px;">排序（小在前）</label>
                    <input name="sort_order" type="number" value="<?= $editing['sort_order'] ?? 0 ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
            </div>
            <div style="margin-top:14px;"><label style="display:block;font-size:13px;margin-bottom:4px;">简介</label>
                <input name="description" value="<?= htmlspecialchars($editing['description'] ?? '') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;"></div>
            <div style="margin-top:14px;display:flex;align-items:center;gap:18px;">
                <label style="font-size:13px;"><input type="checkbox" name="is_visible" <?= !isset($editing['is_visible']) || $editing['is_visible'] ? 'checked' : '' ?>> 前台可见</label>
                <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? '保存修改' : '创建分类' ?></button>
                <?php if ($editing): ?><a href="help-categories.php" class="admin-btn admin-btn-secondary">取消</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
