<?php
/**
 * 微信公众号配置样例
 * 复制本文件为 config/wechat.php 并填写真实值（wechat.php 不入库，服务器专用）
 *
 * 获取方式：
 *   微信公众平台 mp.weixin.qq.com → 设置与开发 → 基本配置
 *   - 开发者ID(AppID)：页面上直接可见
 *   - Token：在"服务器配置"里自定义（与下方 WECHAT_TOKEN 保持一致）
 */

// 公众号服务器配置里的 Token（自定义，用于校验消息签名）
define('WECHAT_TOKEN', 'your_custom_token_here');

// 公众号 AppID（预留，本方案仅用被动回复，暂不调用主动 API）
define('WECHAT_APPID', 'your_appid_here');

// 公众号关注二维码图片地址（登录页展示用，静态二维码即可）
define('WECHAT_QR_URL', 'https://www.58.tl/qrcode_for_gh.jpg');

// 公众号名称（登录页文案用）
define('WECHAT_ACCOUNT_NAME', '58区块城市');
