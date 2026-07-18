# Block 子站 UX 修复

## 修复项

| # | 问题 | 方案 |
|---|------|------|
| 1 | 选中高亮缺失 | 添加 `.block-cell.selected` CSS |
| 2 | 认领全量刷新 | 改为 AJAX + 局部DOM更新 |
| 3 | 自己的区块无区分 | 添加 `.own-block` 绿色标识 |
| 4 | 网格线继承 | `.block-cell` 显式 `border:none` |
| 5 | 布局优化 | 详情面板缩小+容器宽度 |
