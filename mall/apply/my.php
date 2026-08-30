<?php
// 我的申请 - 申请状态查看页
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Application.php';
require_once '../../classes/SeoHelper.php';

requireLogin('../auth/login.php');

$userId = (int)$_SESSION['user_id'];
$app = new Application($pdo);
$list = $app->getMyApplications($userId);

$statusClass = [
    'pending'   => 'st-pending',
    'contacted' => 'st-contacted',
    'approved'  => 'st-approved',
    'rejected'  => 'st-rejected',
];

$site_config['title']       = SeoHelper::title('我的申请 - 58人气值商城');
$site_config['description'] = SeoHelper::description('查看您提交的模特申请与作者合作申请的处理进度。', '58人气值商城');
$site_config['keywords']    = '58,我的申请,模特申请,作者合作';
$site_config['canonical_url'] = 'https://mall.58.tl/apply/my.php';

require_once '../includes/header.php';
?>
<style>
.apply-wrap { max-width: 760px; margin: 24px auto 48px; padding: 0 16px; }
.apply-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 28px; }
.apply-card h1 { font-size: 22px; margin: 0 0 6px; color: #1e293b; }
.apply-sub { color: #94a3b8; font-size: 14px; margin: 0 0 20px; }
.apply-empty { text-align: center; padding: 40px 10px; color: #94a3b8; }
.apply-empty .ico { font-size: 46px; display: block; margin-bottom: 10px; color: #cbd5e1; }
.my-item { display: flex; gap: 16px; align-items: flex-start; border: 1px solid #eef2f7; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
.my-thumb { width: 68px; height: 68px; border-radius: 10px; overflow: hidden; background: #f1f5f9; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #cbd5e1; }
.my-thumb img { width: 100%; height: 100%; object-fit: cover; }
.my-body { flex: 1; min-width: 0; }
.my-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
.my-head .t { font-size: 15px; font-weight: 700; color: #1e293b; }
.my-type { font-size: 12px; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: 2px 10px; }
.my-time { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.my-reject { margin-top: 8px; font-size: 13px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 10px; }
.my-link { margin-top: 8px; font-size: 13px; color: #6c5ce7; text-decoration: none; }
.badge { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; border-radius: 999px; padding: 3px 10px; }
.st-pending { background: #f1f5f9; color: #64748b; }
.st-contacted { background: #eff6ff; color: #2563eb; }
.st-approved { background: #f0fdf4; color: #16a34a; }
.st-rejected { background: #fef2f2; color: #dc2626; }
@media (max-width: 640px) { .my-thumb { width: 56px; height: 56px; } }
</style>

<div class="container">
    <div class="apply-wrap">
        <div class="apply-card">
            <h1>我的申请</h1>
            <p class="apply-sub">模特申请与作者合作申请的处理进度</p>

            <?php if (empty($list)): ?>
                <div class="apply-empty">
                    <span class="ico"><i class="fas fa-file-signature"></i></span>
                    <p>您还没有提交过申请</p>
                    <p style="font-size:13px;margin-top:6px;">
                        <a href="../apply/model.php" style="color:#ff6b00;">我要当模特</a> · 
                        <a href="../apply/author.php" style="color:#6c5ce7;">我是作者，我要合作</a>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($list as $row):
                    $photos = json_decode($row['photos'] ?? '[]', true) ?: [];
                    $isModel = $row['type'] === 'model';
                ?>
                <div class="my-item">
                    <div class="my-thumb">
                        <?php if (!empty($photos[0])): ?>
                            <img src="../<?= htmlspecialchars($photos[0]) ?>" alt="">
                        <?php else: ?>
                            <i class="fas <?= $isModel ? 'fa-user-circle' : 'fa-palette' ?>" style="font-size:26px;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="my-body">
                        <div class="my-head">
                            <span class="t"><?= htmlspecialchars($row['nickname']) ?></span>
                            <span class="my-type"><?= Application::typeLabel($row['type']) ?></span>
                            <span class="badge <?= $statusClass[$row['status']] ?? 'st-pending' ?>">
                                <?= Application::statusLabel($row['status']) ?>
                            </span>
                        </div>
                        <div class="my-time">提交于 <?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></div>
                        <?php if ($row['status'] === 'rejected' && !empty($row['reject_reason'])): ?>
                            <div class="my-reject"><i class="fas fa-times-circle"></i> 驳回原因：<?= htmlspecialchars($row['reject_reason']) ?></div>
                        <?php endif; ?>
                        <?php if ($row['status'] === 'approved' && !empty($row['model_id'])): ?>
                            <a class="my-link" href="../model/view.php?id=<?= (int)$row['model_id'] ?>"><i class="fas fa-arrow-right"></i> 查看我的模特主页</a>
                        <?php elseif ($row['status'] === 'approved' && !empty($row['author_id'])): ?>
                            <a class="my-link" href="../author/view.php?id=<?= (int)$row['author_id'] ?>"><i class="fas fa-arrow-right"></i> 查看我的作者主页</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
