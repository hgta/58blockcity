# Block 站首页重新设计 — blockcity.vip 风格

## 背景

block.58.tl 首页和主站完全一样（城市字母列表+统计卡片），没有体现"区块可视化市场"的定位。blockcity.vip 使用 table 网格展示区块热力图，这是 block 站应该有的样子。

## 目标

- 首页用 blockcity.vip 风格网格图展示热门城市区块实况
- 独立 header/footer 样式，与 58.tl 主站区分
- 保留 A-Z 城市列表（折叠收纳）
- 保留 city.php 单区详细地图入口

## 范围

- `block/includes/header.php` — 独立样式
- `block/index.php` — blockcity.vip 风格网格首页
- `docs/nginx-rewrite.conf` — 加 block.58.tl server block
