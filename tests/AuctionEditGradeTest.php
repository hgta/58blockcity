<?php
/**
 * active-auction-editable-fields 验证套件（本地可运行，无需 MySQL）
 *
 * A. 结构断言：updateAuction 状态分叉、active 白名单 UPDATE 列清单、方向校验
 * B. 表单状态感知：create.php 锁定渲染 / 重定向 / hidden 补值
 * C. 编辑入口：my.php 按钮覆盖 pending + active
 *
 * 设计依据：openspec/changes/active-auction-editable-fields/design.md（D1 状态分叉、D2 方向校验）
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$pass = 0; $fail = 0;

function check($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $name" . PHP_EOL; }
    else       { $fail++; echo "  FAIL  $name" . PHP_EOL; }
}
function src($path) {
    global $root;
    return file_get_contents($root . '/' . $path);
}

/* ========== A. updateAuction 状态分叉（classes/Auction.php） ========== */
$a = src('classes/Auction.php');

// 提取 updateAuction 方法体
$pos = strpos($a, 'public function updateAuction');
$end = strpos($a, '/* ========== 关注 / 收藏 ==========');
$fn  = $pos !== false && $end !== false ? substr($a, $pos, $end - $pos) : '';

check('分叉: active 白名单分支存在', strpos($fn, "if (\$status === 'active')") !== false);
check('分叉: pending 分支保留', strpos($fn, '/* ---------------- pending：全字段（现状行为保留，D1） ---------------- */') !== false);
check('分叉: sold/ended/canceled 明确拒绝', strpos($fn, "['sold', 'ended', 'canceled']") !== false && strpos($fn, '拍卖已结束，不能再编辑') !== false);

// 提取 updateAuction 内所有 UPDATE 的目标列（排除 updated_at = NOW()）
preg_match_all('/UPDATE auctions\s+SET\s+(.+?)WHERE id = \?/s', $fn, $m);
$colGroups = [];
foreach ($m[1] as $setClause) {
    $cols = [];
    foreach (explode(',', preg_replace('/updated_at\s*=\s*NOW\(\)/i', '', $setClause)) as $pair) {
        $col = trim(explode('=', $pair)[0]);
        if ($col !== '') $cols[] = $col;
    }
    if ($cols) $colGroups[] = $cols;
}

$allowedCols = ['description', 'accept_cities', 'end_time', 'reserve_price'];
$single = []; $multi = [];
foreach ($colGroups as $cols) {
    if (count($cols) === 1) $single[] = $cols[0];
    else $multi[] = $cols;
}
check('active 分支存在单列 UPDATE', count($single) >= 3);

// 逐条核对 active 单列 UPDATE 的目标列：绝不出现锁定列
foreach ($single as $col) {
    check("active UPDATE 列合法: $col", in_array($col, $allowedCols, true));
}
// pending 全字段 UPDATE 必须保留 current_price=起拍价重置语义（现值行为）
check('pending 全字段 UPDATE 保留', count($multi) === 1 && count($multi[0]) === 10);
$pendingCols = $multi[0] ?? [];
check('pending UPDATE 仍重置 current_price 与 status', in_array('current_price', $pendingCols, true) && in_array('status', $pendingCols, true));

// 方向校验（D2）：以库中现值为基准
check('方向: 落槌时间只延不缩', strpos($fn, '竞拍中的拍卖只能延长落槌时间') !== false);
check('方向: 底价只降不升', strpos($fn, '竞拍中的拍卖只能下调底价') !== false);
check('方向: 城市只增不减', strpos($fn, '竞拍中的拍卖不能移除已开放的城市') !== false);
check('方向: 底价通用校验（不低于起拍价）保留', strpos($fn, '底价不能低于起拍价') !== false);
check('方向: 城市空集合=不限城市放行', strpos($fn, '旧值为空 = 不限城市') !== false);
check('描述 5000 字上限保留', strpos($fn, '拍品描述不超过 5000 字') !== false);

// active 分支返回结构与 pending 一致
check('active 分支返回 ok 保存文案', strpos($fn, "'msg' => '修改已保存'") !== false);

/* ========== B. create.php 表单状态感知 ========== */
$c = src('bid/create.php');
check('入口: 已结束拍卖重定向 my.php', strpos($c, "header('Location: my.php');") !== false);
check('入口: active 锁定渲染标记', strpos($c, '$isEditActive = ($editStatus === \'active\')') !== false);
check('渲染: 提示文案列明白名单', strpos($c, '竞拍中的拍卖仅可修改') !== false);
check('渲染: 落槌时间只能延长提示', strpos($c, '竞拍中只能延长') !== false);
check('渲染: 底价只能下调提示', strpos($c, '竞拍中只能下调') !== false);
check('渲染: 锁定字段带「竞拍中不可修改」说明', substr_count($c, '竞拍中不可修改') >= 4);
check('渲染: end_time min 限现值（防选过去时间）', strpos($c, 'min="' . htmlspecialchars($fv['end_time'] ?? '') . '"') !== false || preg_match('/min=".*\$fv\[.end_time.\]/', $c) === 1 || strpos($c, "\$isEditActive ? 'min=\"'") !== false);
check('渲染: 已开放城市 disabled + hidden 补值', strpos($c, "name=\"accept_cities[]\" value=\"<?= \$c['id'] ?>\"") !== false && strpos($c, '竞拍中不能取消已开放的城市') !== false);
check('渲染: 锁定字段 hidden 补值防提交丢失', substr_count($c, 'type="hidden" name="start_price"') === 1 && substr_count($c, 'type="hidden" name="bid_increment"') === 1 && substr_count($c, 'type="hidden" name="currency"') === 1 && substr_count($c, 'type="hidden" name="start_time"') === 1);

/* ========== C. my.php 编辑入口 ========== */
$m = src('bid/my.php');
check('入口: pending 与 active 都显示编辑按钮', strpos($m, "if (\$a['status'] === 'pending' || \$a['status'] === 'active')") !== false);
check('入口: active 按钮带白名单 title 提示', strpos($m, '竞拍中：仅可改描述、接受城市、落槌时间（延长）、底价（下调）') !== false);
check('入口: 统一「编辑拍卖」文案', strpos($m, '编辑拍卖') !== false);

/* ========== 汇总 ========== */
echo PHP_EOL . "  结果: $pass 通过, $fail 失败" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
