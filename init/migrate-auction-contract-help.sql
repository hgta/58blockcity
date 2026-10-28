-- =====================================================================
-- 58拍卖 — 契约结算帮助文案（change: auction-contract-settlement, task 2.18）
--
-- 落槌 = 契约成立，钱由买卖双方线下自行结算，平台不碰资金。
-- 帮助中心内容为数据库驱动（help_faq），故以数据迁移的方式同步文案。
--
-- 幂等：按 question 判重，重复执行不会产生新行。
-- 依赖：migration-help-center-ai.sql（help_categories / help_faq）
--
--   执行：
--     mysql -u <user> -p <db> < init/migrate-auction-contract-help.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. 落槌后如何付款
INSERT INTO `help_faq` (`category_id`, `question`, `answer`, `source`, `status`, `sort_order`)
SELECT c.id,
       '拍卖落槌后，我该怎么付款？',
       '落槌即视为买卖双方之间<b>成立交易契约</b>，系统会生成一张契约单。<br><br>1. 打开「我的拍卖」，在对应拍品上点击<b>查看契约</b>；<br>2. 契约页会给出<b>成交价与结算币种</b>（人民币 ¥ 或城市人气值 Ⓟ），以及卖家的收款方式指引；<br>3. 按指引与卖家完成转账（人气值契约还会列出卖家接受的城市）；<br>4. 转账后回到契约页点击「<b>我已付款</b>」。<br><br>请注意：平台<b>不代收、不托管、不提供资金担保</b>，也不介入币种折算，转账前务必与卖家核对账号。',
       'manual', 'published', 900
FROM `help_categories` c
WHERE c.slug = 'bid'
  AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `help_faq` WHERE `question` = '拍卖落槌后，我该怎么付款？') AS dup);

-- 2. 卖家何时确认收款
INSERT INTO `help_faq` (`category_id`, `question`, `answer`, `source`, `status`, `sort_order`)
SELECT c.id,
       '我是卖家，什么时候该点「确认收到款项」？',
       '请<b>务必先核实款项确实到账</b>，再点击「确认收到款项」。<br><br>确认后：<br>· 区块 / NFT 头像的所有权<b>立即交割给买家</b>；<br>· 商城商品的契约订单同步转为「已付款」，进入发货流程。<br><br><b>此操作不可撤销</b>——一旦确认，物品就归买家所有，事后无法追回。如果长期收不到款，请不要确认，让契约自然逾期作废即可。',
       'manual', 'published', 901
FROM `help_categories` c
WHERE c.slug = 'bid'
  AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `help_faq` WHERE `question` = '我是卖家，什么时候该点「确认收到款项」？') AS dup);

-- 3. 逾期后果
INSERT INTO `help_faq` (`category_id`, `question`, `answer`, `source`, `status`, `sort_order`)
SELECT c.id,
       '契约一直不付款会怎样？',
       '买家须在落槌后 <b>7 天内</b>声明付款。<br><br>· 逾期未声明的契约<b>自动作废</b>，物品解除占用、可重新上架或再拍；<br>· 作废的契约<b>物品所有权自始未发生转移</b>，仍在卖家名下；<br>· 商城商品契约作废时，落槌时预扣的库存会原样退回。<br><br>如果买家已声明付款但卖家迟迟不确认，该契约不会自动作废，请联系平台客服人工介入。',
       'manual', 'published', 902
FROM `help_categories` c
WHERE c.slug = 'bid'
  AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `help_faq` WHERE `question` = '契约一直不付款会怎样？') AS dup);
