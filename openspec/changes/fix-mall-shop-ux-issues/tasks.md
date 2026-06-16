# 修复商城店铺 6 个体验问题 — 任务清单

- [ ] **#1** `mall/shop/manage.php` — uploadShopImage() 加入 GD 缩放（Logo 400px, Banner 1200px）
- [x] **#2** `mall/product/list.php` — 每页 18 件商品（6行×3列），原 12 件（4行×3列）
- [ ] **#3** `mall/shop/view.php` — Logo/Banner src 路径加 `../` 前缀
- [ ] **#4** `mall/shop/products.php` — 独立视频 `$videoError`/`$videoSuccess` 变量 + 前端显示
- [ ] **#5** 全局搜索替换 — `"人气"` → `"BCT"` (list.php, index.php 等前台页面)
- [ ] **#6** `mall/shop/products.php` — 分类下拉改为 `<optgroup>` 分组，复用 `getCategoryTree()`
