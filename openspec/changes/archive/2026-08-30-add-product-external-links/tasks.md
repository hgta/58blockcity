# 商品多平台外部售卖渠道 — 任务清单

## Phase 1: 数据库

### 1.1 迁移脚本
- [x] 新增 `init/migrate-product-external-links.sql`
  - `products` 表 ALTER 增加 7 列：`link_xiaohongshu`、`link_taobao`、`link_douyin`、`link_kuaishou`、`link_jd`、`link_pdd`、`link_wechat_shop`
  - 均 `varchar(500) DEFAULT NULL`，`AFTER video_url`
  - 字段带中文 COMMENT

### 1.2 同步建表语句
- [x] `init/db-init.sql` 中 `products` 建表语句追加 7 列（保持与迁移脚本一致）

---

## Phase 2: 业务层

### 2.1 createProduct()
- [x] `classes/Product.php` 的 `createProduct()`：
  - INSERT 字段列表与 VALUES 占位符追加 7 个链接字段
  - 参数从 `$data['link_xxx'] ?? null` 取值

### 2.2 updateProduct()
- [x] `classes/Product.php` 的 `updateProduct()`：
  - `$allowedFields` 白名单追加 7 个链接字段名

---

## Phase 3: 编辑页表单

### 3.1 新增"售卖渠道"分区（表单 UI）
- [x] `mall/shop/products.php`：
  - 在"状态设置" `form-section` 之后新增"售卖渠道"分区
  - 7 行输入框（平台标签 + `type="url"` 输入框 + placeholder）
  - 分区说明文字："填写平台商品链接后，商品详情页将显示对应平台的购买入口；留空则不显示"

### 3.2 值回填
- [x] 编辑模式：输入框 value 回填 `$editProduct['link_xxx']`
- [x] 添加模式：提交失败后回显 `$_POST['link_xxx']`
- [x] 卖同款（copy_from）：预填 `$copyProduct['link_xxx']`（复用 copy 逻辑）

### 3.3 提交处理（add）
- [x] `mall/shop/products.php` add 处理块：
  - 收集 7 个字段并 `trim()`
  - 非空时校验 `#^https?://#i`，不通过则 `$error` 提示
  - 加入 `$productData`（空串转 `null`）

### 3.4 提交处理（edit）
- [x] `mall/shop/products.php` edit 处理块：
  - 同上收集 + 校验
  - 加入 `$updateData`（空串转 `null`）

---

## Phase 4: 详情页展示

### 4.1 平台定义与渲染
- [x] `mall/product/detail.php`：
  - PHP 端定义 7 个平台映射（字段 → 名称/图标/品牌色）
  - 遍历仅收集非空链接的平台
  - 价格区下方、库存行上方插入"更多购买渠道"卡片
  - 每个平台一个按钮：`target="_blank" rel="nofollow noopener"`、`htmlspecialchars()` 输出链接
  - 站内购买表单位置不变（并存）

### 4.2 样式
- [x] 详情页 `<style>` 中新增 `.external-links-card`、`.external-links-grid`、`.platform-btn` 样式
  - 卡片风格与 `info-card` 一致
  - 平台按钮带品牌色标识、外链箭头
  - 移动端适配（按钮两列 → 单列/可换行）

---

## Phase 5: 验证（需部署后手动验证）

### 5.1 添加商品验证
- [ ] 填写部分平台链接（如仅小红书 + 京东），保存后列表正常、编辑页回填正确
- [ ] 留空全部平台链接，保存正常，无报错

### 5.2 详情页验证
- [ ] 商品详情页仅显示已填平台入口
- [ ] 未设置任何链接的商品不显示"更多购买渠道"卡片
- [ ] 点击入口新窗口打开对应外部链接
- [ ] 站内"加入购物车 / 立即购买"仍可用

### 5.3 校验验证
- [ ] 输入 `javascript:alert(1)` / 无协议字符串被拒绝并提示
- [ ] 输入 `https://item.taobao.com/...` 正常保存

### 5.4 卖同款验证
- [ ] "卖同款"新商品自动带上原商品的外部链接

### 5.5 迁移验证
- [ ] 对已有商品执行迁移脚本后，详情页不显示外部渠道卡片（旧数据不受影响）
