<?php
class CityBCT {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // 获取城市人气值信息（流通量取 cities.popularity）
    public function getCityBCT($city) {
        $stmt = $this->pdo->prepare("
            SELECT cb.*, COALESCE(c.popularity, cb.circulating_supply) AS circulating_supply, c.popularity AS city_popularity
            FROM city_bct cb
            LEFT JOIN cities c ON cb.city = c.name COLLATE utf8mb4_unicode_ci
            WHERE cb.city = ?
        ");
        $stmt->execute([$city]);
        return $stmt->fetch();
    }
    
    // 更新城市人气值价格
    public function updatePrice($city, $newPrice) {
        $stmt = $this->pdo->prepare("UPDATE city_bct SET current_price = ?, last_updated = NOW() WHERE city = ?");
        return $stmt->execute([$newPrice, $city]);
    }
    
    // 更新城市人气值基础价
    public function updateBasePrice($city, $basePrice) {
        $stmt = $this->pdo->prepare("UPDATE city_bct SET base_price = ? WHERE city = ?");
        return $stmt->execute([$basePrice, $city]);
    }
    
    // 按 cities.rank 取前 N 个城市（用于 TOP5 热门城市）
    public function getTopCitiesByRank($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT name FROM cities
            WHERE status = 'active'
            ORDER BY rank ASC, popularity DESC, id ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // 获取所有城市人气值信息（流通量取 cities.popularity）
    public function getAllCitiesBCT() {
        $stmt = $this->pdo->prepare("
            SELECT cb.*, COALESCE(c.popularity, cb.circulating_supply) AS circulating_supply, c.popularity AS city_popularity
            FROM city_bct cb
            LEFT JOIN cities c ON cb.city = c.name COLLATE utf8mb4_unicode_ci
            ORDER BY cb.city
        ");
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

        // 总市值 = SUM(cities.popularity * city_bct.current_price)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(COALESCE(c.popularity, cb.circulating_supply) * cb.current_price), 0) as cap
            FROM city_bct cb
            LEFT JOIN cities c ON cb.city = c.name COLLATE utf8mb4_unicode_ci
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

    // 获取所有城市 24h 涨跌幅
    public function get24hChanges() {
        $changes = [];
        $stmt = $this->pdo->query("
            SELECT t.city,
                (t.current_price - COALESCE(t.prev_price, t.current_price)) / NULLIF(COALESCE(t.prev_price, t.current_price), 0) * 100 as change_pct
            FROM (
                SELECT city,
                    (SELECT price FROM bct_transactions WHERE city = cb.city AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 1) as current_price,
                    (SELECT price FROM bct_transactions WHERE city = cb.city AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) ORDER BY created_at DESC LIMIT 1) as prev_price
                FROM city_bct cb
            ) t
        ");
        while ($row = $stmt->fetch()) {
            $changes[$row['city']] = round($row['change_pct'], 2);
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

    // 获取城市 24h 最高/最低成交价
    public function getCity24hHighLow($city) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(price), 0) as high, COALESCE(MIN(price), 0) as low
            FROM bct_transactions
            WHERE city = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$city]);
        return $stmt->fetch();
    }

    // 获取城市价格历史（折线/OHLC）
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
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at ASC), ',', 1) as open,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at DESC), ',', 1) as close,
                SUM(amount) as volume,
                MIN(created_at) as first_time
            FROM bct_transactions
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
                'volume' => (float)$row['volume']
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
    
    private function getPendingOrdersCount($city, $type) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bct_orders WHERE city = ? AND type = ? AND status = 'pending'");
        $stmt->execute([$city, $type]);
        return $stmt->fetchColumn();
    }
}
?>