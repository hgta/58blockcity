<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

require_once '../../config/database.php';


// 加载地址类
require_once '../../classes/Address.php';
require_once '../../classes/User.php';

$address = new Address($pdo);
$user = new User($pdo);

$userId = $_SESSION['user_id'];
$userInfo = $user->getUserById($userId);

// 获取用户地址列表
$addresses = $address->getUserAddresses($userId);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $province = trim($_POST['province']);
        $city = trim($_POST['city']);
        $district = trim($_POST['district']);
        $detail = trim($_POST['detail']);
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        $addressData = [
            'name' => $name,
            'phone' => $phone,
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'detail' => $detail,
            'is_default' => $isDefault
        ];
        
        if ($action === 'add') {
            $result = $address->addAddress($userId, $addressData);
            $message = $result ? '地址添加成功' : '地址添加失败';
        } else {
            $addressId = intval($_POST['address_id']);
            $result = $address->updateAddress($addressId, $userId, $addressData);
            $message = $result ? '地址更新成功' : '地址更新失败';
        }
        
        if ($result) {
            header("Location: address.php?success=" . urlencode($message));
            exit();
        } else {
            $error = $message;
        }
    } elseif ($action === 'delete') {
        $addressId = intval($_POST['address_id']);
        $result = $address->deleteAddress($addressId, $userId);
        
        if ($result) {
            header("Location: address.php?success=地址删除成功");
            exit();
        } else {
            $error = "地址删除失败";
        }
    } elseif ($action === 'set_default') {
        $addressId = intval($_POST['address_id']);
        $result = $address->setDefaultAddress($addressId, $userId);
        
        if ($result) {
            header("Location: address.php?success=默认地址设置成功");
            exit();
        } else {
            $error = "默认地址设置失败";
        }
    }
}

// 获取成功消息
$success = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>地址管理 - 58人气值商城</title>
    <style>
        .address-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
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
        
        /* 地址列表样式 */
        .address-list {
            margin-bottom: 30px;
        }
        
        .address-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
        }
        
        .address-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .address-card.default {
            border-left-color: #e74c3c;
            background: #fff8f8;
        }
        
        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .address-info {
            flex: 1;
        }
        
        .address-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .address-phone {
            color: #666;
            margin-bottom: 8px;
        }
        
        .address-detail {
            color: #333;
            line-height: 1.5;
        }
        
        .address-tag {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .address-actions {
            display: flex;
            gap: 10px;
        }
        
        /* 按钮样式 */
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-default {
            background: #27ae60;
            color: white;
        }
        
        .btn-default:hover {
            background: #219653;
        }
        
        .btn-add {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .btn-add:hover {
            background: #2980b9;
        }
        
        /* 表单样式 */
        .address-form {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox {
            width: 16px;
            height: 16px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-submit {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .btn-submit:hover {
            background: #2980b9;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
            padding: 12px 30px;
            font-size: 16px;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
        }
        
        /* 消息样式 */
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-text {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .address-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .address-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="address-container">
        <div class="page-header">
            <h1 class="page-title">地址管理</h1>
            <p class="page-description">管理您的收货地址，方便购物时快速选择</p>
        </div>
        
        <!-- 消息显示 -->
        <?php if ($success): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- 添加地址表单 -->
        <div class="address-form">
            <h3 class="form-title" id="form-title">添加新地址</h3>
            <form method="POST" action="" id="address-form">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="address_id" id="form-address-id" value="">
                
                <div class="form-group">
                    <label class="form-label" for="name">收货人姓名 *</label>
                    <input type="text" id="name" name="name" class="form-control" required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="phone">手机号码 *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required maxlength="11">
                </div>
                
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label class="form-label" for="province">省份 *</label>
                            <input type="text" id="province" name="province" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label" for="city">城市 *</label>
                            <input type="text" id="city" name="city" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label" for="district">区县 *</label>
                            <input type="text" id="district" name="district" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="detail">详细地址 *</label>
                    <input type="text" id="detail" name="detail" class="form-control" required placeholder="街道、小区、门牌号等">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_default" name="is_default" class="checkbox">
                        <label for="is_default">设为默认地址</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit" id="submit-btn">
                        <i class="fas fa-plus"></i> 添加地址
                    </button>
                    <button type="button" class="btn btn-cancel" id="cancel-btn" style="display: none;">
                        取消编辑
                    </button>
                </div>
            </form>
        </div>
        
        <!-- 地址列表 -->
        <div class="address-list">
            <h3 style="margin-bottom: 20px; color: #333;">我的地址</h3>
            
            <?php if (empty($addresses)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="empty-text">您还没有添加任何收货地址</div>
                    <p style="color: #999; margin-bottom: 20px;">添加地址后，购物时可以直接选择</p>
                </div>
            <?php else: ?>
                <?php foreach ($addresses as $addr): ?>
                    <div class="address-card <?php echo $addr['is_default'] ? 'default' : ''; ?>" id="address-<?php echo $addr['id']; ?>">
                        <div class="address-header">
                            <div class="address-info">
                                <div class="address-name">
                                    <?php echo htmlspecialchars($addr['name']); ?>
                                    <?php if ($addr['is_default']): ?>
                                        <span class="address-tag">默认</span>
                                    <?php endif; ?>
                                </div>
                                <div class="address-phone"><?php echo htmlspecialchars($addr['phone']); ?></div>
                                <div class="address-detail">
                                    <?php echo htmlspecialchars($addr['province'] . $addr['city'] . $addr['district'] . $addr['detail']); ?>
                                </div>
                            </div>
                            <div class="address-actions">
                                <?php if (!$addr['is_default']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                        <button type="submit" class="btn btn-default" title="设为默认">
                                            <i class="fas fa-star"></i> 设默认
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-edit" onclick="editAddress(<?php echo $addr['id']; ?>)" title="编辑">
                                    <i class="fas fa-edit"></i> 编辑
                                </button>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要删除这个地址吗？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                    <button type="submit" class="btn btn-delete" title="删除">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 编辑地址
        function editAddress(addressId) {
            const addressCard = document.getElementById('address-' + addressId);
            const name = addressCard.querySelector('.address-name').textContent.split('默认')[0].trim();
            const phone = addressCard.querySelector('.address-phone').textContent;
            const detail = addressCard.querySelector('.address-detail').textContent;
            
            // 简单解析地址（实际项目中可以使用更复杂的地理编码）
            const addressParts = detail.match(/(.+?[省市])(.+?[市区县])(.+)/);
            if (addressParts) {
                document.getElementById('province').value = addressParts[1] || '';
                document.getElementById('city').value = addressParts[2] || '';
                document.getElementById('district').value = addressParts[3] || '';
            } else {
                document.getElementById('province').value = '';
                document.getElementById('city').value = '';
                document.getElementById('district').value = detail;
            }
            
            document.getElementById('name').value = name;
            document.getElementById('phone').value = phone;
            document.getElementById('detail').value = addressParts ? addressParts[3] : detail;
            document.getElementById('is_default').checked = addressCard.classList.contains('default');
            
            // 更新表单
            document.getElementById('form-title').textContent = '编辑地址';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-address-id').value = addressId;
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> 更新地址';
            document.getElementById('cancel-btn').style.display = 'inline-block';
            
            // 滚动到表单
            document.querySelector('.address-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        // 取消编辑
        document.getElementById('cancel-btn').addEventListener('click', function() {
            resetForm();
        });
        
        // 重置表单
        function resetForm() {
            document.getElementById('address-form').reset();
            document.getElementById('form-title').textContent = '添加新地址';
            document.getElementById('form-action').value = 'add';
            document.getElementById('form-address-id').value = '';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-plus"></i> 添加地址';
            document.getElementById('cancel-btn').style.display = 'none';
        }
        
        // 表单验证
        document.getElementById('address-form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const province = document.getElementById('province').value.trim();
            const city = document.getElementById('city').value.trim();
            const district = document.getElementById('district').value.trim();
            const detail = document.getElementById('detail').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('请输入收货人姓名');
                return;
            }
            
            if (!phone || !/^1[3-9]\d{9}$/.test(phone)) {
                e.preventDefault();
                alert('请输入正确的手机号码');
                return;
            }
            
            if (!province || !city || !district) {
                e.preventDefault();
                alert('请填写完整的省市区信息');
                return;
            }
            
            if (!detail) {
                e.preventDefault();
                alert('请输入详细地址');
                return;
            }
        });
        
        // 自动重置表单（当成功提交后）
        <?php if ($success): ?>
            setTimeout(resetForm, 100);
        <?php endif; ?>
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html> 