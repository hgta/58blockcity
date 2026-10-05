<?php
/**
 * 后台 AI 小任务统一入口（摘要 / slug 生成等短任务）
 * change: help-content-admin (task 9.x)
 *
 * 与前台小帮的区别：
 * - 非流式（要的是完整结果），每渠道独立超时，卡住立刻切换下一渠道
 * - 默认优先直连模型渠道，本机 Hermes（完整 agent 循环）排最后——短任务用它很慢
 * - 超时与渠道偏好可在 system_settings 调整（无需改代码）：
 *     ai_admin_task_timeout   单渠道超时秒数，默认 30
 *     ai_admin_prefer_direct  1=直连模型渠道优先、本机 Hermes 殿后；0=按后台配置顺序（默认）
 *     ai_admin_task_model     后台小任务专用模型（覆盖渠道默认模型）；留空=用渠道默认
 *                             实测提示：ark-code-latest 是带深度思考的编码路由模型，
 *                             写 100 字摘要要 ~92s；本机 Hermes 同题 ~10s。慢模型请在此换成快模型
 */

class AiAdminTask
{
    const DEFAULT_TIMEOUT = 30.0;
    const MAX_TIMEOUT     = 60.0;

    /** 读取配置（缺省回落默认值，不依赖 migration 是否执行） */
    public static function config(PDO $db)
    {
        $cfg = ['timeout' => self::DEFAULT_TIMEOUT, 'prefer_direct' => false, 'model' => ''];
        try {
            $rows = $db->query(
                "SELECT setting_key, setting_value FROM system_settings
                 WHERE setting_key IN ('ai_admin_task_timeout','ai_admin_prefer_direct','ai_admin_task_model')"
            )->fetchAll();
            foreach ($rows as $r) {
                if ($r['setting_key'] === 'ai_admin_task_timeout') {
                    $t = (float)$r['setting_value'];
                    if ($t > 0) $cfg['timeout'] = min(self::MAX_TIMEOUT, max(5.0, $t));
                } elseif ($r['setting_key'] === 'ai_admin_prefer_direct') {
                    $cfg['prefer_direct'] = ($r['setting_value'] === '1');
                } elseif ($r['setting_key'] === 'ai_admin_task_model') {
                    $cfg['model'] = trim((string)$r['setting_value']);
                }
            }
        } catch (Exception $ex) { /* 配置读取失败用默认值 */ }
        return $cfg;
    }

    /**
     * 执行一次后台 AI 任务
     * @return array{ok:bool, answer:string, error:string, meta:array}
     */
    public static function run(PDO $db, array $messages)
    {
        $cfg = self::config($db);
        // 两个渠道 × 单渠道超时 + 余量，避免脚本被 PHP/nginx 砍掉后只剩黑盒失败
        @set_time_limit((int)($cfg['timeout'] * 2 + 20));
        $t0 = microtime(true);

        $res = AiProvider::chatOnceWithFailover(
            $db, $messages, $cfg['timeout'], $cfg['prefer_direct'],
            $cfg['model'] !== '' ? $cfg['model'] : null
        );

        $meta = [
            'ms'            => (int)round((microtime(true) - $t0) * 1000),
            'provider'      => $res['provider'] ? $res['provider']->name() : '',
            'timeout'       => $cfg['timeout'],
            'prefer_direct' => $cfg['prefer_direct'],
            'model'         => $cfg['model'],
            'attempts'      => $res['attempts'],
        ];
        return [
            'ok'     => $res['ok'],
            'answer' => $res['answer'],
            'error'  => $res['error'],
            'meta'   => $meta,
        ];
    }
}
