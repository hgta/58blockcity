<?php
/**
 * 可搜索城市选择器（共享组件）
 *
 * 背景：原生 <datalist> 在微信内置浏览器（X5 / WKWebView）与部分移动端
 * 基本不弹列表，导致注册时"只能看到几个城市"。本组件改为纯原生 JS 实现，
 * 数据全部来自 cities 城市数据库，支持中文名 / 拼音搜索。
 *
 * 用法（在页面中先准备好数据，再 include 本文件）：
 *   $cityOptions     = [['name'=>'北京','pinyin'=>'beijing','is_hot'=>1], ...]; // 来自 cities 表
 *   $cityPickerName  = 'city';                       // 表单字段名，默认 city
 *   $cityPickerValue = $_POST['city'] ?? '';         // 当前值
 *   $cityPickerId    = 'cityPicker';                 // 页面内唯一 id（可选）
 *
 * 可选扩展参数（不传 = 现状行为，注册页零改动）：
 *   $cityPickerCandidates = [['name'=>..,'pinyin'=>..,'is_hot'=>..], ...]; // 默认展示子集（如「该页有数据的城市」）
 *   $cityPickerVariant    = 'default' | 'filter' | 'admin';  // 前台默认 / 前台筛选栏 / 深色后台
 *   $cityPickerAllowClear = true;                  // 显示一键清除（空值 = 全部城市语义）
 *   $cityPickerPlaceholder = '选择或输入城市';
 */

$cityPickerName  = $cityPickerName  ?? 'city';
$cityPickerValue = (string)($cityPickerValue ?? '');
$cityPickerId    = $cityPickerId    ?? 'cityPicker';
$cityOptions     = $cityOptions     ?? [];

$cityPickerCandidates  = $cityPickerCandidates  ?? null;
$cityPickerVariant    = $cityPickerVariant    ?? 'default';
$cityPickerAllowClear = (bool)($cityPickerAllowClear ?? false);
$cityPickerPlaceholder = $cityPickerPlaceholder ?? '选择或输入城市';
if (!in_array($cityPickerVariant, ['default', 'filter', 'admin'], true)) {
    $cityPickerVariant = 'default';
}

// 组装前端数据（name / pinyin / 是否热门）
$__pack = function ($rows) {
    $json = []; $hotNames = [];
    if (!is_array($rows)) { return [$json, $hotNames]; }
    foreach ($rows as $c) {
        $name = is_array($c) ? (string)($c['name'] ?? '') : (string)$c;
        if ($name === '') { continue; }
        $py  = is_array($c) ? (string)($c['pinyin'] ?? '') : '';
        $isHot = is_array($c) ? (int)($c['is_hot'] ?? 0) : 0;
        $json[] = ['n' => $name, 'p' => strtolower($py), 'h' => $isHot];
        if ($isHot === 1 && count($hotNames) < 12) { $hotNames[] = $name; }
    }
    return [$json, $hotNames];
};
[$__cityJson, $__hotNames] = $__pack($cityOptions);
$__cityTotal = count($__cityJson);

// 候选子集（默认展示「有数据的城市」；null = 展开即全量，注册页现状）
$__candJson = null;
$__candTotal = 0;
if (is_array($cityPickerCandidates)) {
    [$__candJson, $__candHotNames] = $__pack($cityPickerCandidates);
    $__candTotal = count($__candJson);
    if ($__candTotal > 0) { $__hotNames = $__candHotNames; } // 热门 chips 取候选内 is_hot
}
$__variantClass = $cityPickerVariant !== 'default' ? ' cp-' . $cityPickerVariant : '';
$__clearClass   = $cityPickerAllowClear ? ' cp-clearable' : '';
?>
<style>
/* 城市选择器（作用域限定，避免影响其它表单元素） */
.city-picker { position:relative; }
.city-picker input.cp-input { width:100%; padding:12px 34px 12px 12px; border:1px solid #ddd; border-radius:6px; font-size:15px; box-sizing:border-box; background:#fff; }
.city-picker input.cp-input:focus { border-color:#ff6b00; outline:none; }
.city-picker .cp-arrow { position:absolute; right:12px; top:50%; transform:translateY(-50%); width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:6px solid #bbb; pointer-events:none; }
.city-picker .cp-panel { position:absolute; left:0; right:0; top:calc(100% + 4px); z-index:60; background:#fff; border:1px solid #e2e2e2; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.12); overflow:hidden; }
.city-picker .cp-panel[hidden] { display:none; }
.city-picker .cp-hot { padding:10px 12px; border-bottom:1px solid #f2f2f2; display:flex; flex-wrap:wrap; gap:6px; background:#fafafa; }
.city-picker .cp-hot-label { width:100%; font-size:12px; color:#999; margin-bottom:2px; }
.city-picker .cp-chip { padding:5px 10px; border:1px solid #eee; border-radius:14px; font-size:13px; color:#666; background:#fff; cursor:pointer; }
.city-picker .cp-chip:hover { border-color:#ff6b00; color:#ff6b00; }
.city-picker .cp-list { max-height:264px; overflow-y:auto; -webkit-overflow-scrolling:touch; }
.city-picker .cp-item { padding:11px 14px; font-size:15px; color:#333; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
.city-picker .cp-item:hover, .city-picker .cp-item.cp-active { background:#fff6ef; color:#ff6b00; }
.city-picker .cp-item .cp-py { font-size:12px; color:#bbb; margin-left:8px; }
.city-picker .cp-empty { padding:18px 14px; text-align:center; font-size:13px; color:#999; line-height:1.8; }
.city-picker .cp-foot { padding:7px 12px; border-top:1px solid #f2f2f2; background:#fafafa; font-size:12px; color:#aaa; text-align:center; }

/* 前台筛选栏变体：对齐 .filter-group select/input 尺寸（main.css） */
.city-picker.cp-filter input.cp-input { padding:8px 34px 8px 10px; font-size:14px; min-width:120px; }

/* 深色后台变体：改用 admin.css 的 --admin-* 变量，天然适配 bct 后台主题 */
.city-picker.cp-admin input.cp-input { background:var(--admin-bg-light,#1e293b); border-color:var(--admin-border,#334155); color:var(--admin-text,#f1f5f9); }
.city-picker.cp-admin input.cp-input::placeholder { color:var(--admin-text-muted,#94a3b8); }
.city-picker.cp-admin input.cp-input:focus { border-color:var(--admin-accent,#ff6b00); }
.city-picker.cp-admin .cp-arrow { border-top-color:var(--admin-text-muted,#94a3b8); }
.city-picker.cp-admin .cp-panel { background:var(--admin-card,#1e293b); border-color:var(--admin-border,#334155); }
.city-picker.cp-admin .cp-hot { background:var(--admin-bg,#0f172a); border-bottom-color:var(--admin-border,#334155); }
.city-picker.cp-admin .cp-hot-label { color:var(--admin-text-muted,#94a3b8); }
.city-picker.cp-admin .cp-chip { background:var(--admin-bg,#0f172a); border-color:var(--admin-border,#334155); color:var(--admin-text,#f1f5f9); }
.city-picker.cp-admin .cp-chip:hover { border-color:var(--admin-accent,#ff6b00); color:var(--admin-accent,#ff6b00); }
.city-picker.cp-admin .cp-item { color:var(--admin-text,#f1f5f9); }
.city-picker.cp-admin .cp-item:hover, .city-picker.cp-admin .cp-item.cp-active { background:rgba(255,107,0,.12); color:var(--admin-accent,#ff6b00); }
.city-picker.cp-admin .cp-py { color:var(--admin-text-muted,#94a3b8); }
.city-picker.cp-admin .cp-empty { color:var(--admin-text-muted,#94a3b8); }
.city-picker.cp-admin .cp-foot { background:var(--admin-bg,#0f172a); border-top-color:var(--admin-border,#334155); color:var(--admin-text-muted,#94a3b8); }

/* 一键清除按钮（allowClear，空值 = 全部城市） */
.city-picker .cp-clear { position:absolute; right:30px; top:50%; transform:translateY(-50%); width:18px; height:18px; line-height:18px; text-align:center; font-size:12px; font-style:normal; color:#bbb; cursor:pointer; user-select:none; }
.city-picker .cp-clear:hover { color:#ff6b00; }
.city-picker.cp-clearable input.cp-input { padding-right:54px; }
.city-picker.cp-clearable.cp-filter input.cp-input { padding-right:54px; }
.city-picker.cp-admin .cp-clear:hover { color:var(--admin-accent,#ff6b00); }
</style>

<div class="city-picker<?= $__variantClass ?><?= $__clearClass ?>" id="<?= htmlspecialchars($cityPickerId) ?>"
     data-name="<?= htmlspecialchars($cityPickerName) ?>"
     data-total="<?= (int)$__cityTotal ?>">
    <input type="text" class="cp-input" name="<?= htmlspecialchars($cityPickerName) ?>"
           value="<?= htmlspecialchars($cityPickerValue) ?>"
           placeholder="<?= htmlspecialchars($cityPickerPlaceholder) ?>" autocomplete="off" autocorrect="off" spellcheck="false">
    <i class="cp-arrow"></i>
    <?php if ($cityPickerAllowClear): ?><i class="cp-clear" title="清除城市（显示全部）">&#10005;</i><?php endif; ?>
    <div class="cp-panel" hidden>
        <?php if (!empty($__hotNames)): ?>
        <div class="cp-hot">
            <span class="cp-hot-label">热门城市</span>
            <?php foreach ($__hotNames as $__hn): ?>
                <span class="cp-chip" data-city="<?= htmlspecialchars($__hn) ?>"><?= htmlspecialchars($__hn) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="cp-list"></div>
        <div class="cp-foot" id="<?= htmlspecialchars($cityPickerId) ?>Foot"></div>
    </div>
</div>
<input type="hidden" id="<?= htmlspecialchars($cityPickerId) ?>Data"
       value="<?= htmlspecialchars(json_encode($__cityJson, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">
<?php if ($__candJson !== null): ?>
<input type="hidden" id="<?= htmlspecialchars($cityPickerId) ?>CandData"
       value="<?= htmlspecialchars(json_encode($__candJson, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">
<?php endif; ?>

<script>
(function () {
    var root = document.getElementById('<?= $cityPickerId ?>');
    if (!root) return;

    var dataEl  = document.getElementById('<?= $cityPickerId ?>Data');
    var input   = root.querySelector('.cp-input');
    var panel   = root.querySelector('.cp-panel');
    var listEl  = root.querySelector('.cp-list');
    var footEl  = root.querySelector('.cp-foot');
    var cities  = [];
    try { cities = JSON.parse(dataEl.value) || []; } catch (e) { cities = []; }

    // 候选子集（默认展示「有数据的城市」；无则展开即全量）
    var candEl  = document.getElementById('<?= $cityPickerId ?>CandData');
    var candData = null;
    if (candEl) { try { candData = JSON.parse(candEl.value) || null; } catch (e) { candData = null; } }

    var clearEl = root.querySelector('.cp-clear');

    var MAX_RENDER = 300;   // 单次渲染上限，避免移动端卡顿
    var activeIdx  = -1;

    function open()  { panel.hidden = false; render(input.value); }
    function close() { panel.hidden = true; activeIdx = -1; }

    function match(c, q) {
        if (c.n.indexOf(q) > -1) return true;          // 中文包含匹配
        if (c.p && c.p.indexOf(q) > -1) return true;   // 拼音包含匹配
        return false;
    }

    function render(keyword) {
        var q = (keyword || '').trim().toLowerCase().replace(/\s+/g, '');
        var source = (q === '' && candData && candData.length) ? candData : cities;
        var matched = [];
        for (var i = 0; i < source.length; i++) {
            if (q === '' || match(source[i], q)) matched.push(source[i]);
        }

        var html = '';
        var shown = matched.slice(0, MAX_RENDER);
        for (var j = 0; j < shown.length; j++) {
            html += '<div class="cp-item" data-city="' + shown[j].n.replace(/"/g, '') + '">'
                  + '<span>' + shown[j].n + '</span>'
                  + (shown[j].p ? '<span class="cp-py">' + shown[j].p + '</span>' : '')
                  + '</div>';
        }
        if (!shown.length) {
            html = '<div class="cp-empty">没有匹配的城市<br>可直接输入城市名后继续</div>';
        }
        listEl.innerHTML = html;
        activeIdx = -1;

        if (q === '' && candData && candData.length) {
            footEl.textContent = '默认 ' + candData.length + ' 个有数据城市，输入可搜全部 ' + cities.length + ' 个';
        } else if (q === '') {
            footEl.textContent = '共收录 ' + cities.length + ' 个城市，可输入城市名或拼音（如 hangzhou）快速查找';
        } else {
            footEl.textContent = '匹配 ' + matched.length + ' 个城市'
                + (matched.length > MAX_RENDER ? '，仅显示前 ' + MAX_RENDER + ' 个' : '');
        }
    }

    function pick(name) {
        input.value = name;
        close();
    }

    // 聚焦/点击即展开（不依赖 input 事件，兼容微信键盘）
    input.addEventListener('focus', open);
    input.addEventListener('click', open);
    input.addEventListener('input', function () { panel.hidden = false; render(input.value); });

    // 键盘操作
    input.addEventListener('keydown', function (e) {
        var items = listEl.querySelectorAll('.cp-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx += (e.key === 'ArrowDown' ? 1 : -1);
            if (activeIdx < 0) activeIdx = items.length - 1;
            if (activeIdx >= items.length) activeIdx = 0;
            for (var i = 0; i < items.length; i++) items[i].classList.toggle('cp-active', i === activeIdx);
            try { items[activeIdx].scrollIntoView({ block: 'nearest' }); }
            catch (err) { items[activeIdx].scrollIntoView(false); }
        } else if (e.key === 'Enter') {
            if (activeIdx > -1) { e.preventDefault(); pick(items[activeIdx].getAttribute('data-city')); }
        } else if (e.key === 'Escape') {
            close();
        }
    });

    // 选择热门城市
    var hotBox = root.querySelector('.cp-hot');
    if (hotBox) {
        hotBox.addEventListener('click', function (e) {
            var chip = e.target.closest ? e.target.closest('.cp-chip') : null;
            if (chip) { pick(chip.getAttribute('data-city')); }
        });
    }

    // 选择列表项
    listEl.addEventListener('click', function (e) {
        var item = e.target.closest ? e.target.closest('.cp-item') : null;
        if (item) { pick(item.getAttribute('data-city')); }
    });

    // 一键清除（allowClear：清空输入 = 全部城市语义）
    if (clearEl) {
        clearEl.addEventListener('click', function (e) {
            e.stopPropagation();
            input.value = '';
            open();
        });
    }

    // 点击外部关闭
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) close();
    });
})();
</script>
