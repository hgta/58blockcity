<?php
/**
 * 微信公众号服务器回调（未认证个人订阅号即可使用）
 *
 * 公众平台配置：mp.weixin.qq.com → 设置与开发 → 基本配置 → 服务器配置
 *   URL:   https://www.58.tl/api/wechat.php
 *   Token: 与 config/wechat.php 中 WECHAT_TOKEN 一致
 *   消息加解密方式：明文模式
 *
 * 行为：
 *   GET  → 接入校验（echostr 原样返回）
 *   POST → 接收事件/消息并被动回复：
 *     - 关注（subscribe）/ 已关注扫码（SCAN）/ 发送任意文字 → 回复 6 位登录验证码
 *     - 发送包含「重置 / 密码 / 找回」的文字 → 回复一次性重置密码链接
 *
 * 注意：启用开发者模式后，公众平台后台的「自动回复」会被本文件接管。
 */

// 本端点不需要会话/登录态，直接裸跑，勿引入 session
$configDir = dirname(__DIR__) . '/config';

if (!is_file($configDir . '/wechat.php')) {
    // 尚未配置 Token：静默应答避免微信反复重试，同时留日志提醒部署
    error_log('[wechat] config/wechat.php 不存在，请复制 config/wechat-sample.php 并填写');
    exit('success');
}
require_once $configDir . '/wechat.php';

require_once $configDir . '/database.php';
require_once dirname(__DIR__) . '/classes/WechatAuth.php';

/* ---------------- 签名校验 ---------------- */

/**
 * 校验微信消息签名：sha1(sort(token, timestamp, nonce)) === signature
 */
function wechat_check_signature(): bool {
    $token      = defined('WECHAT_TOKEN') ? WECHAT_TOKEN : '';
    $signature  = $_GET['signature'] ?? '';
    $timestamp  = $_GET['timestamp'] ?? '';
    $nonce      = $_GET['nonce'] ?? '';

    if ($token === '' || $signature === '' || $timestamp === '' || $nonce === '') {
        return false;
    }
    $arr = [$token, $timestamp, $nonce];
    sort($arr, SORT_STRING);
    $expected = sha1(implode('', $arr));
    return hash_equals($expected, $signature);
}

/* ---------------- XML 工具 ---------------- */

function wechat_xml_text(string $toUser, string $fromUser, string $content): string {
    $time = time();
    $content = htmlspecialchars($content, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return <<<XML
<xml>
<ToUserName><![CDATA[{$toUser}]]></ToUserName>
<FromUserName><![CDATA[{$fromUser}]]></FromUserName>
<CreateTime>{$time}</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[{$content}]]></Content>
</xml>
XML;
}

/* ---------------- 入口 ---------------- */

// 接入校验（首次在公众平台点「提交」时触发）
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (wechat_check_signature()) {
        exit((string)($_GET['echostr'] ?? ''));
    }
    http_response_code(403);
    exit;
}

// 消息/事件推送
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!wechat_check_signature()) {
    http_response_code(403);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    exit('success');
}

// 抑制 XML 解析警告，格式异常时直接 success（微信会重试，不重试也罢）
libxml_use_internal_errors(true);
$msg = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
if ($msg === false) {
    exit('success');
}

$fromUser = (string)($msg->FromUserName ?? ''); // 用户 openid
$toUser   = (string)($msg->ToUserName ?? '');   // 公众号原始ID
$msgType  = (string)($msg->MsgType ?? '');
$event    = (string)($msg->Event ?? '');
$content  = trim((string)($msg->Content ?? ''));

if ($fromUser === '') {
    exit('success');
}

$siteUrl = 'https://www.58.tl';

try {
    $wechatAuth = new WechatAuth($pdo);

    // 关键字：重置密码（用户口令不敏感，包含即可命中）
    $wantReset = ($msgType === 'text') && preg_match('/重置|密码|找回/u', $content);

    if ($wantReset) {
        $code  = $wechatAuth->issueCode($fromUser, WechatAuth::CODE_PURPOSE_RESET);
        $link  = $siteUrl . '/auth/reset_password.php?code=' . $code;
        $reply = "收到！您的一次性密码重置链接：\n{$link}\n\n链接 5 分钟内有效，且只能使用一次。若非本人操作请忽略。";
        echo wechat_xml_text($fromUser, $toUser, $reply);
        exit;
    }

    // 关注事件 / 已关注用户扫码 / 任意消息 → 下发登录验证码
    $isEvent = ($msgType === 'event');
    $code = $wechatAuth->issueCode($fromUser, WechatAuth::CODE_PURPOSE_LOGIN);

    if ($isEvent && $event === 'subscribe') {
        $reply = "欢迎关注！🎉\n\n您的登录验证码：{$code}\n\n在登录页选择「微信扫码登录」，输入上面的 6 位验证码即可登录/注册（5 分钟内有效）。\n\n💡 已有账号想绑定微信，或需要重置密码，直接在对话框发送：重置";
    } elseif ($isEvent && $event === 'unsubscribe') {
        exit('success'); // 取关不回复
    } else {
        $reply = "您的登录验证码：{$code}\n\n请在登录页「微信扫码登录」中输入，5 分钟内有效。\n\n💡 重置密码请发送：重置";
    }

    echo wechat_xml_text($fromUser, $toUser, $reply);
    exit;
} catch (Exception $e) {
    error_log('[wechat] 回调处理失败: ' . $e->getMessage());
    // 5 秒内正常应答失败时返回 success 阻止微信重试风暴（用户重发消息即可再拿码）
    exit('success');
}
