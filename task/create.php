<?php
require_once '../config/database.php';
require_once '../classes/Task.php';
require_once '../classes/TaskCategory.php';
require_once '../includes/auth.php';
require_once 'includes/helpers.php';
checkLogin();

$uid       = (int)$_SESSION['user_id'];
$taskModel = new Task($pdo);
$catModel  = new TaskCategory($pdo);

$categories = $catModel->listActive();

// 该类别下展示中技能卡数量（供发布侧“可跳转找承接人”提示）
$skillCountByCat = [];
try {
    $stmt = $pdo->query(
        "SELECT sc.category_id, COUNT(DISTINCT sc.skill_id) AS cnt
         FROM task_skills s
         JOIN task_skill_categories sc ON s.id = sc.skill_id
         WHERE s.status = 'active'
         GROUP BY sc.category_id"
    );
    foreach ($stmt->fetchAll() as $row) {
        $skillCountByCat[(int)$row['category_id']] = (int)$row['cnt'];
    }
} catch (Exception $e) {
    $skillCountByCat = [];
}

// 城市候选（人气值任务必选，账本维度与 cities.name 对齐）
$cities = [];
try {
    $cities = $pdo->query("SELECT name, pinyin FROM cities ORDER BY rank ASC, id ASC")->fetchAll();
} catch (Exception $e) {
    $cities = [];
}

// 当前用户在城市的余额（人气值展示参考）
$myPopularity = [];
try {
    $stmt = $pdo->prepare("SELECT city, popularity FROM user_city_popularity WHERE user_id = ?");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $row) {
        $myPopularity[$row['city']] = (int)$row['popularity'];
    }
} catch (Exception $e) {
    $myPopularity = [];
}

// ---------- POST 落库 ----------
$errors = [];
$old = [
    'category_id' => 0, 'title' => '', 'description' => '', 'accept_desc' => '',
    'reward_type' => 'popularity', 'city' => '', 'reward_amount' => '', 'reward_amount_cash' => '',
    'quota' => 1, 'review_days' => 3, 'expire_at' => '',
    'target_type' => '', 'target_id' => '',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    foreach ($old as $k => $v) {
        $old[$k] = trim((string)($_POST[$k] ?? $v));
    }

    // 金额：人气值=个；现金=元（入库转分）
    $payType = in_array($old['reward_type'], ['popularity', 'cash'], true) ? $old['reward_type'] : 'popularity';
    $rawAmount = $payType === 'cash' ? $old['reward_amount_cash'] : $old['reward_amount'];
    $rewardAmount = (int)$rawAmount;
    $finalAmount = $payType === 'cash' ? $rewardAmount * 100 : $rewardAmount;

    [$ok, $res] = $taskModel->create([
        'employer_id'   => $uid,
        'category_id'   => (int)$old['category_id'],
        'title'         => $old['title'],
        'description'   => $old['description'],
        'accept_desc'   => $old['accept_desc'],
        'city'          => $old['city'], // 人气值任务结算城市；现金任务可留空（仅展示）
        'reward_type'   => $payType,
        'reward_amount' => $finalAmount,
        'quota'         => (int)$old['quota'],
        'review_days'   => (int)$old['review_days'],
        'expire_at'     => $old['expire_at'],
        'target_type'   => $old['target_type'] !== '' ? $old['target_type'] : '',
        'target_id'     => $old['target_id'],
    ]);
    if ($ok) {
        setFlashMessage('success', '任务发布成功，快去广场/推荐给你的承接人吧');
        header('Location: view.php?id=' . (int)$res);
        exit;
    }
    $errors[] = $res;
}

$nowMin = date('Y-m-d\TH:i', time() + 600);
$csrf   = generateCsrfToken();

$site_config['title'] = '发布悬赏任务 - 任务广场';
$site_config['description'] = '发布悬赏任务：代互访 / 代打卡 / 代做市长 / 其他小任务，人气值或现金（线下结算）众包给承接人。';
require_once 'includes/header.php';
?>
<style>
.ct-wrap { max-width: 860px; margin: 22px auto; padding: 0 15px; }
.ct-card { background: #fff; border: 1px solid #f0ece6; border-radius: 14px; padding: 24px 28px; margin-bottom: 16px; }
.ct-card h2 { font-size: 18px; margin: 0 0 6px; }
.ct-tip { color: #8b929a; font-size: 13px; margin-bottom: 16px; }
.ct-form label { display: block; font-size: 13px; color: #56606b; font-weight: 600; margin: 16px 0 6px; }
.ct-form label .req { color: #ff4d4f; }
.ct-form label .sub { font-weight: 400; color: #9aa1a8; margin-left: 4px; }
.ct-form input[type=text], .ct-form input[type=number], .ct-form input[type=datetime-local],
.ct-form textarea, .ct-form select { width: 100%; border: 1px solid #dde1e6; border-radius: 8px; padding: 10px 12px; font-size: 14px; box-sizing: border-box; }
.ct-form textarea { line-height: 1.7; }
.ct-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
.ct-radio-row { display: flex; gap: 10px; margin-top: 8px; }
.ct-radio { flex: 1; border: 1px solid #dde1e6; border-radius: 10px; padding: 14px 16px; cursor: pointer; }
.ct-radio.selected { border-color: #ff6b00; background: #fff8f2; }
.ct-radio b { font-size: 15px; }
.ct-radio p { margin: 6px 0 0; font-size: 12px; color: #8b929a; line-height: 1.5; }
.ct-balance { font-size: 12px; color: #8b929a; margin-top: 6px; }
.ct-balance b { color: #ff6b00; }
.ct-err { background: #fdecea; color: #c0392b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 12px; }
.ct-actions { display: flex; gap: 12px; margin-top: 22px; align-items: center; }
.ct-btn { border: none; border-radius: 10px; padding: 12px 32px; font-size: 15px; cursor: pointer; text-decoration: none; display: inline-block; }
.ct-btn.primary { background: #ff6b00; color: #fff; }
.ct-btn.ghost { background: #fff; border: 1px solid #d8dce1; color: #56606b; }
.skill-hint { display: none; font-size: 13px; color: #4f6ef7; background: #eef3ff; border-radius: 8px; padding: 8px 12px; margin-top: 10px; }
</style>

<div class="ct-wrap">
    <div class="tk-crumb" style="font-size:13px;color:#9aa1a8;margin-bottom:12px;"><a href="index.php" style="color:#9aa1a8;text-decoration:none;">任务广场</a> › 发布悬赏任务</div>

    <div class="ct-card">
        <h2>➕ 发布悬赏任务</h2>
        <div class="ct-tip">说明你要别人代做的线下动作（如代互访 / 代打卡 / 代做市长），约定验收标准与赏金；接单人提交凭证、你验收通过后结算。现金任务平台不托管，线下自行完成付款。</div>

        <?php foreach ($errors as $e): ?>
            <div class="ct-err"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <form class="ct-form" method="post" action="create.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <label>任务类别 <span class="req">*</span></label>
            <select name="category_id" id="cat-sel" required>
                <option value="">请选择类别</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$old['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="skill-hint" id="skill-hint"></div>

            <label>任务标题 <span class="req">*</span> <span class="sub">120 字内，一句话说清「帮我去某地做什么」</span></label>
            <input type="text" name="title" maxlength="120" required value="<?= htmlspecialchars($old['title']) ?>" placeholder="如：帮我到北京西二旗互访打卡 3 天">

            <label>任务说明 <span class="req">*</span></label>
            <textarea name="description" rows="4" required placeholder="详细描述：做什么、在哪个区块/互访圈、时间要求、需要哪些步骤…"><?= htmlspecialchars($old['description']) ?></textarea>

            <label>验收说明 <span class="req">*</span> <span class="sub">告诉接单人交付什么就算完成（截图/文字凭证标准）</span></label>
            <textarea name="accept_desc" rows="3" required placeholder="例：提供本人在该区块互访成功的截图 + 时间地点文字说明，且截图需清晰可见用户名与互访记录"><?= htmlspecialchars($old['accept_desc']) ?></textarea>

            <label>赏金类型 <span class="req">*</span></label>
            <div class="ct-radio-row">
                <div class="ct-radio <?= ($old['reward_type'] ?? 'popularity') === 'popularity' ? 'selected' : '' ?>" onclick="pickType('popularity')">
                    <b>Ⓟ 人气值</b>
                    <p>记录你在任务关联城市的人气值，验收通过后完成结算（人气值为自管记录，站内不做扣减/划转）。</p>
                </div>
                <div class="ct-radio <?= ($old['reward_type'] ?? '') === 'cash' ? 'selected' : '' ?>" onclick="pickType('cash')">
                    <b>¥ 现金（线下）</b>
                    <p>平台不托管资金，验收通过后由你线下付款给接单人。</p>
                </div>
            </div>
            <input type="hidden" name="reward_type" id="reward_type" value="<?= htmlspecialchars($old['reward_type'] !== '' ? $old['reward_type'] : 'popularity') ?>">

            <div id="block-popularity">
                <label>结算城市 <span class="req">*</span> <span class="sub">人气值账本按城市计，标记本任务关联的城市</span></label>
                <select name="city" id="city-sel">
                    <option value="">请选择城市</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>" <?= $old['city'] === $c['name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="ct-balance" id="pop-balance">当前你在该城市的人气值余额：—（发布无需冻结，结算仅校验不扣减）</div>

                <label>赏金额（人气值）<span class="req">*</span></label>
                <input type="number" name="reward_amount" id="amount-input" min="1" step="1" value="<?= htmlspecialchars((string)$old['reward_amount']) ?>" placeholder="正整数，如 30">
            </div>

            <div id="block-cash" style="display:none;">
                <label>赏金额（元）<span class="req">*</span> <span class="sub">线下结算，仅作任务约定展示</span></label>
                <input type="number" name="reward_amount_cash" id="amount-input-cash" min="1" step="1" value="<?= htmlspecialchars((string)$old['reward_amount_cash']) ?>" placeholder="正整数（元），如 50">
            </div>

            <div class="ct-grid">
                <div>
                    <label>名额 N <span class="req">*</span> <span class="sub">众包：每份独立交付结算；1 = 单人</span></label>
                    <input type="number" name="quota" min="1" max="999" required value="<?= htmlspecialchars((string)$old['quota']) ?>">
                </div>
                <div>
                    <label>验收期限 <span class="req">*</span> <span class="sub">交付后 N 天内须验收</span></label>
                    <input type="number" name="review_days" min="1" max="30" required value="<?= htmlspecialchars((string)$old['review_days']) ?>">
                </div>
            </div>

            <label>领取截止时间 <span class="req">*</span> <span class="sub">到期后不再接受新领取</span></label>
            <input type="datetime-local" name="expire_at" required value="<?= htmlspecialchars($old['expire_at']) ?>" min="<?= $nowMin ?>">

            <label>可选：关联区块 / 互访圈 <span class="sub">买家跳转到对应对象查看（如代互访任务关联某互访圈）</span></label>
            <div class="ct-grid">
                <div>
                    <select name="target_type" id="target-type" onchange="document.getElementById('target-id').style.display=this.value?'block':'none'">
                        <option value="">不关联</option>
                        <option value="block" <?= $old['target_type'] === 'block' ? 'selected' : '' ?>>区块（填区块 ID）</option>
                        <option value="circle" <?= $old['target_type'] === 'circle' ? 'selected' : '' ?>>互访圈（填圈子 ID）</option>
                    </select>
                </div>
                <div>
                    <input type="text" name="target_id" id="target-id" style="<?= $old['target_type'] ? 'display:block' : 'display:none' ?>" placeholder="对象 ID（数字）" value="<?= htmlspecialchars($old['target_id']) ?>">
                </div>
            </div>

            <div class="ct-actions">
                <button class="ct-btn primary" type="submit">发布任务</button>
                <a class="ct-btn ghost" href="index.php">取消</a>
            </div>
        </form>
    </div>
</div>

<script>
var typeNow = document.getElementById('reward_type').value || 'popularity';
var cities = <?= json_encode($cities ? array_column($cities, 'name') : []) ?>;
var myPop = <?= json_encode($myPopularity) ?>;
var skillCountByCat = <?= json_encode($skillCountByCat) ?>;

function pickType(t) {
    typeNow = t;
    document.getElementById('reward_type').value = t;
    document.querySelectorAll('.ct-radio').forEach(function (el) { el.classList.remove('selected'); });
    var blocks = document.querySelectorAll('.ct-radio');
    (t === 'popularity' ? blocks[0] : blocks[1]).classList.add('selected');
    document.getElementById('block-popularity').style.display = t === 'popularity' ? '' : 'none';
    document.getElementById('block-cash').style.display = t === 'cash' ? '' : 'none';
    document.getElementById('city-sel').required = (t === 'popularity');
    document.getElementById('amount-input').required = (t === 'popularity');
    document.getElementById('amount-input-cash').required = (t === 'cash');
}

function showBalance() {
    var el = document.getElementById('city-sel');
    var box = document.getElementById('pop-balance');
    if (typeNow !== 'popularity' || !el.value) { return; }
    var v = myPop[el.value];
    if (typeof v === 'undefined') {
        box.innerHTML = '当前你在 <b>' + el.value + '</b> 的人气值余额：0（人气值不足不影响发布，结算仅校验不扣减）';
    } else {
        box.innerHTML = '当前你在 <b>' + el.value + '</b> 的人气值余额：' + v;
    }
}
document.getElementById('city-sel').addEventListener('change', showBalance);
document.getElementById('city-sel').addEventListener('input', showBalance);

// 类别 → 技能卡提示
function showSkillHint() {
    var sel = document.getElementById('cat-sel');
    var hint = document.getElementById('skill-hint');
    var id = parseInt(sel.value, 10) || 0;
    var n = skillCountByCat[id] || 0;
    if (!id) { hint.style.display = 'none'; return; }
    if (n > 0) {
        hint.style.display = 'block';
        hint.innerHTML = '该类别下当前有 <b>' + n + '</b> 张展示中的技能卡（活跃承接人），<a href="find.php?category=' + id + '" style="color:#3d63dd;">去「找承接人」看看 ›</a>';
    } else {
        hint.style.display = 'none';
    }
}
document.getElementById('cat-sel').addEventListener('change', showSkillHint);
window.addEventListener('DOMContentLoaded', function () {
    pickType(typeNow);
    showBalance();
    showSkillHint();
});
</script>

<?php require_once 'includes/footer.php'; ?>
