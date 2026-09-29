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
 *   include $sharedIncludes . '/city_picker.php';
 */

$cityPickerName  = $cityPickerName  ?? 'city';
$cityPickerValue = (string)($cityPickerValue ?? '');
$cityPickerId    = $cityPickerId    ?? 'cityPicker';
$cityOptions     = $cityOptions     ?? [];

// 组装前端数据（name / pinyin / 是否热门）
$__cityJson = [];
$__hotNames = [];
foreach ($cityOptions as $__c) {
    $__name = is_array($__c) ? (string)($__c['name'] ?? '') : (string)$__c;
    if ($__name === '') {
        continue;
    }
    $__py  = is_array($__c) ? (string)($__c['pinyin'] ?? '') : '';
    $__hot = is_array($__c) ? (int)($__c['is_hot'] ?? 0) : 0;
    $__cityJson[] = ['n' => $__name, 'p' => strtolower($__py), 'h' => $__hot];
    if ($__hot === 1 && count($__hotNames) < 12) {
        $__hotNames[] = $__name;
    }
}
$__cityTotal = count($__cityJson);
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
</style>

<div class="city-picker" id="<?= htmlspecialchars($cityPickerId) ?>"
     data-name="<?= htmlspecialchars($cityPickerName) ?>"
     data-total="<?= (int)$__cityTotal ?>">
    <input type="text" class="cp-input" name="<?= htmlspecialchars($cityPickerName) ?>"
           value="<?= htmlspecialchars($cityPickerValue) ?>"
           placeholder="选择或输入城市" autocomplete="off" autocorrect="off" spellcheck="false">
    <i class="cp-arrow"></i>
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
        var matched = [];
        for (var i = 0; i < cities.length; i++) {
            if (q === '' || match(cities[i], q)) matched.push(cities[i]);
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

        if (q === '') {
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

    // 点击外部关闭
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) close();
    });
})();
</script>
