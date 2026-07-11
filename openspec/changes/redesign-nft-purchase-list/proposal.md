# NFT 求购页面重设计

## 问题
- 强制登录拦截，未登录看不到任何求购信息
- 没有公开的求购数据（每个 NFT 有多少人求购）
- 纯 Bootstrap 样式，与 `claim_list.php` 风格不统一

## 目标
1. 未登录可浏览全站求购信息
2. 显示每个 NFT 的求购人数 + 最高出价
3. 三态按钮：未登录→登录后求购 / 已登录未求购→我要求购 / 已登录已求购→修改求购
4. 简洁美观的视觉设计

## 改动
- `nft/nft/purchase_list.php` — 重写布局和逻辑
- `classes/PurchaseRequest.php` — 新增 getNftPurchaseCounts()
