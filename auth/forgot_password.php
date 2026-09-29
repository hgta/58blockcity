<?php
/**
 * 忘记密码 · 引导页
 *
 * 重置通道（按优先级）：
 *   ① 微信通道：在公众号对话框发送「重置」→ 收到一次性链接 → 设置新密码
 *   ② 人工兜底：联系官方客服微信（BitPFP），管理员在后台核实身份后重置
 */

if (!isset($site_config)) {
    $site_config = [
        'name'                   => '58区块城市',
        'desc'                   => '找回您的账户密码',
        'redirect_after_login'   => '../index.php',
        'home_url'               => '../index.php',
        'db_path'                => '../config/database.php',
        'class_path'             => '../classes/',
        'includes_path'          => __DIR__ . '/../mall/includes/',
    ];
}

$sharedIncludes = dirname(__DIR__) . '/includes';

require_once $site_config['db_path'];
require_once $sharedIncludes . '/functions.php';
require_once $sharedIncludes . '/auth.php';

$__wechatCfg = dirname(__DIR__) . '/config/wechat.php';
if (is_file($__wechatCfg)) {
    require_once $__wechatCfg;
}
$__qrUrl      = defined('WECHAT_QR_URL') ? WECHAT_QR_URL : 'https://www.58.tl/qrcode_for_gh.jpg';
$__wechatName = defined('WECHAT_ACCOUNT_NAME') ? WECHAT_ACCOUNT_NAME : '58区块城市';
?>
<?php include $site_config['includes_path'] . 'header.php'; ?>

<style>
.fp-container { max-width:520px; margin:50px auto; padding:20px; }
.fp-header { text-align:center; margin-bottom:25px; }
.fp-header h2 { color:#333; font-size:22px; margin-bottom:8px; }
.fp-header p { color:#999; font-size:14px; }
.fp-card { background:#fff; border:1px solid #eee; border-radius:10px; padding:26px; margin-bottom:18px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
.fp-card h3 { font-size:16px; color:#333; margin-bottom:12px; }
.fp-qr { width:150px; height:150px; border:1px solid #eee; border-radius:10px; padding:6px; }
.fp-steps { font-size:14px; color:#555; line-height:2; }
.fp-steps b { color:#333; }
.fp-kw { display:inline-block; background:#fff3e0; color:#e65100; border-radius:4px; padding:1px 10px; font-weight:bold; }
.fp-manual { background:#f9f9f9; border-radius:10px; padding:18px 22px; font-size:13.5px; color:#666; line-height:1.9; }
.fp-manual a { color:#ff6b00; }
.fp-footer { text-align:center; margin-top:16px; font-size:14px; }
.fp-footer a { color:#ff6b00; font-weight:bold; }
</style>

<div class="fp-container">
    <div class="fp-header">
        <h2>找回密码</h2>
        <p>通过微信公众号自助重置，或联系人工客服</p>
    </div>

    <div class="fp-card">
        <h3>方式一：微信通道重置（推荐，1 分钟搞定）</h3>
        <div style="display:flex; gap:22px; align-items:flex-start; flex-wrap:wrap;">
            <img class="fp-qr" src="<?= htmlspecialchars($__qrUrl) ?>" alt="公众号二维码">
            <div class="fp-steps" style="flex:1; min-width:220px;">
                <b>①</b> 微信扫二维码关注公众号「<?= htmlspecialchars($__wechatName) ?>」<br>
                <b>②</b> 在公众号对话框发送 <span class="fp-kw">重置</span><br>
                <b>③</b> 公众号会回复一条一次性重置链接（5 分钟内有效）<br>
                <b>④</b> 点击链接即可设置新密码
            </div>
        </div>
        <p style="font-size:12px;color:#999;margin-top:12px;">提示：重置链接只能使用一次；若未收到回复，请确认已关注公众号后重新发送「重置」</p>
    </div>

    <div class="fp-manual">
        <b>方式二：人工兜底</b><br>
        若无法使用微信（如账号未绑定微信、链接已过期等），可直接联系官方客服
        （微信号：<b>BitPFP</b>），注明您的用户名和注册邮箱/手机号，
        管理员核实身份后会为您重置密码。
    </div>

    <div class="fp-footer">
        <a href="login.php">← 返回登录</a>
    </div>
</div>

<?php include $site_config['includes_path'] . 'footer.php'; ?>
