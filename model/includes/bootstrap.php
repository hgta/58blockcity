<?php
/**
 * 模特子站公共引导文件（model.58.tl）
 *
 * 负责：路径常量、依赖加载、会话/登录态、公共视图助手。
 * 所有子站页面应在最顶部 require 本文件。
 */

if (!defined('MODEL_SUBSITE')) {
    define('MODEL_SUBSITE', true);
}

$__modelRoot = dirname(__DIR__);          // .../58blockcity/model
$__appRoot   = dirname($__modelRoot);     // .../58blockcity

define('MODEL_ROOT', $__modelRoot);
define('APP_ROOT', $__appRoot);
define('MODEL_BASE_URL', 'https://model.58.tl');
define('MALL_BASE_URL', 'https://mall.58.tl');
define('WWW_BASE_URL', 'https://www.58.tl');
define('MODEL_LOGIN_URL', 'https://mall.58.tl/auth/login.php');
define('MODEL_REGISTER_URL', 'https://mall.58.tl/auth/register.php');
define('MODEL_DASHBOARD_URL', 'https://mall.58.tl/user/dashboard.php');
define('MODEL_LOGOUT_URL', 'https://mall.58.tl/auth/logout.php');

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/classes/User.php';
require_once APP_ROOT . '/classes/Model.php';
require_once APP_ROOT . '/classes/SeoHelper.php';

// 子站登录入口（复用商城登录页，登录后回跳子站）
if (!function_exists('model_login_url')) {
    /**
     * 生成登录地址并携带回跳目标
     */
    function model_login_url($redirect = '')
    {
        if ($redirect === '') {
            $redirect = MODEL_BASE_URL . ($_SERVER['REQUEST_URI'] ?? '/');
        }
        return MODEL_LOGIN_URL . '?redirect=' . urlencode($redirect);
    }
}

if (!function_exists('model_asset')) {
    /**
     * 子站静态资源地址（带版本参数，便于缓存失效）
     */
    function model_asset($path)
    {
        $file = MODEL_ROOT . '/' . ltrim($path, '/');
        $ver = is_file($file) ? filemtime($file) : null;
        return '/' . ltrim($path, '/') . ($ver ? '?v=' . $ver : '');
    }
}

if (!function_exists('model_media')) {
    /**
     * 媒体资源地址归一化（模特子站）。
     *
     * 站点存在多套并行的上传目录，不能靠猜域名，改为「文件系统探测 + 规则兜底」：
     *
     *   1) 头像：      根/assets/images/uploads/avatars/...  → https://58.tl/assets/images/uploads/avatars/...
     *   2) 模特图：    根/assets/images/uploads/models/...   → https://58.tl/assets/images/uploads/models/...
     *   3) 商品图：    mall/assets/uploads/products/...      → https://mall.58.tl/assets/uploads/products/...
     *   4) 短剧封面：  mall/assets/uploads/dramas/...        → https://mall.58.tl/assets/uploads/dramas/...
     *
     * 数据库里存的通常是简写（如 `assets/uploads/models/202601/x.jpg`），
     * 因此在若干候选物理位置中探测第一个真实存在的文件，据此决定 URL 前缀；
     * 都探测不到时按「主站图片目录」兜底（与 User::avatarUrl 一致）。
     */
    function model_media($path)
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        // 绝对 URL / 协议相对 URL → 原样
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        // 已拼好的根相对路径 → 原样
        if ($path[0] === '/') {
            return $path;
        }

        $rel = ltrim($path, '/');

        // 归一到「uploads/xxx」形式，便于枚举候选位置
        $norm = $rel;
        if (strpos($norm, 'assets/uploads/') === 0) {
            $norm = 'uploads/' . substr($norm, strlen('assets/uploads/'));
        } elseif (strpos($norm, 'assets/images/uploads/') === 0) {
            $norm = 'uploads/' . substr($norm, strlen('assets/images/uploads/'));
        }
        // 裸文件名 → 归入模特上传目录
        if (strpos($norm, '/') === false) {
            $norm = 'uploads/models/' . $norm;
        }

        static $cache = [];

        // 候选物理路径 → URL 前缀（顺序即优先级）
        $candidates = [
            // 主站图片目录（头像 / 模特图）
            [APP_ROOT . '/assets/images/' . $norm, 'https://58.tl/assets/images/'],
            // mall 上传目录（商品图 / 短剧封面）
            [APP_ROOT . '/mall/assets/' . $norm,   'https://mall.58.tl/assets/'],
            // 根 assets 直连（历史 / 兜底）
            [APP_ROOT . '/assets/' . $norm,        'https://58.tl/assets/'],
            [APP_ROOT . '/mall/assets/images/' . $norm, 'https://mall.58.tl/assets/images/'],
        ];

        if (isset($cache[$norm])) {
            return $cache[$norm];
        }
        foreach ($candidates as $c) {
            if (is_file($c[0])) {
                return $cache[$norm] = $c[1] . $norm;
            }
        }
        // 都探测不到（如本地无上传文件）：按主站图片目录兜底
        return $cache[$norm] = 'https://58.tl/assets/images/' . $norm;
    }
}

if (!function_exists('model_img')) {
    /**
     * 模特头像 / 主图解析
     * 优先级：模特专属头像(avatar) → 账号头像(user_avatar) → 默认图
     */
    function model_img($model, $defaultFallback = true)
    {
        if (!empty($model['avatar'])) {
            return model_media($model['avatar']);
        }
        if (!empty($model['user_avatar'])) {
            return User::avatarUrl($model['user_avatar']);
        }
        return $defaultFallback ? 'https://58.tl/assets/images/default.jpg' : '';
    }
}

if (!function_exists('model_default_img')) {
    /**
     * 主站默认图（与 User::avatarUrl 的兜底一致）
     */
    function model_default_img()
    {
        return 'https://58.tl/assets/images/default.jpg';
    }
}

if (!function_exists('model_drama_slug_url')) {
    function model_drama_slug_url($id, $title)
    {
        return SeoHelper::dramaUrl($id, $title);
    }
}

// 当前登录用户
$modelUserId = (int)($_SESSION['user_id'] ?? 0);
$modelPdo    = $pdo;
$modelObj    = new Model($pdo);
require_once APP_ROOT . '/classes/Drama.php';
$dramaObj    = new Drama($pdo);

/**
 * 组装子站页头配置
 */
if (!function_exists('model_site_config')) {
    function model_site_config(array $overrides = [])
    {
        $defaults = [
            'title'       => '58 模特库 - 发现好看的模特与短剧',
            'description' => '58 模特库汇集人气模特个人主页、作品图集与短视频，并可发现模特参演的短剧作品，支持关注与在线申请加入。',
            'keywords'    => '58模特,模特库,模特,短剧,红果短剧,模特申请,模特招募',
            'canonical_url' => MODEL_BASE_URL . '/',
            'og_image'    => 'https://58.tl/assets/images/default.jpg',
            'logo_main'   => '58',
            'logo_sub'    => '模特库',
            'logo_tag'    => '模特 · 短剧',
            'nav_links'   => [
                ['url' => '/',                     'icon' => 'home',           'text' => '首页'],
                ['url' => '/list.php',             'icon' => 'camera',         'text' => '模特库'],
                ['url' => '/dramas.php',           'icon' => 'film',           'text' => '短剧'],
                ['url' => '/rankings.php',         'icon' => 'trophy',         'text' => '排行榜'],
                ['url' => '/apply.php',            'icon' => 'user-plus',      'text' => '申请加入'],
            ],
            'theme_color' => '#E8467C',
            'show_message' => false,
            'show_cart'   => false,
            'url_login'   => 'https://mall.58.tl/auth/login.php',
            'url_register' => 'https://mall.58.tl/auth/register.php',
            'url_dashboard' => 'https://mall.58.tl/user/dashboard.php',
            'url_logout'  => 'https://mall.58.tl/auth/logout.php',
            'footer_name' => '58 模特库 | 58 BlockCity',
            'body_class'  => 'model-shell-body',
            'main_class'  => 'm-main',
        ];
        return array_merge($defaults, $overrides);
    }
}
