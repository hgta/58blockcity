# 修复 Mall 站 4 个问题 — 设计方案

## 问题1：排序按钮保留当前页码

**文件**：`mall/shop/view.php:207-210`

```php
// Before:
<a href="?id=<?= $shopId ?>&page=1&sort=newest">最新</a>
<a href="?id=<?= $shopId ?>&page=1&sort=price_asc">价格↑</a>
<a href="?id=<?= $shopId ?>&page=1&sort=price_desc">价格↓</a>
<a href="?id=<?= $shopId ?>&page=1&sort=sales">销量</a>

// After:
<a href="?id=<?= $shopId ?>&page=<?= $page ?>&sort=newest">最新</a>
<a href="?id=<?= $shopId ?>&page=<?= $page ?>&sort=price_asc">价格↑</a>
<a href="?id=<?= $shopId ?>&page=<?= $page ?>&sort=price_desc">价格↓</a>
<a href="?id=<?= $shopId ?>&page=<?= $page ?>&sort=sales">销量</a>
```

---

## 问题2：模特图集分页

**文件**：`mall/model/view.php:254-268`

方案：为图集增加"加载更多"按钮，首次渲染 12 张，点击后追加 12 张。

```
Before:
  getModelProductImages($modelId, 24)  → 一次渲染全部

After:
  getModelProductImages($modelId, 100, 0) → 全部取出存 JS 数组
  前端首次渲染 12 张 → "加载更多"按钮 → 再追加 12 张
```

修改 `getModelProductImages` 增加 `$limit` 控制或改用前端懒加载：

```php
// classes/Model.php
public function getModelProductImages($modelId, $limit = 100) {
    // 去掉内部 limit，改为不限，前端控制显示数量
}
```

前端逻辑：
```javascript
let galleryPage = 0, galleryPerPage = 12;
function loadMoreGallery() {
    // 渲染下一批图片到 #gallery-grid
}
```

---

## 问题3：商品详情图集灯箱

**文件**：`mall/product/detail.php:1244-1250`

方案：将 `showImageModal(src)` 改造为 `openLightbox(index)`，参考 `model/view.php:312-355`。

```javascript
// 构建图片数组
var productImages = [
    '../uploads/<?= $product['main_image'] ?>',
    <?php foreach ($images as $img): ?>
    '../uploads/<?= $img['image_url'] ?>',
    <?php endforeach; ?>
];

function openLightbox(index) {
    // 弹窗容器
    // 左箭头 onclick="openLightbox(current-1)"
    // 右箭头 onclick="openLightbox(current+1)"
    // 关闭按钮
    // 键盘 ← → Esc
    // 序号显示 "2 / 5"
}
```

HTML 改为传入 index 而非 src：
```html
<!-- Before -->
<img onclick="showImageModal('../uploads/xxx.jpg')">
<!-- After -->
<img onclick="openLightbox(0)">
```

---

## 问题4：模特日常照片

### 4.1 数据库

```sql
ALTER TABLE models ADD COLUMN daily_photos TEXT NULL COMMENT '日常照片JSON数组';
```

### 4.2 Model 类

`classes/Model.php` 的 `update()` 方法加入 `daily_photos`：

```php
$allowed = ['nickname', 'user_id', 'city_id', 'gender', 'age', 'height', 'weight',
    'measurements', 'qq', 'wechat', 'weibo', 'xiaohongshu', 'hobbies', 'zodiac',
    'fans_count', 'status', 'avatar', 'daily_photos'];
```

### 4.3 管理后台

`mall/admin/models.php` 编辑表单增加上传区域：

```html
<div class="form-group">
    <label>日常照片</label>
    <input type="file" name="daily_photos[]" multiple accept="image/*">
    <?php if (!empty($model['daily_photos'])): ?>
        <!-- 已有照片预览 + 删除按钮 -->
    <?php endif; ?>
</div>
```

上传逻辑：多文件保存到 `/uploads/models/{id}/`，路径存为 JSON 数组。

### 4.4 前端展示

`mall/model/view.php` 增加"日常照片"区块：

```html
<?php if (!empty($modelInfo['daily_photos'])): ?>
<div style="margin-bottom:30px;">
    <h3>📷 日常照片</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;">
        <?php 
        $dailyPhotos = json_decode($modelInfo['daily_photos'], true) ?: [];
        foreach ($dailyPhotos as $i => $photo): 
        ?>
        <img src="../<?= htmlspecialchars($photo) ?>" 
             onclick="openLightbox(<?= $i ?>)" 
             style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;cursor:pointer"
             loading="lazy">
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
```
