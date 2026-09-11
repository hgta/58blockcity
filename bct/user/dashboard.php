<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/BCTOrder.php';
require_once '../../classes/UserHoldings.php';

checkLogin();
$userId = $_SESSION['user_id'];

$order = new BCTOrder($pdo);
$holdings = new UserHoldings($pdo);

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
if (isset($_SESSION['holdings_msg'])) {
    $msg = $_SESSION['holdings_msg'];
    unset($_SESSION['holdings_msg']);
}

// 用户持有区块的城市（持仓可编辑范围）
$cityList = $holdings->getUserCities($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $oid = intval($_POST['order_id'] ?? 0);

    if ($_POST['action'] === 'save_holdings') {
        // 表单以 city_id 为键，映射回城市名并交给数据层做白名单校验
        $idToName = [];
        foreach ($cityList as $c) {
            $idToName[(string)$c['city_id']] = $c['name'];
        }
        $posted = $_POST['qty'] ?? [];
        $input = [];
        if (is_array($posted)) {
            foreach ($posted as $cid => $val) {
                $cid = (string)$cid;
                if (!isset($idToName[$cid])) {
                    continue; // 非持有城市：忽略（越权防护）
                }
                $input[$idToName[$cid]] = $val;
            }
        }
        $res = $holdings->saveUserPopularities($userId, $input);
        $errors = $res['errors'] ?? [];
        if (!empty($errors)) {
            $msg = '<div class="alert alert-danger">部分城市未保存：' . htmlspecialchars(implode('；', $errors)) . '</div>';
        } else {
            $msg = '<div class="alert alert-success">持仓已保存</div>';
        }
        $_SESSION['holdings_msg'] = $msg;
        header('Location: dashboard.php?tab=' . urlencode($tab));
        exit;
    }

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

// 人气值持仓模型（数量 × 城市单价）
$popularityMap = $holdings->getUserPopularityMap($userId);
$holdingRows = [];
$totalAmount = 0.0;
$totalPopularity = 0;
foreach ($cityList as $c) {
    $name = (string)$c['name'];
    $price = (float)$c['bct_current_price'];
    $rank = (int)($c['rank'] ?? 0);
    $qty = isset($popularityMap[$name]) ? (int)$popularityMap[$name] : 0;
    $amount = $qty * $price;
    $totalAmount += $amount;
    $totalPopularity += $qty;
    $holdingRows[] = [
        'city_id' => (int)$c['city_id'],
        'name'    => $name,
        'price'   => $price,
        'rank'    => $rank,
        'qty'     => $qty,
        'amount'  => $amount,
    ];
}
$cityCount = count($holdingRows);
$showPie = $totalAmount > 0; // 无金额时不渲染饼图容器，避免脚本对空节点初始化

require_once '../includes/header.php';
?>

<style>
/* 我的持仓：排名 / 步进输入 / 实时计算 的页面级样式 */
.holdings-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.holdings-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.holdings-dirty { font-size:12px; color:var(--bct-accent); display:inline-flex; align-items:center; gap:5px; }
.holdings-dirty i { font-size:8px; }
.holdings-search { position:relative; }
.holdings-search i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--bct-text-muted); font-size:12px; pointer-events:none; }
.holdings-search input {
  background:var(--bct-bg-tertiary); border:1px solid var(--bct-border); color:var(--bct-text);
  border-radius:var(--bct-radius); padding:6px 10px 6px 28px; font-size:13px; width:170px;
}
.holdings-search input:focus { outline:none; border-color:var(--bct-accent); box-shadow:0 0 0 2px rgba(240,185,11,.2); }
.holdings-table-wrap { max-height:560px; overflow:auto; }
.holdings-table thead th { position:sticky; top:0; z-index:2; }
.holdings-table tbody tr.is-hidden { display:none; }
.holdings-rank {
  display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:22px; padding:0 6px;
  border-radius:6px; background:var(--bct-bg-tertiary); border:1px solid var(--bct-border);
  font-family:monospace; font-size:12px; color:var(--bct-text-secondary);
}
.holdings-rank.top { background:rgba(240,185,11,.15); border-color:rgba(240,185,11,.45); color:var(--bct-accent); font-weight:700; }
.qty-stepper { display:inline-flex; align-items:stretch; border:1px solid var(--bct-border); border-radius:var(--bct-radius); overflow:hidden; background:var(--bct-bg-tertiary); }
.qty-stepper:focus-within { border-color:var(--bct-accent); box-shadow:0 0 0 2px rgba(240,185,11,.2); }
.qty-stepper .qty-btn { width:30px; border:none; background:transparent; color:var(--bct-text-secondary); cursor:pointer; font-size:15px; line-height:1; user-select:none; }
.qty-stepper .qty-btn:hover { background:var(--bct-bg-hover); color:var(--bct-accent); }
.qty-stepper .qty-input {
  border:none; background:transparent; color:var(--bct-text); width:70px; text-align:center;
  font-family:'Roboto Mono',monospace; font-size:14px; padding:6px 2px;
}
.qty-stepper .qty-input:focus { outline:none; }
.qty-stepper .qty-input::-webkit-outer-spin-button,
.qty-stepper .qty-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.holdings-table tfoot td { background:var(--bct-bg-tertiary); border-top:1px solid var(--bct-border); }
.holdings-actions { padding:14px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; border-top:1px solid var(--bct-border); }
.holdings-actions .holdings-note { font-size:12px; color:var(--bct-text-secondary); }
.holdings-actions .holdings-btns { display:flex; gap:8px; }
@media (max-width:576px) { .holdings-search input { width:120px; } }
</style>

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
            <div class="label">总资产额</div>
            <div class="value" id="sumTotalAmount">¥<?= number_format($totalAmount, 2) ?></div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">持有城市</div>
            <div class="value"><?= (int)$cityCount ?></div>
        </div>
        <div class="bct-portfolio-card">
            <div class="label">总人气值</div>
            <div class="value" id="sumTotalPop">Ⓟ <?= number_format($totalPopularity) ?></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header holdings-header">
                    <h3 style="margin:0;font-size:16px;"><i class="fas fa-wallet"></i> 我的持仓</h3>
                    <?php if (!empty($holdingRows)): ?>
                    <div class="holdings-toolbar">
                        <span class="holdings-dirty" id="holdingsDirty" hidden><i class="fas fa-circle"></i> 有未保存的修改</span>
                        <div class="holdings-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="holdingsSearch" placeholder="搜索城市" autocomplete="off">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (empty($holdingRows)): ?>
                <div class="text-center" style="padding:40px;color:var(--bct-text-secondary);">
                    <i class="fas fa-wallet" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
                    <p>暂无可管理的城市</p>
                    <p style="font-size:13px;">持有区块后即可在此登记该城市的人气值</p>
                    <a href="../market.php" class="btn btn-primary">去交易</a>
                </div>
                <?php else: ?>
                <form method="post" id="holdingsForm" autocomplete="off">
                    <input type="hidden" name="action" value="save_holdings">
                    <div class="holdings-table-wrap">
                        <table class="table holdings-table">
                            <thead>
                                <tr>
                                    <th style="width:72px;">排名</th>
                                    <th>城市</th>
                                    <th class="text-right">城市单价</th>
                                    <th class="text-right" style="width:190px;">资产数量（人气值）</th>
                                    <th class="text-right">资产金额</th>
                                    <th class="text-right">占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($holdingRows as $row):
                                    $ratio = $totalAmount > 0 ? $row['amount'] / $totalAmount * 100 : 0;
                                    $rankCls = ($row['rank'] > 0 && $row['rank'] <= 10) ? ' top' : '';
                                ?>
                                <tr class="holding-row" data-price="<?= (float)$row['price'] ?>" data-city="<?= htmlspecialchars($row['name']) ?>">
                                    <td>
                                        <span class="holdings-rank<?= $rankCls ?>">
                                            <?= $row['rank'] > 0 ? (int)$row['rank'] : '—' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                    <td class="text-right num">¥<?= number_format($row['price'], 2) ?></td>
                                    <td class="text-right">
                                        <div class="qty-stepper">
                                            <button type="button" class="qty-btn" data-step="-1" tabindex="-1" aria-label="减少">−</button>
                                            <input type="text" inputmode="numeric" pattern="[0-9]*"
                                                   name="qty[<?= (int)$row['city_id'] ?>]"
                                                   value="<?= (int)$row['qty'] ?>"
                                                   class="qty-input" aria-label="人气值数量">
                                            <button type="button" class="qty-btn" data-step="1" tabindex="-1" aria-label="增加">+</button>
                                        </div>
                                    </td>
                                    <td class="text-right num row-amount">¥<?= number_format($row['amount'], 2) ?></td>
                                    <td class="text-right num row-ratio"><?= number_format($ratio, 2) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>—</td>
                                    <td><strong>合计</strong></td>
                                    <td class="text-right">—</td>
                                    <td class="text-right"><strong>Ⓟ <span id="totalPop"><?= number_format($totalPopularity) ?></span></strong></td>
                                    <td class="text-right"><strong>¥<span id="totalAmount"><?= number_format($totalAmount, 2) ?></span></strong></td>
                                    <td class="text-right num">100.00%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="holdings-actions">
                        <span class="holdings-note">
                            <i class="fas fa-info-circle"></i> 人气值为你的自管记录，真实交易以 blockcity.vip 为准
                        </span>
                        <div class="holdings-btns">
                            <button type="button" class="btn btn-default" id="holdingsReset"><i class="fas fa-undo"></i> 重置</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存持仓</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-chart-pie"></i> 持仓分布</h3></div>
                <div class="card-body">
                    <div id="portfolioPie" style="width:100%;height:300px;<?= $showPie ? '' : 'display:none;' ?>"></div>
                    <div id="portfolioEmpty" class="text-center" style="padding:30px;color:var(--bct-text-secondary);" <?= $showPie ? 'hidden' : '' ?>>暂无数据</div>
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
                        <td>¥<?= number_format($o['price'], 2) ?></td>
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
    var pieEl = document.getElementById('portfolioPie');
    <?php if ($showPie): ?>
    var pieData = <?= json_encode(array_map(function($r) {
        return ['name'=>$r['name'], 'value'=>round($r['amount'],2)];
    }, $holdingRows)) ?>;
    if (pieEl && typeof BCTCharts !== 'undefined') BCTCharts.initPortfolioPie(pieEl, pieData);
    <?php endif; ?>

    var form = document.getElementById('holdingsForm');
    if (!form) return;

    var rows = Array.prototype.slice.call(form.querySelectorAll('tr.holding-row'));
    var dirtyEl = document.getElementById('holdingsDirty');
    var initial = {};
    rows.forEach(function(tr) { initial[tr.querySelector('.qty-input').name] = tr.querySelector('.qty-input').value; });

    function fmtMoney(n) { return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function fmtInt(n) { return n.toLocaleString('en-US'); }
    function rowQty(tr) {
        var v = parseInt(tr.querySelector('.qty-input').value, 10);
        return (isNaN(v) || v < 0) ? 0 : v;
    }

    function renderPie(data, total) {
        if (!pieEl) return;
        var emptyEl = document.getElementById('portfolioEmpty');
        if (total <= 0) {
            pieEl.style.display = 'none';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        pieEl.style.display = '';
        if (emptyEl) emptyEl.hidden = true;
        if (typeof echarts === 'undefined' || typeof BCTCharts === 'undefined') return;
        var inst = echarts.getInstanceByDom(pieEl);
        if (inst) { inst.setOption({ series: [{ data: data }] }); }
        else { BCTCharts.initPortfolioPie(pieEl, data); }
    }

    function recalc() {
        var total = 0, totalPop = 0, dirty = false;
        rows.forEach(function(tr) {
            var price = parseFloat(tr.getAttribute('data-price')) || 0;
            var input = tr.querySelector('.qty-input');
            var qty = rowQty(tr);
            totalPop += qty;
            total += qty * price;
            if (input.value !== initial[input.name]) dirty = true;
        });

        var pieData = [];
        rows.forEach(function(tr) {
            var price = parseFloat(tr.getAttribute('data-price')) || 0;
            var qty = rowQty(tr);
            var amount = qty * price;
            var ratio = total > 0 ? amount / total * 100 : 0;
            tr.querySelector('.row-amount').textContent = '¥' + fmtMoney(amount);
            tr.querySelector('.row-ratio').textContent = ratio.toFixed(2) + '%';
            pieData.push({ name: tr.getAttribute('data-city'), value: Math.round(amount * 100) / 100 });
        });

        document.getElementById('totalAmount').textContent = fmtMoney(total);
        document.getElementById('totalPop').textContent = fmtInt(totalPop);
        var sumAmount = document.getElementById('sumTotalAmount');
        var sumPop = document.getElementById('sumTotalPop');
        if (sumAmount) sumAmount.textContent = '¥' + fmtMoney(total);
        if (sumPop) sumPop.textContent = 'Ⓟ ' + fmtInt(totalPop);
        if (dirtyEl) dirtyEl.hidden = !dirty;
        renderPie(pieData, total);
    }

    form.addEventListener('click', function(e) {
        var btn = e.target.closest('.qty-btn');
        if (!btn) return;
        var input = btn.parentNode.querySelector('.qty-input');
        var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
        var v = (parseInt(input.value, 10) || 0) + step;
        input.value = v < 0 ? 0 : v;
        recalc();
    });

    form.addEventListener('input', function(e) {
        if (e.target.classList.contains('qty-input')) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
            recalc();
        }
    });

    var search = document.getElementById('holdingsSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            rows.forEach(function(tr) {
                var name = (tr.getAttribute('data-city') || '').toLowerCase();
                tr.classList.toggle('is-hidden', q !== '' && name.indexOf(q) === -1);
            });
        });
    }

    var resetBtn = document.getElementById('holdingsReset');
    if (resetBtn) resetBtn.addEventListener('click', function() { window.location.reload(); });
});
</script>

<?php require_once '../includes/footer.php'; ?>
