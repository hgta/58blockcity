<?php
/**
 * bid-mall-product-auction 验证套件
 *
 * 本地可运行部分（无需 MySQL）：
 *   A. 纯函数单测：ac_item_cross_url / ac_item_type_label 跨站链接派生
 *   B. 结构断言：Auction / Order / create / view / index / my 各文件的关键实现片段
 *
 * 运行时 e2e（12.1-12.4，需连接数据库后由运维执行）：
 *   - 迁移与 EXPLAIN / 存量对账
 *   - 发起商品拍卖 → 出价 → 落槌 → 订单 + 扣库存 + 通知
 *   - 过期订单取消 + 库存还原
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

/* ========== A. 纯函数单测 ========== */
require_once $root . '/bid/includes/lot_helpers.php';

// ac_item_cross_url
check('cross_url: product', ac_item_cross_url(['item_type' => 'product', 'item_id' => 5, 'block_id' => 0, 'nft_id' => 0]) === 'https://mall.58.tl/product/detail.php?id=5');
check('cross_url: product prefilled item_cross_url 优先', ac_item_cross_url(['item_type' => 'product', 'item_id' => 5, 'item_cross_url' => 'https://mall.58.tl/product/detail.php?id=9']) === 'https://mall.58.tl/product/detail.php?id=9');
check('cross_url: block', ac_item_cross_url(['item_type' => 'block', 'item_id' => 3, 'block_id' => 3]) === 'https://block.58.tl/block/view.php?id=3');
check('cross_url: nft', ac_item_cross_url(['item_type' => 'nft', 'item_id' => 7, 'nft_id' => 7]) === 'https://nft.58.tl/nft/view.php?id=7');
check('cross_url: nft 缺主键回退空串', ac_item_cross_url(['item_type' => 'nft', 'item_id' => 7, 'nft_id' => 0]) === '');
check('cross_url: product 不依赖 block_id/nft_id', ac_item_cross_url(['item_type' => 'product', 'item_id' => 12]) === 'https://mall.58.tl/product/detail.php?id=12');

// ac_item_type_label
check('label: product', ac_item_type_label('product') === '商城商品');
check('label: nft', ac_item_type_label('nft') === 'NFT 头像');
check('label: block', ac_item_type_label('block') === '区块');

/* ========== B. 结构断言 ========== */

// --- 迁移 SQL ---
$sql = src('init/migrate-auction-product.sql');
check('migration: ENUM 扩展语句', strpos($sql, "ENUM(''block'',''nft'',''product'')") !== false);
check('migration: 幂等判存在', strpos($sql, "COLUMN_TYPE LIKE '%''product''%'") !== false);
check('migration: 回滚指引（先 DELETE product 行）', strpos($sql, "DELETE FROM auctions WHERE item_type = 'product'") !== false);

// --- Auction.php ---
$a = src('classes/Auction.php');
check('Auction: item_type 枚举含 product', strpos($a, "['block', 'nft', 'product']") !== false);
check('Auction: checkProductSellable 归属校验', strpos($a, '您不拥有该商品所属店铺') !== false && strpos($a, '店铺当前未营业') !== false);
check('Auction: 商品下架 / 库存区分错误', strpos($a, '该商品当前不可拍') !== false && strpos($a, '该商品当前库存不足') !== false);
check('Auction: isItemListed product 订单互斥', strpos($a, 'FROM order_items oi') !== false && strpos($a, "WHERE oi.product_id = ? AND o.status IN ('pending','paid','shipped')") !== false);
check('Auction: 原子扣库存 WHERE stock >= 1', strpos($a, 'WHERE id = ? AND stock >= 1 AND status = \'active\'') !== false);
check('Auction: 落槌订单 7 天过期', strpos($a, 'DATE_ADD(NOW(), INTERVAL 7 DAY)') !== false);
check('Auction: buyer_note 含 LOT 编号', strpos($a, "'拍卖 ' . \$lotNo . ' 成交'") !== false);
check('Auction: order_items quantity=1', strpos($a, 'VALUES (?, ?, ?, ?, 1, ?, ?, NOW())') !== false);
check('Auction: 得标通知类型 auction_won_product', strpos($a, 'auction_won_product') !== false);
check('Auction: 卖家通知类型 auction_sold_product', strpos($a, 'auction_sold_product') !== false);
check('Auction: 得标通知链 mall 订单详情', strpos($a, 'https://mall.58.tl/user/order_detail.php?id=') !== false);
check('Auction: updateAuction 商品再校验', strpos($a, '商品拍品：编辑时再次校验商品仍可拍') !== false && strpos($a, 'checkProductSellable(intval($a[\'item_id\']), $sellerId)') !== false);
check('Auction: mapProductItem 图集 + 跨站 URL', strpos($a, 'item_images') !== false && strpos($a, 'mallImageUrl') !== false);
check('Auction: 列表筛选含 product', substr_count($a, "['block', 'nft', 'product'], true)") >= 3);

// --- Order.php ---
$o = src('classes/Order.php');
check('Order: createOrder 可自定义过期', strpos($o, 'createOrder($data, $expireMinutes = 30)') !== false && strpos($o, 'INTERVAL ? MINUTE') !== false);
check('Order: expireOverdueOrders 批量扫描', strpos($o, 'public function expireOverdueOrders(') !== false && strpos($o, "expire_at < NOW()") !== false);
check('Order: 过期回滚库存（复用 autoCancelExpiredOrder）', strpos($o, 'autoCancelExpiredOrder(intval($id))') !== false);
check('Order: 拍卖过期通知 auction_expired_product', strpos($o, 'auction_expired_product') !== false);
check('Order: 过期通知链接 mall 订单详情', strpos($o, 'https://mall.58.tl/user/order_detail.php?id=') !== false);
check('Order: 拍卖订单识别（buyer_note 前缀）', strpos($o, "'拍卖 LOT'") !== false);

// --- bid/create.php ---
$c = src('bid/create.php');
check('create: 商品单选按钮', strpos($c, 'value="product"') !== false && strpos($c, 'switchItemType(\'product\')') !== false);
check('create: 商品选择网格 + 互斥查询', strpos($c, 'id="product-select"') !== false && strpos($c, 'NOT EXISTS') !== false);
check('create: 无店铺引导', strpos($c, '您还没有店铺') !== false && strpos($c, 'mall.58.tl/apply/create.php') !== false);
check('create: 无商品引导', strpos($c, '暂无可拍卖商品') !== false && strpos($c, 'mall.58.tl/shop/products.php') !== false);
check('create: switchItemType 切换 product', strpos($c, "document.getElementById('product-select')") !== false);
check('create: 商品选中态 data-type=product', strpos($c, "data-type=\"product\"") !== false);

// --- bid/view.php ---
$v = src('bid/view.php');
check('view: 图集轮播结构', strpos($v, 'ac-stage-gallery') !== false && strpos($v, 'acGalNav') !== false);
check('view: 商品元信息侧栏', strpos($v, '商品信息') !== false && strpos($v, '查看商品详情 →') !== false);
check('view: 商品类型徽章', strpos($v, 'fa-store"></i> 商品') !== false);
check('view: 店铺链接', strpos($v, 'mall.58.tl/shop/view.php?id=') !== false);
check('view: detailUrl 统一由 ac_item_cross_url 派生', strpos($v, '$detailUrl = ac_item_cross_url($a)') !== false);
check('view: 滑动手势 + 键盘切换', strpos($v, 'ArrowLeft') !== false && strpos($v, 'touchstart') !== false);
check('view: 缩略图懒加载', strpos($v, 'loading="lazy"') !== false);

// --- bid/index.php ---
$i = src('bid/index.php');
check('index: 筛选枚举含 product', strpos($i, "['block', 'nft', 'product'], true)") !== false);
check('index: 商品芯片', strpos($i, "ac_q(['type' => 'product', 'page' => 1])") !== false && strpos($i, '>商品</a>') !== false);

// --- bid/my.php ---
$m = src('bid/my.php');
check('my: 得标者订单链接（商品拍品）', strpos($m, '前往订单 →') !== false && strpos($m, 'user/order_detail.php?id=') !== false);
check('my: 反查带缓存避免重复 SQL', strpos($m, 'wonOrderCache') !== false);
check('my: 查不到回退订单中心', strpos($m, 'mall.58.tl/user/orders.php') !== false);

// --- CSS ---
$css = src('bid/assets/css/auction.css');
check('css: 图集组件样式', strpos($css, '.ac-stage-gallery') !== false && strpos($css, '.ac-gal-thumbs') !== false);
check('css: 商品选择网格样式', strpos($css, '.ac-item-opt-prod') !== false);
check('css: 数字 tabular-nums', strpos($css, '.ac-prod-meta') !== false && strpos($css, 'tabular-nums') !== false);
check('css: 移动端适配', strpos($css, '.ac-gal-thumbs img { width: 44px') !== false);

echo PHP_EOL . "=== RESULT: PASS=$pass FAIL=$fail ===" . PHP_EOL;
exit($fail === 0 ? 0 : 1);