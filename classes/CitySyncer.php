<?php
/**
 * 城市数据同步器 — 匿名拉取 blockcity.vip 排行榜并更新 cities 表
 *
 * 请求配方（2026-09-06 真实浏览器抓包逆向 + node 端到端验证通过）：
 *   POST https://www.blockcity.vip/api/area/rankList?areaId=0
 *   （参数必须在 query string，body 为空；以下头缺一即返回 500「系统繁忙」）
 *     Content-Type : application/json;charset=utf-8
 *     platform     : H5
 *     Authorization: false        ← 字面量字符串（匿名态）
 *     ak           : <毫秒时间戳><6位随机 0-9A-Z>   ← 客户端自造，无需注册
 *     u            : 0
 *     a            : <8位随机 0-9A-Z>
 *     t            : <毫秒时间戳>
 *     n            : <ak><t><8位随机 0-9A-Z>
 *     s            : 大写MD5("blockcity_blockcity" + t + n + "Blockcity153#abc#123")
 *
 * 返回结构：{"code":200,"data":{"list":[{"name","ranking","userNum","num",...},...]}}
 * 字段映射：ranking→rank，userNum→resident_count，num→activated_blocks
 *
 * 用法：
 *   require_once __DIR__ . '/CitySyncer.php';
 *   $list = CitySyncer::fetchRankList();          // 失败抛 RuntimeException
 *   $stat = CitySyncer::apply($list, $pdo);       // 单事务批量更新 + 掉榜城市排名清零
 *   // $stat = ['fetched'=>200,'updated'=>198,'unchanged'=>0,'missed'=>['中国数藏',...],'demoted'=>2]
 */

class CitySyncer
{
    const API_BASE    = 'https://www.blockcity.vip';
    const API_URL     = 'https://www.blockcity.vip/api/area/rankList?areaId=0';
    const API_REFERER = 'https://www.blockcity.vip/pages/block/area';
    const SIGN_PREFIX = 'blockcity_blockcity';
    const SIGN_SALT   = 'Blockcity153#abc#123';
    const TIMEOUT     = 30;

    /* ============================ 对外接口 ============================ */

    /**
     * 拉取排行榜城市列表（匿名签名请求，无需任何 token）
     *
     * @return array [ ['name'=>..., 'rank'=>int, 'resident_count'=>int, 'activated_blocks'=>int], ... ]
     * @throws RuntimeException HTTP 非 200 / code 非 200 / 结构异常 / 超时
     */
    public static function fetchRankList()
    {
        $hdr = self::signHeaders();

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',           // body 必须为空，参数全在 query
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json;charset=utf-8',
                'Referer: ' . self::API_REFERER,
                'Origin: ' . self::API_BASE,
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'platform: H5',
                'Authorization: false',
                'ak: ' . $hdr['ak'],
                'u: 0',
                'a: ' . $hdr['a'],
                't: ' . $hdr['t'],
                'n: ' . $hdr['n'],
                's: ' . $hdr['s'],
            ],
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('请求 blockcity.vip 失败：' . ($err ?: '未知网络错误'));
        }
        if ($http !== 200) {
            throw new RuntimeException('接口返回 HTTP ' . $http . '，请稍后重试');
        }

        $arr  = json_decode($resp, true);
        $list = (isset($arr['data']['list']) && is_array($arr['data']['list'])) ? $arr['data']['list'] : null;
        if (!is_array($arr) || (int)($arr['code'] ?? -1) !== 200 || !$list) {
            $msg = is_array($arr) ? (string)($arr['msg'] ?? '') : '响应无法解析';
            throw new RuntimeException('接口返回异常（' . $msg . '），签名配方可能需要更新');
        }

        $out = [];
        foreach ($list as $it) {
            $name = trim((string)($it['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name'             => $name,
                'rank'             => (int)($it['ranking'] ?? 0),
                'resident_count'   => (int)($it['userNum'] ?? 0),
                'activated_blocks' => (int)($it['num'] ?? 0),
            ];
        }
        if (!$out) {
            throw new RuntimeException('榜单解析结果为空');
        }
        return $out;
    }

    /**
     * 批量更新 cities 表（单事务；精确匹配 + 去「市」后缀归一化兜底）
     *
     * @param array $list fetchRankList() 或 applyManualJson() 产出的城市数组
     * @param PDO   $pdo
     * @return array ['fetched'=>n, 'updated'=>n, 'unchanged'=>n, 'missed'=>[name,...]]
     * @throws RuntimeException 匹配预载或事务失败（此时零提交）
     */
    public static function apply(array $list, PDO $pdo)
    {
        $pdo = self::ensurePdo($pdo);

        // 预载全表 name → id 映射（200 城规模，全量预载最简可靠）
        try {
            $rows = $pdo->query('SELECT id, name FROM cities')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('读取 cities 表失败：' . $e->getMessage());
        }
        $byExact = [];
        $byNorm  = [];
        $id2name = [];
        foreach ($rows as $r) {
            $name = (string)$r['name'];
            $id   = (int)$r['id'];
            $byExact[$name] = $id;
            $byNorm[self::normName($name)] = $id;
            $id2name[$id]   = $name;
        }

        $stat = ['fetched' => count($list), 'updated' => 0, 'unchanged' => 0, 'missed' => [], 'demoted' => 0];

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                'UPDATE cities SET rank = ?, resident_count = ?, activated_blocks = ?, updated_at = NOW() WHERE id = ?'
            );
            $matchedNames = [];   // 本次榜单命中的库内城市名（用于掉榜清理）
            foreach ($list as $c) {
                $id = $byExact[$c['name']] ?? ($byNorm[self::normName($c['name'])] ?? null);
                if ($id === null) {
                    $stat['missed'][] = $c['name'];
                    continue;
                }
                $matchedNames[$id2name[$id]] = true;
                $upd->execute([$c['rank'], $c['resident_count'], $c['activated_blocks'], $id]);
                if ($upd->rowCount() > 0) {
                    $stat['updated']++;
                } else {
                    $stat['unchanged']++;
                }
            }

            // 掉榜清理：仅清「官方榜语义」的幽灵排名 —— rank 落在 1-200 但不在本次官方名单中。
            // 收窄范围原因：cities 为全量城市表，管理员可能手动维护 200+ / 300+ 的合法排名
            //（那些城市本就不在官方 TOP200，但有真实名次），不能一并清零。
            if ($matchedNames) {
                $ph  = implode(',', array_fill(0, count($matchedNames), '?'));
                $clr = $pdo->prepare(
                    "UPDATE cities SET rank = 0, updated_at = NOW()
                     WHERE rank BETWEEN 1 AND 200 AND name NOT IN ({$ph})"
                );
                $clr->execute(array_keys($matchedNames));
                $stat['demoted'] = $clr->rowCount();
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new RuntimeException('更新失败（已全部回滚）：' . $e->getMessage());
        }
        return $stat;
    }

    /**
     * 手动 JSON 兜底通道：解析粘贴的 JSON 数组并复用 apply() 更新
     * 兼容字段别名：name/city/cityName、rank/ranking、resident_count/userNum/resident/population、
     * activated_blocks/num/blocks
     *
     * @param string $json
     * @param PDO    $pdo
     * @return array 同 apply()
     * @throws RuntimeException JSON 非法 / 无有效条目
     */
    public static function applyManualJson($json, PDO $pdo)
    {
        $json = trim((string)$json);
        if ($json === '') {
            throw new RuntimeException('请粘贴城市数据 JSON');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON 格式解析失败');
        }

        $list = [];
        foreach ($data as $it) {
            if (!is_array($it)) {
                continue;
            }
            $name = trim((string)($it['name'] ?? $it['city'] ?? $it['cityName'] ?? ''));
            if ($name === '') {
                continue;
            }
            $list[] = [
                'name'             => $name,
                'rank'             => (int)($it['rank'] ?? $it['ranking'] ?? 0),
                'resident_count'   => (int)($it['resident_count'] ?? $it['userNum'] ?? $it['resident'] ?? $it['population'] ?? 0),
                'activated_blocks' => (int)($it['activated_blocks'] ?? $it['num'] ?? $it['blocks'] ?? 0),
            ];
        }
        if (!$list) {
            throw new RuntimeException('JSON 中未解析到有效城市条目（需含 name 字段）');
        }
        return self::apply($list, $pdo);
    }

    /* ============================ 内部工具 ============================ */

    /** 0-9A-Z 随机串（与 blockcity 前端 generateMixed2 同字符集） */
    private static function randChars($n)
    {
        $cs = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $s  = '';
        for ($i = 0; $i < $n; $i++) {
            $s .= $cs[random_int(0, 35)];
        }
        return $s;
    }

    /** 组装签名头（ak/t/a/n/s），公式与官方 H5 前端一致 */
    private static function signHeaders()
    {
        $t  = (string)(int)(microtime(true) * 1000);
        $ak = $t . self::randChars(6);              // 客户端自造，无需服务端注册
        $a  = self::randChars(8);
        $n  = $ak . $t . self::randChars(8);
        $s  = strtoupper(md5(self::SIGN_PREFIX . $t . $n . self::SIGN_SALT));
        return ['ak' => $ak, 'a' => $a, 't' => $t, 'n' => $n, 's' => $s];
    }

    /**
     * 城市名归一化：去掉末尾「市」（如「北京市」→「北京」）
     * 仅用于精确匹配失败后的兜底，不改写任何库中数据
     */
    private static function normName($name)
    {
        $name = (string)$name;
        if (function_exists('mb_strlen')
            && mb_strlen($name, 'UTF-8') > 1
            && mb_substr($name, -1, 1, 'UTF-8') === '市') {
            return mb_substr($name, 0, -1, 'UTF-8');
        }
        return $name;
    }

    /**
     * 确保 PDO 连接可用（curl 远程接口可能耗时较久，MySQL 或已断开）
     * 先 SELECT 1 探活；失败且 DB_* 常量可用时重建连接，否则抛原异常
     * 公开供 admin 页面在长耗时操作后复用（保持与旧 bc_ensure_pdo 相同语义）
     */
    public static function ensurePdo($pdo)
    {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (PDOException $e) {
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                return new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
                    ]
                );
            }
            throw $e;
        }
    }
}
