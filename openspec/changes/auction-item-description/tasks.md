## 1. 数据库迁移

- [x] 1.1 编写 `init/migrate-auction-description.sql`：`ALTER TABLE auctions ADD COLUMN description MEDIUMTEXT NULL COMMENT '拍品描述（Markdown，卖家专写）'`，判存在再 ALTER，可重复执行；验证：连续执行两次不报错；查看 `information_schema.COLUMNS` 确认列已加。
- [x] 1.2 编写 `init/migrate-auction-description.sql` 末尾的"已有数据无影响"注释（NULL 默认值无需回填）；验证：现有拍卖记录 description 为 NULL。

## 2. Markdown 渲染基建

- [x] 2.1 引入 Parsedown：从官方仓库（erusev/parsedown）下载 `Parsedown.php` 单文件（v1.7.4），置于 `vendor/erusev/parsedown/Parsedown.php`；`classes/MarkdownSafe.php` 顶部 `require_once`；验证：能被项目自动加载
- [x] 2.2 新建 `classes/MarkdownSafe.php`，封装 Parsedown safemode，提供静态方法 `render(string $markdown): string`；启用 safemode；验证：传入 `<script>alert(1)</script>` 后输出为空或被转义
- [x] 2.3 在 `MarkdownSafe::render()` 内追加 scheme 白名单过滤：`<a href>` 仅允许 `http/https/mailto`；`<img src>` 仅允许 `http/https`；验证：`[click](javascript:alert(1))` 输出不含 javascript: 协议
- [x] 2.4 在 `MarkdownSafe::render()` 内追加 host 白名单（默认含 `58.tl` 全域，可被 `config/markdown_hosts.php` 覆盖）：`<img src>` 与 `<a href>` host 不在白名单则降级为文本或去除链接；验证：`<img src="https://evil.com/x.png">` 输出不含该 img
- [x] 2.5 兜底过滤 `on*=` 事件属性：解析结果再次过一遍 HTML 属性白名单正则；验证：`<img src="x" onerror="alert(1)">` 经 Parsedown safemode 已被去除，兜底层确认无残留
- [x] 2.6 单元用例：在 `tests/MarkdownSafeTest.php` 写若干用例（XSS payload 全部输出不含原始 payload、合法 markdown 正常渲染、白名单 host 通过）；验证：17/17 PASS

## 3. Auction 类接入

- [x] 3.1 `Auction::createAuction()` 入参新增 `description` 字段；长度校验（`mb_strlen > 5000` 拒绝）；写入 SQL；验证：传 5001 字符拒绝，传 5000 字符成功，传 NULL 成功
- [x] 3.2 `Auction::updateAuction()` 同样接受 `description` 字段；同一 5000 字符上限；写入 SQL UPDATE；验证：active 状态拍品编辑 description 被拒（既有规则），pending 状态可改
- [x] 3.3 `Auction::getAuctionById()` 与 `attachItemInfo()` 透传 `description` 字段（不解析，只回原文）；验证：详情页 `$a['description']` 可读（`SELECT a.*` 自动含新增列）
- [x] 3.4 数据库迁移回滚验证：迁移 SQL 末尾含 `ALTER TABLE auctions DROP COLUMN description;` 注释；运维在测试环境演练通过

## 4. 创建/编辑表单

- [x] 4.1 `bid/create.php` 表单新增「拍品描述」区块：textarea（id=`description`、name=`description`、rows=8、`maxlength=5000`）+ 字数统计 span + 5000 字符上限提示；编辑模式预填既有 markdown；验证：发布/编辑流程 UI 一致
- [x] 4.2 添加 Markdown 工具栏（粗体 / 斜体 / 链接 / 代码 / 列表）：点击按钮向 textarea 插入语法片段并保持光标位置；仅前端 JS，无新依赖；验证：五种按钮功能正常
- [x] 4.3 字数实时统计：JS 监听 input 事件，>5000 时红色警示并禁用发布按钮；验证：超过 5000 字符后无法提交
- [x] 4.4 服务端兜底：即便前端绕过，服务端再次校验 `mb_strlen($description) > 5000` 并返回错误；验证：Auction::createAuction / updateAuction 已加 `mb_strlen > 5000` 拒绝逻辑

## 5. 详情页渲染

- [x] 5.1 `bid/includes/lot_helpers.php` 新增 `ac_render_seller_description($auction)`：description 为空则输出空串，否则调 `MarkdownSafe::render()` 并包裹 `<div class="ac-seller-desc">…</div>`；验证：空拍品 / 有描述拍品两种情况均符合预期
- [x] 5.2 `bid/view.php` 在「拍品信息」栏目下方新增「卖家描述」一栏，仅在 `ac_render_seller_description()` 非空时渲染；视觉沿用既有 `.ac-section` 样式
- [x] 5.3 移动端响应式：`bid/assets/css/auction.css` 新增 `.ac-seller-desc` 样式（图片 `max-width:100%`、字号层级、引用/代码块/列表/表格/水平线样式）；@media (max-width: 640px) 隐藏 md-hint；验证：移动端浏览器无横向滚动
- [x] 5.4 安全回归：`tests/E2E_SellerDescription.php` 跑 12 个用例覆盖 `<script>` / `javascript:` / `evil.com` / `onerror` / `<iframe` 全部应被过滤；合法 markdown（h1/table/code/target=_blank/rel/strong）应正常渲染；12/12 PASS

## 6. 编辑与可读性

- [x] 6.1 `bid/my.php`「卖家看板」中，pending 状态拍品新增「编辑描述/写描述」快捷入口（链接到 `create.php?edit=X#description`）；`create.php` JS 检测 hash `#description` 自动滚动+聚焦 textarea；验证：跳转后 textarea 自动获焦
- [x] 6.2 详情页「卖家描述」区右侧增加"举报"链接（占位）：`<a data-stub="report" href="#" onclick="return false;">举报</a>`；本 change 不实装举报功能，由后续 change 接入

## 7. 文档与依赖

- [x] 7.1 `README.md` 末尾新增「拍品描述（Markdown）撰写指南」：语法速查 + 安全约束 + 默认白名单 + 编辑窗口 + 渲染路径 + 测试方式
- [x] 7.2 在 `config/markdown_hosts.php`（新建）写入默认白名单：`58.tl` 全域 + `github.com` / `githubusercontent.com` / `wikipedia.org` / `wikimedia.org`；验证：新加 host 修改此处即可生效

## 8. 验证与上线

- [x] 8.1 跑完整 e2e：`tests/E2E_SellerDescription.php` 验证 markdown 源 → 详情页 HTML 的完整转换；XSS 全部过滤，合法元素全部正确渲染；12/12 PASS
- [x] 8.2 性能抽查：`tests/Perf_MarkdownSafe.php` 单次渲染 1.95 ms（2KB 中等长度描述 × 1000 轮），远低于 5 ms 阈值
- [x] 8.3 备份与回滚：`init/migrate-auction-description.sql` 末尾注释了回滚命令 `ALTER TABLE \`auctions\` DROP COLUMN \`description\``；运维在测试环境按"备份表 → 执行迁移 → 验证 schema → 演练回滚"流程执行