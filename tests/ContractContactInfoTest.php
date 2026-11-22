<?php
/**
 * contract-contact-info 验证套件（本地可运行，无需 MySQL）
 *
 * A. 服务层：updateContractContact 当事人判定 / 校验 / 按角色更新 / 通知文案
 * B. 契约页：联系方式卡片渲染 / 复制按钮 / 私信直连 / 死链清除 / 输出转义
 * C. 迁移：4 列幂等新增
 *
 * 设计依据：openspec/changes/contract-contact-info/design.md（D1-D6）
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

// 提取 updateContractContact 方法体（到 voidContract 注释为止）
$pos = strpos($a, 'public function updateContractContact');
$end = strpos($a, '契约作废');
$fn  = $pos !== false && $end !== false ? substr($a, $pos, $end - $pos) : '';
check('方法存在', $fn !== '');

check('当事人判定: 卖家', strpos($fn, "intval(\$a['seller_id']) === \$userId") !== false);
check('当事人判定: 买家', strpos($fn, "intval(\$a['current_bidder_id']) === \$userId") !== false);
check('非当事人拒绝', strpos($fn, '无权操作该契约') !== false);
check('契约未成立拒绝', strpos($fn, '契约尚未成立') !== false && strpos($fn, "payment_status") !== false);

check('电话格式校验', strpos($fn, "^[0-9+\\-\\s]{5,20}\$") !== false);
check('微信长度上限 64', strpos($fn, '> 64') !== false);
check('至少填一项', strpos($fn, '至少填写一项') !== false);

// 更新列按角色固定，不接受前端传入
check('卖家侧列固定', strpos($fn, 'seller_contact_phone = ?, seller_contact_wechat = ?') !== false);
check('买家侧列固定', strpos($fn, 'buyer_contact_phone = ?, buyer_contact_wechat = ?') !== false);
check('无用户输入参与列名', strpos($fn, '{$cols}') !== false && strpos($fn, 'contact_role') === false);

// 空值清除语义（空串存 NULL）
check('空串清除为 NULL', substr_count($fn, "!== '' ?") >= 2);

/* ========== B. 契约页（bid/user/contract.php） ========== */
$p = src('bid/user/contract.php');

check('save_contact POST 处理', strpos($p, "action === 'save_contact'") !== false);
check('调用 updateContractContact', strpos($p, 'updateContractContact') !== false);

// 卡片与展示
check('联系方式卡片存在', strpos($p, 'fa-address-book') !== false);
check('复制按钮含双转义', strpos($p, "htmlspecialchars(addslashes(\$oppoPhone))") !== false
                        && strpos($p, "htmlspecialchars(addslashes(\$oppoWechat))") !== false);
check('对方输出转义', strpos($p, 'htmlspecialchars($oppoPhone)') !== false
                    && strpos($p, 'htmlspecialchars($oppoWechat)') !== false);
check('自己输出转义（表单回显）', strpos($p, 'htmlspecialchars($myPhone)') !== false
                                && strpos($p, 'htmlspecialchars($myWechat)') !== false);
check('对方未填提示', strpos($p, '对方未留联系方式') !== false);

// 卖家强引导（分币种）
check('人民币卖家强引导', strpos($p, '买家需要你的收款方式') !== false);
check('人气值卖家引导', strpos($p, '人气值收款信息') !== false);

// 私信直连与文案修复
check('私信直连 URL', strpos($p, 'www.58.tl/messages/?with=') !== false);
check('文案按视角: 联系买家', strpos($p, '联系买家') !== false);
check('文案按视角: 联系卖家', strpos($p, '联系卖家') !== false);
check('通知中心死链已移除', strpos($p, 'href="/user/messages.php"') === false);

// 表单输入约束
check('电话输入 type=tel maxlength=20', strpos($p, 'type="tel" name="contact_phone" maxlength="20"') !== false);
check('微信输入 maxlength=64', strpos($p, 'name="contact_wechat" maxlength="64"') !== false);

// 落槌通知文案（在 Auction.php 内）
check('落槌通知含联系方式引导', strpos($a, '可在契约页填写联系方式保持沟通') !== false);
check('卖家通知强调收款方式', strpos($a, '请尽快在契约页填写联系方式（收款方式）') !== false);

/* ========== C. 迁移（init/migration-contract-contact.sql） ========== */
$m = src('init/migration-contract-contact.sql');
foreach (['seller_contact_phone', 'seller_contact_wechat', 'buyer_contact_phone', 'buyer_contact_wechat'] as $col) {
    check("迁移含 $col", strpos($m, $col) !== false && strpos($m, 'VARCHAR') !== false);
}
check('迁移幂等（信息模式判断）', substr_count($m, 'information_schema.COLUMNS') === 4);

echo PHP_EOL . "  结果: $pass 通过, $fail 失败" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
