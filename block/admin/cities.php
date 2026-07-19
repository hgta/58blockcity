<?php
/**
 * Block 子站 — 城市管理
 * 管理城市基础数据：排名、开启区块、居民数、人气值、是否热门等
 * 这些数据来源于后台录入或 blockcity.vip 同步，不受 block.58.tl 前端认领影响
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';

// 使用统一后台框架
$admin_site_config = [
    'site'       => 'block',
    'page_title' => '城市管理',
];
require_once '../../shared/admin/admin-header.php';

$msg = '';
$err = '';

// 处理编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cityId = intval($_POST['city_id'] ?? 0);
    if ($_POST['action'] === 'update' && $cityId > 0) {
        $rank    = intval($_POST['rank'] ?? 0);
        $actived = intval($_POST['activated_blocks'] ?? 0);
        $resident= intval($_POST['resident_count'] ?? 0);
        $popular = intval($_POST['popularity'] ?? 0);
        $isHot   = intval($_POST['is_hot'] ?? 0);

        $stmt = $pdo->prepare("UPDATE cities SET rank = ?, activated_blocks = ?, resident_count = ?, popularity = ?, is_hot = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$rank, $actived, $resident, $popular, $isHot, $cityId]);
        $msg = '城市数据已更新';
    }
}

// 查询
$search = trim($_GET['search'] ?? '');
$where = '1=1';
$params = [];
if ($search !== '') {
    $where = 'c.name LIKE ? OR c.pinyin LIKE ? OR c.area_code LIKE ?';
    $params = ['%'.$search.'%', '%'.$search.'%', '%'.$search.'%'];
}

$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM blocks WHERE city_id = c.id AND status = 'sold') as block_claimed
    FROM cities c
    WHERE $where
    ORDER BY c.rank ASC, c.name ASC
    LIMIT 500
");
$stmt->execute($params);
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($cities);
?>

<?php if ($msg): ?><div class="admin-alert success" style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:8px;margin-bottom:16px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert error" style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:8px;margin-bottom:16px;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- 顶部操作栏 -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <h3 class="admin-card-title"><i class="fas fa-city"></i> 城市列表</h3>
        <div style="display:flex; gap:8px; align-items:center;">
            <form method="get" style="display:flex; gap:8px;">
                <input type="text" name="search" class="admin-form-control" placeholder="搜索城市名/拼音/区号" value="<?= htmlspecialchars($search) ?>" style="min-width:180px;">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="cities.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i></a>
                <?php endif; ?>
            </form>
            <span class="admin-text-muted">共 <?= $total ?> 个城市</span>
        </div>
    </div>
</div>

<!-- 数据表格 -->
<div class="admin-card">
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:60px;">排名</th>
                    <th>城市</th>
                    <th>拼音</th>
                    <th>开启区块</th>
                    <th>居民数</th>
                    <th>人气值</th>
                    <th>本站认领</th>
                    <th>热门</th>
                    <th style="width:100px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cities as $c): ?>
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;font-weight:700;font-size:13px;
                            <?php if ($c['rank'] <= 3): ?>background:linear-gradient(135deg,var(--admin-accent),var(--admin-accent-light));color:#fff;
                            <?php else: ?>background:#f5f5f5;color:#999;<?php endif; ?>">
                            <?= $c['rank'] ?: '-' ?>
                        </span>
                    </td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong> <?php if ($c['area_code']): ?><span class="admin-text-muted">(<?= htmlspecialchars($c['area_code']) ?>)</span><?php endif; ?></td>
                    <td class="admin-text-muted"><?= htmlspecialchars($c['pinyin']) ?></td>
                    <td><strong><?= number_format($c['activated_blocks']) ?></strong></td>
                    <td><?= number_format($c['resident_count']) ?></td>
                    <td><?= number_format($c['popularity']) ?></td>
                    <td class="admin-text-muted"><?= number_format($c['block_claimed']) ?></td>
                    <td>
                        <?php if ($c['is_hot']): ?><span class="admin-badge warning">热门</span>
                        <?php else: ?><span class="admin-text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <button class="admin-btn admin-btn-sm admin-btn-default" onclick="openEdit(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>', <?= $c['rank'] ?>, <?= $c['activated_blocks'] ?>, <?= $c['resident_count'] ?>, <?= $c['popularity'] ?>, <?= $c['is_hot'] ?>)">
                            <i class="fas fa-edit"></i> 编辑
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cities)): ?>
                <tr><td colspan="9" class="admin-empty-state"><i class="fas fa-city"></i><p>没有找到城市</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 编辑弹窗 -->
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:94vw;box-shadow:0 8px 30px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 16px;font-size:18px;"><i class="fas fa-city"></i> 编辑城市 — <span id="editCityName"></span></h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="city_id" id="editCityId">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:13px;color:#666;margin-bottom:4px;">排名 (rank)</label>
                <input type="number" name="rank" id="editRank" class="admin-form-control" min="0" required>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:13px;color:#666;margin-bottom:4px;">开启区块数 (activated_blocks)</label>
                <input type="number" name="activated_blocks" id="editActivated" class="admin-form-control" min="0" required>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:13px;color:#666;margin-bottom:4px;">居民数 (resident_count)</label>
                <input type="number" name="resident_count" id="editResident" class="admin-form-control" min="0" required>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:13px;color:#666;margin-bottom:4px;">人气值 (popularity)</label>
                <input type="number" name="popularity" id="editPopular" class="admin-form-control" min="0" required>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#666;cursor:pointer;">
                    <input type="checkbox" name="is_hot" value="1" id="editHot"> 设为热门城市
                </label>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="admin-btn admin-btn-default" onclick="document.getElementById('editModal').style.display='none'">取消</button>
                <button type="submit" class="admin-btn admin-btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, rank, activated, resident, popularity, isHot) {
    document.getElementById('editCityId').value = id;
    document.getElementById('editCityName').textContent = name;
    document.getElementById('editRank').value = rank;
    document.getElementById('editActivated').value = activated;
    document.getElementById('editResident').value = resident;
    document.getElementById('editPopular').value = popularity;
    document.getElementById('editHot').checked = isHot == 1;
    document.getElementById('editModal').style.display = 'flex';
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
