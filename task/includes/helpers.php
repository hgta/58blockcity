<?php
/**
 * 任务广场共享展示辅助（金额/状态/目标导流链接）
 */

/** 赏金展示文本 */
function task_reward_text($task) {
    $t = $task['reward_type'] ?? '';
    $amt = (int)($task['reward_amount'] ?? 0);
    if ($t === 'cash') {
        return '¥ ' . number_format($amt / 100, 2);
    }
    return 'Ⓟ ' . number_format($amt) . ' 人气值';
}

/** 任务状态标签（行数据需含 claimed_count/quota/status/expire_at） */
function task_status_label($task) {
    $now = time();
    if (($task['status'] ?? '') === 'closed') return '已结束';
    if (!empty($task['expire_at']) && strtotime($task['expire_at']) <= $now) return '已结束';
    if ((int)($task['claimed_count'] ?? 0) >= (int)($task['quota'] ?? 1)) return '已满';
    return '进行中';
}

/** 名额剩余文本 */
function task_remaining_text($task) {
    $left = (int)($task['quota'] ?? 1) - (int)($task['claimed_count'] ?? 0);
    return $left > 0 ? '剩 ' . $left . ' / ' . (int)($task['quota'] ?? 1) . ' 份' : '名额已满';
}

/**
 * 关联对象的跨站导流链接（L1）
 * @return array|null ['url','label','name']
 */
function task_target_link($task) {
    $type = $task['target_type'] ?? '';
    $id   = (int)($task['target_id'] ?? 0);
    if ($type === 'block' && $id > 0) {
        return [
            'url'   => 'https://block.58.tl/block/view.php?id=' . $id,
            'label' => '前往该区块',
            'name'  => '关联区块',
        ];
    }
    if ($type === 'circle' && $id > 0) {
        return [
            'url'   => 'https://v.58.tl/circles/view.php?id=' . $id,
            'label' => '前往该互访圈',
            'name'  => '关联互访圈',
        ];
    }
    return null;
}

/**
 * 城市目标的导流链接：按城市名解析 pinyin，指向 block.58.tl 城市详情页。
 * @param string $cityName 城市中文名（tasks.city / 人气值结算城市）
 * @return array|null ['url','label','name']
 */
function task_city_link($pdo, $cityName) {
    $cityName = trim((string)$cityName);
    if ($cityName === '') return null;
    try {
        $stmt = $pdo->prepare("SELECT pinyin FROM cities WHERE name = ? LIMIT 1");
        $stmt->execute([$cityName]);
        $pinyin = $stmt->fetchColumn();
    } catch (Exception $e) {
        return null;
    }
    if (!$pinyin) return null;
    return [
        'url'   => 'https://block.58.tl/city.php?name=' . rawurlencode($pinyin),
        'label' => '前往该城市',
        'name'  => $cityName,
    ];
}

/**
 * 批量把城市名解析为 pinyin（广场卡片一次性取，避免逐行查询）
 * @return array [cityName => pinyin]
 */
function task_city_pinyin_map($pdo, array $cityNames) {
    $names = array_values(array_unique(array_filter(array_map('trim', $cityNames))));
    if (!$names) return [];
    $map = [];
    try {
        $holders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $pdo->prepare("SELECT name, pinyin FROM cities WHERE name IN ($holders)");
        $stmt->execute($names);
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['name']] = $row['pinyin'];
        }
    } catch (Exception $e) {
        // 城市表缺行/未建时降级为纯文本
    }
    return $map;
}
