# 全站 SEO 优化 — 设计文档

## 1. 动态页面 TDK 设计

### 1.1 核心原则

- **Title**：`<对象名> - <站点/分类名> | 58区块城市`（长度 30 字左右，不超过 60 字符）。
- **Description**：从对象描述/简介中截取前 120 字符，去除 HTML 标签，补充站点定位。
- **Keywords**：保留原共享关键词 + 对象名 + 城市名/分类名（可选，国内引擎仍参考但权重较低）。
- **Canonical**：使用当前页面的完整规范 URL（含伪静态路径），避免参数重复收录。
- **Open Graph**：title/description 与 TDK 一致，image 取对象主图，type 为 `website` 或 `product`。

### 1.2 各页面 TDK 模板

#### 商品详情页 `mall/product/detail.php`

```php
$site_config['title']       = htmlspecialchars($productDetail['name']) . ' - 58人气值商城';
$site_config['description'] = mb_substr(strip_tags($productDetail['description'] ?? ''), 0, 120) . ' - 58人气值商城';
$site_config['keywords']    = '58,人气值,BCT,' . $productDetail['name'] . ',区块城市,商城';
$site_config['canonical_url'] = 'https://mall.58.tl/product/' . $productId . '-' . seo_slug($productDetail['name']) . '.html';
$site_config['og_image']    = $mainImageUrl;
$site_config['og_type']     = 'product';
```

当前该页面已经自己写了 `<head>`，应改为：
1. 先设置 `$site_config`；
2. 引入 `mall/includes/header.php`（复用 `shared/header.php`），去掉页面内重复的 `<head>`。

#### 店铺详情页 `mall/shop/view.php`

```php
$site_config['title']       = htmlspecialchars($shopInfo['shop_name']) . ' - 58人气值商城店铺';
$site_config['description'] = mb_substr(strip_tags($shopInfo['shop_description'] ?? ''), 0, 120) . ' - 58人气值商城';
$site_config['keywords']    = '58,人气值,BCT,' . $shopInfo['shop_name'] . ',店铺,商城';
$site_config['canonical_url'] = 'https://mall.58.tl/shop/' . $shopId . '-' . seo_slug($shopInfo['shop_name']) . '.html';
$site_config['og_image']    = $shopLogoUrl;
```

#### 商品列表页 `mall/product/list.php`

```php
$categoryName = $categoryId ? ($categories[$categoryId]['name'] ?? '全部商品') : '全部商品';
$site_config['title']       = $categoryName . ' - 58人气值商城';
$site_config['description'] = '浏览58人气值商城的' . $categoryName . '，支持BCT人气值支付，免费开店。';
$site_config['canonical_url'] = 'https://mall.58.tl/product/list.php' . ($categoryId ? '?category=' . $categoryId : '');
```

#### 城市页 `block/city.php`

```php
$site_config['title']       = htmlspecialchars($cityInfo['name']) . '区块城市 - 58 BlockCity';
$site_config['description'] = '58区块城市' . $cityInfo['name'] . '站，探索本地生活、区块、互访圈、商城等元宇宙同城服务。';
$site_config['canonical_url'] = 'https://www.58.tl/city/' . $cityInfo['pinyin'] . '.html';
```

#### 圈子详情页 `hufang/circles/view.php`

```php
$site_config['title']       = htmlspecialchars($circleInfo['name']) . ' - 58互访圈';
$site_config['description'] = mb_substr(strip_tags($circleInfo['description'] ?? ''), 0, 120) . ' - 58互访圈';
$site_config['canonical_url'] = 'https://v.58.tl/circle/' . $circleId . '-' . seo_slug($circleInfo['name']) . '.html';
```

#### NFT 详情页 `nft/nft/view.php`

```php
$site_config['title']       = htmlspecialchars($nftInfo['name']) . ' - 58 NFT';
$site_config['description'] = mb_substr(strip_tags($nftInfo['description'] ?? ''), 0, 120) . ' - 58 NFT 数字藏品';
$site_config['canonical_url'] = 'https://nft.58.tl/nft/' . $nftId . '-' . seo_slug($nftInfo['name']) . '.html';
$site_config['og_image']    = $nftImageUrl;
```

### 1.3 SEO 辅助函数

新增 `classes/SeoHelper.php`：

```php
class SeoHelper {
    public static function slug($str) {
        $str = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]+/u', '-', $str);
        $str = trim($str, '-');
        $str = preg_replace('/-+/', '-', $str);
        return mb_substr($str, 0, 60);
    }

    public static function excerpt($text, $len = 120) {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return mb_substr($text, 0, $len);
    }

    public static function fullUrl($path) {
        $host = $_SERVER['HTTP_HOST'] ?? 'www.58.tl';
        return 'https://' . $host . '/' . ltrim($path, '/');
    }
}
```

## 2. 自动 Sitemap 生成

### 2.1 方案

把 `sitemap.xml` 替换为 `sitemap.php`，通过 `mod_rewrite` 让 `sitemap.xml` 实际执行 `sitemap.php`。

生成内容：
- 首页/列表页（固定）
- 所有城市页（`cities` 表）
- 所有商品页（`products` 表，状态为上架）
- 所有店铺页（`shops` 表，状态为 active）
- 所有圈子页（`circles` 表，状态为 active）
- 所有 NFT 页（`nfts` 表，状态为可认领/可售）

分页控制：单文件不超过 50,000 URL（本项目暂不需要分片）。

### 2.2 输出示例

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://www.58.tl/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://mall.58.tl/product/123-iphone-15.html</loc>
    <lastmod>2026-06-21</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
  ...
</urlset>
```

## 3. 百度主动推送

### 3.1 配置

新增 `config/seo.php`：

```php
<?php
return [
    'baidu_token' => 'YOUR_BAIDU_TOKEN', // 百度搜索资源平台获取
    'site_domain' => 'www.58.tl',
    'subdomains'  => ['www', 'mall', 'block', 'bct', 'nft', 'v'],
];
```

### 3.2 推送类

`classes/SeoHelper.php` 增加方法：

```php
public static function baiduPush($urls) {
    $config = require __DIR__ . '/../config/seo.php';
    $token = $config['baidu_token'] ?? '';
    if (empty($token) || empty($urls)) return false;

    $api = 'http://data.zz.baidu.com/urls?site=https://www.58.tl&token=' . $token;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => implode("\n", (array)$urls),
        CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
```

### 3.3 触发点

- 商品发布/更新：`mall/shop/products.php` 保存成功后调用 `SeoHelper::baiduPush($canonicalUrl)`。
- 店铺创建/更新：`mall/shop/create.php` / `mall/shop/manage.php`。
- 圈子创建/更新：`hufang/circles/create.php` / `hufang/circles/edit.php`。
- NFT 发布/更新：`nft/nft/create.php` / `nft/nft/sell.php`。

## 4. URL 伪静态与重定向

### 4.1 伪静态规则（Apache）

各子站 `.htaccess` 增加：

```apache
RewriteEngine On
RewriteBase /

# 商品详情
RewriteRule ^product/([0-9]+)-.*\.html$ product/detail.php?id=$1 [L,QSA]
# 店铺详情
RewriteRule ^shop/([0-9]+)-.*\.html$ shop/view.php?id=$1 [L,QSA]
# 城市页
RewriteRule ^city/([a-z]+)\.html$ city.php?pinyin=$1 [L,QSA]
# 圈子详情
RewriteRule ^circle/([0-9]+)-.*\.html$ circles/view.php?id=$1 [L,QSA]
# NFT 详情
RewriteRule ^nft/([0-9]+)-.*\.html$ nft/view.php?id=$1 [L,QSA]
# sitemap
RewriteRule ^sitemap\.xml$ sitemap.php [L]
```

### 4.2 旧 URL 301 到新 URL

在页面入口检测：如果当前访问的是 `detail.php?id=123`，且 URL 不是规范伪静态 URL，则 301 跳转。

```php
if (basename($_SERVER['PHP_SELF']) === 'detail.php' && !empty($_GET['id']) && empty($_GET['seo_redirect'])) {
    $canonical = SeoHelper::productUrl($productId, $productDetail['name']);
    if ($canonical !== SeoHelper::currentUrl()) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $canonical);
        exit;
    }
}
```

### 4.3 404 状态码

在 404 页面开头增加：

```php
http_response_code(404);
```

页面不存在时直接返回 404，不要 302 到 404.php。

## 5. 结构化数据

### 5.1 商品详情页 — Product

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "商品名",
  "image": "主图URL",
  "description": "商品描述",
  "brand": {
    "@type": "Brand",
    "name": "店铺名"
  },
  "offers": {
    "@type": "Offer",
    "price": "100",
    "priceCurrency": "CNY",
    "availability": "https://schema.org/InStock",
    "seller": {
      "@type": "Store",
      "name": "店铺名"
    }
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "2"
  }
}
```

### 5.2 店铺详情页 — Store

```json
{
  "@context": "https://schema.org",
  "@type": "Store",
  "name": "店铺名",
  "image": "Logo URL",
  "description": "店铺描述",
  "url": "规范URL"
}
```

### 5.3 首页 — Organization + WebSite

在 `shared/header.php` 的 WebSite schema 基础上，增加 Organization：

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "58区块城市",
  "url": "https://www.58.tl",
  "logo": "https://58.tl/assets/images/logo.png"
}
```

## 6. 内链与面包屑

- 商品详情页已有面包屑，保留并补充到 JSON-LD `BreadcrumbList`。
- 店铺详情页、NFT 详情页、圈子详情页增加面包屑。
- 列表页增加分页 `rel="next"/"prev"`（可选）。

## 7. robots.txt 优化

在现有 `robots.txt` 基础上增加：

```text
Disallow: /mall/user/
Disallow: /mall/cart/
Disallow: /mall/auth/
Disallow: /block/user/
Disallow: /block/auth/
Disallow: /hufang/user/
Disallow: /hufang/auth/
Disallow: /nft/user/
Disallow: /nft/auth/
Disallow: /admin/
Disallow: /shared/
Disallow: /classes/
Disallow: /config/
Disallow: /includes/
Disallow: /*?*success=
Disallow: /*?*error=
Disallow: /*?*redirect=
```
