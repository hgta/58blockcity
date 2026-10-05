<?php
/**
 * 用 AI 把中文标题翻译成英文 SEO slug（后台按钮用）
 * change: help-content-admin (task 7.5)
 *
 * POST JSON: {title: string, id: int(可选，编辑时排除自身)}
 * 返回 JSON: {ok: bool, slug: string, msg: string}
 *
 * - 复用现有聊天渠道（AiProvider 路由 + 故障切换），不额外引入依赖
 * - 模型输出不可信：服务端强制 slugify（只留 a-z0-9-、限 60 字符）
 * - 与 help_articles.slug 唯一键比对，撞车自动加 -2/-3 后缀
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/SecureCrypto.php';
require_once '../classes/AiProvider.php';

checkAdmin();

header('Content-Type: application/json; charset=utf-8');

function slug_out($ok, $slug = '', $msg = '', $meta = null)
{
    $out = ['ok' => $ok, 'slug' => $slug, 'msg' => $msg];
    if ($meta) $out['meta'] = $meta; // 渠道名/耗时/尝试明细
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') slug_out(false, '', '方法不允许');

$in = json_decode(file_get_contents('php://input'), true);
$title = trim((string)($in['title'] ?? ''));
$id    = (int)($in['id'] ?? 0);
if ($title === '') slug_out(false, '', '标题为空');
if (mb_strlen($title) > 120) $title = mb_substr($title, 0, 120);

/** 强制规整：只信任最终形态，不信任模型输出 */
function slug_normalize($s)
{
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = preg_replace('/-{2,}/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') return '';
    return rtrim(mb_substr($s, 0, 60), '-');
}

/** 唯一性兜底：撞车加后缀 */
function unique_slug(PDO $pdo, $slug, $excludeId)
{
    if ($slug === '') return $slug;
    for ($i = 0; $i < 20; $i++) {
        $try = $i === 0 ? $slug : $slug . '-' . ($i + 1);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM help_articles WHERE slug = ? AND id <> ?");
        $stmt->execute([$try, $excludeId]);
        if ((int)$stmt->fetchColumn() === 0) return $try;
    }
    return $slug . '-' . substr(md5($slug . microtime(true)), 0, 6);
}

@set_time_limit(30);
$t0 = microtime(true);

try {
    $messages = [
        [
            'role' => 'system',
            'content' => '你是 SEO 专家。把用户给出的中文文章标题翻译成英文 URL slug。'
                . '只输出 slug 本身：小写英文单词、用连字符连接、3 到 6 个词、不要空格、不要引号、不要解释、不要扩展名。',
        ],
        ['role' => 'user', 'content' => '标题：' . $title],
    ];

    // 非流式 + 每渠道 12s 超时：卡住的渠道会立刻被跳过，切到下一个
    $res = AiProvider::chatOnceWithFailover($pdo, $messages, 12.0);
    $meta = [
        'ms' => (int)round((microtime(true) - $t0) * 1000),
        'provider' => $res['provider'] ? $res['provider']->name() : '',
        'attempts' => $res['attempts'],
    ];
    if (!$res['ok']) {
        slug_out(false, '', 'AI 调用失败：' . $res['error'], $meta);
    }

    $raw = trim((string)$res['answer']);
    // 模型可能带引号/反引号/多余说明，只取第一行看着像 slug 的部分
    $raw = preg_replace('/[`"\']/u', '', $raw);
    $first = trim(strtok($raw, "\n"));
    // 去掉模型常见的开场客套/标签（Sure! / Here is / slug: / URL: …）
    $first = preg_replace('/^\s*(sure|certainly|here(\s+is)?|ok|slug|url|link)\s*[!:,.-]?\s*/i', '', $first);
    $slug = slug_normalize($first);
    if ($slug === '') slug_out(false, '', 'AI 返回无法解析：' . mb_substr($raw, 0, 60));

    $slug = unique_slug($pdo, $slug, $id);
    slug_out(true, $slug, '', $meta);
} catch (Exception $ex) {
    slug_out(false, '', '异常：' . $ex->getMessage());
}
