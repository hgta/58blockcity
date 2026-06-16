# 修复商城店铺 6 个体验问题

## 问题

1. **图片无裁剪**：店铺 Logo/Banner 上传没有缩放，大图导致页面加载慢
2. **商品列表每行太少**：`list.php` 每行只显示 4 个商品（`minmax(250px)`），期望 6 个
3. **Logo 不显示**：`view.php` 中店铺 Logo 路径缺少 `../` 前缀
4. **视频上传无反馈**：上传成功/失败状态不明确，错误被通用 `$error` 吞掉
5. **BCT/人气标签不统一**：商品价格有的显示"人气"、有的显示"BCT"
6. **分类层级不区分**：添加商品时大分类+小分类并列显示，无法区分

## 目标

所有 6 个问题在 mall 子站内修复。

## 范围

| # | 文件 | 改动 |
|---|------|------|
| 1 | `mall/shop/manage.php` | `uploadShopImage()` 加入 GD 缩放 |
| 2 | `mall/product/list.php` | CSS `minmax(250px)` → `minmax(180px)` |
| 3 | `mall/shop/view.php` | Logo/Banner 路径加 `../` |
| 4 | `mall/shop/products.php` | 独立 `$videoError` 变量 + 前端提示 |
| 5 | `mall/product/list.php`, `mall/index.php` 等 | "人气" → "BCT" |
| 6 | `mall/shop/products.php` | 分类下拉改为 `<optgroup>` 分组 |
