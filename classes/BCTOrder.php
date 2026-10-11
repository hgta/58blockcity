<?php
class BCTOrder {
    private $pdo;

    /** 有效期默认选项（未选择时使用） */
    const DEFAULT_DURATION = '30d';

    /** 表示「长期有效、不过期」的选项值 */
    const DURATION_FOREVER = 'forever';

    /**
     * 有效期选项定义（选项值 => 展示名 + 过期时间计算方式）
     *
     * 集中定义便于前端与后端共用同一份语义，避免前端传任意天数。
     *  - days: 按天数累加
     *  - months: 按自然月累加（+3 months 而非 +90 days）
     *  - forever: 不设置过期时间
     */
    private static $durationOptions = [
        '1d'      => ['label' => '1天',   'days' => 1],
        '2d'      => ['label' => '2天',   'days' => 2],
        '7d'      => ['label' => '7天',   'days' => 7],
        '30d'     => ['label' => '30天',  'days' => 30],
        '3m'      => ['label' => '3个月', 'months' => 3],
        'forever' => ['label' => '长期',  'forever' => true],
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 获取有效期选项列表（供页面渲染下拉/分段控件）
     *
     * @return array 选项值 => 展示名
     */
    public static function getDurationOptions() {
        $out = [];
        foreach (self::$durationOptions as $key => $opt) {
            $out[$key] = $opt['label'];
        }
        return $out;
    }

    /** 选项值是否合法 */
    public static function isValidDuration($duration) {
        return is_string($duration) && isset(self::$durationOptions[$duration]);
    }

    /**
     * 把有效期选项解析为过期时间
     *
     * 「长期」返回 null（不设置过期时间，与 Task.php 以空值判断不过期的约定一致）。
     * 时间口径：写入与后续过期判定都使用 PHP 时间，不在 SQL 中混用 NOW()，
     * 避免类似 classes/Auction.php 记录过的时区错位问题。
     *
     * @param string $duration 有效期选项值
     * @param int|null $nowTs  基准时间戳（默认取当前时间）
     * @return string|null 'Y-m-d H:i:s' 格式的过期时间，长期为 null
     */
    public static function resolveExpiresAt($duration, $nowTs = null) {
        $nowTs = $nowTs === null ? time() : $nowTs;

        if (!self::isValidDuration($duration)) {
            $duration = self::DEFAULT_DURATION;
        }

        $opt = self::$durationOptions[$duration];

        if (!empty($opt['forever'])) {
            return null;
        }
        if (isset($opt['months'])) {
            // 自然月：跨不同长度月份时与固定天数不同
            return date('Y-m-d H:i:s', strtotime("+{$opt['months']} months", $nowTs));
        }
        return date('Y-m-d H:i:s', strtotime("+{$opt['days']} days", $nowTs));
    }

    // 生成订单号
    private function generateOrderNo() {
        return date('YmdHis').mt_rand(1000, 9999);
    }
    
    // 创建订单
    public function createOrder($userId, $city, $type, $amount, $tradeType, $contactInfo = null, $userPrice = null, $duration = null, $mediatorId = null) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取当前城市价格，用户可覆盖
            $cityBCT = new CityBCT($this->pdo);
            $cityInfo = $cityBCT->getCityBCT($city);
            $price = ($userPrice > 0) ? $userPrice : $cityInfo['current_price'];
            
            // 计算总金额
            $total = $amount * $price;
            
            // 注：不校验出售订单余额 —— 系统已简化流程，发布订单无需验证余额，
            //     余额在交易完成时才处理。
            
            // 计算过期时间（有效期选项 -> 过期时间）
            // 未指定时使用默认 30 天；兼容按「天数」直接传值的旧调用方式（0 或负数视为长期）
            if (is_numeric($duration)) {
                $days = (int)$duration;
                $expiresAt = ($days > 0)
                    ? date('Y-m-d H:i:s', strtotime("+{$days} days"))
                    : null;
            } else {
                if ($duration === null || $duration === '') {
                    $duration = self::DEFAULT_DURATION;
                }
                $expiresAt = self::resolveExpiresAt($duration);
            }
            
            // 创建订单
            $orderNo = $this->generateOrderNo();
            $stmt = $this->pdo->prepare("INSERT INTO bct_orders 
                (order_no, user_id, city, type, amount, price, total_amount, trade_type, contact_info, mediator_id, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            $stmt->execute([
                $orderNo, 
                $userId, 
                $city, 
                $type, 
                $amount, 
                $price, 
                $total, 
                $tradeType,
                $contactInfo,
                $mediatorId,
                $expiresAt
            ]);
            
            $orderId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $orderId;
        } catch(Exception $e) {
            // 仅在事务仍然活跃时回滚，避免「无事务可回滚」引发二次异常
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    // 取消订单
    public function cancelOrder($orderId, $userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND user_id = ? AND status IN ('pending','processing')");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        
        if (!$order) return false;

        // 交易中（有活跃接单）的订单不允许直接取消，需先在交易里放弃
        if ($this->getActiveClaim($orderId)) return false;
        
        // 卖单：余额未扣过，取消时不需退还
        
        $stmt = $this->pdo->prepare("UPDATE bct_orders SET status = 'canceled' WHERE id = ?");
        return $stmt->execute([$orderId]);
    }

    /**
     * 处理已超期订单：置为 expired
     *
     * 仅处理 status 为 pending / processing 且 expires_at 非空且已早于当前时间的订单。
     *  - completed（已全部成交）不会被标记过期，避免破坏成交语义
     *  - canceled（用户已取消）保持用户意图
     *  - expires_at 为空的「长期」订单永不处理
     *  - processing（部分成交）过期后剩余未成交数量一并作废
     *
     * 幂等：重复执行时已不再是 pending/processing 的行不会被再次更新。
     * 时间口径：当前时间由 PHP 传入，SQL 中不使用 NOW()，与写入 expires_at 的时区保持一致。
     *
     * @param int|null $userId 限定用户（惰性推进时只处理该用户）；null 表示全站
     * @param string|null $city 限定城市；null 表示不限
     * @param int $limit 单次处理上限，避免页面请求中做过量写操作
     * @return int 实际置为过期的订单数
     */
    public function expireOverdueOrders($userId = null, $city = null, $limit = 500) {
        $limit = max(1, (int)$limit);
        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE bct_orders 
                SET status = 'expired' 
                WHERE status IN ('pending','processing') 
                  AND expires_at IS NOT NULL 
                  AND expires_at < ?";

        // 有活跃接单（交易中）的订单不做过期处理：
        // 释放/完成由接单流程（放弃、超时释放、确认收款）负责，避免交易中的订单被误置过期
        $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM bct_order_claims c
                    WHERE c.order_id = bct_orders.id AND c.status IN ('matched','buyer_confirmed'))";
        $params = [$now];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        if ($city !== null) {
            $sql .= " AND city = ?";
            $params[] = $city;
        }

        // 限定影响行数，避免单次处理过多；按最早过期优先
        $sql .= " ORDER BY expires_at ASC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * 统计已超期但尚未处理的订单数（用于监控/日志）
     */
    public function countOverdueOrders($userId = null) {
        $sql = "SELECT COUNT(*) FROM bct_orders 
                WHERE status IN ('pending','processing') 
                  AND expires_at IS NOT NULL 
                  AND expires_at < ?";
        $params = [date('Y-m-d H:i:s')];
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
    
    // 平台交易自动匹配
    public function autoMatchPlatformOrder($orderId) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取订单信息（FOR UPDATE 锁定，避免并发重复撮合同一笔订单）
            $stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if(!$order) {
                throw new Exception("订单不存在或不可匹配");
            }

            // 自身必须仍是待撮合的平台交易订单
            if ($order['trade_type'] !== 'platform') {
                throw new Exception("非平台交易订单，不参与自动撮合");
            }
            if ((int)$order['amount'] <= 0) {
                throw new Exception("订单剩余数量为 0");
            }

            // 查找匹配订单：同城市、相反方向、价格交叉、平台交易、非自身
            $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';

            // 排除自身（id != ?）并锁定候选行，避免与自己成交
            if ($order['type'] == 'buy') {
                // 买单：找卖单价格 <= 买单价格的订单，按价格从低到高，同价格按时间优先
                $stmt = $this->pdo->prepare("SELECT * FROM bct_orders 
                    WHERE city = ? AND type = ? AND status = 'pending' 
                    AND trade_type = 'platform' 
                    AND id <> ?
                    AND price <= ?
                    ORDER BY price ASC, created_at ASC, id ASC
                    LIMIT 1 FOR UPDATE");
                $stmt->execute([$order['city'], $matchType, (int)$orderId, $order['price']]);
            } else {
                // 卖单：找买单价格 >= 卖单价格的订单，按价格从高到低，同价格按时间优先
                $stmt = $this->pdo->prepare("SELECT * FROM bct_orders 
                    WHERE city = ? AND type = ? AND status = 'pending' 
                    AND trade_type = 'platform' 
                    AND id <> ?
                    AND price >= ?
                    ORDER BY price DESC, created_at ASC, id ASC
                    LIMIT 1 FOR UPDATE");
                $stmt->execute([$order['city'], $matchType, (int)$orderId, $order['price']]);
            }
                
            $matchOrder = $stmt->fetch();
            
            if(!$matchOrder) {
                throw new Exception("暂无匹配订单");
            }

            // 对手方必须属于不同用户，禁止自成交
            if ((int)$matchOrder['user_id'] === (int)$order['user_id']) {
                throw new Exception("撮合对手方与自身为同一用户");
            }

            // 确定交易数量
            $tradeAmount = min((int)$order['amount'], (int)$matchOrder['amount']);
            if ($tradeAmount <= 0) {
                throw new Exception("撮合数量无效");
            }

            // 执行交易
            $this->executeTrade($order, $matchOrder, $tradeAmount, 'platform');
            
            $this->pdo->commit();
            return true;
        } catch(Exception $e) {
            // 仅在事务仍然活跃时回滚，避免「无事务可回滚」引发二次异常
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }
    
    /**
     * 手动匹配两个订单并执行交易（供 API 调用）
     * 
     * @param int $orderId1 买方订单ID
     * @param int $orderId2 卖方订单ID
     * @param int $amount   交易数量
     * @param string $tradeType 交易类型
     * @return array ['success' => bool, 'message' => string, 'tx_id' => int]
     */
    public function matchAndExecute($orderId1, $orderId2, $amount, $tradeType = 'direct') {
        try {
            $this->pdo->beginTransaction();
            
            // 获取两个订单
            $stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$orderId1]);
            $order1 = $stmt->fetch();
            
            $stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$orderId2]);
            $order2 = $stmt->fetch();
            
            if (!$order1 || !$order2) {
                throw new Exception("订单不存在或已不可交易");
            }
            
            // 验证方向相反
            if ($order1['type'] == $order2['type']) {
                throw new Exception("两个订单方向相同，无法交易");
            }
            
            // 验证城市相同
            if ($order1['city'] != $order2['city']) {
                throw new Exception("两个订单城市不同，无法交易");
            }
            
            // 验证价格交叉
            $buyOrder = $order1['type'] == 'buy' ? $order1 : $order2;
            $sellOrder = $order1['type'] == 'sell' ? $order1 : $order2;
            
            if ($buyOrder['price'] < $sellOrder['price']) {
                throw new Exception("买卖价格不匹配（买方出价¥" . $buyOrder['price'] . " < 卖方要价¥" . $sellOrder['price'] . "）");
            }
            
            // 验证数量
            if ($amount > $order1['amount'] || $amount > $order2['amount']) {
                throw new Exception("交易数量超过订单剩余数量");
            }
            
            // 执行交易
            $this->executeTrade($order1, $order2, $amount, $tradeType);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => '交易成功'];
        } catch (Exception $e) {
            // 仅在事务仍然活跃时回滚，避免「无事务可回滚」引发二次异常
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // 执行交易
    private function executeTrade($order1, $order2, $amount, $tradeType) {
        $account = new UserBCTAccount($this->pdo);
        $tx = new BCTTransaction($this->pdo);
        
        // 确定买卖双方
        $buyOrder = $order1['type'] == 'buy' ? $order1 : $order2;
        $sellOrder = $order1['type'] == 'sell' ? $order1 : $order2;
        
        // 成交价取卖方价格
        $tradePrice = $sellOrder['price'];
        
        // 计算手续费
        $feeRate = $tradeType == 'platform' ? 0.10 : ($tradeType == 'mediator' ? 0.02 : 0);
        $totalAmount = $amount * $tradePrice;
        $fee = round($totalAmount * $feeRate, 2);
        $netAmount = round($totalAmount - $fee, 2);
        
        // 转账流程
        // 将BCT从卖方转到买方（余额直接扣除）
        $account->transfer($sellOrder['user_id'], $buyOrder['user_id'], $sellOrder['city'], $amount);
        
        // 3. 创建交易记录
        $tx->create(
            $buyOrder['id'], 
            $sellOrder['user_id'], 
            $buyOrder['user_id'], 
            $sellOrder['city'], 
            $amount, 
            $tradePrice, 
            $fee, 
            $feeRate > 0 ? ($tradeType == 'platform' ? 'platform_fee' : 'mediator_fee') : null,
            $netAmount,
            'trade'
        );
        
        // 更新订单状态
        $this->updateOrderAfterTrade($buyOrder['id'], $amount);
        $this->updateOrderAfterTrade($sellOrder['id'], $amount, $sellOrder['user_id'], $sellOrder['city']);

        // 成交价即最新市场价：更新城市现价（同时落一条价格历史，供涨跌统计）
        $this->syncCityPriceAfterTrade($sellOrder['city'], $tradePrice);
    }

    /**
     * 成交后以成交价同步城市现价
     *
     * 走 CityBCT::updatePrice() 唯一收口（内部含价格历史埋点）。
     * 容错：现价同步失败不应影响成交主流程，仅记录日志。
     */
    private function syncCityPriceAfterTrade($city, $tradePrice) {
        try {
            require_once __DIR__ . '/CityBCT.php';
            (new CityBCT($this->pdo))->updatePrice($city, (float)$tradePrice);
        } catch (Exception $e) {
            error_log('BCT sync city price after trade error: ' . $e->getMessage());
        }
    }
    
    /**
     * 成交后更新订单剩余数量与状态
     *
     * 说明：bct_orders.amount 的语义是「剩余可成交数量」。
     * 使用原子递减（amount = amount - ?）而非「先读后写」，避免并发撮合下
     * 基于过期快照计算出的剩余量相互覆盖。
     *
     * @param int $orderId       订单ID
     * @param int $tradedAmount  本次成交数量
     */
    private function updateOrderAfterTrade($orderId, $tradedAmount, $sellerId = null, $city = null) {
        $tradedAmount = (int)$tradedAmount;
        if ($tradedAmount <= 0) {
            return;
        }

        // 原子扣减剩余数量，并用 GREATEST 防止出现负数
        $stmt = $this->pdo->prepare("UPDATE bct_orders 
            SET amount = GREATEST(amount - ?, 0) 
            WHERE id = ?");
        $stmt->execute([$tradedAmount, $orderId]);

        // 剩余数量为 0 视为全部完成，否则标记为部分成交
        $stmt = $this->pdo->prepare("UPDATE bct_orders 
            SET status = CASE WHEN amount <= 0 THEN 'completed' ELSE 'processing' END 
            WHERE id = ?");
        $stmt->execute([$orderId]);
    }
	
    /**
     * 获取买卖盘深度
     */
    public function getOrderBook($city, $type = null, $limit = 10) {
        $sql = "
            SELECT price, SUM(amount) as total_amount, COUNT(*) as order_count
            FROM bct_orders
            WHERE city = ? AND status IN ('pending', 'processing')
        ";
        $params = [$city];

        if ($type === 'sell' || $type === 'buy') {
            $sql .= " AND type = ?";
            $params[] = $type;
        }

        $sql .= " GROUP BY price";

        if ($type === 'sell' || $type === null) {
            // 卖盘按价格从低到高
            $sql .= " ORDER BY price ASC";
        } else {
            // 买盘按价格从高到低
            $sql .= " ORDER BY price DESC";
        }

        $sql .= " LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // 计算累计深度
        $cumulative = 0;
        foreach ($rows as &$row) {
            $cumulative += (float)$row['total_amount'];
            $row['cumulative_amount'] = $cumulative;
        }

        return $rows;
    }

    /**
     * 获取最新成交记录
     */
    public function getRecentTrades($city = null, $limit = 20) {
        $sql = "
            SELECT t.*, u.username as seller_name, ub.username as buyer_name,
                o.type as order_type
            FROM bct_transactions t
            LEFT JOIN users u ON t.from_user = u.id
            LEFT JOIN users ub ON t.to_user = ub.id
            LEFT JOIN bct_orders o ON t.order_id = o.id
            WHERE 1=1
        ";
        $params = [];

        if ($city) {
            $sql .= " AND t.city = ?";
            $params[] = $city;
        }

        $sql .= " ORDER BY t.created_at DESC LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
	 * 获取用户订单（带分页）
	 */
	public function getUserOrders($userId, $type = 'all', $status = 'all', $page = 1, $perPage = 15) {
		$sql = "SELECT * FROM bct_orders WHERE user_id = ?";
		$params = [$userId];

		if ($type === 'buy') { $sql .= " AND type = 'buy'"; }
		elseif ($type === 'sell') { $sql .= " AND type = 'sell'"; }

		$statusSql = self::buildStatusCondition($status);
		if ($statusSql !== '') {
			$sql .= $statusSql;
		}

		$sql .= " ORDER BY created_at DESC, id DESC LIMIT " . (int)$perPage . " OFFSET " . ((int)$page - 1) * (int)$perPage;

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	/**
	 * 获取用户订单总数（用于分页）
	 */
	public function getUserOrderCount($userId, $type = 'all', $status = 'all') {
		$sql = "SELECT COUNT(*) FROM bct_orders WHERE user_id = ?";
		$params = [$userId];

		if ($type === 'buy') {
			$sql .= " AND type = 'buy'";
		} elseif ($type === 'sell') {
			$sql .= " AND type = 'sell'";
		}

		$statusSql = self::buildStatusCondition($status);
		if ($statusSql !== '') {
			$sql .= $statusSql;
		}

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return (int)$stmt->fetchColumn();
	}

	/**
	 * 构造状态筛选条件
	 *
	 * 'all' 或空表示不筛选；'active' 表示未完结（pending + processing）；
	 * 其余为具体状态值（含 expired）。
	 */
	public static function buildStatusCondition($status) {
		$allowed = ['pending', 'processing', 'completed', 'canceled', 'expired'];
		if ($status === 'active') {
			return " AND status IN ('pending','processing')";
		}
		if (in_array($status, $allowed, true)) {
			return " AND status = '{$status}'";
		}
		return '';
	}

	/**
	 * 获取用户订单统计
	 */
	public function getUserOrderStats($userId) {
		$stats = ['buy_active' => 0, 'sell_active' => 0, 'completed' => 0];
		
		$stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bct_orders WHERE user_id = ? AND type='buy' AND status IN ('pending','processing')");
		$stmt->execute([$userId]);
		$stats['buy_active'] = (int)$stmt->fetchColumn();

		$stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bct_orders WHERE user_id = ? AND type='sell' AND status IN ('pending','processing')");
		$stmt->execute([$userId]);
		$stats['sell_active'] = (int)$stmt->fetchColumn();

		$stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bct_orders WHERE user_id = ? AND status = 'completed'");
		$stmt->execute([$userId]);
		$stats['completed'] = (int)$stmt->fetchColumn();

		return $stats;
	}
	
	// 在 classes/BCTOrder.php 的 BCTOrder 类中添加以下方法

	// 在 classes/BCTOrder.php 的 BCTOrder 类中修改 getActiveOrders 方法

	/**
	 * 获取活跃订单
	 * @param string $type 订单类型: 'buy' 或 'sell'
	 * @param int $limit 限制数量
	 * @return array
	 */
	public function getActiveOrders($type = null, $limit = 20) {
		$sql = "SELECT o.*, u.username 
				FROM bct_orders o 
				LEFT JOIN users u ON o.user_id = u.id 
				WHERE o.status IN ('pending', 'processing')";
		
		$params = [];
		
		if ($type) {
			$sql .= " AND o.type = ?";
			$params[] = $type;
		}
		
		$sql .= " ORDER BY o.created_at DESC LIMIT " . (int)$limit;
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		
		return $stmt->fetchAll();
	}

	/**
	 * 挂单大厅：构造活跃挂单的公共筛选条件（列表与计数共用，保证口径一致）
	 *
	 * 支持的筛选：
	 *  - type: 'buy' / 'sell'（空为不限）
	 *  - city: 城市名模糊匹配
	 *  - trade_type: 'direct' / 'platform' / 'mediator'
	 */
	private function buildHallConditions($filters, &$params) {
		$sql = " FROM bct_orders o
			LEFT JOIN users u ON o.user_id = u.id
			LEFT JOIN bct_order_claims c ON c.order_id = o.id AND c.status IN ('matched','buyer_confirmed')
			WHERE o.status IN ('pending', 'processing')";
		$params = [];

		if (!empty($filters['type']) && in_array($filters['type'], ['buy', 'sell'], true)) {
			$sql .= " AND o.type = ?";
			$params[] = $filters['type'];
		}
		if (!empty($filters['city'])) {
			$sql .= " AND o.city LIKE ?";
			$params[] = '%' . $filters['city'] . '%';
		}
		if (!empty($filters['trade_type']) && in_array($filters['trade_type'], ['direct', 'platform', 'mediator'], true)) {
			$sql .= " AND o.trade_type = ?";
			$params[] = $filters['trade_type'];
		}

		return $sql;
	}

	/**
	 * 挂单大厅：分页检索活跃挂单（含挂单人用户名）
	 *
	 * @param array $filters 见 buildHallConditions
	 * @param int $page 页码（从 1 开始）
	 * @param int $perPage 每页条数
	 * @param string $sort 排序方式：time（默认，最新优先）/ price_asc / price_desc / amount_desc
	 */
	public function searchActiveOrders($filters = [], $page = 1, $perPage = 20, $sort = 'time') {
		$params = [];
		$sql = $this->buildHallConditions($filters, $params);

		switch ($sort) {
			case 'price_asc':  $orderBy = "o.price ASC"; break;
			case 'price_desc': $orderBy = "o.price DESC"; break;
			case 'amount_desc': $orderBy = "o.amount DESC"; break;
			default:           $orderBy = "o.created_at DESC, o.id DESC"; break;
		}

		$page = max(1, (int)$page);
		$perPage = max(1, (int)$perPage);
		$sql .= " ORDER BY " . $orderBy
			. " LIMIT " . $perPage . " OFFSET " . (($page - 1) * $perPage);

		$sql = "SELECT o.*, u.username, c.status AS claim_status, c.buyer_side_user_id AS claim_buyer_id, c.seller_side_user_id AS claim_seller_id" . $sql;

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	/**
	 * 挂单大厅：统计符合条件的活跃挂单总数（分页用）
	 */
	public function countActiveOrders($filters = []) {
		$params = [];
		$sql = "SELECT COUNT(*)" . $this->buildHallConditions($filters, $params);

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return (int)$stmt->fetchColumn();
	}

	// ==================== 直接交易接单流程（bct-direct-trade-claim-flow） ====================

	/** 接单后买方未确认付款的超时释放时限（小时） */
	const CLAIM_STALE_HOURS = 24;

	/**
	 * 获取订单当前活跃的接单（claim）
	 * @return array|null 无活跃接单时返回 null
	 */
	public function getActiveClaim($orderId) {
		$stmt = $this->pdo->prepare("SELECT * FROM bct_order_claims
			WHERE order_id = ? AND status IN ('matched','buyer_confirmed')
			ORDER BY id DESC LIMIT 1");
		$stmt->execute([(int)$orderId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	/**
	 * 获取用户参与中的接单列表（个人中心"进行中的交易"）
	 */
	public function getUserActiveClaims($userId) {
		$stmt = $this->pdo->prepare("SELECT c.*,
				o.order_no, o.city, o.type, o.amount, o.price, o.total_amount, o.contact_info,
				ub.username AS buyer_side_name, us.username AS seller_side_name
			FROM bct_order_claims c
			JOIN bct_orders o ON o.id = c.order_id
			LEFT JOIN users ub ON ub.id = c.buyer_side_user_id
			LEFT JOIN users us ON us.id = c.seller_side_user_id
			WHERE c.status IN ('matched','buyer_confirmed')
				AND (c.buyer_side_user_id = ? OR c.seller_side_user_id = ?)
			ORDER BY c.created_at DESC");
		$stmt->execute([(int)$userId, (int)$userId]);
		return $stmt->fetchAll();
	}

	/**
	 * 获取用户已完成（双方确认收款）的接单列表——个人中心"已完成的交易"
	 */
	public function getUserCompletedClaims($userId, $limit = 20) {
		$stmt = $this->pdo->prepare("SELECT c.*,
				o.order_no, o.city, o.type, o.amount, o.price, o.total_amount,
				ub.username AS buyer_side_name, us.username AS seller_side_name
			FROM bct_order_claims c
			JOIN bct_orders o ON o.id = c.order_id
			LEFT JOIN users ub ON ub.id = c.buyer_side_user_id
			LEFT JOIN users us ON us.id = c.seller_side_user_id
			WHERE c.status = 'completed'
				AND (c.buyer_side_user_id = ? OR c.seller_side_user_id = ?)
			ORDER BY c.finished_at DESC
			LIMIT " . (int)$limit);
		$stmt->execute([(int)$userId, (int)$userId]);
		return $stmt->fetchAll();
	}

	/**
	 * 用户接单统计（为后续信用体系预留）
	 */
	public function countUserClaimStats($userId) {
		$stats = ['completed' => 0, 'abandoned' => 0, 'active' => 0];

		$stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS c FROM bct_order_claims
			WHERE buyer_side_user_id = ? OR seller_side_user_id = ? GROUP BY status");
		$stmt->execute([(int)$userId, (int)$userId]);
		foreach ($stmt->fetchAll() as $row) {
			if ($row['status'] === 'completed') $stats['completed'] = (int)$row['c'];
			if ($row['status'] === 'abandoned') $stats['abandoned'] = (int)$row['c'];
			if (in_array($row['status'], ['matched', 'buyer_confirmed'], true)) $stats['active'] += (int)$row['c'];
		}
		return $stats;
	}

	/**
	 * 接单：把待成交的直接交易单锁定为"交易中"（意向锁）
	 *
	 * 角色推导：挂售单(sell)接单人为买方一侧；求购单(buy)接单人为卖方一侧。
	 * 并发防护：事务内 FOR UPDATE 锁订单行后再校验活跃 claim，杜绝重复接单。
	 *
	 * @return array ['success'=>bool, 'message'=>string, 'claim_id'=>int, 'poster_id'=>int, 'taker_id'=>int]
	 */
	public function claimOrder($orderId, $takerId) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? FOR UPDATE");
			$stmt->execute([(int)$orderId]);
			$order = $stmt->fetch();
			if (!$order) throw new Exception('订单不存在');
			if ($order['trade_type'] !== 'direct') throw new Exception('该订单类型不适用接单流程');
			if ($order['status'] !== 'pending') throw new Exception('该订单当前不可接单');
			if ((int)$order['user_id'] === (int)$takerId) throw new Exception('不能接自己的挂单');

			$stmt = $this->pdo->prepare("SELECT id FROM bct_order_claims
				WHERE order_id = ? AND status IN ('matched','buyer_confirmed') FOR UPDATE");
			$stmt->execute([(int)$orderId]);
			if ($stmt->fetch()) throw new Exception('该订单交易中，暂不可接单');

			if ($order['type'] === 'sell') {
				$buyerSide = (int)$takerId;
				$sellerSide = (int)$order['user_id'];
			} else {
				$buyerSide = (int)$order['user_id'];
				$sellerSide = (int)$takerId;
			}

			$stmt = $this->pdo->prepare("INSERT INTO bct_order_claims
				(order_id, buyer_side_user_id, seller_side_user_id) VALUES (?, ?, ?)");
			$stmt->execute([(int)$orderId, $buyerSide, $sellerSide]);
			$claimId = (int)$this->pdo->lastInsertId();

			$stmt = $this->pdo->prepare("UPDATE bct_orders SET status = 'processing', counterparty_id = ? WHERE id = ?");
			$stmt->execute([(int)$takerId, (int)$orderId]);

			$this->pdo->commit();

			// 通知挂单人
			$posterId = (int)$order['user_id'];
			$takerName = $this->fetchUsername((int)$takerId);
			$sideText = $order['type'] === 'sell' ? '出售' : '求购';
			$this->notify((int)$takerId, $posterId, sprintf(
				"【BCT交易】用户 %s 接了你的%s单【%s】%d BCT @ ¥%s，请与其线下沟通付款与转账。",
				$takerName, $sideText, $order['city'], (int)$order['amount'], number_format($order['price'], 2)
			));

			return ['success' => true, 'message' => '接单成功', 'claim_id' => $claimId, 'poster_id' => $posterId, 'taker_id' => (int)$takerId];
		} catch (Exception $e) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			return ['success' => false, 'message' => $e->getMessage()];
		}
	}

	/**
	 * 买方一侧确认已付款（线下转账完成后）
	 */
	public function buyerConfirmClaim($orderId, $userId) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM bct_order_claims
				WHERE order_id = ? AND status IN ('matched','buyer_confirmed') FOR UPDATE");
			$stmt->execute([(int)$orderId]);
			$claim = $stmt->fetch();
			if (!$claim) throw new Exception('该订单当前没有进行中的交易');
			if ((int)$claim['buyer_side_user_id'] !== (int)$userId) throw new Exception('只有买方可以确认付款');

			if ($claim['status'] === 'buyer_confirmed') {
				$this->pdo->commit();
				return ['success' => true, 'message' => '已确认付款'];
			}

			$stmt = $this->pdo->prepare("UPDATE bct_order_claims
				SET status = 'buyer_confirmed', buyer_confirmed_at = ? WHERE id = ?");
			$stmt->execute([date('Y-m-d H:i:s'), (int)$claim['id']]);
			$this->pdo->commit();

			// 通知卖方
			$buyerName = $this->fetchUsername((int)$claim['buyer_side_user_id']);
			$this->notify((int)$userId, (int)$claim['seller_side_user_id'], sprintf(
				"【BCT交易】用户 %s 已确认向你付款（线下转账），请核实到账后在「进行中的交易」里确认收款完成交易。",
				$buyerName
			));

			return ['success' => true, 'message' => '已确认付款，等待卖家确认收款'];
		} catch (Exception $e) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			return ['success' => false, 'message' => $e->getMessage()];
		}
	}

	/**
	 * 卖方一侧确认已收款 → 交易完成
	 *
	 * 卖方线下已收款即可确认完成，无需等待买方先点「确认已付款」；
	 * 若买方尚未确认（matched），视为双方已线下完成，补记 buyer_confirmed_at。
	 * 完成动作：订单置 completed、claim 置 completed，并写入 bct_transactions 留证
	 * （手续费 0，不划转平台余额——交易全程线下）。
	 */
	public function sellerConfirmClaim($orderId, $userId) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM bct_order_claims
				WHERE order_id = ? AND status IN ('matched','buyer_confirmed') FOR UPDATE");
			$stmt->execute([(int)$orderId]);
			$claim = $stmt->fetch();
			if (!$claim) throw new Exception('该订单当前没有进行中的交易');
			if ((int)$claim['seller_side_user_id'] !== (int)$userId) throw new Exception('只有卖方可以确认收款');
			// 允许卖方在买方确认付款前直接确认收款（线下已收款即可完成交易）；
			// matched 状态下视为买方已同步付款，补记 buyer_confirmed_at
			if (!in_array($claim['status'], ['matched', 'buyer_confirmed'], true)) {
				throw new Exception('该交易当前不可确认收款');
			}
			if ($claim['status'] === 'matched' && empty($claim['buyer_confirmed_at'])) {
				$stmt = $this->pdo->prepare("UPDATE bct_order_claims SET buyer_confirmed_at = ? WHERE id = ?");
				$stmt->execute([date('Y-m-d H:i:s'), (int)$claim['id']]);
			}

			$stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? FOR UPDATE");
			$stmt->execute([(int)$orderId]);
			$order = $stmt->fetch();
			if (!$order) throw new Exception('订单不存在');

			$now = date('Y-m-d H:i:s');
			$stmt = $this->pdo->prepare("UPDATE bct_order_claims
				SET status = 'completed', seller_confirmed_at = ?, finished_at = ? WHERE id = ?");
			$stmt->execute([$now, $now, (int)$claim['id']]);

			$stmt = $this->pdo->prepare("UPDATE bct_orders SET status = 'completed' WHERE id = ?");
			$stmt->execute([(int)$orderId]);

			// 交易留证：进入最新成交/24h统计；不调用 UserBCTAccount::transfer
			require_once __DIR__ . '/BCTTransaction.php';
			$totalAmount = round((int)$order['amount'] * (float)$order['price'], 2);
			$tx = new BCTTransaction($this->pdo);
			$tx->create(
				(int)$orderId,
				(int)$claim['seller_side_user_id'],
				(int)$claim['buyer_side_user_id'],
				$order['city'],
				(int)$order['amount'],
				(float)$order['price'],
				0,
				null,
				$totalAmount,
				'trade'
			);

			$this->pdo->commit();

			// 成交价即最新市场价：更新城市现价（同时落一条价格历史，供涨跌统计）
			$this->syncCityPriceAfterTrade($order['city'], (float)$order['price']);

			// 双向通知：操作者（卖方）收到"你已确认收款"，买方收到"对方已确认收款"
			$this->notify((int)$userId, (int)$claim['buyer_side_user_id'],
				"【BCT交易】你已确认收款，与对方在【{$order['city']}】的交易已完成（".(int)$order['amount']." BCT @ ¥".number_format($order['price'], 2)."），已计入成交记录。");
			$this->notify((int)$claim['buyer_side_user_id'], (int)$userId,
				"【BCT交易】对方已确认收款，你们在【{$order['city']}】的交易已完成（".(int)$order['amount']." BCT @ ¥".number_format($order['price'], 2)."），已计入成交记录。");

			return ['success' => true, 'message' => '交易已完成'];
		} catch (Exception $e) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			return ['success' => false, 'message' => $e->getMessage()];
		}
	}

	/**
	 * 放弃交易：买卖任一方在完成前可发起，订单回到待成交并重新开放接单
	 */
	public function abandonClaim($orderId, $userId, $reason = null) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM bct_order_claims
				WHERE order_id = ? AND status IN ('matched','buyer_confirmed') FOR UPDATE");
			$stmt->execute([(int)$orderId]);
			$claim = $stmt->fetch();
			if (!$claim) throw new Exception('该订单当前没有进行中的交易');
			if ((int)$claim['buyer_side_user_id'] !== (int)$userId && (int)$claim['seller_side_user_id'] !== (int)$userId) {
				throw new Exception('只有交易双方可以放弃交易');
			}

			$this->endClaimInternal($claim, 'abandoned', (int)$userId, $reason);
			$this->pdo->commit();
		} catch (Exception $e) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			return ['success' => false, 'message' => $e->getMessage()];
		}

		// 通知对方
		$counterId = ((int)$claim['buyer_side_user_id'] === (int)$userId)
			? (int)$claim['seller_side_user_id'] : (int)$claim['buyer_side_user_id'];
		$myName = $this->fetchUsername((int)$userId);
		$reasonText = (is_string($reason) && $reason !== '') ? '，原因：' . mb_substr($reason, 0, 50) : '';
		$this->notify((int)$userId, $counterId, "【BCT交易】用户 {$myName} 放弃了本次交易{$reasonText}，挂单已重新开放。");

		return ['success' => true, 'message' => '交易已放弃，挂单重新开放'];
	}

	/**
	 * 释放接单超时（买方 24 小时未确认付款）的 claim：订单回到待成交
	 *
	 * 买方已确认付款（buyer_confirmed）的 claim 绝不自动释放——
	 * 款项可能已线下支付，只提醒卖方，纠纷走后续申诉通道。
	 *
	 * @param int|null $userId 惰性推进时限定相关用户；null 表示全站
	 * @param int $limit 单次处理上限
	 * @return int 释放数量
	 */
	public function releaseStaleClaims($userId = null, $limit = 50) {
		$limit = max(1, (int)$limit);
		$cutoff = date('Y-m-d H:i:s', time() - self::CLAIM_STALE_HOURS * 3600);

		$sql = "SELECT c.* FROM bct_order_claims c
			WHERE c.status = 'matched' AND c.created_at < ?";
		$params = [$cutoff];
		if ($userId !== null) {
			$sql .= " AND (c.buyer_side_user_id = ? OR c.seller_side_user_id = ?)";
			$params[] = (int)$userId;
			$params[] = (int)$userId;
		}
		$sql .= " ORDER BY c.created_at ASC LIMIT " . $limit;

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		$claims = $stmt->fetchAll();

		$released = 0;
		foreach ($claims as $claim) {
			try {
				$this->pdo->beginTransaction();

				// 逐行锁内复核，避免与确认/放弃动作竞争
				$stmt = $this->pdo->prepare("SELECT * FROM bct_order_claims WHERE id = ? FOR UPDATE");
				$stmt->execute([(int)$claim['id']]);
				$locked = $stmt->fetch();
				if (!$locked || $locked['status'] !== 'matched') {
					$this->pdo->commit();
					continue;
				}

				$this->endClaimInternal($locked, 'released', null, '接单后 ' . self::CLAIM_STALE_HOURS . ' 小时未确认付款，系统自动释放');
				$this->pdo->commit();
			} catch (Exception $e) {
				if ($this->pdo->inTransaction()) $this->pdo->rollBack();
				continue;
			}
			$released++;

			// 双方通知：买方超时未确认付款——买方收"你未确认付款"，卖方收"对方未确认付款、挂单重新开放"
			$buyerId = (int)$claim['buyer_side_user_id'];
			$sellerId = (int)$claim['seller_side_user_id'];
			$this->notify($buyerId, $sellerId, "【BCT交易】你接单后 " . self::CLAIM_STALE_HOURS . " 小时内未确认付款，交易已自动释放，挂单重新开放。");
			$this->notify($sellerId, $buyerId, "【BCT交易】对方接单后 " . self::CLAIM_STALE_HOURS . " 小时内未确认付款，交易已自动释放，你的挂单重新开放。");
		}
		return $released;
	}

	/**
	 * claim 终态内部方法（事务内调用）：置终态 + 订单回 pending + 清 counterparty
	 *
	 * @param array $claim 已锁定的 claim 行
	 * @param string $finalStatus abandoned / released
	 * @param int|null $endedBy 发起人用户ID（系统释放为 null）
	 */
	private function endClaimInternal($claim, $finalStatus, $endedBy, $reason) {
		$now = date('Y-m-d H:i:s');
		$stmt = $this->pdo->prepare("UPDATE bct_order_claims
			SET status = ?, finished_at = ?, ended_by = ?, reason = ? WHERE id = ?");
		$stmt->execute([$finalStatus, $now, $endedBy, $reason, (int)$claim['id']]);

		// 订单回到待成交并重新开放接单
		$stmt = $this->pdo->prepare("UPDATE bct_orders SET status = 'pending', counterparty_id = NULL WHERE id = ?");
		$stmt->execute([(int)$claim['order_id']]);
	}

	/**
	 * 接单前预览：订单 + 挂单人用户名 + 当前活跃 claim 状态
	 */
	public function getClaimPreview($orderId) {
		$stmt = $this->pdo->prepare("SELECT o.*, u.username
			FROM bct_orders o
			LEFT JOIN users u ON u.id = o.user_id
			WHERE o.id = ?");
		$stmt->execute([(int)$orderId]);
		$order = $stmt->fetch();
		if (!$order) return null;
		$order['active_claim'] = $this->getActiveClaim((int)$orderId);
		return $order;
	}

	/** 查询用户名（通知文案用），失败返回 "用户#ID" */
	private function fetchUsername($userId) {
		try {
			$stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
			$stmt->execute([(int)$userId]);
			$name = $stmt->fetchColumn();
			return $name ?: ('用户#' . (int)$userId);
		} catch (Exception $e) {
			return '用户#' . (int)$userId;
		}
	}

	/** 发送站内信通知（失败仅记日志，不影响交易状态流转） */
	private function notify($fromUserId, $toUserId, $message) {
		if (!$fromUserId || !$toUserId || $fromUserId === $toUserId) return;
		try {
			require_once __DIR__ . '/Message.php';
			$msg = new Message($this->pdo);
			$msg->send($fromUserId, $toUserId, $message);
		} catch (Exception $e) {
			error_log('BCT claim notify error: ' . $e->getMessage());
		}
	}
}
?>