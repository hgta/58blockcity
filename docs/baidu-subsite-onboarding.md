# 百度站点接入指引（子域与一级域名）

本文档说明如何把各子域与一级域名接入百度搜索资源平台。

---

## 一、核心结论（实测）

> 本文档中的 token 一律以 `YOUR_BAIDU_TOKEN` 占位。
> 真实 token 仅存放在 `config/seo.php`（该文件已被 `.gitignore` 忽略，不入库）。

### 1. token 可跨站点通用

同一百度账号下的多个站点，**token 相同**，靠请求中的 `site=` 参数区分站点身份：

```
http://data.zz.baidu.com/urls?site=https://mall.58.tl&token=YOUR_BAIDU_TOKEN
                                  ↑ 决定「以哪个站点身份推送」
```

实测：用同一 token，分别以 `site=mall.58.tl`、`site=block.58.tl`、`site=www.58.tl` 推送，均返回 `success:1`。

### 2. 配额按站点独立计算

**实测数据**：

| 测试顺序 | `site` 参数 | 返回 |
|---------|------------|------|
| 1 | `mall.58.tl` | `{"remain":8,"success":1}` |
| 2 | `block.58.tl` | `{"remain":9,"success":1}` |
| 3 | `www.58.tl` | `{"remain":9,"success":1}` |

判读：`mall` 消耗后 `remain` 变为 8，但 `block` 与 `www` 仍各自独立显示 9。

**结论**：每个 `site` 参数对应**独立的配额池**，互不干扰。各站点可放心启用推送。

> 注意：单站点配额约为 **10 条/天**（低档，非高权重站）。
> 因此推送只适合「告知百度有新内容」，**不能用于批量提交内页**。
> 内页收录需依赖 sitemap + 内链 + 内容质量。

---

## 二、站点清单与配置策略

### 已验证站点（可启用推送）

| 站点 | 验证文件位置 | 推送配置 |
|------|------------|---------|
| `www.58.tl` | — | `enabled = true` |
| `mall.58.tl` | `mall/baidu_verify_...wbs82Z2IBw.html` | `enabled = true` |
| `block.58.tl` | `block/baidu_verify_...Ug75tUQzMY.html` | `enabled = true` |
| `model.58.tl` | `model/baidu_verify_...XMr3DzFkdc.html` | `enabled = true` |
| `nft.58.tl` | `nft/baidu_verify_...0O72DeDhet.html` | `enabled = true` |

### 待验证站点（保持关闭）

| 站点 | 说明 |
|------|------|
| `bct.58.tl` | canonical 已收口到 `renqizhi.com`，**不建议推送**（见下） |
| `v.58.tl` | canonical 已收口到 `hufangquan.com`，**不建议推送** |
| `bid.58.tl` / `club.58.tl` / `task.58.tl` | 未验证 |
| `renqizhi.com` | 待验证后启用（BCT 主收录目标） |
| `hufangquan.com` | 待验证后启用（互访圈主收录目标） |

### 为何 bct.58.tl 与 v.58.tl 不推送

这两个子域的 canonical 已指向一级域名：

```
  页面地址：  https://bct.58.tl/market.php
  canonical： https://renqizhi.com/market.php
                        ↑ 页面声明「正版在这里」
```

若推送 `bct.58.tl` 的 URL：

```
  百度收到 https://bct.58.tl/market.php
        ↓
  爬取后发现 canonical 指向 renqizhi.com
        ↓
  判定为重复内容，忽略该 URL
        ↓
  ❌ 浪费配额，且可能干扰信号
```

**正确做法**：等 `renqizhi.com` / `hufangquan.com` 验证完成后，**推送一级域名**。

---

## 三、配置方法

编辑 `config/seo.php`（该文件已被 `.gitignore` 忽略，不上传仓库）：

```php
'sites' => [
    'www.58.tl' => [
        'token'   => 'YOUR_BAIDU_TOKEN',
        'enabled' => true,
    ],

    // 已验证子域：token 通用，启用推送
    'mall.58.tl'  => ['token' => 'YOUR_BAIDU_TOKEN', 'enabled' => true],
    'block.58.tl' => ['token' => 'YOUR_BAIDU_TOKEN', 'enabled' => true],
    'model.58.tl' => ['token' => 'YOUR_BAIDU_TOKEN', 'enabled' => true],
    'nft.58.tl'   => ['token' => 'YOUR_BAIDU_TOKEN', 'enabled' => true],

    // 已收口或未验证：保持关闭
    'bct.58.tl'   => ['token' => '', 'enabled' => false],
    'v.58.tl'     => ['token' => '', 'enabled' => false],
    'bid.58.tl'   => ['token' => '', 'enabled' => false],
    'club.58.tl'  => ['token' => '', 'enabled' => false],
    'task.58.tl'  => ['token' => '', 'enabled' => false],

    // 一级域名：验证后填入各自 token 并启用
    'renqizhi.com'   => ['token' => '', 'enabled' => false],
    'hufangquan.com' => ['token' => '', 'enabled' => false],
],
```

---

## 四、子域验证流程（逐个）

### 1. 在百度搜索资源平台添加站点

访问 [百度搜索资源平台](https://ziyuan.baidu.com/) → 用户中心 → 站点管理 → 添加站点，填入完整地址（含 `https://`）。

### 2. 完成验证

三种方式任选：

| 方式 | 说明 |
|------|------|
| **DNS 验证**（推荐） | 添加百度给出的 TXT 记录，可一次批量加多个子域 |
| **HTML 文件验证** | 下载验证文件放到子域根目录（如 `mall/`） |
| **CNAME 验证** | 添加百度给出的 CNAME 记录 |

### 3. 获取 token

验证通过后，在「普通收录 → API 提交」页面查看 token。
（实测：同一账号下各站点 token 相同。）

### 4. 填入配置并启用

按上文第三节修改 `config/seo.php`。

### 5. 验证推送

```bash
cd /www/wwwroot/58git/58blockcity
php site.php https://mall.58.tl/
```

期望返回 `success` 计数。

---

## 五、排查手册

### 推送无效果

1. 查看 error log 中的 `[SEO]` 记录：
   ```bash
   tail -f /path/to/php-error.log | grep '\[SEO\]'
   ```
2. 日志会写明跳过原因（如「该子域未启用」、「token 未配置」）。

### 常见返回码

| 返回 | 原因 | 处理 |
|------|------|------|
| `{"success":N,"remain":M}` | 成功 | 正常 |
| `site is not valid` | 该域名未在平台验证 | 补验证 |
| `token is not valid` | token 错误 | 核对 token |
| `not_same_site` | 推送 URL 与 site 参数不同域 | 检查 URL 归属 |
| `over quota` | 当日配额耗尽（约 10 条/天） | 次日再推 |

### sitemap 404

确认服务器 nginx 已加对应 rewrite，且子目录下 `sitemap.php` 存在。

---

## 六、启用节奏建议

```
  第 1 批（已完成验证）：mall + model
  第 2 批：club + block
  第 3 批：bct + nft
  第 4 批：bid + v + task
```

**不要一次全部启用**，原因：

1. 多个同主体、同模板子域在百度侧有「站群」特征，集中上线可能触发整体降权。
2. 分批便于观察每个子域对主站的影响。
3. 出问题时回滚范围可控。

**监控要点**：每批启用后 3~7 天，观察 `www.58.tl` 收录是否下降。若下降，暂停下一批并排查。

---

## 七、重要限制说明

```
  ⚠️ 单站点配额约 10 条/天，这个数字是最大约束
  ═══════════════════════════════════════════════════════
  
  推送的定位：
    ✅ 告知百度「有新内容产生」（首页级信号）
    ❌ 批量提交内页（配额远远不够）
  
  内页收录的真正依赖：
    1. sitemap 合规且可访问（已修复跨域问题）
    2. 良好的内链结构（首页 → 列表 → 详情）
    3. 内容质量与原创度
    4. 外链（当前严重不足）
  
  → 不要指望推送能解决内页收录问题
  → 推送只是辅助信号
  ═══════════════════════════════════════════════════════
```
