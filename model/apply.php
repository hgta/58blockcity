<?php
/**
 * 模特子站 · 我要当模特（申请）
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/classes/Application.php';
require_once APP_ROOT . '/classes/City.php';

if (!$modelUserId) {
    header('Location: ' . model_login_url(MODEL_BASE_URL . '/apply.php'));
    exit;
}

$userId = $modelUserId;
$app    = new Application($pdo);
$type   = 'model';

$active  = $app->hasActive($type, $userId);
$errors  = [];
$success = false;
$form    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    if ($active) {
        $errors[] = '您已提交过模特申请，请勿重复提交';
    } else {
        $form = [
            'nickname'     => trim($_POST['nickname'] ?? ''),
            'gender'       => in_array($_POST['gender'] ?? '', ['男', '女', '保密']) ? $_POST['gender'] : '保密',
            'age'          => ($_POST['age'] ?? '') !== '' ? intval($_POST['age']) : null,
            'height'       => ($_POST['height'] ?? '') !== '' ? $_POST['height'] : null,
            'weight'       => ($_POST['weight'] ?? '') !== '' ? $_POST['weight'] : null,
            'measurements' => trim($_POST['measurements'] ?? ''),
            'city'         => trim($_POST['city'] ?? ''),
            'zodiac'       => trim($_POST['zodiac'] ?? ''),
            'hobbies'      => trim($_POST['hobbies'] ?? ''),
            'phone'        => trim($_POST['phone'] ?? ''),
            'qq'           => trim($_POST['qq'] ?? ''),
            'weixin'       => trim($_POST['weixin'] ?? ''),
            'weibo'        => trim($_POST['weibo'] ?? ''),
            'xiaohongshu'  => trim($_POST['xiaohongshu'] ?? ''),
        ];

        if ($form['nickname'] === '') {
            $errors[] = '请填写昵称';
        } elseif (empty($_FILES['photos']['name'][0])) {
            $errors[] = '请至少上传一张照片（首张将作为头像）';
        } else {
            $photos = Application::uploadPhotos($_FILES['photos'], $type);
            if (empty($photos)) {
                $errors[] = '照片上传失败，请检查图片格式（jpg/png/gif/webp）且单张不超过 5MB';
            } else {
                $id = $app->create($type, $userId, $form, $photos);
                if ($id > 0) {
                    $success = true;
                } else {
                    $errors[] = '您已提交过模特申请，请勿重复提交';
                }
            }
        }
    }
}

$cityObj   = new City($pdo);
$allCities = $cityObj->getAllCities();
$zodiacs   = ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座'];

$site_config = model_site_config([
    'title'       => SeoHelper::title('我要当模特 - 加入 58 模特库'),
    'description' => SeoHelper::description('报名成为 58 模特库模特，填写基本资料、联系方式并上传照片，审核通过后即可拥有专属主页并参演短剧。', '58 模特库'),
    'keywords'    => '58模特,模特报名,模特招募,当模特,模特申请',
    'canonical_url' => MODEL_BASE_URL . '/apply.php',
]);
require_once __DIR__ . '/includes/header.php';
?>

<div class="model-wrap">
    <div class="m-form-card">
        <?php if ($success): ?>
            <div style="text-align:center;padding:24px 6px;">
                <div style="font-size:52px;color:#16a34a;margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
                <h1 style="text-align:center;">申请提交成功</h1>
                <p class="sub" style="text-align:center;">我们已收到您的模特申请，审核通过后会在模特库展示。<br>您可以在「我的申请」中查看处理进度。</p>
                <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:20px;">
                    <a class="m-btn m-btn-primary" href="/my.php">查看我的申请</a>
                    <a class="m-back" href="/list.php" style="align-self:center;">返回模特库</a>
                </div>
            </div>
        <?php elseif ($active): ?>
            <div class="m-alert info"><i class="fas fa-info-circle"></i> 您已提交过模特申请，请勿重复提交。可在「我的申请」中查看处理进度。</div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px;">
                <a class="m-btn m-btn-primary" href="/my.php">查看我的申请</a>
                <a class="m-back" href="/list.php" style="align-self:center;">返回模特库</a>
            </div>
        <?php else: ?>
            <h1>我要当模特</h1>
            <p class="sub">填写基本资料与联系方式，上传照片，审核通过后即可拥有专属主页并参演短剧。</p>

            <div class="m-alert info"><i class="fas fa-lightbulb"></i> 请务必填写真实有效的联系方式，方便工作人员与您联系；照片建议使用清晰的正面或半身照。</div>

            <?php foreach ($errors as $e): ?>
                <div class="m-alert err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="m-form-grid">
                    <div class="m-field">
                        <label>昵称 <span class="req">*</span></label>
                        <input type="text" name="nickname" maxlength="50" value="<?= htmlspecialchars($form['nickname'] ?? '') ?>" placeholder="你希望展示的名字">
                    </div>
                    <div class="m-field">
                        <label>性别</label>
                        <select name="gender">
                            <?php foreach (['女', '男', '保密'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($form['gender'] ?? '女') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="m-field">
                        <label>年龄</label>
                        <input type="number" name="age" min="14" max="80" value="<?= htmlspecialchars($form['age'] ?? '') ?>" placeholder="如 22">
                    </div>
                    <div class="m-field">
                        <label>身高（cm）</label>
                        <input type="text" name="height" maxlength="10" value="<?= htmlspecialchars($form['height'] ?? '') ?>" placeholder="如 168">
                    </div>
                    <div class="m-field">
                        <label>体重（kg）</label>
                        <input type="text" name="weight" maxlength="10" value="<?= htmlspecialchars($form['weight'] ?? '') ?>" placeholder="如 48">
                    </div>
                    <div class="m-field">
                        <label>三围</label>
                        <input type="text" name="measurements" maxlength="50" value="<?= htmlspecialchars($form['measurements'] ?? '') ?>" placeholder="如 84-62-88">
                    </div>
                    <div class="m-field">
                        <label>城市</label>
                        <select name="city">
                            <option value="">请选择城市</option>
                            <?php foreach ($allCities as $c): $cn = is_array($c) ? ($c['name'] ?? '') : $c; if ($cn === '') continue; ?>
                                <option value="<?= htmlspecialchars($cn) ?>" <?= ($form['city'] ?? '') === $cn ? 'selected' : '' ?>><?= htmlspecialchars($cn) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="m-field">
                        <label>星座</label>
                        <select name="zodiac">
                            <option value="">请选择星座</option>
                            <?php foreach ($zodiacs as $z): ?>
                                <option value="<?= $z ?>" <?= ($form['zodiac'] ?? '') === $z ? 'selected' : '' ?>><?= $z ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="m-field full">
                        <label>爱好 / 特长</label>
                        <textarea name="hobbies" rows="2" placeholder="介绍一下你的爱好特长，比如舞蹈、表演、主持…"><?= htmlspecialchars($form['hobbies'] ?? '') ?></textarea>
                    </div>
                    <div class="m-field">
                        <label>手机号</label>
                        <input type="text" name="phone" maxlength="20" value="<?= htmlspecialchars($form['phone'] ?? '') ?>" placeholder="方便工作人员联系您">
                    </div>
                    <div class="m-field">
                        <label>微信</label>
                        <input type="text" name="weixin" maxlength="100" value="<?= htmlspecialchars($form['weixin'] ?? '') ?>" placeholder="微信号">
                    </div>
                    <div class="m-field">
                        <label>QQ</label>
                        <input type="text" name="qq" maxlength="20" value="<?= htmlspecialchars($form['qq'] ?? '') ?>" placeholder="QQ 号">
                    </div>
                    <div class="m-field">
                        <label>微博 / 小红书</label>
                        <input type="text" name="weibo" maxlength="200" value="<?= htmlspecialchars($form['weibo'] ?? '') ?>" placeholder="链接或 ID">
                    </div>
                    <div class="m-field full">
                        <label>照片 <span class="req">*</span></label>
                        <div class="m-upload">
                            <i class="fas fa-camera"></i>
                            <input type="file" name="photos[]" accept="image/*" multiple>
                            <small>支持多选，首张将作为头像；单张不超过 5MB（jpg/png/gif/webp）</small>
                        </div>
                    </div>
                </div>
                <div class="m-form-actions">
                    <button type="submit" class="m-btn m-btn-primary">提交申请</button>
                    <a href="/list.php" class="m-back">返回模特库</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
