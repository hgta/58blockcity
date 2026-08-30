# 设计文档

## 总体思路

"填了才显示，没填不显示"。与 `video_url` 的空判断模式保持一致：

- 7 个平台各一个独立列，空值 = 未设置。
- 编辑页 7 行输入框，店主按需填写。
- 详情页遍历 7 个平台，仅渲染非空链接的入口。

## 数据层

### 字段定义

`products` 表新增 7 列（与 `video_url` 同为 `varchar(500)`，可空）：

| 列名 | 平台 | 详情页按钮文案 |
|------|------|---------------|
| `link_xiaohongshu` | 小红书 | 小红书购买 |
| `link_taobao` | 淘宝 | 淘宝购买 |
| `link_douyin` | 抖音 | 抖音购买 |
| `link_kuaishou` | 快手 | 快手购买 |
| `link_jd` | 京东 | 京东购买 |
| `link_pdd` | 拼多多 | 拼多多购买 |
| `link_wechat_shop` | 微信小店 | 微信小店购买 |

### 迁移脚本（新增）

`init/migrate-product-external-links.sql`：

```sql
ALTER TABLE `products`
  ADD COLUMN `link_xiaohongshu` varchar(500) DEFAULT NULL COMMENT '小红书售卖链接' AFTER `video_url`,
  ADD COLUMN `link_taobao` varchar(500) DEFAULT NULL COMMENT '淘宝售卖链接' AFTER `link_xiaohongshu`,
  ADD COLUMN `link_douyin` varchar(500) DEFAULT NULL COMMENT '抖音售卖链接' AFTER `link_taobao`,
  ADD COLUMN `link_kuaishou` varchar(500) DEFAULT NULL COMMENT '快手售卖链接' AFTER `link_douyin`,
  ADD COLUMN `link_jd` varchar(500) DEFAULT NULL COMMENT '京东售卖链接' AFTER `link_kuaishou`,
  ADD COLUMN `link_pdd` varchar(500) DEFAULT NULL COMMENT '拼多多售卖链接' AFTER `link_jd`,
  ADD COLUMN `link_wechat_shop` varchar(500) DEFAULT NULL COMMENT '微信小店售卖链接' AFTER `link_pdd`;
```

同步更新 `init/db-init.sql` 中 `products` 建表语句，加入上述 7 列（新增库直接建全）。

## 业务层（classes/Product.php）

### createProduct()

INSERT 语句增加 7 个字段，参数从 `$data['link_xxx'] ?? null` 取值：

```
INSERT INTO products
(shop_id, model_id, category_id, name, description, main_image, thumb_image, images, video_url,
 link_xiaohongshu, link_taobao, link_douyin, link_kuaishou, link_jd, link_pdd, link_wechat_shop,
 price_type, price_bct, price_cny, stock, status, is_recommended)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
```

### updateProduct()

`$allowedFields` 白名单追加 7 个字段名即可（现有 foreach 机制自动支持）：

```php
$allowedFields = ['name', 'description', ..., 'video_url',
    'link_xiaohongshu', 'link_taobao', 'link_douyin', 'link_kuaishou',
    'link_jd', 'link_pdd', 'link_wechat_shop',
    'price_type', ...];
```

### getProductById() / 列表查询

均使用 `SELECT p.*`，新字段自动返回，无需改动。

## 编辑页（mall/shop/products.php）

### 表单 UI

在"状态设置"分区之后新增"售卖渠道" `form-section`：

- 标题：`<i class="fas fa-external-link-alt"></i> 售卖渠道`（可选填）
- 说明小字：`填写平台商品链接后，商品详情页将显示对应平台的购买入口；留空则不显示`
- 7 行，每行：平台标签（带平台品牌色小图标）+ URL 输入框
- 输入框：`type="url"`、`placeholder="https://..."`、`maxlength="500"`

### 值回填

| 场景 | 取值来源 |
|------|---------|
| 编辑已有商品 | `$editProduct['link_xxx']` |
| 添加失败后回显 | `$_POST['link_xxx']` |
| 卖同款（copy_from） | 复用 copy 逻辑，读取 `$copyProduct['link_xxx']` 预填 |

### 提交处理（add / edit 两处）

- 收集 7 个字段：`$linkXxx = trim($_POST['link_xxx'] ?? '')`
- 校验：非空时必须匹配 `#^https?://#i`，否则 `$error = 'xx平台链接格式不正确，需以 http:// 或 https:// 开头'`
- 加入 `$productData` / `$updateData`（空字符串统一转 `null`）

## 详情页（mall/product/detail.php）

### 展示位置

价格区（`.product-price`）下方、库存行上方，新增"购买渠道"卡片；站内购买表单（`.purchase-options`）保持在原位置不变 → 实现并存。

### 渲染逻辑

```php
<?php
// 平台定义（PHP 端统一定义一次）
$externalPlatforms = [
    'link_xiaohongshu' => ['name' => '小红书', 'icon' => '...'],
    'link_taobao'      => ['name' => '淘宝',   'icon' => '...'],
    'link_douyin'      => ['name' => '抖音',   'icon' => '...'],
    'link_kuaishou'    => ['name' => '快手',   'icon' => '...'],
    'link_jd'          => ['name' => '京东',   'icon' => '...'],
    'link_pdd'         => ['name' => '拼多多', 'icon' => '...'],
    'link_wechat_shop' => ['name' => '微信小店','icon' => '...'],
];
$setPlatforms = [];
foreach ($externalPlatforms as $field => $p) {
    if (!empty($productDetail[$field])) $setPlatforms[$field] = $p;
}
if (!empty($setPlatforms)): ?>
<div class="external-links-card">
    <div class="info-card-title"><i class="fas fa-store-alt"></i> 更多购买渠道</div>
    <div class="external-links-grid">
        <?php foreach ($setPlatforms as $field => $p): ?>
        <a href="<?= htmlspecialchars($productDetail[$field]) ?>"
           class="platform-btn"
           target="_blank"
           rel="nofollow noopener"
           title="前往<?= htmlspecialchars($p['name']) ?>购买">
            <?= $p['icon'] ?> <?= htmlspecialchars($p['name']) ?>购买
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
```

### 样式

- 卡片：与 `info-card` 风格一致（白底、圆角、浅边框）
- 平台按钮：flex 网格排列，每行 2 个，左侧品牌色圆点 + 平台名，右侧 `↗` 外链箭头
- 平台品牌色（用于图标/描边）：小红书 `#ff2442`、淘宝 `#ff5000`、抖音 `#161823`、快手 `#ff4906`、京东 `#e1251b`、拼多多 `#e02e24`、微信小店 `#07c160`

## 安全与健壮性

| 项 | 处理 |
|----|------|
| 链接格式 | 仅接受 `http(s)://` 开头，其余拒绝并提示 |
| XSS | 输出一律 `htmlspecialchars()` |
| 外链安全 | `target="_blank"` + `rel="nofollow noopener"` |
| 超长 | 输入 `maxlength="500"`，与字段长度一致 |
| 历史数据 | 新列可空，旧商品默认无外部链接，详情页不显示该卡片 |
