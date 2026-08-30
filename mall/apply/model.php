<?php
// 我要当模特 - 申请页
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Application.php';
require_once '../../classes/City.php';
require_once '../../classes/SeoHelper.php';

requireLogin('../auth/login.php');

$userId = (int)$_SESSION['user_id'];
$app = new Application($pdo);
$type = 'model';

$active = $app->hasActive($type, $userId);
$errors = [];
$success = false;
$form = [];

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
                $errors[] = '照片上传失败，请检查图片格式（jpg/png/gif/webp）且单张不超过5MB';
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

$city = new City($pdo);
$allCities = $city->getAllCities();
$zodiacs = ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座'];

$site_config['title']       = SeoHelper::title('我要当模特 - 58人气值商城');
$site_config['description'] = SeoHelper::description('报名成为 58人气值商城模特，填写基本资料、联系方式并上传照片，审核通过后即可在模特库展示。', '58人气值商城');
$site_config['keywords']    = '58,模特,报名,模特申请,商城';
$site_config['canonical_url'] = 'https://mall.58.tl/apply/model.php';

require_once '../includes/header.php';
?>
<style>
.apply-wrap { max-width: 760px; margin: 24px auto 48px; padding: 0 16px; }
.apply-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 28px; }
.apply-card h1 { font-size: 22px; margin: 0 0 6px; color: #1e293b; }
.apply-sub { color: #94a3b8; font-size: 14px; margin: 0 0 20px; }
.apply-tip { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: 8px; padding: 12px 14px; font-size: 14px; margin-bottom: 20px; }
.apply-alert { border-radius: 8px; padding: 12px 14px; font-size: 14px; margin-bottom: 16px; }
.apply-alert.err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
.apply-alert.ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.apply-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; margin-bottom: 8px; }
.apply-field label { display: block; font-size: 13px; color: #475569; margin-bottom: 5px; font-weight: 600; }
.apply-field label .req { color: #ef4444; }
.apply-field input, .apply-field select, .apply-field textarea {
    width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; color: #1e293b; background: #f8fafc; box-sizing: border-box; outline: none;
}
.apply-field input:focus, .apply-field select:focus, .apply-field textarea:focus { border-color: #ff6b00; background: #fff; }
.apply-field.full { grid-column: 1 / -1; }
.apply-upload { border: 2px dashed #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; color: #94a3b8; background: #fafafa; }
.apply-upload input[type=file] { font-size: 13px; }
.apply-upload small { display: block; margin-top: 6px; color: #cbd5e1; }
.apply-btn { display: inline-block; border: none; cursor: pointer; padding: 12px 34px; border-radius: 999px; font-size: 15px; font-weight: 700; color: #fff; background: linear-gradient(135deg,#ff6b00,#f43f5e); transition: opacity .2s; }
.apply-btn:hover { opacity: .9; }
.apply-actions { display: flex; gap: 10px; align-items: center; margin-top: 22px; }
.apply-back { color: #64748b; font-size: 14px; text-decoration: none; }
.apply-success { text-align: center; padding: 30px 10px; }
.apply-success .ico { font-size: 52px; color: #22c55e; margin-bottom: 10px; }
.apply-success h2 { margin: 0 0 8px; color: #1e293b; font-size: 20px; }
.apply-success p { color: #64748b; margin: 0 0 20px; }
@media (max-width: 640px) { .apply-grid { grid-template-columns: 1fr; } }
</style>

<div class="container">
    <div class="apply-wrap">
        <div class="apply-card">
            <?php if ($success): ?>
                <div class="apply-success">
                    <div class="ico"><i class="fas fa-check-circle"></i></div>
                    <h2>申请提交成功</h2>
                    <p>我们已收到您的模特申请，审核通过后会在模特库展示。<br>您可以在「我的申请」中查看处理进度。</p>
                    <a href="my.php" class="apply-btn">查看我的申请</a>
                    <div style="margin-top:14px;"><a href="../model/list.php" class="apply-back">返回模特库</a></div>
                </div>
            <?php elseif ($active): ?>
                <div class="apply-alert err"><i class="fas fa-info-circle"></i> 您已提交过模特申请，请勿重复提交。可在「我的申请」中查看处理进度。</div>
                <div style="text-align:center;margin-top:16px;">
                    <a href="my.php" class="apply-btn">查看我的申请</a>
                    <div style="margin-top:14px;"><a href="../model/list.php" class="apply-back">返回模特库</a></div>
                </div>
            <?php else: ?>
                <h1>我要当模特</h1>
                <p class="apply-sub">填写基本资料与联系方式，上传照片，审核通过后即可在模特库展示。</p>

                <?php foreach ($errors as $e): ?>
                    <div class="apply-alert err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <div class="apply-tip"><i class="fas fa-lightbulb"></i> 请务必填写真实有效的联系方式，方便工作人员与您沟通。</div>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <div class="apply-grid">
                        <div class="apply-field">
                            <label>昵称 <span class="req">*</span></label>
                            <input type="text" name="nickname" value="<?= htmlspecialchars($form['nickname'] ?? '') ?>" maxlength="100" placeholder="您的昵称或艺名">
                        </div>
                        <div class="apply-field">
                            <label>性别</label>
                            <select name="gender">
                                <?php foreach (['保密', '女', '男'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($form['gender'] ?? '保密') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="apply-field">
                            <label>年龄</label>
                            <input type="number" name="age" min="1" max="120" value="<?= htmlspecialchars($form['age'] ?? '') ?>" placeholder="如 22">
                        </div>
                        <div class="apply-field">
                            <label>所在城市</label>
                            <select name="city">
                                <option value="">-- 选择城市 --</option>
                                <?php foreach ($allCities as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($form['city'] ?? '') === $c['name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="apply-field">
                            <label>身高 (cm)</label>
                            <input type="number" step="0.1" name="height" min="100" max="220" value="<?= htmlspecialchars($form['height'] ?? '') ?>" placeholder="如 168">
                        </div>
                        <div class="apply-field">
                            <label>体重 (kg)</label>
                            <input type="number" step="0.1" name="weight" min="30" max="150" value="<?= htmlspecialchars($form['weight'] ?? '') ?>" placeholder="如 50">
                        </div>
                        <div class="apply-field">
                            <label>三围</label>
                            <input type="text" name="measurements" maxlength="50" value="<?= htmlspecialchars($form['measurements'] ?? '') ?>" placeholder="例：86-60-88">
                        </div>
                        <div class="apply-field">
                            <label>星座</label>
                            <select name="zodiac">
                                <option value="">-- 选择星座 --</option>
                                <?php foreach ($zodiacs as $z): ?>
                                <option value="<?= $z ?>" <?= ($form['zodiac'] ?? '') === $z ? 'selected' : '' ?>><?= $z ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="apply-field full">
                            <label>爱好</label>
                            <textarea name="hobbies" rows="2" style="resize:vertical;" placeholder="介绍一下您的爱好特长"><?= htmlspecialchars($form['hobbies'] ?? '') ?></textarea>
                        </div>
                        <div class="apply-field">
                            <label>手机号</label>
                            <input type="text" name="phone" maxlength="20" value="<?= htmlspecialchars($form['phone'] ?? '') ?>" placeholder="方便工作人员联系您">
                        </div>
                        <div class="apply-field">
                            <label>微信</label>
                            <input type="text" name="weixin" maxlength="100" value="<?= htmlspecialchars($form['weixin'] ?? '') ?>" placeholder="微信号">
                        </div>
                        <div class="apply-field">
                            <label>QQ</label>
                            <input type="text" name="qq" maxlength="20" value="<?= htmlspecialchars($form['qq'] ?? '') ?>" placeholder="QQ 号">
                        </div>
                        <div class="apply-field">
                            <label>微博 / 小红书</label>
                            <input type="text" name="weibo" maxlength="200" value="<?= htmlspecialchars($form['weibo'] ?? '') ?>" placeholder="微博链接或ID">
                        </div>
                        <div class="apply-field full">
                            <label>照片 <span class="req">*</span></label>
                            <div class="apply-upload">
                                <i class="fas fa-camera" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                <input type="file" name="photos[]" accept="image/*" multiple>
                                <small>支持多选，首张将作为头像；单张不超过 5MB（jpg/png/gif/webp）</small>
                            </div>
                        </div>
                    </div>
                    <div class="apply-actions">
                        <button type="submit" class="apply-btn">提交申请</button>
                        <a href="../model/list.php" class="apply-back">返回模特库</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
