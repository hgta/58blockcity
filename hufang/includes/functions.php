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
 * @param string $text 原始文本
 * @param int $length 最大长度
 * @param string $suffix 后缀
 * @return string 截断后的文本
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
 * @param string $status 状态
 * @return string CSS类名
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
        case 'cancelled':
            return 'danger';
        case 'inactive':
            return 'secondary';
        default:
            return 'light';
    }
}
}

/**
 * 获取互访状态的中文标签与 CSS 类名
 * @param string $status 状态码
 * @return array ['label' => ..., 'class' => ...]
 */
function getVisitStatusLabel($status) {
    $map = [
        'pending'   => ['label' => '待确认', 'class' => 'warning'],
        'confirmed' => ['label' => '已确认', 'class' => 'info'],
        'completed' => ['label' => '已完成', 'class' => 'success'],
        'cancelled' => ['label' => '已取消', 'class' => 'danger'],
        'visited'   => ['label' => '已访问', 'class' => 'primary'],
        'returned'  => ['label' => '已回访', 'class' => 'primary'],
    ];
    return $map[$status] ?? ['label' => $status, 'class' => 'secondary'];
}

/**
 * 渲染互访圈卡片
 * @param array $circle 圈子数据
 * @param string|null $visitStatus 当前用户对该圈子的访问状态
 * @param string $extraActions 额外的操作按钮 HTML
 * @param string $extraBody 卡片主体额外 HTML（如统计、说明）
 * @param bool $compact 是否紧凑模式（用于后台小列表）
 * @return string HTML
 */
function renderCircleCard($circle, $visitStatus = null, $extraActions = '', $extraBody = '', $compact = false) {
    $badgeHtml = '';
    if ($visitStatus === 'completed') {
        $badgeHtml = '<div class="circle-visit-badge completed">已互访</div>';
    } elseif (in_array($visitStatus, ['visited', 'returned'])) {
        $badgeHtml = '<div class="circle-visit-badge visited">已访</div>';
    } elseif (in_array($visitStatus, ['pending', 'confirmed'])) {
        $badgeHtml = '<div class="circle-visit-badge pending">已访问</div>';
    }

    $desc = htmlspecialchars(mb_substr($circle['description'] ?? '暂无描述', 0, $compact ? 45 : 60, 'UTF-8') . (mb_strlen($circle['description'] ?? '') > ($compact ? 45 : 60) ? '...' : ''));
    $html = '<div class="circle-card" style="position:relative;">';
    $html .= $badgeHtml;
    $html .= '<div class="circle-header">';
    $html .= '<img src="../assets/images/' . e($circle['avatar'] ?? 'default.jpg') . '" class="circle-avatar" alt="' . e($circle['username'] ?? '') . '">';
    $html .= '<div class="circle-title">';
    $html .= '<h3>' . e($circle['name']) . '</h3>';
    $html .= '<span class="circle-owner"><i class="fas fa-user"></i> ' . e($circle['username'] ?? '') . '</span>';
    $html .= '</div></div>';
    $html .= '<div class="circle-body">';
    $html .= '<div class="circle-location"><i class="fas fa-map-marker-alt"></i> ' . e($circle['city']) . '<span class="circle-category">' . ($circle['block_count'] ?? 0) . ' 区块</span></div>';
    $html .= '<div class="circle-description">' . nl2br($desc) . '</div>';
    if ($extraBody) {
        $html .= $extraBody;
    }
    $html .= '</div>';
    $html .= '<div class="circle-actions">';
    $html .= '<a href="circles/view.php?id=' . (int)$circle['id'] . '" class="btn btn-primary"><i class="fas fa-eye"></i> 详情</a>';
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != ($circle['user_id'] ?? 0)) {
        $html .= '<a href="circles/view.php?id=' . (int)$circle['id'] . '" class="btn btn-outline-primary"><i class="fas fa-handshake"></i> 互访</a>';
    }
    $html .= $extraActions;
    $html .= '</div></div>';
    return $html;
}

/**
 * 渲染互访记录项
 * @param array $visit 访问记录
 * @param bool $showUser 是否显示访问者头像
 * @param string $extraActions 额外的操作按钮 HTML
 * @return string HTML
 */
function renderVisitItem($visit, $showUser = true, $extraActions = '') {
    $statusInfo = getVisitStatusLabel($visit['status']);
    $html = '<div class="visit-item status-' . e($visit['status']) . '">';
    if ($showUser) {
        $html .= '<div class="visit-user">';
        $html .= '<img src="../assets/images/' . e($visit['avatar'] ?? 'default.jpg') . '" class="avatar" alt="">';
        $html .= '<span class="username">' . e($visit['username'] ?? '') . '</span>';
        $html .= '</div>';
    }
    $html .= '<div class="visit-info">';
    $html .= '<div class="info-row"><span class="label">互访圈:</span><span class="value">' . e($visit['circle_name'] ?? '') . '</span></div>';
    $html .= '<div class="info-row"><span class="label">状态:</span><span class="status-badge badge-' . $statusInfo['class'] . '">' . $statusInfo['label'] . '</span></div>';
    if (!empty($visit['visit_date'])) {
        $html .= '<div class="info-row"><span class="label">访问日期:</span><span class="value">' . e($visit['visit_date']) . '</span></div>';
    }
    if (!empty($visit['return_date'])) {
        $html .= '<div class="info-row"><span class="label">回访日期:</span><span class="value">' . e($visit['return_date']) . '</span></div>';
    }
    if (!empty($visit['created_at'])) {
        $html .= '<div class="info-row"><span class="label">申请时间:</span><span class="value">' . e($visit['created_at']) . '</span></div>';
    }
    $html .= '</div>';
    if ($extraActions) {
        $html .= '<div class="visit-actions">' . $extraActions . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 渲染空状态
 * @param string $icon FontAwesome 图标类名（不含 fas）
 * @param string $title 标题
 * @param string $message 说明文字
 * @param string $actionHtml 底部操作按钮 HTML
 * @return string HTML
 */
function renderEmptyState($icon, $title, $message, $actionHtml = '') {
    $html = '<div class="empty-state">';
    $html .= '<div class="empty-icon"><i class="fas fa-' . e($icon) . '"></i></div>';
    $html .= '<h3>' . e($title) . '</h3>';
    if ($message) {
        $html .= '<p>' . e($message) . '</p>';
    }
    if ($actionHtml) {
        $html .= $actionHtml;
    }
    $html .= '</div>';
    return $html;
}

/**
 * 重定向到指定URL
 * @param string $url 目标URL
 * @param int $statusCode HTTP状态码
 */
if (!function_exists('redirect')) {
function redirect($url, $statusCode = 302) {
    header('Location: '.$url, true, $statusCode);
    exit;
}
}

/**
 * 获取当前URL的基本路径
 * @return string 基本URL
 */
if (!function_exists('baseUrl')) {
function baseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol.'://'.$_SERVER['HTTP_HOST'];
}
}

/**
 * 生成随机字符串
 * @param int $length 长度
 * @return string 随机字符串
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
 * @param string $email 电子邮件地址
 * @return bool 是否有效
 */
if (!function_exists('isValidEmail')) {
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
}

/**
 * 获取客户端IP地址
 * @return string IP地址
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
 * @param int $currentPage 当前页码
 * @param int $totalPages 总页数
 * @param string $baseUrl 基础URL
 * @return string 分页HTML
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
 * @param string $fieldName 文件字段名
 * @param string $targetDir 目标目录
 * @param array $allowedTypes 允许的文件类型
 * @param int $maxSize 最大文件大小（字节）
 * @return array 包含成功状态和消息的数组
 */
if (!function_exists('handleFileUpload')) {
function handleFileUpload($fieldName, $targetDir, $allowedTypes = [], $maxSize = 2097152) {
    if (!isset($_FILES[$fieldName])) {
        return ['success' => false, 'message' => '没有文件被上传'];
    }

    $file = $_FILES[$fieldName];
    
    // 检查错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传错误: '.$file['error']];
    }
    
    // 检查文件大小
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }
    
    // 检查文件类型
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowedTypes) && !in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => '不允许的文件类型'];
    }
    
    // 创建目标目录（如果不存在）
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // 生成唯一文件名
    $fileName = uniqid().'.'.$fileExt;
    $targetPath = rtrim($targetDir, '/').'/'.$fileName;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $targetPath];
    } else {
        return ['success' => false, 'message' => '文件移动失败'];
    }
}
}
/**
 * 输出 CSRF 隐藏字段 HTML
 * @return string
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

/**
 * 校验 POST 请求的 CSRF token，失败则终止
 * 复用 auth.php 的 validateCsrfToken()（无参版，自动从 $_POST 读取）
 */
function requireCsrf() {
    validateCsrfToken();
}

/**
 * 压缩上传图片：限制最大宽度，超过则等比缩放并转为 JPEG
 * @param string $srcPath  源文件路径
 * @param string $dir      目标目录
 * @param string $origName 原始文件名
 * @param int    $maxWidth 最大宽度（像素）
 * @return string 最终存储路径（相对 uploads/...）
 */
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
