# 九区全景视图 — 技术设计

## 一、页面结构

```
city.php?name=beijing (默认全景)
┌──────────────────────────────────────────┐
│  Header (include)                        │
├──────────────────────────────────────────┤
│  北京区块城市  居民:xxx  激活:xxx         │
├──────────────────────────────────────────┤
│  [🏙️ 全城] [A区] [B区] ... [Z区]         │  ← 新增全城Tab
├──────────────────────────────────────────┤
│  ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│  │  A 区   │ │  B 区   │ │  C 区   │   │
│  │  ░░░░░░ │ │  ░░░░░░ │ │  ░░░░░░ │   │
│  │  2837/  │ │  1892/  │ │  445/   │   │
│  │  9999   │ │  9999   │ │  9999   │   │
│  │  28%已售│ │  19%已售│ │  4%已售 │   │
│  └─────────┘ └─────────┘ └─────────┘   │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│  │  D 区   │ │  E 区   │ │  F 区   │   │
│  └─────────┘ └─────────┘ └─────────┘   │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│  │  G 区   │ │  H 区   │ │  Z 区   │   │
│  └─────────┘ └─────────┘ └─────────┘   │
│                                        │
│  ■ 图例: □可售 ■已售 ▓合并             │
└──────────────────────────────────────────┘
```

## 二、切换逻辑

```
URL参数:
  city.php?name=beijing            → view=panorama (默认)
  city.php?name=beijing&zone=A     → view=zone (原单区模式)
  city.php?name=beijing&zone=B     → view=zone

Tab栏:
  "全城"   → href="city.php?name=<?=$pinyin?>"  (不加zone参数)
  "A区"   → href="?name=<?=$pinyin?>&zone=A"
```

## 三、数据加载

```php
$view_mode = isset($_GET['zone']) ? 'zone' : 'panorama';

if ($view_mode === 'panorama') {
    // 一次性加载全部9区数据
    $all_zones_data = [];
    foreach (['A','B','C','D','E','F','G','H','Z'] as $zone) {
        $zone_blocks = $block->getBlocksByCityZone($city_id, $zone);
        $merged_blocks = $block->getMergedBlocks($city_id, $zone);
        
        // 统计
        $sold_count = 0;
        $merged_count = count($merged_blocks);
        foreach ($zone_blocks as $zb) {
            if ($zb['status'] === 'sold') $sold_count++;
        }
        
        $all_zones_data[$zone] = [
            'blocks' => $zone_blocks,
            'merged' => $merged_blocks,
            'sold_count' => $sold_count,
            'merged_count' => $merged_count,
        ];
    }
}
```

## 四、迷你网格渲染

每区渲染为 101×99 的 3px 单元格，只分 3 种颜色：

```css
.pano-cell { width:3px; height:3px; float:left; }
.pano-cell.available { background:#e8f5e8; }   /* 淡绿 = 可售 */
.pano-cell.sold      { background:#ff6b00; }   /* 橙红 = 已售 */
.pano-cell.merged    { background:#1976d2; }   /* 蓝色 = 合并区块 */
```

不显示区块编号，纯色块热力图效果。

## 五、每区卡片

```html
<div class="zone-pano-card" onclick="location.href='?name=<?=$pinyin?>&zone=<?=$zone?>'">
  <div class="zone-pano-header">
    <span class="zone-label"><?=$zone?>区</span>
    <span class="zone-stats"><?=$sold_count?>/9999 (<?=round($sold_count/9999*100)?>%)</span>
  </div>
  <div class="zone-pano-grid">
    <?php /* 101×99 个 3px div */ ?>
  </div>
</div>
```

## 六、文件变更

| 文件 | 操作 | 说明 |
|------|------|------|
| `block/city.php` | 修改 | 增加全景模式判断、九区数据加载、3×3缩略图渲染、Tab逻辑改造 |

仅 1 个文件。
