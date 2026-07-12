# 统一区域配置任务清单

## 前置检查

- [x] 全局搜索 `$zones` 变量引用，确认没有其他文件依赖 `block/city.php` 中的 `$zones` 数组
- [x] 全局搜索 `$zone_col_ranges` 引用，确认只有 `block/city.php` 在使用
- [x] 全局搜索 `calculateBlockPrice(` 调用位置，确认只需要改 city.php 内部
- [x] 确认 `config/database.php` 在线上服务器是否有实际内容 — 已添加占位注释

---

## Task 1 — 新建 `config/zones.php` 统一区域配置

**文件**：`config/zones.php`（新建）

**操作**：
- 定义区域配置数组，包含 `block_start`, `block_end`, `col_start`, `col_end`, `base_price`
- Z 区额外包含 `parts` 字段（三段式价格定义）
- 返回 PHP 数组

**验收**：
- 文件可被 `require` 正常加载
- 包含 A-Z 共 9 个区域的完整定义
- 数值与现有 3 处硬编码完全一致

---

## Task 2 — 修改 `classes/Block.php` 使用统一配置

**文件**：`classes/Block.php`

**操作**：
- `determineZoneByBlockId()` 方法：删掉硬编码的 `$zones` 数组（394-407行），改为 `require config/zones.php` 后遍历匹配
- 逻辑等价：`$blockNum` 在 `block_start` 和 `block_end` 之间则返回对应 zone

**验收**：
- `determineZoneByBlockId()` 对 A-Z 区的区块号返回正确区域
- 边界测试：101→A, 1299→A, 1301→B, 9699→H, 9701→Z

---

## Task 3 — 修改 `block/city.php` 清理冗余配置

**文件**：`block/city.php`

**操作**：
- 删除 `$zones` 数组（35-49行）
- 删除 `$zone_col_ranges` 数组（67-71行）
- 改为从 `config/zones.php` 动态生成所需数据结构
- `calculateBlockPrice()` 函数签名移除未使用的 `$zones` 参数
- 所有调用点 `calculateBlockPrice($zone, $bn, $zones)` → `calculateBlockPrice($zone, $bn)`

**验收**：
- 单区模式渲染正常（A-Z 均可切换）
- 全景模式渲染正常
- 每个区块的 `data-price` 属性值正确

---

## Task 4 — 修改 `block/city/map.php` 修复价格公式

**文件**：`block/city/map.php`

**操作**：
- 移除 `$priceFactors` 数组和 `pow(1.314, n)` 价格计算
- 引入 `config/block_prices.php` 使用 `calculateBlockPriceNew()`
- 移除硬编码 `$basePrice = 1000`
- 区域列表改用 `config/zones.php`

**验收**：
- 访问 `map.php?city_id=1&zone=A` 显示正确价格
- A区 0101 显示 1286（不是 1000）
- H区 8501 显示 8698（不是 ~6728）

---

## Task 5 — 更新 `config/block_prices.php` 兜底逻辑

**文件**：`config/block_prices.php`

**操作**：
- 兜底 `$basePrices` 改为引用 `config/zones.php` 的 `base_price`
- 删除硬编码的基准价数组
- Z 区兜底改用 `parts` 的第一段 `base`

**验收**：
- 不存在的区块调用兜底返回正确的区域基准价
- 不再有多处硬编码的基准价

---

## Task 6 — 处理 `config/database.php`

**文件**：`config/database.php`

**操作**：
- 添加注释说明该文件为空占位，实际数据库配置加载路径
- 或：如果确认可删除，则删除

**验收**：
- 文件有明确的注释，不再误导开发者

---

## Task 7 — 全量回归验证

**操作**：
- 用 HTML 价格表数据验证所有页面的价格输出
- A区全部区块用公式验证
- B-Z 区随机抽样 20 个区块用查找表验证

**验收**：
- 所有区块价格与 docs/A.html ~ Z.html 一致
- 无 PHP 语法错误
- 无 undefined variable/function 警告
