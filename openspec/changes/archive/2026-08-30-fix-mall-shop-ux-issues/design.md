# 修复商城店铺 6 个体验问题 — 设计文档

## 1. 图片裁剪

```php
// manage.php: uploadShopImage() 改为
function uploadShopImage($file, $subdir, $maxWidth = 400) {
    // ... 类型检查 ...
    $src = imagecreatefromjpeg/png/webp($file['tmp_name']);
    // 等比缩放
    $ratio = min($maxWidth / imagesx($src), 1.0);
    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, ...);
    imagejpeg($dst, $filePath, 85);
    return ['success' => true, 'file_path' => $relativePath];
}
```

Logo 缩放至 400px，Banner 缩放至 1200px 宽。

## 2. 商品列表每行6个

```css
/* list.php */
.products-grid {
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); /* 原 250px */
}
```

## 3. Logo 路径修复

```php
// view.php
<img src="../<?= htmlspecialchars($shopInfo['shop_logo']) ?>">     // 加 ../
<img src="../<?= htmlspecialchars($shopInfo['shop_banner']) ?>">   // 加 ../
```

## 4. 视频上传反馈

```php
// products.php: POST 处理中
$videoError = '';  // 独立错误变量
// ... uploadVideo() 失败时设置 $videoError
// 成功后 $videoSuccess = '视频已上传';

// 前端：视频区域显示独立错误/成功提示
<?php if ($videoError): ?> <div class="video-msg error"><?= $videoError ?></div> <?php endif; ?>
<?php if ($videoSuccess): ?> <div class="video-msg success"><?= $videoSuccess ?></div> <?php endif; ?>
```

## 5. 统一 "BCT" 标签

全局搜索 `价格单位` 显示，统一替换：
- `list.php`: `"人气"` → `"BCT"`
- `index.php`: `"人气"` → `"BCT"`
- `customer/dashboard.php` 中有 `"BCT"` 的保持
- 其他在前台显示价格的页面

## 6. 分类层级

```php
// products.php: 改用 getCategoryTree()
$categoryTree = getCategoryTree($pdo); // 复用 categories.php 的函数

// HTML:
<select name="category_id">
    <option value="">请选择分类</option>
    <?php foreach ($categoryTree as $parent): ?>
        <optgroup label="<?= htmlspecialchars($parent['name']) ?>">
            <?php foreach ($parent['children'] as $child): ?>
                <option value="<?= $child['id'] ?>"><?= htmlspecialchars($child['name']) ?></option>
            <?php endforeach; ?>
        </optgroup>
    <?php endforeach; ?>
</select>
```
