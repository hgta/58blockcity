## Context

动机见 `proposal.md - Why`。设计前已确认的现状约束：

- `bid/` 是纯 PHP 页面体系，无构建链、无常驻进程，CSS/JS 直接通过 `<link>`/`<script>` 引入。
- `auctions.item_type` 当前为 `ENUM('block','nft')`，落槌后由 `Auction::transferOwnership()` 按类型分发（区块→UPDATE blocks.owner_id；NFT→UPDATE nft_city_user.user_id + 写 nft_transactions）。
- `Auction::createAuction()` 入参 `$data` 当前包含 `start_price / reserve_price / bid_increment / start_time / end_time / currency / accept_cities`，新增 `description` 不破坏现有签名。
- `Auction::updateAuction()` 已有「物品不可更换」与「pending 才可编辑」的校验；description 编辑并入这条路径，**不**单独开 edit 窗口。
- 平台已有通知基建 `Notification::sendSystemNotify()` 与富文本先例（BCT 交易所描述），但**未引入过 Markdown 库**——本次需要新依赖。
- 项目使用 PDO + MySQL utf8mb4；`MEDIUMTEXT` 单列上限 16MB，对描述绰绰有余。

## Goals / Non-Goals

**Goals:**

- 让三种 item_type 的卖家都能写一段富文本描述；范围一致，体验一致。
- 安全第一：服务端解析+过滤 XSS，绝不信任客户端。
- 5000 字符上限防止滥用；空描述合法（视为「无描述」）。
- 编辑窗口与现有 updateAuction 一致：仅 `pending` 状态可改。
- 不动 `placeBid` / `settle` / 通知 / 跨站链接。

**Non-Goals:**

- 不做实时协同编辑、不做 @提及、不做版本历史。
- 不做评论、不做"卖家回复"。
- 不做图片上传（描述中仅支持已存在的外链 URL，且需通过 scheme 白名单）。
- 不引入前端 marked.js + DOMPurify 的双层渲染方案（已选 Parsedown safemode 一站式服务端渲染）。
- 不改商品图片库——Markdown 中的图片 src 仍走白名单 URL 验证。

## Decisions

### 1. 解析与过滤方案：Parsedown safemode + scheme/url 白名单

- 引入 Parsedown（`vendor/erusev/parsedown/Parsedown.php` + `Parsedown.php`），启用 `setSafeMode(true)`，得到第一层白名单 HTML（仅 `safeModeWhitelist` 中注册的标签/属性通过）。
- 在 `classes/MarkdownSafe.php` 包装一层，对渲染结果做后置过滤：
  - `<a href>` scheme 仅允许 `http` / `https` / `mailto`，过滤 `javascript:` / `data:` / `vbscript:` 等。
  - `<img src>` scheme 仅允许 `http` / `https`，限定允许的 host 白名单（站内 58.tl 全域 + 用户在 `site_config` 里配置的额外域）。
  - 过滤 `on*=` 事件属性（safemode 已过滤大部分，此处兜底）。
  - 限制 `<a target="_blank">` 必须伴随 `rel="noopener noreferrer"`（自动追加）。

- **为什么不选前端 marked.js + DOMPurify**：服务端一次性渲染好直接 `echo`，不需前端再加载额外库、不需 await。XSS 风险收敛在服务端一处，前端只负责样式。代价：服务端 CPU 多走一次解析（拍卖站读多写少，可接受）。

### 2. 字段与列：`MEDIUMTEXT` 而不是 `TEXT`

- `TEXT` 上限 64KB 实际够用（5000 字符中文≈15KB），但 `MEDIUMTEXT` 16MB 给后续扩展留余地（未来允许嵌入图床 URL 列表、长文档型拍品）。
- 选用 `MEDIUMTEXT NULL`，**默认 NULL**——无描述的拍品无需空串占位，减少冗余。

### 3. 编辑窗口：复用 `updateAuction()` 既有「仅 pending」规则

- `Auction::updateAuction()` 当前已校验 `$a['status'] !== 'pending'` 时拒绝；`description` 与 `start_price / reserve_price / ...` 并列同一 UPDATE 集合，无需新方法。
- 卖家在 `pending` 阶段可改 description；进入 `active` 后只读（前端展示为只读预览框）。
- 编辑表单 UI：textarea 默认显示当前 markdown 源文，"预览" tab 调一次 `/api/preview-markdown.php`（或直接服务端渲染一次）。**第一版仅做服务端预览**（提交前/编辑页内联渲染一次），避免新增接口。

### 4. 详情页渲染：单点函数 + 折叠

- `bid/includes/lot_helpers.php` 新增 `ac_render_seller_description($auction)`：
  - 若 `description` 为空/NULL，输出空串（调用方决定折叠）。
  - 否则用 `MarkdownSafe::render()` 渲染，包裹在 `<div class="ac-seller-desc">…</div>`，CSS 已有的 `.ac-section` 样式复用。
- `bid/view.php` 现有「拍品信息 / 卖家 / 拍卖规则」三栏下方新增一栏「卖家描述」；空描述时不渲染该栏。

### 5. 长度上限 5000 字符：`mb_strlen` 度量

- 服务端校验：`mb_strlen($data['description']) > 5000` 时拒绝并返回错误。
- 前端 textarea 显示当前字数 / 5000，超限高亮。
- 数据库无长度约束（`MEDIUMTEXT` 自然容纳），但应用层先卡住。

### 6. 跨站链接说明（与本 change 无关但备记）

- 本 change 不引入新跨站链接；`description` 中允许任何 58.tl 子站 URL（如 `https://nft.58.tl/...`、`https://block.58.tl/...`）作为参考链接。
- 站点 `MarkdownSafe` 白名单 host 名单统一维护，可后续单独 change 扩展。

## Risks / Open Questions

- **Parsedown safemode 偶有 CVE**：定期跟进上游修复；可在 README 中标注"使用 1.7.x 以上版本"。
- **白名单 host 维护**：商品子站 `mall.58.tl`、NFT `nft.58.tl`、区块 `block.58.tl`、BCT `bct.58.tl`、拍卖 `bid.58.tl` 等全部应在白名单。建议白名单维护在 `config/markdown_hosts.php`，新增子站时一并加白。
- **超大图片**：Markdown 中 `<img>` 若引用未压缩大图会拖慢详情页渲染。建议 MarkdownSafe 在 `<img>` 渲染时统一包一层 `<a target="_blank">` 让用户主动点击放大，而不是默认展开。
- **图片上传**：本 change 明确不做；后续若需支持，由独立 change（涉及存储、CDN、审核）。