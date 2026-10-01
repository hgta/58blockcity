# Tasks: model-author-social-links

## 1. 数据层

- [x] 1.1 新建 `init/migrate-model-author-social-links.sql`：`models` 与 `authors` 各 ALTER ADD 16 列（7 名字 varchar(100) + 9 链接 varchar(500)）
- [x] 1.2 更新 `init/db-init.sql` 中 `models`、`authors` 建表语句，补齐 16 列

## 2. 业务层

- [x] 2.1 `classes/Model.php`：`create()` `$optional` 与 `update()` `$allowed` 追加 16 个字段名
- [x] 2.2 `classes/Author.php`：同上追加 16 个字段名

## 3. 后台编辑页

- [x] 3.1 `mall/admin/models.php`：表单新增「社媒主页」分区（7 名字框 + 9 链接框，链接 maxlength=500）；保存处理收集 16 字段并校验 `#^https?://#i`
- [x] 3.2 `mall/admin/authors.php`：同上

## 4. 前台展示

- [x] 4.1 `model/view.php`：社媒区拆「联系方式（QQ/微信纯文本）」+「社媒主页（9 平台按钮，链接空名字非空降级文本）」；新增对应样式（品牌色、flex-wrap）
- [x] 4.2 `mall/author/view.php`：增加社媒主页按钮行，渲染逻辑与模特页一致

## 5. 验证

- [ ] 5.1 服务器执行迁移脚本，验证旧数据无感（列全空、前台展示不变）
- [ ] 5.2 后台编辑模特/作者：填写/清空名字与链接，验证保存、回填、非法链接拒绝
- [ ] 5.3 前台模特页与作者页：链接非空出按钮可跳转；只填名字降级文本；全空不渲染
- [ ] 5.4 申请链路回归：model.58.tl 提交申请 → 后台申请预填录入照旧
