-- 修复店铺 #11 的店主归属
-- 店铺: 拂光小铺 58tl (id=11)
-- 错误店主: viphgta@qq.com
-- 正确店主: ID 368, 拂光小铺, 793550093@qq.com

-- 1. 修正店铺的 user_id
UPDATE shops SET user_id = 368 WHERE id = 11;

-- 2. 确认修改结果
SELECT id, user_id, shop_name, status FROM shops WHERE id = 11;
