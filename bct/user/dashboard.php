<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/BCTOrder.php';

checkLogin();
$userId = $_SESSION['user_id'];

$account = new UserBCTAccount($pdo);
$order = new BCTOrder($pdo);

$userAccounts = $account->getUserAccounts($userId);
$portfolio = $account->getPortfolioSummary($userId);
$stats = $order->getUserOrderStats($userId);

$tab = $_GET['tab'] ?? 'buy';
$tab = in_array($tab, ['buy','sell','completed']) ? $tab : 'buy';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

if ($tab === 'completed') {
    $orders = $order->getUserOrders($userId, 'all', 'completed', $page, $perPage);
    $totalOrders = $stats['completed'];
} else {
    $orders = $order->getUserOrders($userId, $tab, 'active', $page, $perPage);
    $totalOrders = $stats[$tab . '_active'] ?? 0;
}
$totalPages = ceil($totalOrders / $perPage);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $oid = intval($_POST['order_id'] ?? 0);
    if ($_POST['action'] === 'cancel' && $oid) {
        if ($order->cancelOrder($oid, $userId)) {
            $msg = '<div class="alert alert-success">订单已取消</div>';
            header("Location: dashboard.php?tab={$tab}");
            exit;
        } else {
            $msg = '<div class="alert alert-danger">取消失败</div>';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="dash-wrap" style="padding-top:20px;">
    <?= $msg ?>

    <div class="bct-page-title">
        <div>
            <h1><i class="fas fa-user-circle"></i> 欢迎回来，<?= htmlspecialchars($_SESSION['username']) ?></h1>
            <div class="subtitle">管理您的 BCT 资产与订单</div>
        </div>
        <div>
            <a href="../trade.php" class="btn btn-primary"><i class="fas fa-plus"></i> 发布交易</a>
            <a href="profile.php" class="btn btn-default" style="margin-left:8px;"><i class="fas fa-cog"></i> 账户设置</a>
        </div>
    </div>

    <div class="bct-portfolio-summary">
        <div class="bct-portfolio-card">
            <div class="label">总资产估值</div>
            <div class="value">¥<?= number_format($portfolio['total_valuation'], 2) ?></div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">可用余额</div>
            <div class="value"><?= number_format($portfolio['total_balance']) ?> BCT</div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">冻结中</div>
            <div class="value"><?= number_format($portfolio['total_frozen']) ?> BCT</div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">持有城市</div>
            <div class="value"><?= $portfolio['city_count'] ?></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-wallet"></i> 我的持仓</h3></div>
                <?php if (empty($userAccounts)): ?>
                <div class="text-center" style="padding:40px;color:var(--bct-text-secondary);">
                    <i class="fas fa-wallet" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
                    <p>暂无 BCT 资产</p>
                    <a href="../market.php" class="btn btn-primary">去交易</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>城市</th>
                                <th class="text-right">余额</th>
                                <th class="text-right">当前价</th>
                                <th class="text-right">估值</th>
                                <th class="text-right">占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalVal = $portfolio['total_valuation'] ?: 1;
                            foreach ($userAccounts as $acc):
                                $ratio = $acc['valuation'] / $totalVal * 100;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($acc['city']) ?></strong></td>
                                <td class="text-right"><?= number_format($acc['balance']) ?> BCT</td>
                                <td class="text-right">¥<?= number_format($acc['current_price'], 4) ?></td>
                                <td class="text-right">¥<?= number_format($acc['valuation'], 2) ?></td>
                                <td class="text-right"><?= number_format($ratio, 2) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-chart-pie"></i> 持仓分布</h3></div>
                <div class="card-body">
                    <?php if (count($userAccounts) < 1): ?>
                    <div class="text-center" style="padding:30px;color:var(--bct-text-secondary);">暂无数据</div>
                    <?php else: ?>
                    <div id="portfolioPie" style="width:100%;height:300px;"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:24px;">
        <div class="card-header">
            <ul class="nav nav-tabs" style="border-bottom:none;">
                <li class="<?= $tab=='buy'?'active':'' ?>"><a href="?tab=buy">买入订单</a></li>
                <li class="<?= $tab=='sell'?'active':'' ?>"><a href="?tab=sell">卖出订单</a></li>
                <li class="<?= $tab=='completed'?'active':'' ?>"><a href="?tab=completed">已完成</a></li>
            </ul>
        </div>
        <?php if (empty($orders)): ?>
        <div class="text-center" style="padding:40px;color:var(--bct-text-secondary);">
            <i class="fas fa-inbox" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
            <p><?= $tab=='completed' ? '暂无成交记录' : '暂无订单' ?></p>
            <a href="../market.php" class="btn btn-primary">去交易</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>订单号</th><th>城市</th><th>数量</th><th>价格</th><th>总金额</th>
                        <th>状态</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        $statusMap = [
                            'pending'    => ['待成交', 'badge-warning'],
                            'processing' => ['部分成交', 'badge-info'],
                            'completed'  => ['已完成', 'badge-success'],
                            'canceled'   => ['已取消', 'badge-default'],
                        ];
                        $s = $statusMap[$o['status']] ?? [$o['status'], ''];
                    ?>
                    <tr>
                        <td style="font-size:12px;color:var(--bct-text-muted);"><?= substr($o['order_no'], 0, 8) ?></td>
                        <td><?= htmlspecialchars($o['city']) ?></td>
                        <td><?= number_format($o['amount']) ?> BCT</td>
                        <td>¥<?= number_format($o['price'], 4) ?></td>
                        <td>¥<?= number_format($o['total_amount'] ?? ($o['amount']*$o['price']), 2) ?></td>
                        <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                        <td>
                            <?php if (in_array($o['status'], ['pending','processing'])): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确定取消该订单?')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button class="btn btn-sm btn-danger">取消</button>
                            </form>
                            <?php elseif ($o['status'] === 'completed'): ?>
                            <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-default">查看</a>
                            <?php else: ?>
                            <span style="color:var(--bct-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="padding:16px;display:flex;justify-content:center;">
            <ul class="pagination">
                <?php for ($i=1;$i<=$totalPages;$i++):
                    if ($i==1 || $i==$totalPages || abs($i-$page)<=2):
                        $active = $i==$page ? 'class="active"' : '';
                ?>
                <li <?= $active ?>><a href="?tab=<?= $tab ?>&page=<?= $i ?>"><?= $i ?></a></li>
                <?php elseif (abs($i-$page)==3): ?><li class="disabled"><span>...</span></li><?php endif; ?>
                <?php endfor; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
$(function() {
    <?php if (!empty($userAccounts)): ?>
    var pieData = <?= json_encode(array_map(function($a) {
        return ['name'=>$a['city'], 'value'=>round($a['valuation'],2)];
    }, $userAccounts)) ?>;
    BCTCharts.initPortfolioPie('portfolioPie', pieData);
    <?php endif; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>
