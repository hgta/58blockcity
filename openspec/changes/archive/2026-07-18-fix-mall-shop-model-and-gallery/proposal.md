# 修复 Mall 站 4 个问题

## 背景与问题

### 问题1：店铺商品列表分页跳转异常

`mall/shop/view.php` 排序按钮硬编码 `page=1`，用户在 `page=2` 切换排序后跳回首页。

**文件**：`mall/shop/view.php:207-210`

```php
<a href="?id=<?= $shopId ?>&page=1&sort=newest">最新</a>   <!-- 永远是第1页 -->
```

### 问题2：模特页图集加载慢

`mall/model/view.php` 作品图集一次性渲染所有关联商品图片，无分页。虽然关联商品列表有分页，但图集 `getModelProductImages($modelId, 24)` 全部加载。

**文件**：`mall/model/view.php:254-268`, `classes/Model.php:239-264`

### 问题3：商品详情图集无前后切换

`mall/product/detail.php:1244-1250` 的 `showImageModal(src)` 只弹单图，没有上一张/下一张。

**对比**：`mall/model/view.php:312-355` 已有完整的灯箱实现（箭头 + 键盘事件 + 序号），可直接复用。

### 问题4：模特日常照片缺失

整个流程缺失：
- 后台 `mall/admin/models.php` 编辑页无照片上传
- 数据库 `models` 表无 daily_photos 字段
- 前端 `mall/model/view.php` 无展示区域

## 目标

| # | 问题 | 目标 |
|---|------|------|
| 1 | 排序后页码丢失 | 排序按钮保留当前 page 参数 |
| 2 | 图集无分页，加载慢 | 图集实现分页或加载更多 |
| 3 | 图集无上下张 | 添加灯箱箭头 + 键盘导航 |
| 4 | 模特无日常照片 | 后台可上传 + 前端可展示 |

## 范围

### 纳入范围

| 文件 | 操作 |
|------|------|
| `mall/shop/view.php` | 排序按钮 `page=1` → `page=<?= $page ?>` |
| `mall/model/view.php` | 图集分页 + 日常照片展示区块 |
| `mall/product/detail.php` | 灯箱增加前后切换（复用 model 灯箱逻辑） |
| `mall/admin/models.php` | 编辑表单增加日常照片上传 |
| `classes/Model.php` | `update()` 增加 daily_photos 字段 |

### 不纳入范围

- 商品列表本身的分页逻辑（工作正常）
- 其他子站的图集功能

## 风险

- 问题4 需修改数据库表结构，需执行迁移 SQL
- 灯箱 JS 代码需对 IE 做兼容处理（arrow function → function）
