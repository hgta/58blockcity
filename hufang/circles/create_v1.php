<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';

checkLogin();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $blockCount = intval($_POST['block_count'] ?? 0);

    if (empty($name) || empty($city)) {
        $error = '互访圈名称和所在城市是必填项';
    } elseif ($blockCount < 0) {
        $error = '区块数不能为负数';
    } else {
        $circle = new Circle($pdo);
        if ($circle->create($userId, $name, $description, $city, $category, $blockCount)) {
            $success = '互访圈创建成功！';
            $_POST = []; // 清空表单
        } else {
            $error = '创建互访圈时出错，请稍后再试';
        }
    }
}

// 扩展城市列表

$cities = ['北京', '杭州', '深圳', '上海', '中国数藏', '广州', '成都', '重庆', '天津', '苏州', '西安', '太原', '合肥', 
			'武汉', '南京', '济南', '长沙', '青岛', '宁波', '贵阳', '郑州', '昆明', '沈阳', '金华', '无锡', '厦门', '周口', '东莞', 
			'烟台', '海口', '宁德', '枣庄', '珠海', '惠州', '福州', '湖州', '常州', '南昌', '佛山', '肇庆', '嘉兴', '绍兴', '温州',
			'南宁', '舟山', '哈尔滨', '石家庄', '潮州', '藏南', '中山', '乌鲁木齐', '安顺', '大连', '济宁', '云浮', '长春', '济宁', 
			'徐州', '洛阳', '泉州', '连云港', '临沂', '台州', '蚌埠', '马鞍山', '汕头', '潍坊', '西宁', '沧州', '三亚', '威海', '兰州', 
			'扬州', '衢州', '南平', '淄博', '遵义', '鄂尔多斯', '茂名', '呼和浩特', '拉萨', '芜湖', '景德镇', '泰安', '聊城', '三明', 
			'银川', '营口', '朝阳', '吴忠', '新余', '铁岭', '自贡', '铜仁', '葫芦岛', '芜湖'];
			
$categories = ['BlockCity'];
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> 创建新互访圈</h1>
        <p>创建一个互访圈，邀请其他用户互相访问交流</p>
    </div>

    <div class="row">
        <div class="col-md-8 col-lg-6 mx-auto">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" class="circle-form">
                <div class="form-group">
                    <label for="name">互访圈名称 *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    <small class="form-text text-muted">为您的互访圈起一个独特的名字</small>
                </div>

                <div class="form-group">
                    <label for="description">互访圈描述</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= 
                        htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <small class="form-text text-muted">描述您的互访圈特色或访问要求</small>
                </div>

                <div class="form-group">
                    <label for="city">所在城市 *</label>
                    <select class="form-control" id="city" name="city" required>
                        <option value="">-- 请选择城市 --</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>" <?= 
                                (isset($_POST['city']) && $_POST['city'] === $city) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($city) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="block_count">拥有区块总数 *</label>
                    <input type="number" class="form-control" id="block_count" name="block_count" 
                           min="0" value="<?= htmlspecialchars($_POST['block_count'] ?? '0') ?>" required>
                    <small class="form-text text-muted">请输入您的互访圈包含的区块数量</small>
                </div>

                <div class="form-group">
                    <label for="category">互访圈类型</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">-- 请选择类型 --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= 
                                (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> 创建互访圈
                    </button>
                    <a href="../user/circles.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 返回
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>