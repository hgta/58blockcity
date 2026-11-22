<?php
/**
 * 卖家直接确认收款（unpaid → settled）验证套件（本地可运行，无需 MySQL）
 *
 * A. 服务层：confirmPaid 放开 unpaid 入口 / 商品订单防复活校验
 * B. 契约页：unpaid 卖家确认卡片渲染 / 警示文案 / 二次确认 / 条款对齐
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

/* ========== A. 服务层（classes/Auction.php） ========== */
$a = src('classes/Auction.php');
$pos = strpos($a, 'public function confirmPaid');
$end = strpos($a, '契约作废');
$fn  = $pos !== false && $end !== false ? substr($a, $pos, $end - $pos) : '';
check('方法体提取', $fn !== '');

check('unpaid 入口已放开', strpos($fn, "['unpaid', 'buyer_declared']") !== false);
check('旧的单态判断已移除', strpos($fn, "payment_status'] !== 'buyer_declared'") === false);
check('卖家身份校验保留', strpos($fn, "intval(\$a['seller_id']) !== intval(\$userId)") !== false);
check('物权交割语义注释保留', strpos($a, '落槌只是契约成立，不是交割') !== false);
check('防复活：订单状态校验', strpos($fn, "['pending', 'paid'], true") !== false && strpos($fn, '无法确认收款。请联系买家') !== false);
check('markPaidByContract 保留', strpos($fn, 'markPaidByContract') !== false);
check('settled 更新保留', strpos($fn, "'settled', settled_at = NOW()") !== false);

/* ========== B. 契约页（bid/user/contract.php） ========== */
$p = src('bid/user/contract.php');

check('unpaid 卖家确认卡片存在', strpos($p, '我已线下收到款项，直接确认') !== false);
check('不再依赖买家声明的文案', strpos($p, '无需等待买家操作') !== false);
check('不可撤销警示', strpos($p, '未收到款项请勿确认') !== false && strpos($p, '此操作不可撤销') !== false);
check('confirm_paid 动作复用', substr_count($p, "value=\"confirm_paid\"") === 2);
check('二次确认弹窗', substr_count($p, "onsubmit=\"return confirm(") === 3);

check('条款 4 已对齐', strpos($p, '逾期未声明且卖家未确认收款的契约自动作废') !== false);

echo PHP_EOL . "  结果: $pass 通过, $fail 失败" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
