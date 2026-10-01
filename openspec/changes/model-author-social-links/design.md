# 设计文档

## 总体思路

沿用 `add-product-external-links`（商品外链，2026-08-30 已归档）验证过的模式：**一平台一列、空值即未设置、前台遍历非空渲染**。

- 名字列存「该平台上的账号名/演员名」，与现有 `weibo`、`xiaohongshu` 的语义一致
- 链接列存完整 URL，命名 `link_<platform>`，与商品外链一致
- `models` 与 `authors` 两侧结构完全对称，便于共用平台定义与渲染逻辑

## 数据层

### 平台清单与列定义

`models` 表新增 16 列；`authors` 表同样 16 列（结构对称）：

**名字列（7 个，varchar(100) 可空，置于 `xiaohongshu` 之后）：**

| 列名 | 平台 | 品牌色 |
|------|------|--------|
| `douyin` | 抖音 | `#fe2c55` |
| `kuaishou` | 快手 | `#ff4906` |
| `bilibili` | B站 | `#fb7299` |
| `hongguo` | 红果 | `#e6162d` |
| `tencent_video` | 腾讯视频 | `#ff6022` |
| `iqiyi` | 爱奇艺 | `#00be06` |
| `youku` | 优酷 | `#0fb5ff` |

**链接列（9 个，varchar(500) 可空，置于名字列之后）：**

| 列名 | 平台 | 品牌色 |
|------|------|--------|
| `link_weibo` | 微博 | `#e6162d` |
| `link_xiaohongshu` | 小红书 | `#ff2442` |
| `link_douyin` | 抖音 | `#fe2c55` |
| `link_kuaishou` | 快手 | `#ff4906` |
| `link_bilibili` | B站 | `#fb7299` |
| `link_hongguo` | 红果 | `#e6162d` |
| `link_tencent_video` | 腾讯视频 | `#ff6022` |
| `link_iqiyi` | 爱奇艺 | `#00be06` |
| `link_youku` | 优酷 | `#0fb5ff` |

注：微博/小红书的名字列已存在（`weibo` varchar(200)、`xiaohongshu` varchar(200)），只新增链接列。

### 迁移脚本（新建）

`init/migrate-model-author-social-links.sql`：对 `models` 与 `authors` 两表各执行一条 ALTER，ADD 全部 16 列（名字列 7 + 链接列 9）。

同步更新 `init/db-init.sql` 中两表建表语句（新增库直接建全）。

### 品牌色/图标统一约定

为避免 `model/view.php`、`mall/author/view.php`、两个后台编辑页四处重复定义，平台元数据（列名、平台名、品牌色、图标）在每处用 PHP 数组定义一次（与商品外链详情页做法一致；如实施时发现重复严重，可抽到共享 helper，但不在本 change 强制）。

图标可用性说明：Font Awesome 6 有 `fa-weibo`、`fa-qq`、`fa-weixin`；抖音/B站/快手/红果/视频平台无官方品牌图标，用平台首字/emoji/简单色块替代（如商品外链用 📕 表示小红书）。

## 业务层

### classes/Model.php

- `create()` 的 `$optional` 数组追加 16 个字段名
- `update()` 的 `$allowed` 数组追加 16 个字段名
- `getById()` / `getList()` 均为 `SELECT m.*`，新列自动透出，无需改动

### classes/Author.php

同上：`create()` 的 `$optional` 与 `update()` 的 `$allowed` 各追加 16 个字段名。

## 后台编辑页

### mall/admin/models.php

表单在现有「QQ/微信/微博/小红书」网格之后新增「社媒主页」分区（深色卡片，同「模特子站展示」分区风格）：

- **7 个名字输入框**：抖音、快手、B站、红果、腾讯视频、爱奇艺、优酷（各平台上的名字/演员名，maxlength=100）
- **9 个链接输入框**：微博、小红书 + 上述 7 平台（type="url"、placeholder="https://…"、maxlength=500）
- 保存处理（POST `action=save`）：`$data` 数组追加 16 个字段（`trim($_POST[...] ?? '')`）
- 校验：链接非空时必须匹配 `#^https?://#i`，否则报错提示（同商品外链）
- 编辑回填：`$formData` 来自 `getById()`（`SELECT *`），直接输出即可

### mall/admin/authors.php

同上结构（字段名完全一致）。

## 前台详情页

### model/view.php（模特子站）

现有 `.m-socials` 拆分为两层：

1. **联系方式**（保留现状）：QQ、微信纯文本药丸
2. **社媒主页**（新增）：微博、小红书、抖音、快手、B站、红果、腾讯视频、爱奇艺、优酷 9 平台

渲染逻辑（每平台独立判断）：

```
链接非空            → 品牌色跳转按钮：平台名 + ↗，target=_blank rel="nofollow noopener"
链接空 & 名字非空    → 降级纯文本药丸（兼容现状微博/小红书行为）
两者皆空            → 不渲染
```

品牌色按钮风格与页面现有 `.m-socials` 药丸一致（圆角小标签，平台品牌色背景/描边），移动端 flex-wrap 换行。

### mall/author/view.php（作者详情页）

在现有社交药丸行之后增加「社媒主页」按钮行，渲染逻辑与模特页一致。

## 安全与健壮性

| 项 | 处理 |
|----|------|
| 链接格式 | 后台仅接受 `http(s)://` 开头，其余拒绝并提示 |
| XSS | 输出一律 `htmlspecialchars()` |
| 外链安全 | `target="_blank"` + `rel="nofollow noopener"` |
| 超长 | 名字 maxlength=100、链接 maxlength=500，与列长度一致 |
| 历史数据 | 新列可空，旧数据不受影响；未填链接的微博/小红书降级文本展示，与现状一致 |
| 申请链路 | `model/apply.php` 与 `applications` 表不动（信息不经申请通道） |

## 明确不做

- 申请表单不加字段（用户决定）
- 红果等视频平台名字列语义 = 演员名（用户确认），其余平台为其在该平台上的账号名
- 不做平台数据自动同步
