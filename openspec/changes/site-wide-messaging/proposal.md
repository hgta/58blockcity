# Proposal: 全站站内信系统

## 问题

1. **通知系统仅 hufang 可用** — mall/block/BCT 的事件无通知，用户错过重要信息
2. **无用户私信** — 用户无法在站内直接沟通
3. **通知铃铛仅 hufang 显示** — 其他子站用户看不到通知

## 目标

建立统一的站内信系统，覆盖所有子站（mall/bct/block/hufang/nft），支持系统通知和用户私信。

## 范围

**Phase 1-4（本次实施）：**
- DB: `notifications` 表 `type` 列扩展枚举 + 增加 `from_user_id`、`related_url` 字段
- 类: Notification 增加站内信方法
- 事件: Mall 支付/发货/收货/评论自动发通知
- UI: 全局通知铃铛 + 消息中心页面

**Phase 5-6（后续）：**
- 用户私信 UI（DM）
- Block/BCT 事件通知
