## Purpose

把子站首页做成「引人入胜」的门面：以全屏 Hero 承载最具吸引力的模特，以主推模特与热播短剧制造内容张力，以发现入口承接目的性用户，以新人引导完成招募闭环。

## ADDED Requirements

### Requirement: 全屏 Hero 与视频降级链

首页 SHALL 设置全屏 Hero，展示一位主推模特。视频展示 SHALL 按「直链 `<video>` → 外链 `<iframe>` → 图片」三级降级，SHALL NOT 在任一环节出现破图或空白。

#### Scenario: 主推模特有直链视频

- **WHEN** 主推模特的 `video_url` 是视频直链
- **THEN** Hero 使用 `<video>` 播放，并启用 `muted`、`loop`、`playsinline` 与封面 `poster`
- **AND** 提供显式的静音/播放切换控件

#### Scenario: 主推模特只有外链视频

- **WHEN** 主推模特的 `video_url` 是第三方外链
- **THEN** Hero 使用懒加载的 `<iframe>` 呈现

#### Scenario: 无视频或加载失败

- **WHEN** 主推模特无 `video_url`，或视频加载失败
- **THEN** Hero 降级展示封面图；无封面时展示头像大图
- **AND** 页面不出现错误提示或空白区域

#### Scenario: 完全没有活跃模特

- **WHEN** 站内没有任何活跃模特
- **THEN** Hero 渲染静态品牌区与加入引导，不出现破损布局

### Requirement: Hero 主推选取规则

Hero 主推模特 SHALL 按「有视频优先，其次粉丝数」排序选取。

#### Scenario: 视频优先

- **WHEN** 站内同时存在有视频与无视频的模特
- **THEN** Hero 选取有视频的模特中粉丝数最高者

#### Scenario: 无视频时按粉丝数

- **WHEN** 站内没有任何模特配置视频
- **THEN** Hero 选取粉丝数最高的模特

### Requirement: Hero 信息完整

Hero SHALL 展示主推模特的昵称、个人简介、城市、粉丝数与进入个人页的入口。

#### Scenario: 关键信息首屏可见

- **WHEN** 用户在手机端打开首页
- **THEN** 昵称与进入个人页的入口在首屏内可见

### Requirement: 本期主推模特滑轨

首页 SHALL 提供「本期主推模特」横向滑轨，展示若干位模特的头像或主图、昵称、粉丝数与关注入口。

#### Scenario: 滑轨可横向浏览

- **WHEN** 主推模特数量超过一行可容纳的数量
- **THEN** 用户可横向滑动浏览全部

#### Scenario: 未登录关注

- **WHEN** 未登录用户点击滑轨中的关注按钮
- **THEN** 跳转登录页，并在登录后回到首页

### Requirement: 正在热播短剧滑轨

首页 SHALL 提供「正在热播短剧」横向滑轨，每项展示剧封面、剧名、集数与该剧参演模特信息，点击进入短剧详情页。

#### Scenario: 展示热播短剧

- **WHEN** 站内存在短剧数据
- **THEN** 滑轨展示短剧的封面、剧名、集数与参演模特
- **AND** 点击任一短剧进入其详情页

#### Scenario: 无短剧数据

- **WHEN** 站内暂无短剧数据
- **THEN** 该区块整体隐藏，不出现空壳

### Requirement: 发现模特入口

首页 SHALL 提供「发现模特」区，包含性别 / 城市 / 星座筛选条件、模特瀑布流网格与分页加载。

#### Scenario: 首页直接筛选

- **WHEN** 用户点击任一筛选条件
- **THEN** 网格按条件刷新为对应的模特集合

#### Scenario: 加载更多

- **WHEN** 用户点击加载更多
- **THEN** 追加下一页模特卡片且不刷新整页

### Requirement: 新人加入引导

首页 SHALL 提供新人加入引导区，说明加入流程并提供进入申请页的入口。

#### Scenario: 登录用户点击

- **WHEN** 已登录用户点击加入入口
- **THEN** 进入申请页

#### Scenario: 未登录用户点击

- **WHEN** 未登录用户点击加入入口
- **THEN** 跳转登录页，并在登录后进入申请页
