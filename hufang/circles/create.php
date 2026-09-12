<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Block.php';
require_once '../../classes/City.php';
require_once '../../classes/SeoHelper.php';

checkLogin();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $category = trim($_POST['category'] ?? 'BlockCity');
    $blockCountRaw = $_POST['block_count'] ?? '';
    $blockCount = intval($blockCountRaw);

    if (empty($name) || empty($city)) {
        $error = '互访圈名称和所在城市是必填项';
    } else {
        // 后端兜底：区块数未回填（空）或非法（负数）时，按所选城市重新计算实际拥有区块数（多块合并的按 1 块计）
        if ($blockCountRaw === '' || $blockCount < 0) {
            $cityId = 0;
            $stmt = $pdo->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
            $stmt->execute([$city]);
            $cityRow = $stmt->fetch();
            if ($cityRow) {
                $cityId = (int)$cityRow['id'];
            } else {
                // 去除“市”后缀后再匹配一次
                $short = preg_replace('/市$/u', '', $city);
                if ($short !== $city) {
                    $stmt = $pdo->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
                    $stmt->execute([$short]);
                    $cityRow = $stmt->fetch();
                    if ($cityRow) {
                        $cityId = (int)$cityRow['id'];
                    }
                }
            }
            if ($cityId) {
                $block = new Block($pdo);
                // 实际拥有区块数（多块合并的按 1 块计）
                $blockCount = $block->countUserActualBlocksByCity($userId, $cityId);
            }
        }

        if ($blockCount < 0) {
            $error = '区块数不能为负数';
        } else {
            $circle = new Circle($pdo);
            if ($circle->create($userId, $name, $description, $city, $category, $blockCount)) {
                $success = '互访圈创建成功！';
                // 百度主动推送新创建的互访圈
                $newCircleId = $pdo->lastInsertId();
                if ($newCircleId) {
                    SeoHelper::baiduPush(SeoHelper::circleUrl($newCircleId, $name));
                }
                $_POST = []; // 清空表单
            } else {
                $error = '创建互访圈时出错，请稍后再试';
            }
        }
    }
}

// 获取所有城市
$cityObj = new City($pdo);
$cities = $cityObj->getAllCities();
$citiesJson = json_encode(array_column($cities, 'name'));

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
                    <input type="text" class="form-control" id="city" name="city" 
                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" 
                           list="cityOptions" autocomplete="off" required
                           placeholder="输入城市名称或从下拉列表选择">
                    <datalist id="cityOptions">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="form-text text-muted">输入城市名称，支持拼音或汉字搜索</small>
                </div>

                <div class="form-group">
                    <label for="block_count">拥有区块总数 *</label>
                    <input type="number" class="form-control" id="block_count" name="block_count" 
                           min="0" value="<?= htmlspecialchars($_POST['block_count'] ?? '0') ?>" 
                           readonly required>
                    <small class="form-text text-muted">根据 block 子站自动计算实际拥有区块数（多块合并的按 1 块计），选择城市后自动更新，无需手动填写</small>
                    <small class="form-text text-muted" id="block_count_hint" style="display:none;"></small>
                </div>

                <!--<div class="form-group">
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
                </div>-->

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

<style>
/* 增强输入框样式 */
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* 数据列表下拉样式 */
#cityOptions {
    max-height: 200px;
    overflow-y: auto;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cityInput = document.getElementById('city');
    const cityOptions = <?= $citiesJson ?>;
    const blockCountInput = document.getElementById('block_count');
    const blockCountHint = document.getElementById('block_count_hint');

    // 根据所选城市，从 block 子站自动计算实际拥有区块数（多块合并的按 1 块计）
    function fetchBlockCount(cityName) {
        if (!cityName) {
            blockCountInput.value = 0;
            return;
        }
        blockCountHint.textContent = '正在计算实际拥有区块数…';
        blockCountHint.style.display = 'block';
        const body = new URLSearchParams();
        body.append('city', cityName);
        fetch('ajax-block-count.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            if (data && data.success) {
                blockCountInput.value = data.count;
                blockCountHint.style.display = 'none';
            } else {
                blockCountInput.value = 0;
                blockCountHint.textContent = (data && data.msg) ? data.msg : '未能获取区块数';
                blockCountHint.style.display = 'block';
            }
        })
        .catch(function() {
            blockCountInput.value = 0;
            blockCountHint.style.display = 'none';
        });
    }

    // 选择/切换城市后重新计算
    cityInput.addEventListener('change', function() {
        fetchBlockCount(this.value.trim());
    });

    // 页面加载时若已有城市值（如提交报错回显），同步一次
    if (cityInput.value.trim()) {
        fetchBlockCount(cityInput.value.trim());
    }

    // 实时搜索功能
    cityInput.addEventListener('input', function() {
        const value = this.value.toLowerCase();
        
        // 清空现有选项
        const datalist = document.getElementById('cityOptions');
        datalist.innerHTML = '';
        
        // 过滤匹配的城市
        const filteredCities = cityOptions.filter(city => 
            city.toLowerCase().includes(value) || 
            convertToPinyin(city).toLowerCase().includes(value)
        );
        
        // 添加过滤后的选项
        filteredCities.forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            datalist.appendChild(option);
        });
    });
    
    // 简单的拼音转换函数（基础版）
    function convertToPinyin(chinese) {
        // 这里可以添加更完整的拼音转换逻辑
        // 目前只做简单示例
        const pinyinMap = {
            '北京': 'beijing',
            '上海': 'shanghai',
            '广州': 'guangzhou',
            '深圳': 'shenzhen',
            '杭州': 'hangzhou',
            '成都': 'chengdu'
            // 可以继续添加更多城市的拼音映射
        };
        
        return pinyinMap[chinese] || chinese;
    }
    
    // 输入框获得焦点时显示所有选项
    cityInput.addEventListener('focus', function() {
        this.setAttribute('list', 'cityOptions');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>