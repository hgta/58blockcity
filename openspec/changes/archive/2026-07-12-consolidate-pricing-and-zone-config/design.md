# 统一区域配置设计方案

## 一、架构决策

### 1.1 统一配置源：`config/zones.php`

**决策**：将分散在 3 处的区域配置提取到 `config/zones.php` 单一文件。

```
Before (分散):
┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│ classes/Block.php   │  │ block/city.php       │  │ block/city.php       │
│ $zones (start/end)  │  │ $zones (start/end/   │  │ $zone_col_ranges     │
│                     │  │   base_price)         │  │ (col_start/end)      │
└─────────┬───────────┘  └─────────┬───────────┘  └─────────┬───────────┘
          │                        │                         │
          └────────────────────────┼─────────────────────────┘
                                   │  各自维护，可能不同步
                                   
After (统一):
┌─────────────────────────────────────────────────────────────┐
│                  config/zones.php                           │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ 'A' => [                                               │  │
│  │   'block_start' => 101,   'block_end' => 1299,       │  │
│  │   'col_start' => 1,        'col_end' => 12,           │  │
│  │   'base_price' => 1286,                                │  │
│  │ ],                                                      │  │
│  │ 'B' => [...],  'C' => [...],  ... 'H' => [...],       │  │
│  │ 'Z' => [                                                │  │
│  │   'block_start' => 9701,  'block_end' => 9999,        │  │
│  │   'col_start' => 97,      'col_end' => 99,            │  │
│  │   'parts' => [                                         │  │
│  │     ['start' => 9701, 'end' => 9999, 'base' => 11429], │  │
│  │     ['start' => 1,    'end' => 99,   'base' => 34101], │  │
│  │     ['start' => 100,  'end' => 9900, 'base' => 34020], │  │
│  │   ],                                                    │  │
│  │ ],                                                      │  │
│  └───────────────────────────────────────────────────────┘  │
└──────────────────────┬──────────────────────────────────────┘
                       │ require_once
          ┌────────────┼────────────┐
          ▼            ▼            ▼
    ┌──────────┐ ┌──────────┐ ┌──────────┐
    │ Block.php│ │ city.php │ │ map.php  │
    └──────────┘ └──────────┘ └──────────┘
```

**字段说明**：

| 字段 | 类型 | 说明 | 使用者 |
|------|------|------|--------|
| `block_start` | int | 区块号起始 | `Block::determineZoneByBlockId()` |
| `block_end` | int | 区块号结束 | `Block::determineZoneByBlockId()` |
| `col_start` | int | 列号起始 | `block/city.php` 地图渲染 |
| `col_end` | int | 列号结束 | `block/city.php` 地图渲染 |
| `base_price` | int | 基准价格（参考/兜底） | 价格查找表兜底 |
| `parts` | array | Z区专有：三段式分区定义 | Z区范围判断 |

### 1.2 map.php 价格修复方案

**决策**：切换到 `config/block_prices.php` 查找表，与主站点使用同一套价格体系。

```
Before:
  $price = 1000 * pow(1.314, $zoneIndex);

After:
  require_once __DIR__ . '/../config/block_prices.php';
  $price = calculateBlockPriceNew($zone, $blockNo);
```

配置加载也切换到 `config/zones.php`：
```php
// Before: 硬编码
$zones = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'Z'];

// After: 从配置读取
$zoneConfig = require __DIR__ . '/../config/zones.php';
$zones = array_keys($zoneConfig);
```

### 1.3 函数简化

**决策**：保留 `calculateBlockPriceNew()` 作为唯一价格计算入口，清理冗余包装层。

```
Before (3个包装层):
┌──────────────────────────────────────────────┐
│ calculateBlockPriceNew($zone, $blockNo)       │  ← 唯一实现
├──────────────────────────────────────────────┤
│ Block::calculateBlockPrice($z, $bn)           │  ← 包装1 (private)
│   → calculateBlockPriceNew($z, $bn)           │
├──────────────────────────────────────────────┤
│ Block::calculateBasePrice($blockId, $zone)    │  ← 包装2 (public)
│   → calculateBlockPriceNew($zone, pad($id))   │
├──────────────────────────────────────────────┤
│ calculateBlockPrice($z, $id, $zones)          │  ← 包装3 (全局)
│   → calculateBlockPriceNew($z, pad($id))      │
└──────────────────────────────────────────────┘

After (简化):
┌──────────────────────────────────────────────┐
│ calculateBlockPriceNew($zone, $blockNo)       │  ← 唯一实现
├──────────────────────────────────────────────┤
│ Block::calculateBlockPrice($zone, $blockNo)   │  ← thin wrapper
│ Block::calculateBasePrice($blockId, $zone)    │  ← 保留，逻辑同上
│ calculateBlockPrice($zone, $block_id)         │  ← 移除 $zones 参数
└──────────────────────────────────────────────┘
```

**具体改动**：

| 位置 | 当前 | 改为 |
|------|------|------|
| `block/city.php:1438` | `function calculateBlockPrice($zone, $block_id, $zones)` | 移除未使用的 `$zones` 参数 |
| `block/city.php:964,1032` | `calculateBlockPrice($zone, $bn, $zones)` | `calculateBlockPrice($zone, $bn)` |
| `classes/Block.php:298` | 保留 private `calculateBlockPrice()` | 不变（已是 thin wrapper） |
| `classes/Block.php:430` | `calculateBasePrice()` | 保持，供外部调用 |

### 1.4 `block/city.php` 死代码清理

**决策**：删除 `$zones` 变量（35-49行）和 `$zone_col_ranges` 变量（67-71行），改用 `config/zones.php`。

```php
// Before:
$zones = ['A' => ['start' => 101, 'end' => 1299, 'base_price' => 1286], ...];
$zone_col_ranges = ['A' => [1, 12], ...];

// After:
$zoneConfig = require __DIR__ . '/../config/zones.php';
$zone_col_ranges = [];
foreach ($zoneConfig as $zone => $cfg) {
    $zone_col_ranges[$zone] = [$cfg['col_start'], $cfg['col_end']];
}
```

### 1.5 `config/database.php`

**决策**：不删除空文件（可能是线上部署的占位符），但添加注释说明其用途。

## 二、文件变更清单

| 文件 | 操作 | 影响 |
|------|------|------|
| `config/zones.php` | **新建** | 单一配置源 |
| `config/block_prices.php` | **修改** | 兜底价引用 `zones.php` |
| `classes/Block.php` | **修改** | `determineZoneByBlockId()` 读取 `zones.php` |
| `block/city.php` | **修改** | 移除重复配置，简化函数签名 |
| `block/city/map.php` | **修改** | 替换价格公式 + 使用统一配置 |
| `config/database.php` | **修改** | 添加注释说明 |

## 三、向后兼容

- `calculateBlockPrice()` 函数签名变更（移除 `$zones` 参数），需全局搜索调用点
- `Block::calculateBlockPrice()` 和 `Block::calculateBasePrice()` 行为不变
- `determineZoneByBlockId()` 返回值不变（仅实现方式改为从配置文件读取）
- map.php 的 URL 结构不变
