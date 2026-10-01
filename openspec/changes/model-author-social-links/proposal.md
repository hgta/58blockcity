# Proposal: 模特与作者社媒主页（名字 + 9 平台可点击链接）

## Why

模特与作者目前在各大内容平台（微博、小红书等）的社媒信息只能以纯文本展示，访客无法直接跳转到其平台主页。同时模型/作者库正在向短视频与长视频平台扩展（短剧、影视向模特），需要覆盖抖音、快手、B站、红果、腾讯视频、爱奇艺、优酷等平台的名字与主页入口。

## What Changes

- `models` 表：新增 7 个平台名字列（抖音/快手/B站/红果/腾讯视频/爱奇艺/优酷）与 9 个主页链接列（上述 7 个 + 微博、小红书）
- `authors` 表：与 `models` 完全对称的 16 列
- 后台 `mall/admin/models.php` 与 `mall/admin/authors.php`：编辑/添加表单新增「社媒主页」分区（7 名字框 + 9 链接框，链接校验 `#^https?://#i`）
- 前台 `model/view.php`（模特子站详情页）：社媒区拆分为「联系方式（QQ/微信，纯文本）」与「社媒主页（品牌色跳转按钮）」两层；填名字未填链接时降级为纯文本
- 前台 `mall/author/view.php`（作者详情页）：同样增加社媒主页按钮行

## Out of Scope

- 申请表单（`model/apply.php`）不加新字段；`applications` 表与申请预填链路不动
- 站内商品、短剧等其他实体的外链（已有 `add-product-external-links` 覆盖商品）
- 平台数据自动抓取/同步（纯手工维护）

## Patterns From

沿用已归档 change `add-product-external-links`（2026-08-30）的成熟模式：独立列 + `link_<platform>` 命名 + 白名单 foreach + 空值不渲染 + `rel="nofollow noopener"`。

## Impact

- 受影响文件：`init/db-init.sql`、新建 `init/migrate-model-author-social-links.sql`、`classes/Model.php`、`classes/Author.php`、`mall/admin/models.php`、`mall/admin/authors.php`、`model/view.php`、`mall/author/view.php`
- 兼容性：全部新列可空，旧数据不受影响，前台未填链接时展示行为与现状一致
