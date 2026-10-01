<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Transaction.php';
checkLogin();

$tx = new Transaction($pdo);
$userId = $_SESSION['user_id'];
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

$transactions = $tx->getUserTransactions($userId, $perPage * 3);
$total = count($transactions);
$totalPages = ceil($total / $perPage);
$transactions = array_slice($transactions, ($page-1)*$perPage, $perPage);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:1000px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; }
.tx-table { width:100%; background:white; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.tx-table th { background:#f8f9fa; padding:12px 15px; text-align:left; font-size:13px; color:#666; border-bottom:2px solid #eee; }
.tx-table td { padding:12px 15px; border-bottom:1px solid #f0f0f0; font-size:14px; }
.badge { padding:3px 10px; border-radius:12px; font-size:12px; }
.badge-buy { background:#d4edda; color:#155724; }
.badge-sell { background:#fff3cd; color:#856404; }
.badge-done { background:#d1ecf1; color:#0c5460; }
.pagination { display:flex; justify-content:center; gap:8px; margin-top:20px; }
.pagination a { padding:8px 14px; border:1px solid #ddd; border-radius:4px; color:#333; text-decoration:none; }
.pagination a.active { background:#ff6b00; color:white; border-color:#ff6b00; }
.empty-state { text-align:center; padding:60px; color:#999; background:white; border-radius:8px; }
/* 移动端卡片化（mob-cards 通用规则来自 main.css，这里重置本页表格自身的内边距/边框） */
@media (max-width: 767px) {
    .tx-table.mob-cards td { padding:5px 0; border-bottom:none; text-align:right; }
    .tx-table.mob-cards tr { background:white; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .tx-table.mob-cards tr td[colspan]:only-child { padding:12px 15px; }
}
</style>

<div class="container">
    <h1 class="page-title"><i class="fas fa-exchange-alt"></i> 交易记录</h1>
    
    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <i class="fas fa-receipt" style="font-size:48px;display:block;margin-bottom:15px;"></i>
            <p>暂无交易记录</p>
        </div>
    <?php else: ?>
        <table class="tx-table mob-cards">
            <thead>
                <tr>
                    <th>区块</th>
                    <th>类型</th>
                    <th>价格</th>
                    <th>时间</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td data-label="区块">
                        <a href="../block/view.php?id=<?= $t['block_id'] ?>" style="color:#333;">
                            <?= htmlspecialchars($t['city_name'] ?? '') ?> <?= $t['zone'] ?? '' ?>-<?= $t['block_number'] ?? '' ?>
                        </a>
                    </td>
                    <td data-label="类型">
                        <span class="badge <?= ($t['buyer_id']==$userId) ? 'badge-buy' : 'badge-sell' ?>">
                            <?= ($t['buyer_id']==$userId) ? '购买' : '出售' ?>
                        </span>
                    </td>
                    <td data-label="价格">¥<?= number_format($t['price'], 2) ?></td>
                    <td data-label="时间"><?= date('m-d H:i', strtotime($t['created_at'])) ?></td>
                    <td data-label="状态">
                        <span class="badge badge-done"><?= $t['status'] == 'completed' ? '已完成' : ($t['status'] == 'pending' ? '处理中' : '已取消') ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
                <a href="?page=<?=$i?>" class="<?=$i==$page?'active':''?>"><?=$i?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
