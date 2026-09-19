<?php
class CityBCT {
    // 全词条统一总供给量（原 city_bct.total_supply 全表固定值，不再逐行存储）
    const TOTAL_SUPPLY = 21000000;

    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // BCT 行情统一出自 cities 表：单价取 bct_base_price/bct_current_price，
    // 流通量 = popularity - popularity_consume（不再落库、不再依赖旧 city_bct）。
    // 返回键与旧实现兼容：id/city/base_price/current_price/circulating_supply/
    // total_supply/last_updated/city_popularity，便于各消费方无感切换。
    private function bctSelect($where = '', $orderBy = 'ORDER BY c.name') {
        return "
            SELECT c.id,
                c.name AS city,
                c.bct_base_price AS base_price,
                c.bct_current_price AS current_price,
                GREATEST(COALESCE(c.popularity, 0) - COALESCE(c.popularity_consume, 0), 0) AS circulating_supply,
                " . self::TOTAL_SUPPLY . " AS total_supply,
                COALESCE(c.bct_price_updated, c.updated_at) AS last_updated,
                c.popularity AS city_popularity
            FROM cities c
            {$where}
            {$orderBy}
        ";
    }

    // 获取单个词条的人气值行情（城市不存在返回 false）
    public function getCityBCT($city) {
        $stmt = $this->pdo->prepare($this->bctSelect('WHERE c.name = ?'));
        $stmt->execute([$city]);
        return $stmt->fetch();
    }
    
    /**
     * 更新城市人气值当前价
     *
     * 这里是所有改价途径（后台单行保存、后台批量设置、autoAdjustPrice 自动调价）
     * 的唯一收口，因此价格历史在此埋点即可覆盖全部路径。
     *
     * 历史记录遵循两条约束：
     *  - 仅在价格实际发生变化时写入，避免自动调价反复以同价调用产生冗余记录
     *  - 记录失败不阻断价格更新（历史是派生数据，不应影响主流程）
     */
    public function updatePrice($city, $newPrice) {
        $newPrice = round((float)$newPrice, 2);

        // 读取当前价与基础价：用于判断是否真的变化，以及记录当时的基础价
        $stmt = $this->pdo->prepare("SELECT bct_current_price, bct_base_price FROM cities WHERE name = ?");
        $stmt->execute([$city]);
        $before = $stmt->fetch();
        if (!$before) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE cities SET bct_current_price = ?, bct_price_updated = NOW(), updated_at = NOW() WHERE name = ?");
        $ok = $stmt->execute([$newPrice, $city]);

        // 价格未变化则不重复记录
        if ($ok && round((float)$before['bct_current_price'], 2) !== $newPrice) {
            $this->recordPriceHistory($city, $newPrice, $before['bct_base_price']);
        }

        return $ok;
    }

    /**
     * 记录一条价格历史
     *
     * 容错：历史表写入异常不应影响价格更新主流程，因此这里吞掉异常并记录日志。
     */
    private function recordPriceHistory($city, $price, $basePrice = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO bct_price_history (city, price, base_price, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$city, $price, $basePrice, date('Y-m-d H:i:s')]);
        } catch (Exception $e) {
            error_log("recordPriceHistory failed for [{$city}]: " . $e->getMessage());
        }
    }
    
    // 更新城市人气值基础价
    public function updateBasePrice($city, $basePrice) {
        $stmt = $this->pdo->prepare("UPDATE cities SET bct_base_price = ?, updated_at = NOW() WHERE name = ?");
        return $stmt->execute([$basePrice, $city]);
    }
    
    // 按 cities.rank 取前 N 个城市（用于 TOP5 热门城市）
    // 若 rank 字段不存在或查询失败，则 fallback 为按 popularity 降序取前 N
    public function getTopCitiesByRank($limit = 5) {
        $limit = (int)$limit;
        try {
            $hasRank = $this->pdo->query("SHOW COLUMNS FROM cities LIKE 'rank'")->rowCount() > 0;
            if ($hasRank) {
                $stmt = $this->pdo->prepare("
                    SELECT name FROM cities
                    WHERE status = 'active'
                    ORDER BY rank ASC, popularity DESC, id ASC
                    LIMIT {$limit}
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT name FROM cities
                    WHERE status = 'active'
                    ORDER BY popularity DESC, id ASC
                    LIMIT {$limit}
                ");
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("getTopCitiesByRank fallback: " . $e->getMessage());
            $stmt = $this->pdo->prepare("
                SELECT name FROM cities
                ORDER BY popularity DESC, id ASC
                LIMIT {$limit}
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    // 获取所有词条人气值行情（= cities 全量，天然包含全部城市与品牌/数字资产词条）
    public function getAllCitiesBCT() {
        $stmt = $this->pdo->prepare($this->bctSelect());
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // 获取市场全局统计
    public function getMarketStats() {
        $stats = [
            'total_volume_24h' => 0,
            'total_market_cap' => 0,
            'gainers_count' => 0,
            'losers_count' => 0,
            'active_orders' => 0
        ];

        // 24h 成交额
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(amount * price), 0) as volume
            FROM bct_transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['total_volume_24h'] = (float)$stmt->fetchColumn();

        // 总市值 = SUM(真实流通量 * cities.bct_current_price)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(GREATEST(COALESCE(c.popularity, 0) - COALESCE(c.popularity_consume, 0), 0) * c.bct_current_price), 0) as cap
            FROM cities c
        ");
        $stats['total_market_cap'] = (float)$stmt->fetchColumn();

        // 涨跌城市数
        $changes = $this->get24hChanges();
        foreach ($changes as $change) {
            if ($change > 0) $stats['gainers_count']++;
            elseif ($change < 0) $stats['losers_count']++;
        }

        // 活跃订单数
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM bct_orders WHERE status IN ('pending', 'processing')
        ");
        $stats['active_orders'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    /**
     * 获取所有城市的 24h 涨跌幅（批量，一次查询返回全部城市）
     *
     * 数据源为 bct_price_history（由 updatePrice 埋点写入），不再依赖
     * bct_transactions —— 后者仅由平台交易撮合写入，而平台交易限 500 BCT，
     * 大额挂单走 direct/mediator 永不成交，导致该表结构性为空、涨跌恒为 0。
     *
     * 口径为「时间点对齐」：
     *   cur  = 城市最新一条历史价；若近 24h 无记录则回退为该最新一条（价格未变）
     *   prev = 24 小时前及之前的最后一条历史价
     * 与旧实现「要求前价落在 [24h,48h] 窗口内」相比，不因窗口内缺记录而丢弃城市。
     *
     * 性能：全部计算在单条 SQL 内完成，避免对 421 个城市逐个查询（N+1）。
     *
     * @return array [city => change_pct]，无任何历史记录的城市不出现在结果中
     */
    public function get24hChanges() {
        $changes = [];
        $stmt = $this->pdo->query("
            SELECT t.city,
                CASE
                    WHEN t.cur_price IS NULL OR t.cur_price = 0 THEN 0
                    WHEN t.prev_price IS NULL THEN 0
                    ELSE (t.cur_price - t.prev_price) / t.prev_price * 100
                END AS change_pct
            FROM (
                SELECT c.name AS city,
                    -- 当前价：该城市最新一条历史（24h 内无记录时即为那条未变的价格）
                    (SELECT h.price FROM bct_price_history h
                     WHERE h.city = c.name
                     ORDER BY h.created_at DESC, h.id DESC LIMIT 1) AS cur_price,
                    -- 前价：24 小时前及之前的最后一条
                    (SELECT h.price FROM bct_price_history h
                     WHERE h.city = c.name
                       AND h.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     ORDER BY h.created_at DESC, h.id DESC LIMIT 1) AS prev_price
                FROM cities c
            ) t
            WHERE t.cur_price IS NOT NULL
        ");
        while ($row = $stmt->fetch()) {
            $changes[$row['city']] = round((float)$row['change_pct'], 2);
        }
        return $changes;
    }

    // 获取涨跌榜
    public function getTopGainersLosers($limit = 10) {
        $changes = $this->get24hChanges();
        arsort($changes);
        $gainers = array_slice($changes, 0, $limit, true);
        asort($changes);
        $losers = array_slice($changes, 0, $limit, true);
        return ['gainers' => $gainers, 'losers' => $losers];
    }

    // 获取城市 24h 成交量
    public function getCity24hVolume($city) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount * price), 0) as volume
            FROM bct_transactions
            WHERE city = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$city]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * 获取城市 24h 最高/最低价
     *
     * 数据源为 bct_price_history（价格历史），不再是成交流水。
     * 无 24h 内记录时返回 0，表示无数据 —— 不以其它数据填充。
     */
    public function getCity24hHighLow($city) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(price), 0) as high, COALESCE(MIN(price), 0) as low
            FROM bct_price_history
            WHERE city = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$city]);
        return $stmt->fetch();
    }

    /**
     * 获取城市价格走势（折线/OHLC）
     *
     * 数据源为 bct_price_history（价格历史）。
     *
     * 注意 volume 字段：成交量必须来自真实成交（bct_transactions），而该表
     * 结构性为空（见 get24hChanges 注释）。这里显式返回 0 而非用「调价次数」
     * 等数据冒充，待成交流水机制建立后再接入。
     */
    public function getPriceHistory($city, $interval = '24h') {
        $intervalMap = [
            '1h' => ['start' => 'INTERVAL 1 HOUR', 'group' => '%Y-%m-%d %H:%i'],
            '24h' => ['start' => 'INTERVAL 24 HOUR', 'group' => '%Y-%m-%d %H:00'],
            '7d' => ['start' => 'INTERVAL 7 DAY', 'group' => '%Y-%m-%d %H:00'],
            '30d' => ['start' => 'INTERVAL 30 DAY', 'group' => '%Y-%m-%d']
        ];
        $cfg = $intervalMap[$interval] ?? $intervalMap['24h'];

        $stmt = $this->pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, ?) as time_key,
                MIN(price) as low,
                MAX(price) as high,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at ASC, id ASC), ',', 1) as open,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at DESC, id DESC), ',', 1) as close,
                MIN(created_at) as first_time
            FROM bct_price_history
            WHERE city = ? AND created_at >= DATE_SUB(NOW(), {$cfg['start']})
            GROUP BY time_key
            ORDER BY first_time ASC
        ");
        $stmt->execute([$cfg['group'], $city]);
        $rows = $stmt->fetchAll();

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'time' => $row['time_key'],
                'open' => (float)$row['open'],
                'high' => (float)$row['high'],
                'low' => (float)$row['low'],
                'close' => (float)$row['close'],
                // 成交量依赖成交流水（bct_transactions），当前该表无记录，故为 0
                'volume' => 0.0
            ];
        }
        return $history;
    }

    // 根据供需自动调整价格
    public function autoAdjustPrice($city) {
        // 获取当前供需数据
        $buyOrders = $this->getPendingOrdersCount($city, 'buy');
        $sellOrders = $this->getPendingOrdersCount($city, 'sell');
        
        // 简单供需算法
        $ratio = $buyOrders / max(1, $sellOrders);
        $cityInfo = $this->getCityBCT($city);
        
        if($ratio > 1.2) {
            // 需求旺盛，价格上涨5%
            $newPrice = $cityInfo['current_price'] * 1.05;
        } elseif($ratio < 0.8) {
            // 供给过剩，价格下跌5%
            $newPrice = $cityInfo['current_price'] * 0.95;
        } else {
            // 供需平衡，价格不变
            $newPrice = $cityInfo['current_price'];
        }
        
        // 确保不低于基础价格
        $newPrice = max($newPrice, $cityInfo['base_price']);
        
        $this->updatePrice($city, $newPrice);
        return $newPrice;
    }
    
    /**
     * 统计待撮合的挂单数量
     *
     * 仅统计平台交易（platform）的 pending 订单，与自动撮合的可见范围保持一致，
     * 避免把 direct / mediator 订单计入供需而影响价格。
     */
    private function getPendingOrdersCount($city, $type) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bct_orders 
            WHERE city = ? AND type = ? AND status = 'pending' AND trade_type = 'platform'");
        $stmt->execute([$city, $type]);
        return $stmt->fetchColumn();
    }
}
?>