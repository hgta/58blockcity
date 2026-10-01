<?php
/**
 * 区块子站用户排行榜（change: block-user-ranking）
 *
 * 七个维度（全部只读实时计算；数据规模：blocks sold 千级、用户百级，无需缓存/物化）：
 *   blocks 实际区块数：合并组整组计 1 块（与 Block::getUserActualBlockStats 同口径）
 *   votes  投票数：合并组拆开逐块各计 1 票（与 top200city.php「我拥有的」同口径）
 *   cities 拥有城市数：去重计数
 *   value  区块总价值：按 calculateBlockPriceNew() 权威计价（不依赖 blocks.price 的历史完整性）
 *   merged 合并组数：merged_blocks 按 owner 计数
 *   listed 挂牌中：block_listings(seller_id, status='listed')
 *   wanted 求购中：purchase_requests(user_id, status='active')
 *
 * 账号过滤：默认不过滤 role / status（持有即事实，管理员与站长的区块同样计入）；
 *   如需隐藏测试号/系统号，在 HIDDEN_USER_IDS 里列 users.id。
 * 名次规则：RANK() 并列语义（同值同名次、后续名次跳号），user_id 升序为稳定 tie-break。
 * 合并组口径：以"该用户自己的合并组"为准去重子块（与 Block::getUserMergedBlockIndex 一致）；
 *   若历史数据存在组 owner 与子块 owner 不一致，各用户计数仍以自身行 + 自身组自洽，不会抛错。
 */
require_once __DIR__ . '/Block.php';
require_once __DIR__ . '/../config/block_prices.php';

class BlockRanking
{
    const DEFAULT_SORT = 'blocks';
    const TOP_LIMIT    = 50;

    /**
     * 榜单隐藏账号（users.id 列表）。
     *
     * 默认空数组：管理员、站长与任意状态账号都参与排名——持有区块是客观事实，
     * 早期版本排除 role='admin' 会导致"站长自己的 500 多块不上榜"，与实际持仓矛盾。
     * 需要屏蔽测试号/系统号时用 setHiddenUserIds([...])（页面脚注会自动说明有账号被隐藏）。
     *
     * @var int[]
     */
    public static $hiddenUserIds = [];

    public static function setHiddenUserIds(array $ids)
    {
        self::$hiddenUserIds = array_map('intval', $ids);
    }

    public static function hiddenUserIds()
    {
        return self::$hiddenUserIds;
    }

    /** 维度定义：metric 为聚合字段名（与 aggregate() 输出键对应） */
    private static $sortDefs = [
        'blocks' => ['label' => '区块数', 'icon' => 'fa-cubes',         'metric' => 'blocks', 'unit' => '个'],
        'votes'  => ['label' => '投票数', 'icon' => 'fa-vote-yea',      'metric' => 'votes',  'unit' => '票'],
        'cities' => ['label' => '城市数', 'icon' => 'fa-map-marked-alt', 'metric' => 'cities', 'unit' => '城'],
        'value'  => ['label' => '总价值', 'icon' => 'fa-coins',          'metric' => 'value',  'unit' => '元'],
        'merged' => ['label' => '合并组', 'icon' => 'fa-object-group',  'metric' => 'merged', 'unit' => '组'],
        'listed' => ['label' => '挂牌中', 'icon' => 'fa-tag',           'metric' => 'listed', 'unit' => '条'],
        'wanted' => ['label' => '求购中', 'icon' => 'fa-search-dollar',  'metric' => 'wanted', 'unit' => '条'],
    ];

    /** @var PDO */
    private $pdo;
    /** @var Block */
    private $block;
    /** @var array|null 单请求内聚合缓存 */
    private $agg = null;

    public function __construct($pdo)
    {
        $this->pdo   = $pdo;
        $this->block = new Block($pdo);
    }

    public static function allowedSorts()
    {
        return array_keys(self::$sortDefs);
    }

    /** 白名单校验：非法值回退默认维度 */
    public static function normalizeSort($sort)
    {
        $sort = (string)$sort;
        return isset(self::$sortDefs[$sort]) ? $sort : self::DEFAULT_SORT;
    }

    public static function sortDefs()
    {
        return self::$sortDefs;
    }

    public static function metricOf($sort)
    {
        return self::$sortDefs[self::normalizeSort($sort)]['metric'];
    }

    /**
     * 全量聚合（所有维度一次算完；单请求内缓存）
     * @return array [user_id, username, avatar, blocks, votes, cities, value, merged, listed, wanted]
     */
    private function aggregate()
    {
        if ($this->agg !== null) {
            return $this->agg;
        }

        // 1) 参与用户（默认全部用户；仅跳过 $hiddenUserIds 里显式隐藏的账号）
        $hidden = [];
        foreach (self::$hiddenUserIds as $hid) {
            $hidden[(int)$hid] = true;
        }

        $users = [];
        try {
            $stmt = $this->pdo->query("SELECT id, username, avatar FROM users");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $uid = (int)$u['id'];
                if (isset($hidden[$uid])) {
                    continue; // 测试号 / 系统号：显式隐藏
                }
                $users[$uid] = [
                    'user_id'  => $uid,
                    'username' => (string)$u['username'],
                    'avatar'   => (string)$u['avatar'],
                    'blocks'   => 0,
                    'votes'    => 0,
                    'cities'   => 0,
                    'value'    => 0.0,
                    'merged'   => 0,
                    'listed'   => 0,
                    'wanted'   => 0,
                    '_citySet' => [],
                ];
            }
        } catch (PDOException $e) {
            error_log('[BlockRanking] users 查询失败: ' . $e->getMessage());
            return $this->agg = [];
        }

        // 2) 合并组：按 owner 分组 + 子块键索引（去重计数/计价用）
        $groupsByOwner   = [];
        $childKeysByOwner = [];
        try {
            foreach ($this->pdo->query("SELECT id, city_id, zone, merged_blocks, owner_id FROM merged_blocks") as $g) {
                $uid  = (int)$g['owner_id'];
                $nums = [];
                foreach (explode(',', (string)$g['merged_blocks']) as $n) {
                    $n = trim($n);
                    if ($n !== '') {
                        $nums[] = $n;
                    }
                }
                $groupsByOwner[$uid][] = [
                    'city_id' => (int)$g['city_id'],
                    'zone'    => (string)$g['zone'],
                    'nums'    => $nums,
                ];
                foreach ($nums as $n) {
                    $childKeysByOwner[$uid][$this->block->normalizeBlockKey($g['city_id'], $g['zone'], $n)] = true;
                }
            }
        } catch (PDOException $e) {
            error_log('[BlockRanking] merged_blocks 查询失败: ' . $e->getMessage());
        }

        // 3) 已认领区块：投票数逐行计；区块数/价值跳过"自己合并组的子块"（由组统一计）
        try {
            foreach ($this->pdo->query(
                "SELECT city_id, zone, block_number, owner_id
                 FROM blocks WHERE status = 'sold' AND owner_id IS NOT NULL"
            ) as $b) {
                $uid = (int)$b['owner_id'];
                if (!isset($users[$uid])) {
                    continue; // 管理员 / 封禁 / 已删除用户不参与
                }
                $u = &$users[$uid];
                $u['votes']++;
                $u['_citySet'][(int)$b['city_id']] = true;
                if (isset($childKeysByOwner[$uid][$this->block->normalizeBlockKey($b['city_id'], $b['zone'], $b['block_number'])])) {
                    continue;
                }
                $u['blocks']++;
                $u['value'] += calculateBlockPriceNew((string)$b['zone'], (string)$b['block_number']);
            }
            unset($u);
        } catch (PDOException $e) {
            error_log('[BlockRanking] blocks 查询失败: ' . $e->getMessage());
        }

        // 4) 合并组计入：每组 +1 块、+1 组、子块价格累加（与 top200city「我拥有的」口径一致）
        foreach ($groupsByOwner as $uid => $groups) {
            if (!isset($users[$uid])) {
                continue;
            }
            $u = &$users[$uid];
            foreach ($groups as $g) {
                $u['blocks']++;
                $u['merged']++;
                $u['_citySet'][$g['city_id']] = true;
                foreach ($g['nums'] as $n) {
                    $u['value'] += calculateBlockPriceNew($g['zone'], $n);
                }
            }
            unset($u);
        }

        // 5) 交易维度
        try {
            foreach ($this->pdo->query(
                "SELECT seller_id, COUNT(*) AS c FROM block_listings WHERE status = 'listed' GROUP BY seller_id"
            ) as $r) {
                $uid = (int)$r['seller_id'];
                if (isset($users[$uid])) {
                    $users[$uid]['listed'] = (int)$r['c'];
                }
            }
        } catch (PDOException $e) {
            error_log('[BlockRanking] block_listings 查询失败: ' . $e->getMessage());
        }
        try {
            foreach ($this->pdo->query(
                "SELECT user_id, COUNT(*) AS c FROM purchase_requests WHERE status = 'active' GROUP BY user_id"
            ) as $r) {
                $uid = (int)$r['user_id'];
                if (isset($users[$uid])) {
                    $users[$uid]['wanted'] = (int)$r['c'];
                }
            }
        } catch (PDOException $e) {
            error_log('[BlockRanking] purchase_requests 查询失败: ' . $e->getMessage());
        }

        foreach ($users as &$u) {
            $u['cities'] = count($u['_citySet']);
            unset($u['_citySet']);
        }
        unset($u);

        return $this->agg = array_values($users);
    }

    /**
     * 按维度排序并赋予 RANK() 并列名次
     * @return array
     */
    private function rankedList($sort)
    {
        $metric = self::metricOf($sort);
        $list   = array_values(array_filter($this->aggregate(), function ($u) use ($metric) {
            return $u[$metric] > 0;
        }));

        usort($list, function ($a, $b) use ($metric) {
            if ($a[$metric] != $b[$metric]) {
                return $b[$metric] > $a[$metric] ? 1 : -1;
            }
            return $a['user_id'] <=> $b['user_id'];
        });

        $ranked = [];
        $prevVal = null;
        $rank    = 0;
        foreach ($list as $i => $u) {
            if ($prevVal === null || $u[$metric] != $prevVal) {
                $rank    = $i + 1;
                $prevVal = $u[$metric];
            }
            $u['rank'] = $rank;
            $ranked[]  = $u;
        }
        return $ranked;
    }

    /**
     * TOP 榜单
     * @return array
     */
    public function getTopUsers($sort, $limit = self::TOP_LIMIT)
    {
        return array_slice($this->rankedList($sort), 0, max(1, (int)$limit));
    }

    /** 当前维度的实际上榜人数（非零参与者） */
    public function getParticipantCount($sort)
    {
        return count($this->rankedList($sort));
    }

    /**
     * 我的名次定位
     * @return array|null {rank, value, in_board, gap_prev, gap_board}；用户被排除或不存在时 null
     */
    public function getUserRank($userId, $sort)
    {
        $userId = (int)$userId;
        $metric = self::metricOf($sort);

        $mine = null;
        foreach ($this->aggregate() as $u) {
            if ($u['user_id'] === $userId) {
                $mine = $u;
                break;
            }
        }
        if ($mine === null) {
            return null;
        }

        $list = $this->rankedList($sort);
        foreach ($list as $i => $u) {
            if ($u['user_id'] === $userId) {
                return [
                    'rank'     => $u['rank'],
                    'value'    => $mine[$metric],
                    'in_board' => true,
                    'gap_prev' => $i > 0 ? ($list[$i - 1][$metric] - $mine[$metric]) : null,
                    'gap_board' => null,
                ];
            }
        }

        // 榜外：给出距上榜门槛的差距
        $lastVal = !empty($list) ? $list[count($list) - 1][$metric] : null;
        return [
            'rank'      => null,
            'value'     => $mine[$metric],
            'in_board'  => false,
            'gap_prev'  => null,
            'gap_board' => ($lastVal === null || $mine[$metric] >= $lastVal) ? null : ($lastVal - $mine[$metric]),
        ];
    }
}
