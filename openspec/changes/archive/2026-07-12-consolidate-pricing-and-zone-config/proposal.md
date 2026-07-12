# 统一区块价格与区域配置提案

## 背景与问题

上次修复了区块价格计算公式（`94f39a8`），将价格数据对齐到 9 个 HTML 正确价格表。但在探索过程中发现价格体系和区域配置仍存在以下遗留问题：

### 1. `block/city/map.php` 使用旧版价格公式（严重）

**文件**：`block/city/map.php:17-28,88-89`

```php
$priceFactors = [
    'A' => 1.0,
    'B' => 1.314,
    'C' => pow(1.314, 2),
    // ...
];
$basePrice = 1000; // 硬编码 A区价格为 1000
$price = $basePrice * $priceFactor;
```

- 使用 `pow(1.314, n)` 指数递增模型，与正确价格表完全不匹配
- 硬编码基准价 1000，而 A区 0101 的正确价格是 **1286**
- H区 8501：旧公式约 6,728 元，正确价格应 **8,698 元**

map.php 仍能被直接访问（`vote.php` 有 fallback 引用），用户看到的价格将是错误的。

### 2. 区域配置分散在 3 处（维护风险）

| 位置 | 内容 | 行号 |
|------|------|------|
| `classes/Block.php` | `$zones` — start/end 范围 | 398-407 |
| `block/city.php` | `$zones` — start/end/base_price | 35-49 |
| `block/city.php` | `$zone_col_ranges` — 列号范围 | 67-71 |

同一套区域范围信息被定义了 3 次，且格式、字段不完全相同。如果未来需要调整区域边界，必须同时修改 3 处，极易遗漏。

### 3. `block/city.php` 的 `$zones` 已成为死代码

上次修复后，`calculateBlockPrice()` 实际委托给 `config/block_prices.php` 中的查找表，不再使用 `$zones['base_price']`。但 `$zones` 变量仍然定义了 `base_price` 字段，且作为参数传给 `calculateBlockPrice($zone, $block_id, $zones)` 完全不被使用。

### 4. 价格计算有 3 个包装层

```
classes/Block.php::calculateBlockPrice()  → calculateBlockPriceNew()
classes/Block.php::calculateBasePrice()   → calculateBlockPriceNew()
block/city.php::calculateBlockPrice()     → calculateBlockPriceNew()
```

三个函数做的事完全一样，只是参数签名略有不同。存在不必要的抽象层。

### 5. `config/database.php` 为空文件

0 字节的空文件。虽然项目通过其他路径加载数据库配置，但空配置文件容易误导新开发者。

## 目标

1. 消除 `map.php` 中的错误价格呈现
2. 区域配置统一到单一来源，消除重复定义
3. 清理死代码和冗余包装函数
4. 确保所有入口使用一致的正确价格体系

## 范围

### 纳入范围

| 文件 | 操作 |
|------|------|
| `config/zones.php` | **新建** — 统一的区域配置（范围、列映射、基准价） |
| `block/city/map.php` | 切换到新价格查找表 + 切换到统一区域配置 |
| `block/city.php` | 移除冗余 `$zones` 和 `$zone_col_ranges`，改用 `config/zones.php`；简化 `calculateBlockPrice()` |
| `classes/Block.php` | 移除 `determineZoneByBlockId()` 的硬编码配置，改用 `config/zones.php`；简化 `calculateBasePrice()` |
| `config/block_prices.php` | 兜底基准价改为引用 `config/zones.php` |

### 不纳入范围

- 区块数据结构变更
- 前端 UI 改造
- 数据库迁移
- `block/city/map.php` 删除（保留兼容，仅修复价格）

## 成功标准

| 指标 | 当前 | 目标 |
|------|------|------|
| map.php 价格准确性 | 错误（pow 公式） | 与正确价格表完全一致 |
| 区域范围定义 | 3 处重复 | 1 处（`config/zones.php`） |
| `block/city.php` 死代码 | `$zones['base_price']` 有值但不用 | 移除或标记 |
| 价格包装函数 | 3 个几乎相同函数 | 保留必要的，减少冗余 |
| database.php | 空文件 | 明确其用途或删除 |

## 风险与假设

- **假设**：`block/city/map.php` 的改动不影响 `vote.php` 的 fallback 行为（仅改价格，不改 URL 结构）
- **风险**：如果有其他未发现的文件引用了 `$zones` 或旧的价格变量，需搜索确认
- **风险**：删除/重命名函数可能影响未知调用方，需全局搜索引用
