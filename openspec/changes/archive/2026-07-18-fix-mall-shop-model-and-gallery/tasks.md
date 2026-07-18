# Mall 站 4 项修复任务清单

## Task 1 — 排序按钮保留当前页码

**文件**：`mall/shop/view.php`

**操作**：
- 第 207-210 行，4 个排序按钮的 `page=1` 改为 `page=<?= $page ?>`

**验收**：在 page=2 切换排序，不跳回首页

---

## Task 2 — 模特图集懒加载

**文件**：`mall/model/view.php`, `classes/Model.php`

**操作**：
- `getModelProductImages()` 增大 limit 或取消 limit，返回全部图片 URL
- 前端：首次渲染 12 张，加"加载更多"按钮，点击追加 12 张
- 已设置 `loading="lazy"` 保持

**验收**：模特页首次打开快（仅 12 张），点击加载更多可继续浏览

---

## Task 3 — 商品详情图集灯箱

**文件**：`mall/product/detail.php`

**操作**：
- 页面顶部构建 `productImages` JS 数组（主图 + 所有副图）
- 删除旧 `showImageModal(src)` 函数
- 新增 `openLightbox(index)` 函数：
  - 显示当前图片 + 左箭头（前一页）+ 右箭头（后一页）
  - 关闭按钮（右上角 X）
  - 键盘事件：← → Esc
  - 序号提示（"3 / 7"）
- 所有图集的 `<img onclick>` 改为传入索引

**验收**：点击图集中任意图片，可左右切换，关闭正常

---

## Task 4 — 模特日常照片（DB + 后台 + 前端）

**文件**：`mall/admin/models.php`, `classes/Model.php`, `mall/model/view.php`

**操作**：
- **DB**：执行 `ALTER TABLE models ADD COLUMN daily_photos TEXT NULL`
- **Model 类**：`update()` 的 `$allowed` 加入 `daily_photos`
- **管理后台**：编辑表单增加多图上传 + 已有照片预览
- **前端**：详情页增加"日常照片"网格展示区块

**验收**：
- 后台可上传多张日常照片
- 前端详情页可见日常照片区块
- 点击照片可灯箱查看（复用问题3灯箱逻辑）
