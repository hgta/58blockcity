<?php
/**
 * 统一后台菜单配置
 * 定义所有子站后台的站点信息和菜单结构
 */

// 所有后台站点定义
$ADMIN_SITES = [
    'main' => [
        'name' => '总控后台',
        'url'  => 'https://58.tl/admin/dashboard.php',
        'icon' => 'fa-home',
    ],
    'block' => [
        'name' => '区块交易',
        'url'  => 'https://block.58.tl/admin/dashboard.php',
        'icon' => 'fa-cubes',
    ],
    'bct' => [
        'name' => 'BCT市场',
        'url'  => 'https://bct.58.tl/admin/dashboard.php',
        'icon' => 'fa-coins',
    ],
    'hufang' => [
        'name' => '互访圈',
        'url'  => 'https://v.58.tl/hufang/admin/dashboard.php',
        'icon' => 'fa-users',
    ],
    'mall' => [
        'name' => '人气商城',
        'url'  => 'https://mall.58.tl/admin/dashboard.php',
        'icon' => 'fa-shopping-bag',
    ],
    'nft' => [
        'name' => 'NFT头像',
        'url'  => 'https://nft.58.tl/admin/dashboard.php',
        'icon' => 'fa-image',
    ],
    'task' => [
        'name' => '任务广场',
        'url'  => 'https://task.58.tl/admin/dashboard.php',
        'icon' => 'fa-tasks',
    ],
];

// 各站点菜单配置
$ADMIN_MENUS = [
    'main' => [
        ['icon' => 'fa-home',        'text' => '总控看板', 'url' => 'dashboard.php'],
        ['icon' => 'fa-users',       'text' => '用户管理', 'url' => 'users.php'],
        ['icon' => 'fa-store',       'text' => '店铺管理', 'url' => 'shops.php'],
        ['icon' => 'fa-comments',    'text' => '评论管理', 'url' => 'reviews.php'],
    ],
    'block' => [
        ['icon' => 'fa-home',        'text' => '管理看板', 'url' => 'dashboard.php'],
        ['icon' => 'fa-th',          'text' => '区块管理', 'url' => 'blocks.php'],
        ['icon' => 'fa-city',        'text' => '城市管理', 'url' => 'cities.php'],
        ['icon' => 'fa-sync',        'text' => '同步数据', 'url' => 'sync-cities.php'],
    ],
    'mall' => [
        ['icon' => 'fa-home',        'text' => '商城看板', 'url' => 'dashboard.php'],
        ['icon' => 'fa-box',         'text' => '商品管理', 'url' => 'categories.php'],
        ['icon' => 'fa-user-circle', 'text' => '模特管理', 'url' => 'models.php'],
        ['icon' => 'fa-palette',     'text' => '作者管理', 'url' => 'authors.php'],
        ['icon' => 'fa-file-signature', 'text' => '申请管理', 'url' => 'applications.php'],
        ['icon' => 'fa-store',       'text' => '店铺管理', 'url' => 'shops.php'],
        ['icon' => 'fa-users',       'text' => '用户管理', 'url' => 'users.php'],
        ['icon' => 'fa-comments',    'text' => '评论管理', 'url' => 'reviews.php'],
        ['icon' => 'fa-seedling',    'text' => '数据种子', 'url' => 'seed.php'],
    ],
    'nft' => [
        ['icon' => 'fa-home',        'text' => 'NFT看板',   'url' => 'dashboard.php'],
        ['icon' => 'fa-image',       'text' => 'NFT管理',   'url' => 'nfts.php'],
        ['icon' => 'fa-tags',        'text' => '标签管理',  'url' => 'tags.php'],
        ['icon' => 'fa-gavel',       'text' => '申诉审核',  'url' => 'appeal_review.php'],
    ],
    'hufang' => [
        ['icon' => 'fa-home',        'text' => '互访看板', 'url' => 'dashboard.php'],
        ['icon' => 'fa-users',       'text' => '用户管理', 'url' => 'users.php'],
        ['icon' => 'fa-circle-notch','text' => '互访圈管理','url' => 'circles.php'],
        ['icon' => 'fa-handshake',   'text' => '互访记录', 'url' => 'visits.php'],
        ['icon' => 'fa-city',        'text' => '城市管理', 'url' => 'cities.php'],
    ],
    'bct' => [
        ['icon' => 'fa-home',        'text' => 'BCT看板',        'url' => 'dashboard.php'],
        ['icon' => 'fa-wallet',      'text' => '余额管理',        'url' => 'bct_management.php'],
        ['icon' => 'fa-tags',        'text' => '人气值单价管理',  'url' => 'city_prices.php'],
        ['icon' => 'fa-exchange-alt','text' => '触发匹配',        'url' => 'trigger_match.php'],
    ],
    'task' => [
        ['icon' => 'fa-home',        'text' => '任务看板',    'url' => 'dashboard.php'],
        ['icon' => 'fa-tags',        'text' => '类别管理',    'url' => 'categories.php'],
        ['icon' => 'fa-tasks',       'text' => '任务列表',    'url' => 'tasks.php'],
        ['icon' => 'fa-hand-paper',  'text' => '认领管理',    'url' => 'claims.php'],
        ['icon' => 'fa-gavel',       'text' => '争议仲裁',    'url' => 'disputes.php'],
    ],
];

/**
 * 获取所有后台站点
 */
function getAllAdminSites() {
    global $ADMIN_SITES;
    return $ADMIN_SITES;
}

/**
 * 获取指定站点的菜单
 * @param string $site
 */
function getAdminMenus($site) {
    global $ADMIN_MENUS;
    return $ADMIN_MENUS[$site] ?? [];
}

/**
 * 获取指定站点的配置
 * @param string $site
 */
function getAdminSiteConfig($site) {
    global $ADMIN_SITES;
    return $ADMIN_SITES[$site] ?? null;
}
