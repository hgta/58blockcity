# 商品多平台外部售卖渠道

## 问题陈述

当前商城（`mall` 子站）的商品只有站内购买（人气值/人民币订单）这一条成交路径。模特/店主希望将商品同步铺到小红书、淘宝、抖音、快手、京东、拼多多、微信小店等外部平台，并在商品详情页提供对应入口，实现"站内展示、站外成交"的多平台导购。

现状痛点：

1. `products` 表没有任何外部售卖链接字段（仅有 `video_url` 一个外部 URL 概念）。
2. 商品详情页只有"加入购物车 / 立即购买"站内购买区，无法导流到外部平台。
3. 之前的小红书功能尝试已回滚（录入的是昵称而非链接，无法拼出有效 URL）。

## 目标

为 `mall` 子站商品增加**多平台外部售卖链接**能力：

- 店主在商品编辑页可为商品设置最多 7 个平台（小红书、淘宝、抖音、快手、京东、拼多多、微信小店）的售卖地址链接。
- 商品详情页**根据是否设置了对应链接**，显示对应平台的购买入口；点击后新窗口打开外部商城对应商品的售卖地址。
- 站内购买（加入购物车 / 立即购买）**与外部平台入口并存**，不互斥。

## 范围

### In Scope

| 模块 | 内容 |
|------|------|
| 数据库 | `products` 表新增 7 个外部链接字段；提供迁移脚本 |
| 业务层 | `classes/Product.php` 的 create / update 支持读写 7 个链接字段 |
| 编辑页 | `mall/shop/products.php` 新增"售卖渠道"表单分区（添加、编辑、卖同款均可） |
| 详情页 | `mall/product/detail.php` 在购买区展示已设置平台入口，`target="_blank" rel="nofollow noopener"` |

### Out of Scope

- 其它子站（`bct` / `block` / `nft` / `hufang` 等）的产品体系不做外部链接。
- 站内购买流程不做任何改动（保持并存）。
- 不做外部平台链接的真实性校验（仅校验 URL 格式与协议头）。
- 不做平台链接的点击统计/转换追踪。

## 影响范围

- **修改文件**：`classes/Product.php`、`mall/shop/products.php`、`mall/product/detail.php`
- **新增文件**：`init/migrate-product-external-links.sql`
- **数据库变更**：`products` 表新增 7 个 `varchar(500)` 列（可空）

## 成功标准

1. 店主在添加/编辑商品时可填写 7 个平台的售卖链接，保存后回显正确。
2. "卖同款"复制商品时，外部链接一并复制。
3. 商品详情页仅渲染已填写链接的平台入口；未填的平台不显示。
4. 平台入口以新窗口打开（`target="_blank"`），并带 `rel="nofollow noopener"`。
5. 站内"加入购物车 / 立即购买"与外部平台入口同时可用。
6. 非法链接（非 `http(s)://` 开头）保存时被拒绝并提示。

## 参考

- `classes/Product.php` — `createProduct()`（INSERT 字段列表）、`updateProduct()`（`$allowedFields` 白名单）
- `mall/shop/products.php` — 添加/编辑商品表单（`form-section` 结构）、"卖同款"逻辑
- `mall/product/detail.php` — 商品详情购买区（价格区下方、库存/购买表单位置）
- `init/db-init.sql` — `products` 表结构（`video_url` 字段风格）
