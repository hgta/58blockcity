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

 

?>
