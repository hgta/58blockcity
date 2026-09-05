<?php
/**
 * CityKey — 城市键统一换算与清洗工具类
 *
 * 解决库内两套城市维度字段并存的问题：
 *   - int  `city_id`   （blocks / nft_sales / city_profiles 等表）
 *   - str  城市名      （city_bct / circles / posts / users / models / authors 等表）
 *
 * 用法：$key = new CityKey($pdo);
 *   $city    = $key->byPinyin('beijing');          // 取 cities 行
 *   $id      = $key->cityNameToId('北京市');        // 任意形态 → city_id
 *   $cityRow = $key->byId($id);
 *
 * 约定：cities.name 是唯一事实源；所有对外展示/过滤统一走「标准短名」
 * （normalizeName 去除 市/省/自治区/自治州 等行政后缀），业务模块不再各自处理。
 */
class CityKey {
    private $pdo;

    /** @var array|null 短名 → city_id 映射（进程内跨实例静态缓存） */
    private static $nameMap = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* -----------------------------------------------------------------
     * cities 行查询（薄封装 City，保持与现有调用方一致）
     * --------------------------------------------------------------- */

    /** 按拼音取 cities 行（未找到返回 null） */
    public function byPinyin($pinyin) {
        return (new City($this->pdo))->getCityByPinyin((string)$pinyin);
    }

    /** 按 id 取 cities 行（未找到返回 null） */
    public function byId($id) {
        return (new City($this->pdo))->getCityById((int)$id);
    }

    /* -----------------------------------------------------------------
     * 城市名清洗
     * --------------------------------------------------------------- */

    /**
     * 清洗城市名 → 标准短名。
     * 处理：全半角（字母/数字/空格）、首尾空白、行政后缀
     *   北京市 → 北京 ; 北京　(全角空格) → 北京 ; 香港特别行政区 → 香港 ;
     *   广西壮族自治区 → 广西壮族（自动源若带民族前缀，映射未命中时再兜底查询）
     */
    public static function normalizeName($raw) {
        if ($raw === null || $raw === '') {
            return '';
        }
        $s = (string)$raw;

        // 全半角：字母数字转半角；全角空格归一为半角再 trim
        if (function_exists('mb_convert_kana')) {
            $s = mb_convert_kana($s, 'a');
        }
        $s = str_replace(["\u{3000}", "\u{00A0}"], ' ', $s);
        $s = trim($s);

        if ($s === '') {
            return '';
        }

        // 按序去除行政后缀（仅截一次尾；去完不得为空）
        $suffixes = [
            '特别行政区', '自治区', '自治州', '自治县',
            '地区', '盟', '省', '市', '县', '区',
        ];
        foreach ($suffixes as $suf) {
            if (mb_strlen($s) > mb_strlen($suf) && mb_substr($s, -mb_strlen($suf)) === $suf) {
                $s = mb_substr($s, 0, -mb_strlen($suf));
                break;
            }
        }
        return $s;
    }

    /* -----------------------------------------------------------------
     * 短名 ⇄ city_id
     * --------------------------------------------------------------- */

    /** 构建「短名 → city_id」映射（进程内静态缓存，force 可刷新） */
    public function idsByNameLookup($force = false) {
        if (self::$nameMap !== null && !$force) {
            return self::$nameMap;
        }
        $map = [];
        try {
            $stmt = $this->pdo->query('SELECT id, name, pinyin FROM cities');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $n = self::normalizeName($row['name'] ?? '');
                if ($n !== '' && !isset($map[$n])) {
                    $map[$n] = (int)$row['id'];
                }
            }
        } catch (PDOException $e) {
            error_log('[CityKey::idsByNameLookup] 查询失败: ' . $e->getMessage());
        }
        self::$nameMap = $map;
        return $map;
    }

    /**
     * 任意城市名形态 → city_id；找不到返回 null。
     * 三层：① 原样精确查 name；② normalize 后查静态映射；③ LIKE 兜底（防脏数据如 广西壮族市）。
     */
    public function cityNameToId($raw) {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }

        try {
            // ① 原样精确
            $stmt = $this->pdo->prepare('SELECT id FROM cities WHERE name = ? LIMIT 1');
            $stmt->execute([$raw]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }

            // ② 短名映射
            $n = self::normalizeName($raw);
            $map = $this->idsByNameLookup();
            if ($n !== '' && isset($map[$n])) {
                return $map[$n];
            }

            // ③ LIKE 兜底（覆盖 广西壮族→广西 / 名称含后缀错位 等）
            if ($n !== '') {
                $stmt = $this->pdo->prepare('SELECT id FROM cities WHERE name LIKE ? LIMIT 1');
                $stmt->execute(['%' . $n . '%']);
                $id = $stmt->fetchColumn();
                if ($id) {
                    return (int)$id;
                }
            }
        } catch (PDOException $e) {
            error_log('[CityKey::cityNameToId] 查询失败: ' . $e->getMessage());
        }
        return null;
    }

    /** city_id → 标准短名；异常/不存在返回 null */
    public function idToCityName($id) {
        try {
            $stmt = $this->pdo->prepare('SELECT name FROM cities WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$id]);
            $name = $stmt->fetchColumn();
            return $name ? self::normalizeName($name) : null;
        } catch (PDOException $e) {
            error_log('[CityKey::idToCityName] 查询失败: ' . $e->getMessage());
            return null;
        }
    }
}
