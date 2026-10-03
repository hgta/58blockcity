<?php
/**
 * 通用辅助函数
 */

/**
 * 转义HTML输出
 * @param string $value 要转义的值
 * @return string 转义后的值
 */
if (!function_exists('e')) {
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
}

/**
 * 格式化日期
 * @param string $date 日期字符串
 * @param string $format 格式，默认为Y-m-d
 * @return string 格式化后的日期
 */
if (!function_exists('formatDate')) {
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}
}

/**
 * 截断文本
 */
if (!function_exists('truncateText')) {
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length).$suffix;
}
}

/**
 * 获取状态对应的CSS类
 */
if (!function_exists('getStatusBadgeClass')) {
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'confirmed':
            return 'info';
        case 'visited':
            return 'primary';
        case 'completed':
            return 'success';
        case 'inactive':
            return 'secondary';
        default:
            return 'light';
    }
}
}

/**
 * 重定向到指定URL
 */
if (!function_exists('redirect')) {
function redirect($url, $statusCode = 302) {
    header('Location: '.$url, true, $statusCode);
    exit;
}
}

/**
 * 获取当前URL的基本路径
 */
if (!function_exists('baseUrl')) {
function baseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol.'://'.$_SERVER['HTTP_HOST'];
}
}

/**
 * 生成随机字符串
 */
if (!function_exists('generateRandomString')) {
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}
}

/**
 * 验证电子邮件格式
 */
if (!function_exists('isValidEmail')) {
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
}

/**
 * 城市名归一化
 *
 * 把用户在注册/补全表单里填写的城市（可能是拼音，或大小写不一致的名称）
 * 映射回 cities 城市数据库中的标准城市名；未收录的城市按原输入保留。
 *
 * @param string $input       用户输入
 * @param array  $cityOptions cities 表数据（每项含 name / pinyin）
 * @return string 标准城市名
 */
if (!function_exists('normalizeCityName')) {
function normalizeCityName($input, array $cityOptions = []) {
    $input = trim((string)$input);
    if ($input === '') {
        return '';
    }

    $lower = strtolower(str_replace([' ', '-'], '', $input));

    foreach ($cityOptions as $city) {
        $name = is_array($city) ? (string)($city['name'] ?? '') : (string)$city;
        if ($name === '') {
            continue;
        }
        $pinyin = is_array($city) ? strtolower((string)($city['pinyin'] ?? '')) : '';

        if ($name === $input || ($pinyin !== '' && $pinyin === $lower)) {
            return $name;
        }
    }

    // 去掉行政区划后缀再匹配（如「武汉市」→「武汉」，与 City::getCityByName 顺序一致）
    $stripped = preg_replace('/(市|地区|自治州|自治县|盟|省)$/u', '', $input);
    if ($stripped !== null && $stripped !== '' && $stripped !== $input) {
        foreach ($cityOptions as $city) {
            $name = is_array($city) ? (string)($city['name'] ?? '') : (string)$city;
            if ($name === $stripped) {
                return $name;
            }
        }
    }

    return $input;
}
}

/**
 * 城市筛选 WHERE 构造（归一化优先 + 双列 LIKE 兜底）
 *
 * 用于列表页城市筛选：先把拼音 / 别名（如 hangzhou、「武汉市」）归一化为
 * cities 标准名，再生成包含匹配条件，消除「输入拼音未点选 → 静默 0 结果」。
 * 未收录输入按原样包含匹配（保留「输入子串也能搜」的宽松语义）。
 *
 * @param string      $input      用户输入（GET city）
 * @param array       $allCities cities 表数据（每项含 name / pinyin）
 * @param string      $nameCol   SQL 中城市名列（如 c.name / o.city）
 * @param string|null $pinyinCol SQL 中拼音列（c.pinyin；null = 单列匹配，如 bct_orders 无拼音列）
 * @return array{where:string, params:string[], city:string}
 *   where  => SQL 片段（不含 AND 前缀；输入为空时为空串）
 *   params => 绑定参数
 *   city   => 归一化后的标准名（供输入框回显；未收录时为原输入）
 */
if (!function_exists('buildCityFilterWhere')) {
function buildCityFilterWhere($input, array $allCities, $nameCol = 'c.name', $pinyinCol = 'c.pinyin') {
    $input = trim((string)$input);
    if ($input === '') {
        return ['where' => '', 'params' => [], 'city' => ''];
    }

    $norm = normalizeCityName($input, $allCities);

    // LIKE 转义（| 作转义符，与 City::getCityByName 一致，避免 % _ 通配符干扰）
    $like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $norm) . '%';

    if ($pinyinCol) {
        return [
            'where'  => "({$nameCol} LIKE ? ESCAPE '|' OR {$pinyinCol} LIKE ? ESCAPE '|')",
            'params' => [$like, $like],
            'city'   => $norm,
        ];
    }
    return [
        'where'  => "{$nameCol} LIKE ? ESCAPE '|'",
        'params' => [$like],
        'city'   => $norm,
    ];
}
}

/**
 * 获取客户端IP地址
 */
if (!function_exists('getClientIp')) {
function getClientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
}

/**
 * 生成分页HTML
 */
if (!function_exists('generatePagination')) {
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<ul class="pagination">';
    
    // 上一页
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.'?page='.($currentPage - 1).'">&laquo; 上一页</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo; 上一页</span></li>';
    }
    
    // 页码
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.'?page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">'.$i.'</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.'?page='.$i.'">'.$i.'</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.'?page='.$totalPages.'">'.$totalPages.'</a></li>';
    }
    
    // 下一页
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.'?page='.($currentPage + 1).'">下一页 &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">下一页 &raquo;</span></li>';
    }
    
    $html .= '</ul>';
    return $html;
}
}

/**
 * 上传文件处理
 */
if (!function_exists('handleFileUpload')) {
function handleFileUpload($fieldName, $targetDir, $allowedTypes = [], $maxSize = 2097152) {
    if (!isset($_FILES[$fieldName])) {
        return ['success' => false, 'message' => '没有文件被上传'];
    }

    $file = $_FILES[$fieldName];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传错误: '.$file['error']];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowedTypes) && !in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => '不允许的文件类型'];
    }
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fileName = uniqid().'.'.$fileExt;
    $targetPath = rtrim($targetDir, '/').'/'.$fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $targetPath];
    } else {
        return ['success' => false, 'message' => '文件移动失败'];
    }
}
}

/**
 * 压缩上传图片：限制最大宽度，超过则等比缩放并转为 JPEG
 * （由 hufang/includes/functions.php 同名函数共享化而来，供任务站等新子站复用；
 *  使用 function_exists 守卫，避免与 hufang 站点函数重复定义冲突）
 * @param string $srcPath  源文件路径
 * @param string $dir      目标目录
 * @param string $origName 原始文件名
 * @param int    $maxWidth 最大宽度（像素）
 * @return string 最终存储路径（相对 uploads/...）
 */
if (!function_exists('compressImage')) {
function compressImage($srcPath, $dir, $origName, $maxWidth = 1200) {
    $info = getimagesize($srcPath);
    if (!$info) {
        return str_replace('../', '', $srcPath); // 非图片，返回原路径
    }

    $width  = $info[0];
    $height = $info[1];
    $mime   = $info['mime'];

    // 如果宽度未超过限制，直接返回原路径
    if ($width <= $maxWidth) {
        return str_replace('../', '', $srcPath);
    }

    // 计算新尺寸
    $newWidth  = $maxWidth;
    $newHeight = (int) round($height * ($maxWidth / $width));

    // 根据原图类型创建图像资源
    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($srcPath);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($srcPath);
            break;
        default:
            $src = imagecreatefromjpeg($srcPath);
    }

    if (!$src) {
        return str_replace('../', '', $srcPath);
    }

    // 创建新图
    $dst = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // 保存为 JPEG（截图不需要透明通道，体积更小）
    $newName = preg_replace('/\.[a-zA-Z]+$/', '.jpg', $origName);
    $newPath = $dir . $newName;
    imagejpeg($dst, $newPath, 85);

    // 释放内存，删除原文件
    imagedestroy($src);
    imagedestroy($dst);
    if ($newPath !== $srcPath && file_exists($srcPath)) {
        unlink($srcPath);
    }

    return str_replace('../', '', $newPath);
}
}

?>