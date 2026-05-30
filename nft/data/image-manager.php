<?php
// 配置部分
$imageDir = '../../assets/nfts/'; // 要扫描的图片文件夹路径
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$maxDisplayCount = 200; // 最大显示数量
$minFilenameLength = 8; // 最小文件名长度(不含扩展名)

// 自定义str_contains函数用于PHP8.0以下版本
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

// 处理文件重命名请求
if (isset($_POST['rename']) && isset($_POST['oldname']) && isset($_POST['newname'])) {
    $oldName = basename($_POST['oldname']); // 防止目录遍历
    $newName = trim($_POST['newname']);
    
    // 安全检查
    if (file_exists($imageDir . $oldName) && 
        !empty($newName) && 
        strpos($newName, '/') === false &&
        strpos($newName, '\\') === false) {
        
        $extension = pathinfo($oldName, PATHINFO_EXTENSION);
        $newNameWithExt = $newName . '.' . $extension;
        
        if (rename($imageDir . $oldName, $imageDir . $newNameWithExt)) {
            echo "<script>alert('文件名修改成功！'); window.location.href = window.location.href;</script>";
            exit;
        } else {
            echo "<script>alert('文件名修改失败！');</script>";
        }
    } else {
        echo "<script>alert('无效的文件名！');</script>";
    }
}

// 获取符合条件的图片文件
function getFilteredImageFiles($dir, $extensions, $maxCount, $minLength) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    $count = 0;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($count >= $maxCount) break;
        
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        $filename = pathinfo($item, PATHINFO_FILENAME);
        
        if (in_array($ext, $extensions) && strlen($filename) >= $minLength) {
            $files[] = $item;
            $count++;
        }
    }
    
    return $files;
}

$images = getFilteredImageFiles($imageDir, $allowedExtensions, $maxDisplayCount, $minFilenameLength);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片管理器</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .image-item {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            position: relative;
        }
        .image-container {
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            cursor: pointer;
            overflow: hidden;
        }
        .image-item img, .image-item object {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .filename {
            margin: 10px 0;
            font-weight: bold;
            word-break: break-all;
            font-size: 0.9em;
        }
        .rename-form {
            display: none;
            margin-top: 10px;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            z-index: 10;
            border: 1px solid #ddd;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .rename-form input {
            width: calc(100% - 20px);
            padding: 5px;
            margin-bottom: 5px;
        }
        .rename-form button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            width: 100%;
        }
        .rename-form button:hover {
            background: #45a049;
        }
        .info-bar {
            background: #f5f5f5;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>图片管理器</h1>
    <div class="info-bar">
        <p>共找到 <?php echo count($images); ?> 张图片 (只显示文件名长度≥<?php echo $minFilenameLength; ?>且扩展名为<?php echo implode(', ', $allowedExtensions); ?>的文件，最多显示<?php echo $maxDisplayCount; ?>张)</p>
    </div>
    
    <div class="gallery">
        <?php foreach ($images as $image): ?>
            <?php 
            $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
            $filenameWithoutExt = pathinfo($image, PATHINFO_FILENAME);
            ?>
            <div class="image-item">
                <div class="image-container" onclick="showRenameForm('<?php echo md5($image); ?>')">
                    <?php if ($ext === 'svg'): ?>
                        <object type="image/svg+xml" data="<?php echo 'https://58.tl/assets/nfts/' . $image;//$imageDir . $image; ?>">
                            SVG图像无法显示
                        </object>
                    <?php else: ?>
                        <img src="<?php echo 'https://58.tl/assets/nfts/' . $image;//$imageDir . $image; ?>" alt="<?php echo $image; ?>">
                    <?php endif; ?>
                </div>
                
                <div class="filename" title="<?php echo $image; ?>">
                    <?php echo strlen($filenameWithoutExt) > 20 ? substr($filenameWithoutExt, 0, 20).'...' : $filenameWithoutExt; ?>.<?php echo $ext; ?>
                </div>
                
                <div id="form-<?php echo md5($image); ?>" class="rename-form">
                    <form method="post">
                        <input type="hidden" name="oldname" value="<?php echo $image; ?>">
                        <input type="text" name="newname" 
                               value="<?php echo htmlspecialchars($filenameWithoutExt, ENT_QUOTES); ?>"
                               required
                               pattern="[^\/\\]+" 
                               title="不能包含斜杠或反斜杠">
                        <button type="submit" name="rename">重命名</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        let currentOpenForm = null;
        
        function showRenameForm(formId) {
            // 隐藏当前打开的表单
            if (currentOpenForm) {
                currentOpenForm.style.display = 'none';
            }
            
            // 显示新的表单
            const form = document.getElementById('form-' + formId);
            if (form) {
                form.style.display = 'block';
                currentOpenForm = form;
                
                // 点击页面其他地方关闭表单
                setTimeout(() => {
                    document.addEventListener('click', closeFormOnClickOutside, true);
                }, 100);
            }
        }
        
        function closeFormOnClickOutside(e) {
            if (currentOpenForm && !currentOpenForm.contains(e.target)) {
                currentOpenForm.style.display = 'none';
                currentOpenForm = null;
                document.removeEventListener('click', closeFormOnClickOutside, true);
            }
        }
        
        // 简单的MD5函数用于生成唯一ID
        function md5(input) {
            return input.split('').reduce((acc, char) => {
                return (acc << 5) - acc + char.charCodeAt(0);
            }, 0);
        }
    </script>
</body>
</html>