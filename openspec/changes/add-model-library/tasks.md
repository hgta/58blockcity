# 模特库功能 — 任务清单

## 阶段 1：数据库 + Model 类

- [ ] 1.1 在 `init/db-init.sql` 新增 `models` 表 + `model_likes` 表 DDL
- [ ] 1.2 在 `init/db-init.sql` 新增 `ALTER TABLE products ADD model_id` 语句
- [ ] 1.3 创建 `classes/Model.php`：实现 getById, getByUserId, create, update, delete, getList, getAll, like, isLiked, getModelProducts, getModelProductImages, getProductCount, refreshCounts, getRanking
- [ ] 1.4 修改 `classes/Product.php`：createProduct/updateProduct 中处理 model_id，关联时调用 Model::refreshCounts

## 阶段 2：后台管理

- [ ] 2.1 创建 `admin/models.php`：模特列表页（分页+搜索+CRUD）
- [ ] 2.2 修改 `shared/admin/admin-menu-config.php`：mall 后台菜单添加"模特管理"
- [ ] 2.3 后台新建/编辑模特表单：昵称★ + 用户搜索选择器 + 性别/年龄/社交/身材字段

## 阶段 3：商品关联

- [ ] 3.1 修改 `mall/shop/products.php`：商品编辑表单增加"关联模特"搜索下拉选择器
- [ ] 3.2 修改 `classes/Product.php`：getProductById SQL JOIN models 取 nickname
- [ ] 3.3 修改 `mall/product/detail.php`：商品元数据区展示模特昵称（可点击跳转）

## 阶段 4：模特详情页

- [ ] 4.1 创建 `mall/model/view.php`：模特基本信息 + 关联商品列表 + 图集
- [ ] 4.2 模特页 SEO 配置（title/description/keywords/canonical/og）
- [ ] 4.3 模特页注入 Person JSON-LD + BreadcrumbList 结构化数据
- [ ] 4.4 模特点赞按钮 + AJAX 交互（like/unlike + 实时计数）

## 阶段 5：排行榜

- [ ] 5.1 修改 `classes/MallRanking.php`：增加 getModelRanking($type) 方法
- [ ] 5.2 修改 `mall/rankings/index.php`：新增"模特排行"tab + 3 个子排行
- [ ] 5.3 模特排行卡片显示：排名 + 头像 + 昵称 + 数值 + 链接

## 阶段 6：SEO + 基础设施

- [ ] 6.1 修改 `classes/SeoHelper.php`：增加 modelUrl() 方法
- [ ] 6.2 修改 `sitemap.php`：增加模特 URL 生成
- [ ] 6.3 修改 `docs/nginx-rewrite.conf`：增加 `/model/{id}-{slug}.html` rewrite
- [ ] 6.4 创建 `mall/model/list.php`：模特列表页（可选，做简单分页）
- [ ] 6.5 模特产品列表页增加 SeoHelper 百度推送

## 阶段 7：验证与提交

- [ ] 7.1 本地测试：创建模特 → 关联商品 → 商品详情页显示 → 点击跳转模特页
- [ ] 7.2 本地测试：模特点赞 → 排行榜数据
- [ ] 7.3 本地测试：SEO（title/description/canonical/JSON-LD）
- [ ] 7.4 代码 lint 检查通过
- [ ] 7.5 提交并推送到远程仓库
