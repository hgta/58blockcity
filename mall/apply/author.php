<?php
// 我是作者，我要合作 - 作者合作申请页
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Application.php';
require_once '../../classes/City.php';
require_once '../../classes/SeoHelper.php';

requireLogin('../auth/login.php');

$userId = (int)$_SESSION['user_id'];
$app = new Application($pdo);
$type = 'author';

$active = $app->hasActive($type, $userId);
$errors = [];
$success = false;
$form = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    if ($active) {
        $errors[] = '您已提交过作者合作申请，请勿重复提交';
    } else {
        $form = [
            'nickname'    => trim($_POST['nickname'] ?? ''),
            'gender'      => in_array($_POST['gender'] ?? '', ['男', '女', '保密']) ? $_POST['gender'] : '保密',
            'city'        => trim($_POST['city'] ?? ''),
            'zodiac'      => trim($_POST['zodiac'] ?? ''),
            'style'       => trim($_POST['style'] ?? ''),
            'bio'         => trim($_POST['bio'] ?? ''),
            'phone'       => trim($_POST['phone'] ?? ''),
            'qq'          => trim($_POST['qq'] ?? ''),
            'weixin'      => trim($_POST['weixin'] ?? ''),
            'weibo'       => trim($_POST['weibo'] ?? ''),
            'xiaohongshu' => trim($_POST['xiaohongshu'] ?? ''),
        ];

        if ($form['nickname'] === '') {
            $errors[] = '请填写昵称';
        } elseif (empty($_FILES['works']['name'][0])) {
            $errors[] = '请至少上传一张原创作品照片（首张将作为头像）';
        } else {
            $photos = Application::uploadPhotos($_FILES['works'], $type);
            if (empty($photos)) {
                $errors[] = '作品上传失败，请检查图片格式（jpg/png/gif/webp）且单张不超过5MB';
            } else {
                $id = $app->create($type, $userId, $form, $photos);
                if ($id > 0) {
                    $success = true;
                } else {
                    $errors[] = '您已提交过作者合作申请，请勿重复提交';
                }
            }
        }
    }
}

$city = new City($pdo);
$allCities = $city->getAllCities();
$zodiacs = ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座'];
$authorStyles = ['插画', '国潮', '卡通', '写实', '水墨', '涂鸦', '极简', '复古', '萌系', '科技', '像素风', '其他'];

$site_config['title']       = SeoHelper::title('我是作者，我要合作 - 58人气值商城');
$site_config['description'] = SeoHelper::description('加入 58人气值商城作者计划，填写作者资料、联系方式并上传原创作品，合作洽谈后即可展示作品并关联商品。', '58人气值商城');
$site_config['keywords']    = '58,作者,合作,插画,原创,作者合作';
$site_config['canonical_url'] = 'https://mall.58.tl/apply/author.php';

require_once '../includes/header.php';
?>
<style>
.apply-wrap { max-width: 760px; margin: 24px auto 48px; padding: 0 16px; }
.apply-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 28px; }
.apply-card h1 { font-size: 22px; margin: 0 0 6px; color: #1e293b; }
.apply-sub { color: #94a3b8; font-size: 14px; margin: 0 0 20px; }
.apply-tip { background: #f5f3ff; border: 1px solid #ddd6fe; color: #5b21b6; border-radius: 8px; padding: 12px 14px; font-size: 14px; margin-bottom: 20px; }
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
.apply-field input:focus, .apply-field select:focus, .apply-field textarea:focus { border-color: #6c5ce7; background: #fff; }
.apply-field.full { grid-column: 1 / -1; }
.apply-upload { border: 2px dashed #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; color: #94a3b8; background: #fafafa; }
.apply-upload input[type=file] { font-size: 13px; }
.apply-upload small { display: block; margin-top: 6px; color: #cbd5e1; }
.apply-btn { display: inline-block; border: none; cursor: pointer; padding: 12px 34px; border-radius: 999px; font-size: 15px; font-weight: 700; color: #fff; background: linear-gradient(135deg,#6c5ce7,#a29bfe); transition: opacity .2s; }
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
                    <h2>合作申请提交成功</h2>
                    <p>我们已收到您的合作申请，工作人员会尽快与您联系。<br>您可以在「我的申请」中查看处理进度。</p>
                    <a href="my.php" class="apply-btn">查看我的申请</a>
                    <div style="margin-top:14px;"><a href="../author/list.php" class="apply-back">返回作者库</a></div>
                </div>
            <?php elseif ($active): ?>
                <div class="apply-alert err"><i class="fas fa-info-circle"></i> 您已提交过作者合作申请，请勿重复提交。可在「我的申请」中查看处理进度。</div>
                <div style="text-align:center;margin-top:16px;">
                    <a href="my.php" class="apply-btn">查看我的申请</a>
                    <div style="margin-top:14px;"><a href="../author/list.php" class="apply-back">返回作者库</a></div>
                </div>
            <?php else: ?>
                <h1>我是作者，我要合作</h1>
                <p class="apply-sub">填写作者资料与联系方式，上传原创作品，工作人员审核后与您洽谈合作。</p>

                <?php foreach ($errors as $e): ?>
                    <div class="apply-alert err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <div class="apply-tip"><i class="fas fa-lightbulb"></i> 请上传最能代表您创作风格的原创作品，并填写真实有效的联系方式。</div>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <div class="apply-grid">
                        <div class="apply-field">
                            <label>昵称 / 笔名 <span class="req">*</span></label>
                            <input type="text" name="nickname" value="<?= htmlspecialchars($form['nickname'] ?? '') ?>" maxlength="100" placeholder="您的作者名或艺名">
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
                            <label>所在城市</label>
                            <select name="city">
                                <option value="">-- 选择城市 --</option>
                                <?php foreach ($allCities as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($form['city'] ?? '') === $c['name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                            <label>创作风格</label>
                            <select name="style">
                                <option value="">-- 选择风格 --</option>
                                <?php foreach ($authorStyles as $s): ?>
                                <option value="<?= $s ?>" <?= ($form['style'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="apply-field full">
                            <label>作者简介</label>
                            <textarea name="bio" rows="3" style="resize:vertical;" placeholder="介绍您的创作背景、风格特色等"><?= htmlspecialchars($form['bio'] ?? '') ?></textarea>
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
                            <label>小红书</label>
                            <input type="text" name="xiaohongshu" maxlength="200" value="<?= htmlspecialchars($form['xiaohongshu'] ?? '') ?>" placeholder="小红书账号">
                        </div>
                        <div class="apply-field full">
                            <label>原创作品照片 <span class="req">*</span></label>
                            <div class="apply-upload">
                                <i class="fas fa-palette" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                <input type="file" name="works[]" accept="image/*" multiple>
                                <small>支持多选，首张将作为头像；单张不超过 5MB（jpg/png/gif/webp）</small>
                            </div>
                        </div>
                    </div>
                    <div class="apply-actions">
                        <button type="submit" class="apply-btn">提交合作申请</button>
                        <a href="../author/list.php" class="apply-back">返回作者库</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
