<?php
// 配置部分
$imageDir = '../avatar/'; // 要扫描的图片文件夹路径
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$maxDisplayCount = 200; // 最大显示数量
$minFilenameLength = 8; // 最小文件名长度(不含扩展名)

require_once '../../config/database.php';

// 自定义str_contains函数用于PHP8.0以下版本
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

// 处理文件重命名和数据库更新请求
if (isset($_POST['rename']) && isset($_POST['oldname']) && isset($_POST['newname']) && isset($_POST['avatar_id'])) {
    $oldName = basename($_POST['oldname']);
    $newName = trim($_POST['newname']);
    $avatarId = trim($_POST['avatar_id']);
    
    // 修正标签处理
    $tags = [];
    if (isset($_POST['tags'])) {
        if (is_array($_POST['tags'])) {
            $tags = array_map('trim', $_POST['tags']);
        } else {
            $tags = array_map('trim', explode(',', $_POST['tags']));
        }
        $tags = array_filter($tags); // 移除空标签
    }
    
    // 安全检查
    if (file_exists($imageDir . $oldName) && 
        !empty($newName) && 
        !empty($avatarId) &&
        strpos($newName, '/') === false &&
        strpos($newName, '\\') === false) {
        
        $extension = pathinfo($oldName, PATHINFO_EXTENSION);
        $newNameWithExt = $newName . '.' . $extension;
		$oldfilename = pathinfo($oldName, PATHINFO_FILENAME);
        
        // 开始事务
        $pdo->beginTransaction();
        
        try {
            // 重命名文件
            if (rename($imageDir . $oldName, $imageDir . $newNameWithExt)) {
                // 检查是否已存在记录
                $stmt = $pdo->prepare("SELECT id FROM nft_avatars WHERE code = ? OR avatar_id = ?");
                $stmt->execute([$newName, $avatarId]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $nftId = $existing['id'];
                    // 更新现有记录
                    $stmt = $pdo->prepare("UPDATE nft_avatars SET code = ?, base_image = ?, avatar_id = ? WHERE id = ?");
                    $stmt->execute([$newName, $newNameWithExt, $avatarId, $nftId]);
                } else {
                    // 插入新记录
                    $stmt = $pdo->prepare("INSERT INTO nft_avatars (code, base_image, avatar_id, avatar_key) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$newName, $newNameWithExt, $avatarId, $oldfilename]);
                    $nftId = $pdo->lastInsertId();
                }
                
                // 处理标签
                if ($nftId && !empty($tags)) {
                    // 先删除旧的标签关联
                    $stmt = $pdo->prepare("DELETE FROM nft_tags WHERE nft_avatar_id = ?");
                    $stmt->execute([$nftId]);
                    
                    // 添加新标签
                    foreach ($tags as $tagName) {
                        $tagName = trim($tagName);
                        if (empty($tagName)) continue;
                        
                        // 查找或创建标签
                        $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                        $stmt->execute([$tagName]);
                        $tag = $stmt->fetch();
                        
                        if (!$tag) {
                            $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                            $stmt->execute([$tagName]);
                            $tagId = $pdo->lastInsertId();
                        } else {
                            $tagId = $tag['id'];
                        }
                        
                        // 关联标签
                        $stmt = $pdo->prepare("INSERT IGNORE INTO nft_tags (nft_avatar_id, tag_id) VALUES (?, ?)");
                        $stmt->execute([$nftId, $tagId]);
                    }
                }
                
                $pdo->commit();
                echo "<script>alert('操作成功！'); window.location.href = window.location.href;</script>";
                exit;
            } else {
                $pdo->rollBack();
                echo "<script>alert('文件重命名失败！');</script>";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<script>alert('数据库错误: " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('无效的输入数据！');</script>";
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


// 获取已存在的NFT信息和标签
$nftInfo = [];
$allTags = [];
try {
    // 获取NFT信息
    $stmt = $pdo->query("SELECT id, code, base_image, avatar_id FROM nft_avatars");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nftInfo[$row['base_image']] = $row;
    }
    
    // 获取所有标签
    $stmt = $pdo->query("SELECT id, name FROM tags ORDER BY name");
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取NFT的标签
    if (!empty($nftInfo)) {
        $nftIds = array_column($nftInfo, 'id');
        
        if (!empty($nftIds)) {
            $placeholders = implode(',', array_fill(0, count($nftIds), '?'));
            
            $stmt = $pdo->prepare("
                SELECT nt.nft_avatar_id, t.id as tag_id, t.name as tag_name 
                FROM nft_tags nt
                JOIN tags t ON nt.tag_id = t.id
                WHERE nt.nft_avatar_id IN ($placeholders)
            ");
            $stmt->execute($nftIds);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($nftInfo[$row['nft_avatar_id']])) {
                    if (!isset($nftInfo[$row['nft_avatar_id']]['tags'])) {
                        $nftInfo[$row['nft_avatar_id']]['tags'] = [];
                    }
                    $nftInfo[$row['nft_avatar_id']]['tags'][] = $row;
                }
            }
        }
    }
} catch (PDOException $e) {
    // 忽略错误，可能表不存在
    error_log("数据库错误: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片管理器</title>
     <link href="https://58.tl/assets/css/select2.min.css" rel="stylesheet" />
	
	<!-- 确保使用最新版Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
        .avatar-id {
            color: #666;
            font-size: 0.8em;
            margin-bottom: 5px;
        }
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        .tag {
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7em;
            color: #555;
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
        .rename-form input, .rename-form select {
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
        .select2-container {
            width: 100% !important;
            margin-bottom: 5px;
        }
		
		.select2-container--open .select2-dropdown {
    z-index: 10000; /* 确保下拉框在最上层 */
}

.rename-form {
    z-index: 100; /* 高于其他元素 */
    /* 其他原有样式 */
}

.select2-container {
    z-index: 10001; /* 确保选择器在最上层 */
    margin-bottom: 10px;
}

/* 修改或添加以下CSS规则 */
.image-item {
    position: relative;
    overflow: visible !important; /* 关键！防止下拉菜单被裁剪 */
}

.rename-form {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: white;
    z-index: 1050; /* 高于普通元素 */
    border: 1px solid #ddd;
    padding: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Select2相关样式 */
.select2-container {
    z-index: 1060 !important; /* 高于表单 */
    width: 100% !important;
    margin-bottom: 10px;
}

.select2-dropdown {
    z-index: 1070 !important; /* 最高层级 */
    position: absolute !important;
}

.select2-container--open .select2-dropdown {
    position: absolute !important;
    left: 0 !important;
    top: 100% !important;
    margin-top: 2px !important;
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
            $currentNftInfo = $nftInfo[$image] ?? null;
            $currentTags = $currentNftInfo['tags'] ?? [];
            ?>
            <div class="image-item">
                <div class="image-container" onclick="showRenameForm('<?php echo md5($image); ?>')">
                    <?php if ($ext === 'svg'): ?>
                        <object type="image/svg+xml" data="<?php echo $imageDir . $image; ?>">
                            SVG图像无法显示
                        </object>
                    <?php else: ?>
                        <img src="<?php echo $imageDir . $image; ?>" alt="<?php echo $image; ?>">
                    <?php endif; ?>
                </div>
                
                <div class="filename" title="<?php echo $image; ?>">
                    <?php echo strlen($filenameWithoutExt) > 20 ? substr($filenameWithoutExt, 0, 20).'...' : $filenameWithoutExt; ?>.<?php echo $ext; ?>
                </div>
                
                <?php if ($currentNftInfo): ?>
                    <div class="avatar-id">Avatar ID: <?php echo htmlspecialchars($currentNftInfo['avatar_id']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($currentTags)): ?>
                    <div class="tags">
                        <?php foreach ($currentTags as $tag): ?>
                            <span class="tag"><?php echo htmlspecialchars($tag['tag_name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div id="form-<?php echo md5($image); ?>" class="rename-form">
                    <form method="post">
                        <input type="hidden" name="oldname" value="<?php echo $image; ?>">
                        <input type="text" name="newname" 
                               value="<?php echo htmlspecialchars($filenameWithoutExt, ENT_QUOTES); ?>"
                               required
                               pattern="[^\/\\]+" 
                               title="不能包含斜杠或反斜杠">
                        <input type="text" name="avatar_id" 
                               value="<?php echo $currentNftInfo ? htmlspecialchars($currentNftInfo['avatar_id'], ENT_QUOTES) : ''; ?>"
                               required
                               placeholder="输入Avatar ID">
                        <select name="tags[]" class="tags-select" multiple="multiple">
                            <?php foreach ($allTags as $tag): ?>
                                <option value="<?php echo htmlspecialchars($tag['name']); ?>"
                                    <?php 
                                    if ($currentNftInfo) {
                                        foreach ($currentTags as $currentTag) {
                                            if ($currentTag['tag_id'] == $tag['id']) {
                                                echo ' selected';
                                                break;
                                            }
                                        }
                                    }
                                    ?>>
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="rename">保存</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let currentOpenForm = null;

function showRenameForm(formId) {
    // 隐藏当前打开的表单并销毁Select2实例
    if (currentOpenForm) {
        $(currentOpenForm).find('.tags-select').select2('destroy');
        currentOpenForm.style.display = 'none';
    }
    
    // 显示新的表单
    const form = document.getElementById('form-' + formId);
    if (form) {
        form.style.display = 'block';
        currentOpenForm = form;
        
        // 初始化Select2 - 关键修改
        $(form).find('.tags-select').select2({
            tags: true,
            tokenSeparators: [','],
            placeholder: "添加标签(用逗号分隔)",
            width: '100%',
            dropdownParent: $(form).closest('.image-item'), // 关键修改
            //dropdownParent: $('#select2-dropdown-root') // 替代原来的dropdownParent设置
			dropdownAutoWidth: true,
            closeOnSelect: false
        }).on('select2:open', function() {
            // 手动调整下拉菜单位置
            setTimeout(() => {
                const dropdown = $('.select2-dropdown');
                const input = $('.select2-search__field');
                dropdown.css({
                    'position': 'absolute',
                    'top': input.offset().top + input.outerHeight() + 'px',
                    'left': input.offset().left + 'px',
                    'width': input.outerWidth() + 'px'
                });
            }, 10);
        });
        
        // 点击页面其他地方关闭表单
        setTimeout(() => {
            document.addEventListener('click', closeFormOnClickOutside, true);
        }, 100);
    }
}

function closeFormOnClickOutside(e) {
    if (currentOpenForm && !currentOpenForm.contains(e.target)) {
        $(currentOpenForm).find('.tags-select').select2('destroy');
        currentOpenForm.style.display = 'none';
        currentOpenForm = null;
        document.removeEventListener('click', closeFormOnClickOutside, true);
    }
}
    </script>
	<div id="select2-dropdown-root"></div>
</body>
</html>