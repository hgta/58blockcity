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
     * 媒体资源地址归一化：
     *   - 以 http(s):// 开头 → 原样返回
     *   - assets/ 开头        → 走主站域名（上传目录在仓库根）
     *   - 其他相对路径        → 走主站域名
     */
    function model_media($path)
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return 'https://58.tl/' . ltrim($path, '/');
    }
}

if (!function_exists('model_img')) {
    /**
     * 模特头像解析（模特专属头像优先，其次账号头像，最后默认图）
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
            'og_image'    => 'https://58.tl/assets/images/og-mall.jpg',
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
