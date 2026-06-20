<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';
require_once '../../classes/Category.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$product = new Product($pdo);

// AJAX 上传视频截帧图片（返回 JSON 路径，避免 base64 超过 post_max_size）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_upload_thumbnail'])) {
    header('Content-Type: application/json');
    if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => '文件上传失败']);
        exit;
    }
    $result = uploadImage($_FILES['thumbnail'], 800, 800, 85);
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'file_path' => $result['file_path'],
            'thumb_path' => $result['thumb_path'] ?? '',
            'url' => '../' . $result['file_path'],
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit;
}
$category = new Category($pdo);

// 获取店铺ID
$shopId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 如果没有传ID，取用户最新店铺
if (!$shopId) {
    $userShop = $shop->getShopByUserId($_SESSION['user_id']);
    if (!$userShop) {
        header('Location: create.php');
        exit;
    }
    $shopId = $userShop['id'];
}

// 加载目标店铺并验证权限
$userShop = $shop->getShopById($shopId);
if (!$userShop || ($userShop['user_id'] != $_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin')) {
    // 用户无权访问该店铺，跳转到自己的店铺
    $myShop = $shop->getShopByUserId($_SESSION['user_id']);
    if ($myShop) {
        header('Location: products.php?id=' . $myShop['id']);
    } else {
        header('Location: create.php');
    }
    exit;
}

// 获取操作类型
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 获取商品分类（树形结构）
function getCategoryTreeForSelect($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM product_categories ORDER BY parent_id, sort_order, name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == 0) {
            $tree[$cat['id']] = $cat;
            $tree[$cat['id']]['children'] = [];
        }
    }
    foreach ($categories as $cat) {
        if ($cat['parent_id'] != 0 && isset($tree[$cat['parent_id']])) {
            $tree[$cat['parent_id']]['children'][] = $cat;
        }
    }
    return $tree;
}
$categoryTree = getCategoryTreeForSelect($pdo);

$error = '';
$success = '';
$videoError = '';
$videoSuccess = '';

// 处理添加商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $categoryId = intval($_POST['category_id']);
    $priceBct = intval($_POST['price_bct']);
    $priceCny = floatval($_POST['price_cny']);
    $stock = intval($_POST['stock']);
    $status = $_POST['status'];
    
    // 基本验证
    if (empty($name)) {
        $error = '商品名称不能为空';
    } elseif (empty($description)) {
        $error = '商品描述不能为空';
    } elseif ($categoryId <= 0) {
        $error = '请选择商品分类';
    } elseif ($priceBct <= 0) {
        $error = '人气值价格必须大于0';
    } elseif ($stock < 0) {
        $error = '库存不能为负数';
    } else {
        try {
            // 处理主图上传
            $mainImage = '';
            $thumbImage = '';
            if (isset($_FILES['main_image'])) {
                if ($_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadImage($_FILES['main_image'], 1200, 1200, 85);
                    if ($uploadResult['success']) {
                        $mainImage = $uploadResult['file_path'];
                        $thumbImage = $uploadResult['thumb_path'] ?? '';
                    } else {
                        $error = $uploadResult['error'];
                    }
                } elseif ($_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $error = '主图上传失败（错误码：' . $_FILES['main_image']['error'] . '），请检查文件大小或格式';
                }
            }

            // 处理视频上传或链接（移到主图检查之前）
            $videoUrl = '';
            if (isset($_FILES['product_video'])) {
                if ($_FILES['product_video']['error'] === UPLOAD_ERR_OK) {
                    $videoResult = uploadVideo($_FILES['product_video']);
                    if ($videoResult['success']) {
                        $videoUrl = $videoResult['file_path'];
                    } else {
                        $videoError = $videoResult['error'];
                    }
                } elseif ($_FILES['product_video']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $videoError = '视频上传失败（错误码：' . $_FILES['product_video']['error'] . '），请检查文件大小或格式';
                }
            }
            if (!$error && empty($videoUrl) && !empty($_POST['video_link'])) {
                $videoUrl = trim($_POST['video_link']);
            }

            // 如果没有主图，使用 AJAX 上传的截帧路径或 base64 解码（与视频URL独立）
            if (!$error && empty($mainImage)) {
                // 优先使用 AJAX 上传的路径
                if (!empty($_POST['video_thumb_path'])) {
                    $mainImage = $_POST['video_thumb_path'];
                    $thumbImage = $_POST['video_thumb_thumb'] ?? '';
                } elseif (!empty($_POST['video_thumbnail'])) {
                    // 兼容旧 base64 方式
                    $thumbResult = decodeBase64ToImage($_POST['video_thumbnail'], 800);
                    if ($thumbResult['success']) {
                        $mainImage = $thumbResult['file_path'];
                        $thumbImage = $thumbResult['thumb_path'] ?? '';
                    } else {
                        $error = '封面图片生成失败，请手动上传主图';
                    }
                }
            }

            // 主图检查
            if (!$error && empty($mainImage)) {
                if (!empty($videoUrl)) {
                    $error = '请上传主图或从视频中截取封面';
                } else {
                    $error = '请上传商品主图';
                }
            }

            // 处理多图上传
            $extraImages = [];
            if (!$error && isset($_FILES['extra_images']) && !empty($_FILES['extra_images']['name'][0])) {
                $imgError = '';
                $extraImages = uploadMultipleImages($_FILES['extra_images'], $imgError);
                if ($imgError) {
                    $error = '副图上传失败：' . $imgError;
                }
            }

            if (!$error) {
                // 准备商品数据
                $productData = [
                    'shop_id' => $shopId,
                    'category_id' => $categoryId,
                    'name' => $name,
                    'description' => $description,
                    'main_image' => $mainImage,
                    'thumb_image' => $thumbImage ?: null,
                    'images' => !empty($extraImages) ? json_encode($extraImages) : null,
                    'video_url' => $videoUrl ?: null,
                    'price_type' => 'bct',
                    'price_bct' => $priceBct,
                    'price_cny' => $priceCny,
                    'stock' => $stock,
                    'status' => $status,
                    'is_recommended' => isset($_POST['is_recommended']) ? 1 : 0
                ];

                // 创建商品
                $productId = $product->createProduct($productData);

                if ($productId) {
                    $success = '商品添加成功！';
                    // 重定向到商品列表
                    header('Location: products.php?id=' . $shopId . '&success=' . urlencode($success));
                    exit;
                } else {
                    $error = '商品添加失败，请稍后重试';
                }
            }
        } catch (Exception $e) {
            $error = '系统错误：' . $e->getMessage();
        }
    }
}

// 处理编辑商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $productId = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $categoryId = intval($_POST['category_id']);
    $priceBct = intval($_POST['price_bct']);
    $priceCny = floatval($_POST['price_cny']);
    $stock = intval($_POST['stock']);
    $status = $_POST['status'];
    
    // 基本验证
    if (empty($name)) {
        $error = '商品名称不能为空';
    } elseif (empty($description)) {
        $error = '商品描述不能为空';
    } elseif ($categoryId <= 0) {
        $error = '请选择商品分类';
    } elseif ($priceBct <= 0) {
        $error = '人气值价格必须大于0';
    } elseif ($stock < 0) {
        $error = '库存不能为负数';
    } else {
        try {
            // 处理主图上传（编辑时主图可选）
            $mainImage = '';
            $thumbImage = '';
            if (isset($_FILES['main_image'])) {
                if ($_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadImage($_FILES['main_image'], 1200, 1200, 85);
                    if ($uploadResult['success']) {
                        $mainImage = $uploadResult['file_path'];
                        $thumbImage = $uploadResult['thumb_path'] ?? '';
                    } else {
                        $error = $uploadResult['error'];
                    }
                } elseif ($_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $error = '主图上传失败（错误码：' . $_FILES['main_image']['error'] . '），请检查文件大小或格式';
                }
            }

            // 处理视频上传或链接（移到前面）
            $videoUrl = null;
            if (isset($_FILES['product_video'])) {
                if ($_FILES['product_video']['error'] === UPLOAD_ERR_OK) {
                    $videoResult = uploadVideo($_FILES['product_video']);
                    if ($videoResult['success']) {
                        $videoUrl = $videoResult['file_path'];
                    } else {
                        $videoError = $videoResult['error'];
                    }
                } elseif ($_FILES['product_video']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $videoError = '视频上传失败（错误码：' . $_FILES['product_video']['error'] . '），请检查文件大小或格式';
                }
            }
            if (!$error && $videoUrl === null && !empty($_POST['video_link'])) {
                $videoUrl = trim($_POST['video_link']);
            }
            // 用户点击了移除视频按钮
            if (!$error && isset($_POST['remove_video']) && $_POST['remove_video'] === '1') {
                $videoUrl = null;
            }

            // 如果没新主图，使用 AJAX 上传的截帧路径或 base64 解码（与视频URL独立）
            if (!$error && empty($mainImage)) {
                if (!empty($_POST['video_thumb_path'])) {
                    $mainImage = $_POST['video_thumb_path'];
                    $thumbImage = $_POST['video_thumb_thumb'] ?? '';
                } elseif (!empty($_POST['video_thumbnail'])) {
                    $thumbResult = decodeBase64ToImage($_POST['video_thumbnail'], 800);
                    if ($thumbResult['success']) {
                        $mainImage = $thumbResult['file_path'];
                        $thumbImage = $thumbResult['thumb_path'] ?? '';
                    } else {
                        $error = '封面图片生成失败，请手动上传主图';
                    }
                }
            }

            // 处理多图上传
            $extraImages = [];
            if (!$error && isset($_FILES['extra_images']) && !empty($_FILES['extra_images']['name'][0])) {
                $imgError = '';
                $extraImages = uploadMultipleImages($_FILES['extra_images'], $imgError);
                if ($imgError) {
                    $error = '副图上传失败：' . $imgError;
                }
            }

            if (!$error) {
                // 准备更新数据
                $updateData = [
                    'name' => $name,
                    'description' => $description,
                    'category_id' => $categoryId,
                    'price_bct' => $priceBct,
                    'price_cny' => $priceCny,
                    'stock' => $stock,
                    'status' => $status,
                    'video_url' => $videoUrl,
                    'is_recommended' => isset($_POST['is_recommended']) ? 1 : 0
                ];

                // 支持移动到其他店铺（仅限该用户拥有的店铺）
                if (!empty($_POST['shop_id'])) {
                    $targetShopId = intval($_POST['shop_id']);
                    $userShops = $shop->getUserShops($_SESSION['user_id']);
                    $userShopIds = array_column($userShops, 'id');
                    if (in_array($targetShopId, $userShopIds)) {
                        $updateData['shop_id'] = $targetShopId;
                    }
                }

                if ($mainImage) {
                    $updateData['main_image'] = $mainImage;
                    $updateData['thumb_image'] = $thumbImage ?: null;
                }

                // 合并保留的旧副图与新上传的副图
                $existingImages = [];
                if (isset($_POST['keep_images'])) {
                    $existingImages = array_values(array_filter($_POST['keep_images'], 'strlen'));
                }
                $allImages = array_merge($existingImages, $extraImages);
                if (!empty($allImages)) {
                    $updateData['images'] = json_encode(array_values($allImages));
                } else {
                    $updateData['images'] = null;
                }

                // 更新商品
                if ($product->updateProduct($productId, $updateData)) {
                    $success = '商品更新成功！';
                    // 重定向到商品列表
                    header('Location: products.php?id=' . $shopId . '&success=' . urlencode($success));
                    exit;
                } else {
                    $error = '商品更新失败，请稍后重试';
                }
            }
        } catch (Exception $e) {
            $error = '系统错误：' . $e->getMessage();
        }
    }
}

// 处理下架商品（软删除）
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);

    // 验证商品属于当前店铺
    $productInfo = $product->getProductById($productId, true);
    if ($productInfo && $productInfo['shop_id'] == $shopId) {
        if ($product->updateProduct($productId, ['status' => 'inactive'])) {
            $success = '商品已下架';
        } else {
            $error = '商品下架失败';
        }
    } else {
        $error = '商品不存在或无权操作';
    }

    header('Location: products.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理彻底删除商品
if (isset($_GET['action']) && $_GET['action'] === 'hard_delete' && isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
    $result = $product->deleteProduct($productId, $shopId);
    if ($result['success']) {
        $success = '商品已彻底删除';
    } else {
        $error = $result['error'];
    }
    header('Location: products.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理上架商品
if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
    $productInfo = $product->getProductById($productId, true);
    if ($productInfo && $productInfo['shop_id'] == $shopId) {
        if ($product->updateProduct($productId, ['status' => 'active'])) {
            $success = '商品已上架';
        } else {
            $error = '商品上架失败';
        }
    } else {
        $error = '商品不存在或无权操作';
    }
    header('Location: products.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && !empty($_POST['product_ids'])) {
    $batchAction = $_POST['batch_action'];
    $productIds = array_map('intval', $_POST['product_ids']);

    if ($batchAction === 'activate') {
        $product->batchUpdateStatus($productIds, $shopId, 'active');
        $success = '已批量上架 ' . count($productIds) . ' 个商品';
    } elseif ($batchAction === 'deactivate') {
        $product->batchUpdateStatus($productIds, $shopId, 'inactive');
        $success = '已批量下架 ' . count($productIds) . ' 个商品';
    } elseif ($batchAction === 'delete') {
        $product->batchDeleteProducts($productIds, $shopId);
        $success = '已批量删除 ' . count($productIds) . ' 个商品';
    }

    header('Location: products.php?id=' . $shopId . '&success=' . urlencode($success));
    exit;
}

// 图片压缩与上传函数
function uploadImage($file, $maxWidth = 1200, $maxHeight = 1200, $quality = 85) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB（前端压缩前允许更大）

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => '只允许上传 JPG, PNG, GIF, WebP 格式的图片'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => '图片大小不能超过 10MB'];
    }

    // 创建上传目录（按日期分目录，减少单目录文件数）
    // products.php 位于 mall/shop/，Web 根目录是 mall/，assets/ 在 Web 根目录下，因此用 ../
    $subDir = date('Ym') . '/';
    $uploadDir = __DIR__ . '/../assets/uploads/products/' . $subDir;
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'error' => '无法创建上传目录，请联系管理员检查目录权限：' . dirname($uploadDir) . '（请确保 mall/assets/uploads/ 及其上级目录对 Web 服务器可写）'];
        }
    }

    // 生成唯一文件名（统一用 jpg 以减小体积）
    $fileName = uniqid() . '_' . time() . '.jpg';
    $filePath = $uploadDir . $fileName;
    $relativePath = 'assets/uploads/products/' . $subDir . $fileName;

    // 读取原图
    $src = null;
    switch ($file['type']) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $src = @imagecreatefromgif($file['tmp_name']);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($file['tmp_name']);
            break;
    }

    if (!$src) {
        // GD 读取失败，尝试原样保存
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => true, 'file_path' => $relativePath];
        }
        return ['success' => false, 'error' => '图片处理失败'];
    }

    // 获取原图尺寸
    $origW = imagesx($src);
    $origH = imagesy($src);

    // 计算压缩后尺寸（保持比例）
    $ratio = min($maxWidth / $origW, $maxHeight / $origH, 1.0);
    $newW = (int) round($origW * $ratio);
    $newH = (int) round($origH * $ratio);

    // 创建新画布
    $dst = imagecreatetruecolor($newW, $newH);

    // PNG/GIF 保留透明背景
    if ($file['type'] === 'image/png' || $file['type'] === 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
    }

    // 缩放
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);

    // 保存为 JPEG（质量 85%，体积较小）
    $result = imagejpeg($dst, $filePath, $quality);
    imagedestroy($dst);

    if ($result) {
        // 生成缩略图（400x400，质量80%）
        $thumbFileName = 'thumb_' . $fileName;
        $thumbFilePath = $uploadDir . $thumbFileName;
        $thumbRelativePath = 'assets/uploads/products/' . $subDir . $thumbFileName;
        
        $thumbSize = 400;
        $thumbRatio = min($thumbSize / $origW, $thumbSize / $origH, 1.0);
        $thumbW = (int) round($origW * $thumbRatio);
        $thumbH = (int) round($origH * $thumbRatio);
        
        $thumbDst = imagecreatetruecolor($thumbW, $thumbH);
        if ($file['type'] === 'image/png' || $file['type'] === 'image/gif') {
            imagealphablending($thumbDst, false);
            imagesavealpha($thumbDst, true);
            $transparent = imagecolorallocatealpha($thumbDst, 0, 0, 0, 127);
            imagefill($thumbDst, 0, 0, $transparent);
        }
        
        // 重新读取原图生成缩略图（因为原图资源已被释放，从保存的文件读取）
        $src2 = imagecreatefromjpeg($filePath);
        if ($src2) {
            imagecopyresampled($thumbDst, $src2, 0, 0, 0, 0, $thumbW, $thumbH, $newW, $newH);
            imagedestroy($src2);
            imagejpeg($thumbDst, $thumbFilePath, 80);
            imagedestroy($thumbDst);
        }
        
        return ['success' => true, 'file_path' => $relativePath, 'thumb_path' => $thumbRelativePath];
    }
    return ['success' => false, 'error' => '图片保存失败'];
}

// 多图上传辅助函数
function uploadMultipleImages($files, &$errorMsg) {
    $images = [];
    if (empty($files) || empty($files['name'][0])) {
        return $images;
    }
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $file = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $result = uploadImage($file, 1200, 1200, 85);
        if ($result['success']) {
            $images[] = $result['file_path'];
        } else {
            $errorMsg = $result['error'];
        }
    }
    return $images;
}

// 视频上传函数
function uploadVideo($file) {
    $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
    $allowedExts  = ['mp4', 'webm', 'ogg'];
    $maxSize = 50 * 1024 * 1024; // 50MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return ['success' => false, 'error' => '只允许上传 MP4、WebM、OGG 格式的视频'];
    }
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => '视频格式不支持：' . htmlspecialchars($file['type'])];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => '视频大小不能超过 50MB'];
    }

    $uploadDir = __DIR__ . '/../assets/uploads/videos/';
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'error' => '无法创建视频上传目录，请联系管理员检查权限'];
        }
    }

    $fileName = uniqid() . '_' . time() . '.' . $ext;
    $filePath = $uploadDir . $fileName;
    $relativePath = 'assets/uploads/videos/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'file_path' => $relativePath];
    }
    return ['success' => false, 'error' => '视频保存失败'];
}

// Base64 解码为图片（视频截帧用）
function decodeBase64ToImage($base64, $maxWidth = 800) {
    // 去掉 data:image/jpeg;base64, 前缀
    if (strpos($base64, 'data:') === 0) {
        $base64 = substr($base64, strpos($base64, ',') + 1);
    }
    $imgData = base64_decode($base64);
    if (!$imgData) return ['success' => false];

    $src = @imagecreatefromstring($imgData);
    if (!$src) return ['success' => false];

    $origW = imagesx($src);
    $origH = imagesy($src);
    $ratio = min($maxWidth / $origW, 1.0);
    $newW = (int) round($origW * $ratio);
    $newH = (int) round($origH * $ratio);

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);

    $subDir = date('Ym') . '/';
    $uploadDir = __DIR__ . '/../assets/uploads/products/' . $subDir;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    $fileName = 'vid_' . uniqid() . '_' . time() . '.jpg';
    $filePath = $uploadDir . $fileName;
    $relativePath = 'assets/uploads/products/' . $subDir . $fileName;

    if (!imagejpeg($dst, $filePath, 85)) {
        imagedestroy($dst);
        return ['success' => false];
    }
    imagedestroy($dst);

    // 生成缩略图
    $thumbFileName = 'thumb_vid_' . uniqid() . '.jpg';
    $thumbPath = $uploadDir . $thumbFileName;
    $thumbRelative = 'assets/uploads/products/' . $subDir . $thumbFileName;
    $thumbSize = 400;
    $thumbR = min($thumbSize / $origW, $thumbSize / $origH, 1.0);
    $tw = (int) round($origW * $thumbR);
    $th = (int) round($origH * $thumbR);
    $tdst = imagecreatetruecolor($tw, $th);
    $src2 = @imagecreatefromjpeg($filePath);
    if ($src2) {
        imagecopyresampled($tdst, $src2, 0, 0, 0, 0, $tw, $th, $newW, $newH);
        imagejpeg($tdst, $thumbPath, 80);
        imagedestroy($src2);
    }
    imagedestroy($tdst);

    return ['success' => true, 'file_path' => $relativePath, 'thumb_path' => $thumbRelative];
}

// 显示成功消息
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="manage.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i> 数据看板
                    </a>
                    <a href="products.php?id=<?= $shopId ?>" class="nav-item <?= $action === 'list' ? 'active' : '' ?>">
                        <i class="fas fa-box"></i> 商品管理
                        <?php
                        $sbStats = $product->getShopProductStats($shopId);
                        ?>
                        <span class="nav-badge"><?= $sbStats['total_products'] ?? 0 ?></span>
                    </a>
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="nav-item <?= $action === 'add' || $action === 'edit' ? 'active' : '' ?>">
                        <i class="fas fa-plus"></i> 添加商品
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="coupons.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-ticket-alt"></i> 优惠券
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                </nav>

                <div class="sidebar-card">
                    <h4>商品概览</h4>
                    <div class="sidebar-stat-row">
                        <span class="label">在售商品</span>
                        <span class="value text-success"><?= $sbStats['active_products'] ?? 0 ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">已售罄</span>
                        <span class="value text-warning"><?= $sbStats['sold_out_products'] ?? 0 ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">草稿/下架</span>
                        <span class="value text-muted"><?= ($sbStats['total_products'] ?? 0) - ($sbStats['active_products'] ?? 0) - ($sbStats['sold_out_products'] ?? 0) ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">总销量</span>
                        <span class="value text-info"><?= $sbStats['total_sales'] ?? 0 ?></span>
                    </div>
                </div>
            </aside>
        </div>
        
        <div class="col-md-9">
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- 加载编辑商品数据（需提前，卡片头部可能引用） -->
                <?php if ($action === 'edit' && isset($_GET['product_id'])): ?>
                <?php 
                    $editProductId = intval($_GET['product_id']);
                    $editProduct = $product->getProductById($editProductId, true);
                    if ($editProduct) {
                        $existingImages = [];
                        if (!empty($editProduct['images'])) {
                            $decoded = json_decode($editProduct['images'], true);
                            if (is_array($decoded)) $existingImages = $decoded;
                        }
                    }
                ?>
                <?php endif; ?>
                <!-- 添加/编辑商品表单 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0"><?= $action === 'add' ? '添加商品' : '编辑商品' ?></h4>
                            <small class="text-muted"><i class="fas fa-store"></i> 当前店铺：<?= htmlspecialchars($userShop['shop_name'] ?? '') ?></small>
                            <?php if ($action === 'edit' && isset($editProduct)): ?>
                            <?php $userShops = $shop->getUserShops($_SESSION['user_id']); ?>
                            <?php if (count($userShops) > 1): ?>
                            <div class="mt-2">
                                <label class="text-muted small">移动到其他店铺：</label>
                                <select name="shop_id" class="form-control form-control-sm" style="max-width:260px;">
                                    <?php foreach ($userShops as $us): ?>
                                        <option value="<?= $us['id'] ?>" <?= $us['id'] == $shopId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($us['shop_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <a href="products.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> 返回列表
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="productForm">
                            <?php if ($action === 'edit'): ?>
                                <?php if (!$editProduct || $editProduct['shop_id'] != $shopId): ?>
                                    <div class="alert alert-danger">商品不存在或无权编辑</div>
                                    <?php require_once '../includes/footer.php'; exit; ?>
                                <?php endif; ?>
                                <input type="hidden" name="product_id" value="<?= $editProductId ?>">
                                <input type="hidden" name="action" value="edit">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add">
                            <?php endif; ?>

                            <div class="row">
                                <!-- 左侧表单 -->
                                <div class="col-md-8">
                                    <div class="form-section">
                                        <h5 class="section-title"><i class="fas fa-tag"></i> 基本信息</h5>
                                        <div class="form-group">
                                            <label for="name">商品名称 *</label>
                                            <input type="text" class="form-control form-control-lg" id="name" name="name"
                                                   placeholder="请输入商品名称"
                                                   value="<?= isset($editProduct) ? htmlspecialchars($editProduct['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>"
                                                   required maxlength="200">
                                        </div>

                                        <div class="form-group">
                                            <label for="description">商品描述 *</label>
                                            <textarea class="form-control" id="description" name="description"
                                                      rows="5" required placeholder="详细描述商品特点、规格、售后等信息"><?= isset($editProduct) ? htmlspecialchars($editProduct['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h5 class="section-title"><i class="fas fa-coins"></i> 价格与库存</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="price_bct">人气值价格 (BCT) *</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend"><span class="input-group-text">BCT</span></div>
                                                        <input type="number" class="form-control" id="price_bct" name="price_bct"
                                                               value="<?= isset($editProduct) ? intval($editProduct['price_bct']) : (isset($_POST['price_bct']) ? intval($_POST['price_bct']) : '') ?>"
                                                               step="1" min="1" required placeholder="例如 100">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="price_cny">人民币价格 (¥)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend"><span class="input-group-text">¥</span></div>
                                                        <input type="number" class="form-control" id="price_cny" name="price_cny"
                                                               value="<?= isset($editProduct) ? $editProduct['price_cny'] : (isset($_POST['price_cny']) ? $_POST['price_cny'] : '') ?>"
                                                               step="0.01" min="0" placeholder="可选">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="category_id">商品分类 *</label>
                                                    <select class="form-control" id="category_id" name="category_id" required>
                                                        <option value="">请选择分类</option>
                                                        <?php foreach ($categoryTree as $parent): ?>
                                                            <optgroup label="📁 <?= htmlspecialchars($parent['name']) ?>">
                                                                <?php foreach ($parent['children'] as $child): ?>
                                                                    <option value="<?= $child['id'] ?>"
                                                                        <?= (isset($editProduct) && $editProduct['category_id'] == $child['id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $child['id']) ? 'selected' : '' ?>>
                                                                        &nbsp;&nbsp;<?= htmlspecialchars($child['name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </optgroup>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="stock">库存数量 *</label>
                                                    <input type="number" class="form-control" id="stock" name="stock"
                                                           value="<?= isset($editProduct) ? $editProduct['stock'] : (isset($_POST['stock']) ? $_POST['stock'] : 0) ?>"
                                                           min="0" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h5 class="section-title"><i class="fas fa-sliders-h"></i> 状态设置</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="status">商品状态 *</label>
                                                    <select class="form-control" id="status" name="status" required>
                                                        <option value="draft" <?= (isset($editProduct) && $editProduct['status'] == 'draft') || (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>草稿（暂不上架）</option>
                                                        <option value="active" <?= (isset($editProduct) && $editProduct['status'] == 'active') || (isset($_POST['status']) && $_POST['status'] == 'active') || !isset($editProduct) ? 'selected' : '' ?>>上架销售</option>
                                                        <option value="inactive" <?= (isset($editProduct) && $editProduct['status'] == 'inactive') || (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>下架</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>推荐设置</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" id="is_recommended" name="is_recommended" value="1"
                                                            <?= (isset($editProduct) && $editProduct['is_recommended']) || (isset($_POST['is_recommended']) && $_POST['is_recommended']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="is_recommended">
                                                            <i class="fas fa-star text-warning"></i> 设为推荐商品
                                                        </label>
                                                    </div>
                                                    <small class="form-text text-muted">推荐商品会在首页优先展示</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 右侧图片 -->
                                <div class="col-md-4">
                                    <div class="form-section sticky-top" style="top:80px;z-index:10;">
                                        <h5 class="section-title"><i class="fas fa-images"></i> 商品图片</h5>

                                        <!-- 主图 -->
                                        <div class="form-group">
                                            <label>商品主图 <small class="text-muted">(首张展示图)</small></label>
                                            <input type="file" id="main_image" name="main_image" accept="image/*" class="d-none">
                                            <div class="img-upload-area" id="mainImageArea">
                                                <?php if (isset($editProduct) && !empty($editProduct['main_image'])): ?>
                                                    <div class="upload-preview active">
                                                        <img src="../<?= htmlspecialchars($editProduct['main_image']) ?>" alt="主图">
                                                        <button type="button" class="btn-remove-img" onclick="document.getElementById('main_image').click()">
                                                            <i class="fas fa-sync-alt"></i> 更换
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="upload-placeholder" onclick="document.getElementById('main_image').click()">
                                                        <i class="fas fa-camera"></i>
                                                        <span>点击上传主图</span>
                                                        <small>支持 JPG/PNG/GIF/WebP</small>
                                                    </div>
                                                    <div class="upload-preview d-none">
                                                        <img id="mainPreview" src="#" alt="预览">
                                                        <button type="button" class="btn-remove-img" onclick="resetMainImage()">
                                                            <i class="fas fa-trash"></i> 移除
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div id="mainImageInfo" class="img-info text-muted small mt-1"></div>
                                        </div>

                                        <!-- 副图 -->
                                        <div class="form-group">
                                            <label>商品副图 <small class="text-muted">(最多8张)</small></label>
                                            <div class="extra-images-grid" id="extraImagesGrid">
                                                <!-- 已有副图 -->
                                                <?php if (!empty($existingImages)): ?>
                                                    <?php foreach ($existingImages as $idx => $imgPath): ?>
                                                        <div class="extra-img-item existing" data-index="<?= $idx ?>">
                                                            <img src="../<?= htmlspecialchars($imgPath) ?>" alt="副图<?= $idx+1 ?>">
                                                            <button type="button" class="btn-remove" onclick="removeExistingImage(this)" title="删除">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <input type="hidden" name="keep_images[]" value="<?= htmlspecialchars($imgPath) ?>">
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <!-- 添加按钮 -->
                                                <div class="extra-img-item add-btn" onclick="openExtraImages()">
                                                    <i class="fas fa-plus"></i>
                                                    <span>添加图片</span>
                                                </div>
                                            </div>
                                            <div id="extraImageInfo" class="img-info text-muted small mt-1"></div>
                                        </div>

                                        <!-- 商品视频 -->
                                        <div class="form-group">
                                            <h5 class="section-title"><i class="fas fa-video"></i> 商品视频</h5>
                                            <?php if ($videoError): ?>
                                                <div style="background:#7f1d1d;color:#fca5a5;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:13px;">
                                                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($videoError) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($videoSuccess): ?>
                                                <div style="background:#14532d;color:#86efac;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:13px;">
                                                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($videoSuccess) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="video-upload-tabs">
                                                <div class="form-group">
                                                    <label>上传视频 <small class="text-muted">(MP4/WebM/OGG, 最大50MB)</small></label>
                                                    <div class="video-upload-area" id="videoUploadArea">
                                                        <input type="file" id="product_video" name="product_video" accept="video/mp4,video/webm,video/ogg" class="d-none">
                                                        <?php if (isset($editProduct) && !empty($editProduct['video_url']) && strpos($editProduct['video_url'], '://') === false): ?>
                                                            <div class="video-preview active">
                                                                <video src="../<?= htmlspecialchars($editProduct['video_url']) ?>" controls style="width:100%;max-height:180px;border-radius:8px;"></video>
                                                                <button type="button" class="btn-remove-img" onclick="document.getElementById('product_video').click()">
                                                                    <i class="fas fa-sync-alt"></i> 更换
                                                                </button>
                                                            </div>
                                                            <input type="hidden" name="remove_video" id="removeVideoFlag" value="0">
                                                        <?php else: ?>
                                                            <div class="upload-placeholder" onclick="document.getElementById('product_video').click()">
                                                                <i class="fas fa-video"></i>
                                                                <span>点击上传视频</span>
                                                                <small>支持 MP4/WebM/OGG</small>
                                                            </div>
                                                            <div class="video-preview d-none">
                                                                <video id="videoPreviewPlayer" controls style="width:100%;max-height:180px;border-radius:8px;"></video>
                                                                <button type="button" class="btn-remove-img" onclick="resetVideoUpload()">
                                                                    <i class="fas fa-trash"></i> 移除
                                                                </button>
                                                            </div>
                                                            <input type="hidden" name="remove_video" id="removeVideoFlag" value="0">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div id="videoUploadInfo" class="img-info text-muted small mt-1"></div>
                                                </div>
                                                <div class="form-group">
                                                    <label>或填写视频链接 <small class="text-muted">(外部视频 URL)</small></label>
                                                    <input type="url" class="form-control" id="video_link" name="video_link"
                                                           value="<?= isset($editProduct) && !empty($editProduct['video_url']) && strpos($editProduct['video_url'], '://') !== false ? htmlspecialchars($editProduct['video_url']) : '' ?>"
                                                           placeholder="https://example.com/video.mp4">
                                                </div>
                                                <div id="videoThumbnailSection" style="display:none;margin-top:10px;padding:10px;background:#0f172a;border-radius:8px;">
                                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                                        <span style="font-size:13px;color:#94a3b8;">视频封面截取</span>
                                                        <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="captureVideoFrame()">
                                                            <i class="fas fa-camera"></i> 截取当前帧为封面
                                                        </button>
                                                    </div>
                                                    <canvas id="videoCanvas" style="display:none;"></canvas>
                                                    <div id="videoThumbPreview" style="text-align:center;color:#64748b;font-size:12px;">
                                                        拖动视频到想要的画面，点击上方按钮截取
                                                    </div>
                                                </div>
                                                <input type="hidden" name="video_thumbnail" id="video_thumbnail" value="">
                                                <input type="hidden" name="video_thumb_path" id="video_thumb_path" value="">
                                                <input type="hidden" name="video_thumb_thumb" id="video_thumb_thumb" value="">
                                                <video id="videoCaptureEl" style="display:none;" playsinline></video>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions-bar">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> <?= $action === 'add' ? '添加商品' : '保存修改' ?>
                                </button>
                                <a href="products.php?id=<?= $shopId ?>" class="btn btn-outline-secondary btn-lg">取消返回</a>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- 商品列表 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">商品管理</h4>
                        <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 添加商品
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <?php
                        // 筛选、搜索与分页参数
                        $listFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                        $perPage = 18;

                        // 获取店铺商品列表（支持筛选、搜索和分页）
                        $products = $product->getProductsByShopWithFilter($shopId, $listFilter, $search, $page, $perPage);
                        $totalProducts = $product->getProductsCountByShopWithFilter($shopId, $listFilter, $search);
                        $totalPages = ceil($totalProducts / $perPage);
                        ?>
                        
                        <?php if ($products): ?>
                            <!-- 筛选与批量操作栏 -->
                            <div class="product-toolbar">
                                <div class="toolbar-left">
                                    <label class="check-all-wrap">
                                        <input type="checkbox" id="checkAll" onchange="toggleAll(this)">
                                        <span>全选</span>
                                    </label>
                                    <div class="batch-actions" id="batchActions" style="display:none;">
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="batchAction('activate')">
                                            <i class="fas fa-play"></i> 批量上架
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="batchAction('deactivate')">
                                            <i class="fas fa-pause"></i> 批量下架
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="batchAction('delete')">
                                            <i class="fas fa-trash"></i> 批量删除
                                        </button>
                                    </div>
                                </div>
                                <div class="toolbar-right">
                                    <div class="filter-tabs">
                                        <a href="?id=<?= $shopId ?>" class="filter-tab <?= $listFilter == 'all' ? 'active' : '' ?>">全部</a>
                                        <a href="?id=<?= $shopId ?>&filter=active" class="filter-tab <?= $listFilter == 'active' ? 'active' : '' ?>">在售</a>
                                        <a href="?id=<?= $shopId ?>&filter=inactive" class="filter-tab <?= $listFilter == 'inactive' ? 'active' : '' ?>">下架</a>
                                        <a href="?id=<?= $shopId ?>&filter=draft" class="filter-tab <?= $listFilter == 'draft' ? 'active' : '' ?>">草稿</a>
                                    </div>
                                    <div class="search-box-sm">
                                        <input type="text" id="productSearch" placeholder="搜索商品名称..." value="<?= htmlspecialchars($search) ?>">
                                        <button onclick="doSearch()"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                            </div>

                            <form id="batchForm" method="POST" action="products.php?id=<?= $shopId ?>">
                                <input type="hidden" name="batch_action" id="batchActionInput" value="">
                                <div class="product-grid">
                                    <?php foreach ($products as $productItem): ?>
                                        <div class="product-card" data-status="<?= $productItem['status'] ?>" data-name="<?= htmlspecialchars($productItem['name']) ?>">
                                            <div class="product-select">
                                                <input type="checkbox" name="product_ids[]" value="<?= $productItem['id'] ?>" class="product-checkbox">
                                            </div>
                                            <div class="product-image-wrap">
                                                <?php $listImage = $productItem['main_image'] ?: 'assets/images/default-product.jpg'; ?>
                                                <img src="../<?= htmlspecialchars($listImage) ?>" alt="">
                                                <?php if ($productItem['is_recommended']): ?>
                                                    <span class="product-badge">推荐</span>
                                                <?php endif; ?>
                                                <div class="product-overlay">
                                                    <a href="../product/detail.php?id=<?= $productItem['id'] ?>" target="_blank" class="overlay-btn" title="查看">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="products.php?action=edit&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="overlay-btn" title="编辑">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="product-body">
                                                <h4 class="product-title"><?= htmlspecialchars($productItem['name']) ?></h4>
                                                <div class="product-prices">
                                                    <span class="price-bct"><?= number_format($productItem['price_bct'], 0) ?> BCT</span>
                                                    <?php if ($productItem['price_cny']): ?>
                                                        <span class="price-cny">¥<?= number_format($productItem['price_cny'], 2) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="product-metrics">
                                                    <span>库存 <?= $productItem['stock'] ?></span>
                                                    <span>已售 <?= $productItem['sold_count'] ?></span>
                                                </div>
                                                <div class="product-footer">
                                                    <span class="status-pill status-<?= $productItem['status'] ?>">
                                                        <?= $productItem['status'] == 'active' ? '在售' : ($productItem['status'] == 'draft' ? '草稿' : ($productItem['status'] == 'sold_out' ? '售罄' : '下架')) ?>
                                                    </span>
                                                    <div class="quick-actions">
                                                        <?php if ($productItem['status'] == 'active'): ?>
                                                            <a href="products.php?action=delete&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="qa-btn qa-warning" title="下架" onclick="return confirm('下架此商品？')">
                                                                <i class="fas fa-pause"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="products.php?action=activate&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="qa-btn qa-success" title="上架" onclick="return confirm('上架此商品？')">
                                                                <i class="fas fa-play"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="products.php?action=hard_delete&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="qa-btn qa-danger" title="彻底删除" onclick="return confirm('⚠️ 彻底删除后不可恢复，确定删除？')">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                            <?php if ($totalPages > 1): ?>
                            <div class="pagination-wrap">
                                <span class="pagination-info">共 <?= $totalProducts ?> 件商品，<?= $totalPages ?> 页</span>
                                <div class="pagination-links">
                                    <?php 
                                    $baseUrl = "products.php?id=$shopId&filter=$listFilter" . ($search ? "&search=" . urlencode($search) : "");
                                    if ($page > 1): ?>
                                        <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="page-link">&laquo; 上一页</a>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                    <?php endfor; ?>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="page-link">下一页 &raquo;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">暂无商品</h5>
                                <p class="text-muted">您还没有添加任何商品</p>
                                <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> 添加第一个商品
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ===== 侧边栏（manage.php 风格） ===== */
.shop-sidebar { width: 100%; }
.sidebar-nav { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; position: relative; }
.nav-item:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
.nav-item.active { background: #fff7ed; color: #ea580c; }
.nav-badge { margin-left: auto; background: #f1f5f9; color: #64748b; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.nav-item.active .nav-badge { background: #fed7aa; color: #c2410c; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.sidebar-card h4 { font-size: 14px; font-weight: 600; color: #1e293b; margin: 0 0 12px; }
.sidebar-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.sidebar-stat-row:last-child { border-bottom: none; }
.sidebar-stat-row .label { color: #64748b; }
.sidebar-stat-row .value { font-weight: 600; }

/* ===== 表单分节 ===== */
.form-section { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #f1f5f9; }
.section-title { font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.section-title i { color: #ff6b00; }
.form-group label { font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.form-control { border-radius: 8px; border: 1px solid #d1d5db; font-size: 14px; }
.form-control:focus { border-color: #ff6b00; box-shadow: 0 0 0 3px rgba(255,107,0,0.1); }
.form-control-lg { font-size: 16px; padding: 12px 14px; }
textarea.form-control { resize: vertical; min-height: 120px; }
.input-group-text { background: #f9fafb; border-color: #d1d5db; color: #6b7280; font-size: 13px; }

/* ===== 图片上传区域 ===== */
.img-upload-area { border: 2px dashed #d1d5db; border-radius: 12px; overflow: hidden; cursor: pointer; transition: all 0.2s; background: #f9fafb; position: relative; }
.img-upload-area:hover { border-color: #ff6b00; background: #fff7ed; }
.upload-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px 16px; color: #9ca3af; gap: 6px; }
.upload-placeholder i { font-size: 28px; }
.upload-placeholder span { font-size: 13px; font-weight: 500; }
.upload-placeholder small { font-size: 11px; }
.upload-preview { position: relative; width: 100%; height: 200px; background: #f3f4f6; }
.upload-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.upload-preview .btn-remove-img { position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.6); color: #fff; border: none; border-radius: 6px; padding: 4px 10px; font-size: 12px; cursor: pointer; }
.upload-preview .btn-remove-img:hover { background: rgba(0,0,0,0.8); }
.img-info { font-size: 12px; color: #6b7280; }

/* 副图网格 */
.extra-images-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.extra-img-item { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: #f3f4f6; border: 1px solid #e5e7eb; }
.extra-img-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
.extra-img-item.add-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; color: #9ca3af; cursor: pointer; border-style: dashed; transition: all 0.2s; }
.extra-img-item.add-btn:hover { border-color: #ff6b00; color: #ff6b00; background: #fff7ed; }
.extra-img-item.add-btn i { font-size: 20px; margin-bottom: 4px; }
.extra-img-item.add-btn span { font-size: 11px; }
.extra-img-item .btn-remove { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(0,0,0,0.5); color: #fff; border: none; display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer; padding: 0; }
.extra-img-item .btn-remove:hover { background: #dc2626; }

/* 卡片统一样式 */
.card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); background: #fff; }
.card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 18px 20px; border-radius: 12px 12px 0 0 !important; }
.card-body { padding: 20px; }
.card-title { font-size: 16px; font-weight: 700; color: #1e293b; }

/* 按钮统一样式 */
.btn-primary { background: #ff6b00; border-color: #ff6b00; }
.btn-primary:hover { background: #e05d00; border-color: #e05d00; }
.btn-outline-secondary { color: #64748b; border-color: #d1d5db; }
.btn-outline-secondary:hover { background: #f1f5f9; color: #1e293b; }

/* Alert */
.alert { border-radius: 8px; font-size: 14px; padding: 12px 16px; }
.alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

/* 表单操作栏 */
.form-actions-bar { display: flex; gap: 12px; padding: 20px 0 0; border-top: 1px solid #e5e7eb; margin-top: 8px; }
.form-actions-bar .btn-lg { padding: 10px 28px; font-size: 15px; border-radius: 8px; }

/* 商品卡片网格 */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin-top: 12px;
}
.product-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    overflow: hidden;
    transition: all 0.2s;
    position: relative;
}
.product-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.product-select {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 3;
}
.product-select input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #ff6b00;
}
.product-image-wrap {
    position: relative;
    width: 100%;
    height: 160px;
    background: #f8f9fa;
    overflow: hidden;
}
.product-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.product-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #28a745;
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    z-index: 2;
}
.product-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    opacity: 0;
    transition: opacity 0.2s;
    z-index: 2;
}
.product-card:hover .product-overlay {
    opacity: 1;
}
.overlay-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #fff;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
}
.overlay-btn:hover {
    background: #ff6b00;
    color: #fff;
}
.product-body {
    padding: 12px;
}
.product-title {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 6px;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    min-height: 40px;
}
.product-prices {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 6px;
}
.price-bct {
    font-size: 15px;
    font-weight: 700;
    color: #ff6b00;
}
.price-cny {
    font-size: 12px;
    color: #6b7280;
}
.product-metrics {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 10px;
}
.product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status-pill {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: 500;
}
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-draft { background: #e2e3e5; color: #383d41; }
.status-sold_out { background: #fff3cd; color: #856404; }
.quick-actions {
    display: flex;
    gap: 4px;
}
.qa-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.15s;
}
.qa-warning { background: #fff3cd; color: #856404; }
.qa-warning:hover { background: #ffc107; color: #000; }
.qa-success { background: #d4edda; color: #155724; }
.qa-success:hover { background: #28a745; color: #fff; }
.qa-danger { background: #f8d7da; color: #721c24; }
.qa-danger:hover { background: #dc3545; color: #fff; }

/* 分页 */
.pagination-wrap {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 20px; padding: 12px 0; border-top: 1px solid #e9ecef;
}
.pagination-info { font-size: 13px; color: #6c757d; }
.pagination-links { display: flex; gap: 4px; }
.page-link {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 10px;
    border-radius: 6px; font-size: 13px; font-weight: 500;
    text-decoration: none; color: #495057; background: #f8f9fa;
    transition: all 0.15s;
}
.page-link:hover { background: #e9ecef; color: #1e293b; text-decoration: none; }
.page-link.active { background: #ff6b00; color: #fff; }

/* 工具栏 */
.product-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.check-all-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #495057;
    cursor: pointer;
    user-select: none;
}
.batch-actions {
    display: flex;
    gap: 6px;
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    gap: 2px;
    background: #f1f3f5;
    border-radius: 8px;
    padding: 3px;
}
.filter-tab {
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 13px;
    color: #6c757d;
    text-decoration: none;
    transition: all 0.15s;
}
.filter-tab:hover {
    color: #495057;
    background: rgba(255,255,255,0.6);
}
.filter-tab.active {
    background: #fff;
    color: #ff6b00;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.search-box-sm {
    display: flex;
    border: 1px solid #ced4da;
    border-radius: 8px;
    overflow: hidden;
    min-width: 200px;
}
.search-box-sm input {
    border: none;
    outline: none;
    padding: 6px 10px;
    font-size: 13px;
    flex: 1;
}
.search-box-sm button {
    border: none;
    background: #f8f9fa;
    padding: 0 10px;
    color: #6c757d;
    cursor: pointer;
}
.search-box-sm button:hover {
    background: #e9ecef;
}

/* 图片上传 */
.image-upload-container {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}
</style>

<script>
/* ========== 主图上传预览 ========== */
const mainInput = document.getElementById('main_image');
const mainArea = document.getElementById('mainImageArea');
if (mainInput && mainArea) {
    mainInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            mainArea.innerHTML = `
                <div class="upload-preview active">
                    <img src="${ev.target.result}" alt="主图预览">
                    <button type="button" class="btn-remove-img" onclick="resetMainImage()">
                        <i class="fas fa-trash"></i> 移除
                    </button>
                </div>
            `;
            document.getElementById('mainImageInfo').textContent = file.name + ' (' + formatSize(file.size) + ')';
        };
        reader.readAsDataURL(file);
    });
}
function resetMainImage() {
    if (!mainInput) return;
    mainInput.value = '';
    mainArea.innerHTML = `
        <div class="upload-placeholder" onclick="document.getElementById('main_image').click()">
            <i class="fas fa-camera"></i>
            <span>点击上传主图</span>
            <small>支持 JPG/PNG/GIF/WebP</small>
        </div>
        <div class="upload-preview d-none">
            <img id="mainPreview" src="#" alt="预览">
            <button type="button" class="btn-remove-img" onclick="resetMainImage()">
                <i class="fas fa-trash"></i> 移除
            </button>
        </div>
    `;
    document.getElementById('mainImageInfo').textContent = '';
}

/* ========== 副图多图上传预览 ========== */
const extraGrid = document.getElementById('extraImagesGrid');
let extraInputCounter = 0;

function openExtraImages() {
    // 创建一个新的隐藏 file input
    extraInputCounter++;
    const inputId = 'extra_images_' + extraInputCounter;
    const input = document.createElement('input');
    input.type = 'file';
    input.id = inputId;
    input.name = 'extra_images[]';
    input.accept = 'image/*';
    input.multiple = true;
    input.className = 'd-none';
    input.dataset.forPreview = '';
    document.getElementById('productForm').appendChild(input);

    input.addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        const currentCount = extraGrid.querySelectorAll('.extra-img-item:not(.add-btn)').length;
        const remaining = 8 - currentCount;
        if (remaining <= 0) {
            alert('最多只能上传8张副图');
            input.remove();
            return;
        }
        const toAdd = files.slice(0, remaining);
        toAdd.forEach((file) => {
            const reader = new FileReader();
            reader.onload = function(ev) {
                const div = document.createElement('div');
                div.className = 'extra-img-item new';
                div.dataset.inputId = inputId;
                div.innerHTML = `
                    <img src="${ev.target.result}" alt="新副图">
                    <button type="button" class="btn-remove" onclick="removeNewImage(this)" title="删除">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                const addBtn = extraGrid.querySelector('.add-btn');
                extraGrid.insertBefore(div, addBtn);
                updateExtraImageCount();
            };
            reader.readAsDataURL(file);
        });
        if (files.length > remaining) {
            alert('已超出8张限制，仅添加了前 ' + remaining + ' 张');
        }
    });

    input.click();
}

function removeExistingImage(btn) {
    const item = btn.closest('.extra-img-item');
    if (item) {
        item.remove();
        updateExtraImageCount();
    }
}
function removeNewImage(btn) {
    const item = btn.closest('.extra-img-item');
    if (!item) return;
    const inputId = item.dataset.inputId;
    // 如果该 input 对应的所有预览都被删除，则移除 input
    item.remove();
    const stillUsing = extraGrid.querySelector('.extra-img-item[data-input-id="' + inputId + '"]');
    if (!stillUsing) {
        const input = document.getElementById(inputId);
        if (input) input.remove();
    }
    updateExtraImageCount();
}
function updateExtraImageCount() {
    const count = extraGrid ? extraGrid.querySelectorAll('.extra-img-item:not(.add-btn)').length : 0;
    const info = document.getElementById('extraImageInfo');
    if (info) info.textContent = count > 0 ? '已选 ' + count + ' / 8 张' : '';
    const addBtn = extraGrid ? extraGrid.querySelector('.add-btn') : null;
    if (addBtn) addBtn.style.display = count >= 8 ? 'none' : 'flex';
}
function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/* ========== 视频上传预览 + 封面截取 ========== */
const videoInput = document.getElementById('product_video');
const videoArea = document.getElementById('videoUploadArea');
const captureEl = document.getElementById('videoCaptureEl');
const thumbSection = document.getElementById('videoThumbnailSection');
const thumbPreview = document.getElementById('videoThumbPreview');
const videoCanvas = document.getElementById('videoCanvas');
let currentVideoUrl = null;

if (videoInput && videoArea) {
    videoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        currentVideoUrl = url;
        videoArea.innerHTML = `
            <div class="video-preview active">
                <video src="${url}" controls style="width:100%;max-height:180px;border-radius:8px;"></video>
                <button type="button" class="btn-remove-img" onclick="resetVideoUpload()">
                    <i class="fas fa-trash"></i> 移除
                </button>
            </div>
            <input type="hidden" name="remove_video" id="removeVideoFlag" value="0">
        `;
        document.getElementById('videoUploadInfo').innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> 视频已选择：' + file.name + ' (' + formatSize(file.size) + ')</span>';

        // 加载到隐藏 video 元素用于 Canvas 截帧
        captureEl.src = url;
        captureEl.load();
        thumbSection.style.display = 'block';
        document.getElementById('video_thumbnail').value = '';
        thumbPreview.innerHTML = '<span style="color:#64748b;">拖动视频到想要的画面，点击上方按钮截取</span>';
    });
}

// Canvas 截取视频当前帧作为封面（AJAX 上传，避免 base64 超过 post_max_size）
function captureVideoFrame() {
    if (!captureEl.src || captureEl.readyState < 2) {
        thumbPreview.innerHTML = '<span style="color:#fca5a5;">视频未就绪，请稍后再试</span>';
        return;
    }
    try {
        var maxW = 800;
        var vw = captureEl.videoWidth || 640;
        var vh = captureEl.videoHeight || 480;
        var ratio = Math.min(maxW / vw, 1.0);
        videoCanvas.width = Math.round(vw * ratio);
        videoCanvas.height = Math.round(vh * ratio);
        var ctx = videoCanvas.getContext('2d');
        ctx.drawImage(captureEl, 0, 0, videoCanvas.width, videoCanvas.height);

        // Canvas → Blob → AJAX 上传
        videoCanvas.toBlob(function(blob) {
            if (!blob) {
                thumbPreview.innerHTML = '<span style="color:#fca5a5;">截取失败，请手动上传主图</span>';
                return;
            }
            var formData = new FormData();
            formData.append('ajax_upload_thumbnail', '1');
            formData.append('thumbnail', blob, 'video_frame.jpg');

            thumbPreview.innerHTML = '<span style="color:#94a3b8;">正在上传封面...</span>';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    // 存路径而非 base64
                    document.getElementById('video_thumbnail').value = data.file_path;
                    document.getElementById('video_thumb_path').value = data.file_path;
                    if (data.thumb_path) {
                        document.getElementById('video_thumb_thumb').value = data.thumb_path;
                    }
                    // 更新主图预览
                    var mainArea = document.getElementById('mainImageArea');
                    if (mainArea && !document.getElementById('main_image').files.length) {
                        mainArea.innerHTML = '<div class="upload-preview active"><img src="' + data.url + '" alt="视频封面" style="width:100%;height:200px;object-fit:cover;"><button type="button" class="btn-remove-img" onclick="document.getElementById(\'main_image\').click()"><i class="fas fa-sync-alt"></i> 更换</button></div>';
                    }
                    thumbPreview.innerHTML = '<img src="' + data.url + '" style="max-width:100%;max-height:120px;border-radius:6px;" alt="封面预览"><br><span style="color:#86efac;font-size:12px;"><i class="fas fa-check-circle"></i> 封面已截取并上传</span>';
                } else {
                    thumbPreview.innerHTML = '<span style="color:#fca5a5;">' + (data.error || '上传失败') + '</span>';
                }
            })
            .catch(function() {
                thumbPreview.innerHTML = '<span style="color:#fca5a5;">上传失败，请手动上传主图</span>';
            });
        }, 'image/jpeg', 0.85);
    } catch (e) {
        thumbPreview.innerHTML = '<span style="color:#fca5a5;">截取失败，请手动上传主图</span>';
    }
}

function resetVideoUpload() {
    if (!videoInput) return;
    videoInput.value = '';
    currentVideoUrl = null;
    captureEl.src = '';
    videoArea.innerHTML = `
        <div class="upload-placeholder" onclick="document.getElementById('product_video').click()">
            <i class="fas fa-video"></i>
            <span>点击上传视频</span>
            <small>支持 MP4/WebM/OGG</small>
        </div>
        <div class="video-preview d-none">
            <video id="videoPreviewPlayer" controls style="width:100%;max-height:180px;border-radius:8px;"></video>
            <button type="button" class="btn-remove-img" onclick="resetVideoUpload()">
                <i class="fas fa-trash"></i> 移除
            </button>
        </div>
        <input type="hidden" name="remove_video" id="removeVideoFlag" value="1">
    `;
    document.getElementById('videoUploadInfo').textContent = '';
    document.getElementById('video_thumbnail').value = '';
    thumbSection.style.display = 'none';
    thumbPreview.innerHTML = '<span style="color:#64748b;">拖动视频到想要的画面，点击上方按钮截取</span>';
}

// 表单验证
document.getElementById('productForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const description = document.getElementById('description').value.trim();
    const categoryId = document.getElementById('category_id').value;
    const priceBct = document.getElementById('price_bct').value;
    const stock = document.getElementById('stock').value;
    
    if (!name) {
        alert('请输入商品名称');
        e.preventDefault();
        return;
    }
    
    if (!description) {
        alert('请输入商品描述');
        e.preventDefault();
        return;
    }
    
    if (!categoryId) {
        alert('请选择商品分类');
        e.preventDefault();
        return;
    }
    
    if (!priceBct || parseFloat(priceBct) <= 0) {
        alert('请输入有效的人气值价格');
        e.preventDefault();
        return;
    }
    
    if (stock < 0) {
        alert('库存不能为负数');
        e.preventDefault();
        return;
    }
});

/* ========== 商品列表批量操作与搜索 ========== */
function toggleAll(checkbox) {
    const boxes = document.querySelectorAll('.product-checkbox');
    boxes.forEach(b => b.checked = checkbox.checked);
    updateBatchActions();
}

function updateBatchActions() {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    const batchActions = document.getElementById('batchActions');
    batchActions.style.display = checked.length > 0 ? 'flex' : 'none';
}

function batchAction(action) {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    if (checked.length === 0) {
        alert('请至少选择一个商品');
        return;
    }
    const labels = { activate: '上架', deactivate: '下架', delete: '删除' };
    if (!confirm('确定要批量' + labels[action] + ' ' + checked.length + ' 个商品吗？')) return;
    document.getElementById('batchActionInput').value = action;
    document.getElementById('batchForm').submit();
}

function doSearch() {
    const keyword = document.getElementById('productSearch').value.trim();
    const url = new URL(window.location.href);
    if (keyword) {
        url.searchParams.set('search', keyword);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

// 监听各商品复选框变化
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('product-checkbox')) {
        updateBatchActions();
    }
});

// 搜索框回车触发
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') doSearch();
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>