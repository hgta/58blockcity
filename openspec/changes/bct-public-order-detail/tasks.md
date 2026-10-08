# Tasks: bct-public-order-detail

## 1. 页面骨架与数据

- [x] 1.1 新建 `bct/order.php`：`id` 强转校验，查 `bct_orders` JOIN `users`（取挂单人用户名）与活跃 claim（复用 `BCTOrder::getClaimPreview`）；订单不存在或参数非法时跳 `orders.php`
- [x] 1.2 复用 `orders.php` 的角色判定输出 `$isMine / $inTrade / $isParty / $canClaim`，服务端算完再渲染（前端不做权限判断）
- [x] 1.3 渲染信息区：单号（截断展示）、方向徽标、城市、单价、数量、总价、交易方式、剩余有效期（复用 `formatRemainingValidity`）、发布时间、挂单人、该城市当前价与挂单价偏离
- [x] 1.4 状态区：pending / processing / 交易中 / completed / canceled / expired 徽标与文案（D7）
- [x] 1.5 页面 `<title>` 与 `description`：城市 + 方向 + 价格；样式沿用 `exchange-theme.css`，移动端单列

## 2. 按钮与交互

- [x] 2.1 CTA 区按 D4 矩阵渲染：唯一主按钮（游客「登录后接单」带 redirect 回跳；非本人「接单（买入）/接单（卖给TA）」；本人「管理我的挂单」/「交易中 · 去处理」；参与方「继续交易」→ `dashboard.php#claims`）
- [x] 2.2 平台/中介交易单不渲染接单按钮，显示「由平台撮合」/「由中介协调」说明
- [x] 2.3 接单：点击 → GET `preview` 校验 → 确认弹窗 → POST `claim`（CSRF）→ 跳 `user/dashboard.php#claims`
- [x] 2.4 抢单失败改为顶部提示条 + 延时自动刷新（替换大厅现存的 `alert` 做法）；触发按钮与确认按钮提交后 disable，失败恢复
- [x] 2.5 「私信」收为挂单人名字旁的小图标链接（登录态可点，未登录跳登录）
- [x] 2.6 接单弹窗样式 `.claim-modal*` 从 `orders.php` 内联样式提取到 `assets/css/exchange-theme.css`（大厅与详情页共用），CSS 版本号 v=6 → v=7

## 3. 入口改造

- [x] 3.1 `bct/index.php` 买入挂单列表条目：链接由 `city.php?city=` 改为 `order.php?id=`
- [x] 3.2 `bct/index.php` 卖出挂单列表条目：同上
- [x] 3.3 `bct/orders.php` 操作列新增「详情」按钮 → `order.php?id=N`（城市名链接保留跳行情）

## 4. 隐私与失效态

- [x] 4.1 联系方式按 D6 五档规则渲染，与大厅逐条一致；页面不输出 `email / phone`
- [x] 4.2 终态单（completed / canceled / expired）通过 URL 直达时渲染状态说明而非 404
- [x] 4.3 交易说明文案与挂单大厅保持一致（接单后进入交易中、线下转账、双方确认），平台/中介单给出对应说明

## 5. 验证

- [ ] 5.1 四种角色 × 五种订单状态 × 三种交易方式的按钮渲染手测（重点：本人、交易中参与方、游客）
- [ ] 5.2 接单全链路：详情页点击 → 确认 → claim 成功 → 跳个人中心可见"进行中的交易"
- [ ] 5.3 抢单冲突：两个浏览器同时对同一单接单，后者收到顶部提示并自动刷新
- [ ] 5.4 隐私：未登录看不到联系方式；交易中第三方看不到；参与方可见
- [ ] 5.5 失效态：手工构造 expired / canceled / completed 单，URL 直达渲染正常、无死链
- [ ] 5.6 安全：直接 POST claim 缺 CSRF / 未登录 / 非 direct 单 / 接自己的单均被拒
- [ ] 5.7 回归：首页两列表与大厅原有链接行为除新增入口外无变化；`php -l` 通过

> 运行时验证需部署到线上后手测；静态验证（`php -l`、残留引用检索）已完成。
