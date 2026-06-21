 
<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Category.php';

 

$shop = new Shop($pdo);
$category = new Category($pdo);
$userId = $_SESSION['user_id']; // 明确获取用户ID

// 获取用户已有店铺列表
$userShops = $shop->getUserShops($userId);

// 获取分类列表
$categories = $category->getAllCategories();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName = trim($_POST['shop_name']);
    $description = trim($_POST['description']);
    $categoryId = intval($_POST['category_id']);
    $contactInfo = trim($_POST['contact_info']);
    
    // 基本验证
    if (empty($shopName)) {
        $error = "店铺名称不能为空";
    } elseif (strlen($shopName) < 2 || strlen($shopName) > 50) {
        $error = "店铺名称长度应在2-50个字符之间";
    } elseif (empty($categoryId)) {
        $error = "请选择店铺分类";
    } else {
        try {
            // 明确传递两个参数
            $data = [
                'shop_name' => $shopName,
                'description' => $description,
                'category_id' => $categoryId,
                'contact_info' => $contactInfo
            ];
            
            $result = $shop->createShop($userId, $data);
            
            if ($result) {
                header("Location: manage.php");
                exit();
            } else {
                $error = "创建店铺失败，请稍后重试";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建店铺 - 58人气值商城</title>
    <style>
        .create-shop-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-description {
            color: #666;
            font-size: 16px;
        }
        
        .create-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        textarea.form-control {
            height: 100px;
            resize: vertical;
        }
        
        select.form-control {
            background: white;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .error-message {
            color: #e74c3c;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8d7da;
            border-radius: 4px;
            border-left: 4px solid #e74c3c;
        }
        
        .form-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .required {
            color: #e74c3c;
        }
        @media(max-width:768px){
            .row { flex-direction: column; }
            .col-md-4, .col-md-6, .col-md-8 { width: 100%; max-width: 100%; flex: none; }
            .form-control, .form-select { width: 100%; font-size: 16px; }
            .btn { min-height: 44px; width: 100%; }
            textarea.form-control { min-height: 100px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="create-shop-container">
        <div class="page-header">
            <h1 class="page-title">创建我的店铺</h1>
            <p class="page-description">开启您的电商之旅，展示优质商品</p>
        </div>
        
        <?php if (!empty($userShops)): ?>
            <div style="background:white;padding:20px 25px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin-bottom:20px;">
                <h3 style="font-size:16px;margin:0 0 12px;color:#333;"><i class="fas fa-store"></i> 您已有 <?php echo count($userShops); ?> 个店铺</h3>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php foreach ($userShops as $s): ?>
                        <a href="manage.php?id=<?php echo $s['id']; ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#f8f9fa;border-radius:6px;text-decoration:none;color:#333;font-size:13px;border:1px solid #eee;">
                            <?php if (!empty($s['shop_logo'])): ?>
                                <img src="../<?php echo htmlspecialchars($s['shop_logo']); ?>" style="width:24px;height:24px;border-radius:4px;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:24px;height:24px;border-radius:4px;background:#3498db;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;"><?php echo mb_substr($s['shop_name'], 0, 1); ?></div>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($s['shop_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="create-form">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="shop_name">
                        店铺名称 <span class="required">*</span>
                    </label>
                    <input type="text" id="shop_name" name="shop_name" class="form-control" 
                           value="<?php echo isset($_POST['shop_name']) ? htmlspecialchars($_POST['shop_name']) : ''; ?>" 
                           required maxlength="50" placeholder="请输入店铺名称">
                    <div class="form-note">店铺名称长度2-50个字符</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="category_id">
                        店铺分类 <span class="required">*</span>
                    </label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">请选择店铺分类</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-note">选择最适合您店铺的分类</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">店铺描述</label>
                    <textarea id="description" name="description" class="form-control" 
                              placeholder="简单描述您的店铺特色、主营商品等..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <div class="form-note">让顾客了解您的店铺特色</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="contact_info">联系方式</label>
                    <input type="text" id="contact_info" name="contact_info" class="form-control" 
                           value="<?php echo isset($_POST['contact_info']) ? htmlspecialchars($_POST['contact_info']) : ''; ?>" 
                           placeholder="微信号、手机号、QQ号等">
                    <div class="form-note">方便顾客与您联系</div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-store"></i> 创建店铺
                </button>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // 表单验证
        document.querySelector('form').addEventListener('submit', function(e) {
            const shopName = document.getElementById('shop_name').value.trim();
            const categoryId = document.getElementById('category_id').value;
            
            if (shopName.length < 2 || shopName.length > 50) {
                e.preventDefault();
                alert('店铺名称长度应在2-50个字符之间');
                return;
            }
            
            if (!categoryId) {
                e.preventDefault();
                alert('请选择店铺分类');
                return;
            }
        });
    </script>
</body>
</html> 