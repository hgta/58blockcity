<?php
class BCTOrder {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // 生成订单号
    private function generateOrderNo() {
        return date('YmdHis').mt_rand(1000, 9999);
    }
    
    // 创建订单
    public function createOrder($userId, $city, $type, $amount, $tradeType, $contactInfo = null, $userPrice = null, $durationDays = 0) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取当前城市价格，用户可覆盖
            $cityBCT = new CityBCT($this->pdo);
            $cityInfo = $cityBCT->getCityBCT($city);
            $price = ($userPrice > 0) ? $userPrice : $cityInfo['current_price'];
            
            // 计算总金额
            $total = $amount * $price;
            
            // 如果是出售订单，验证余额（不扣余额，交易完成时才处理）
            if($type == 'sell') {
                $account = new UserBCTAccount($this->pdo);
                $userAccount = $account->getAccount($userId, $city);
                
                if(!$userAccount || $userAccount['balance'] < $amount) {
                    throw new Exception("可用余额不足");
                }
            }
            
            // 计算过期时间
            $expiresAt = ($durationDays > 0) 
                ? date('Y-m-d H:i:s', strtotime("+{$durationDays} days"))
                : null;
            
            // 创建订单
            $orderNo = $this->generateOrderNo();
            $stmt = $this->pdo->prepare("INSERT INTO bct_orders 
                (order_no, user_id, city, type, amount, price, total_amount, trade_type, contact_info, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
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
                $expiresAt
            ]);
            
            $orderId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $orderId;
        } catch(Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // 取消订单
    public function cancelOrder($orderId, $userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND user_id = ? AND status IN ('pending','processing')");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        
        if (!$order) return false;
        
        // 卖单：余额未扣过，取消时不需退还
        
        $stmt = $this->pdo->prepare("UPDATE bct_orders SET status = 'canceled' WHERE id = ?");
        return $stmt->execute([$orderId]);
    }
    
    // 平台交易自动匹配
    public function autoMatchPlatformOrder($orderId) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取订单信息
            $stmt = $this->pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND status = 'pending'");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if(!$order) {
                throw new Exception("订单不存在或不可匹配");
            }
            
            // 查找匹配订单：同城市、相反方向、价格交叉、平台交易
            $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';
            
            if ($order['type'] == 'buy') {
                // 买单：找卖单价格 <= 买单价格的订单，按价格从低到高，同价格按时间优先
                $stmt = $this->pdo->prepare("SELECT * FROM bct_orders 
                    WHERE city = ? AND type = ? AND status = 'pending' 
                    AND trade_type = 'platform' 
                    AND price <= ?
                    ORDER BY price ASC, created_at ASC 
                    LIMIT 1 FOR UPDATE");
                $stmt->execute([$order['city'], $matchType, $order['price']]);
            } else {
                // 卖单：找买单价格 >= 卖单价格的订单，按价格从高到低，同价格按时间优先
                $stmt = $this->pdo->prepare("SELECT * FROM bct_orders 
                    WHERE city = ? AND type = ? AND status = 'pending' 
                    AND trade_type = 'platform' 
                    AND price >= ?
                    ORDER BY price DESC, created_at ASC 
                    LIMIT 1 FOR UPDATE");
                $stmt->execute([$order['city'], $matchType, $order['price']]);
            }
                
            $matchOrder = $stmt->fetch();
            
            if(!$matchOrder) {
                throw new Exception("暂无匹配订单");
            }
            
            // 确定交易数量
            $tradeAmount = min($order['amount'], $matchOrder['amount']);
            
            // 保存交易前订单数量（用于冻结余额修复）
            $orderBeforeTrade = $order;
            $matchBeforeTrade = $matchOrder;
            
            // 执行交易
            $this->executeTrade($order, $matchOrder, $tradeAmount, 'platform');
            
            $this->pdo->commit();
            return true;
        } catch(Exception $e) {
            $this->pdo->rollBack();
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
            $this->pdo->rollBack();
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
    }
    
    private function updateOrderAfterTrade($orderId, $tradedAmount, $sellerId = null, $city = null) {
        // 获取订单原始数量
        $stmt = $this->pdo->prepare("SELECT amount, type, user_id, city FROM bct_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        $originalAmount = $order['amount'];
        
        if($tradedAmount >= $originalAmount) {
            // 全部完成
            $stmt = $this->pdo->prepare("UPDATE bct_orders SET amount = 0, status = 'completed' WHERE id = ?");
            $stmt->execute([$orderId]);
        } else {
            // 部分完成
            $remainingAmount = $originalAmount - $tradedAmount;
            $stmt = $this->pdo->prepare("UPDATE bct_orders SET amount = ?, status = 'processing' WHERE id = ?");
            $stmt->execute([$remainingAmount, $orderId]);
        }
    }
	
	/**
	 * 获取用户订单（带分页）
	 */
	public function getUserOrders($userId, $type = 'all', $status = 'all', $page = 1, $perPage = 15) {
		$sql = "SELECT * FROM bct_orders WHERE user_id = ?";
		$params = [$userId];
		
		if ($type === 'buy') { $sql .= " AND type = 'buy'"; $params[] = 'buy'; }
		elseif ($type === 'sell') { $sql .= " AND type = 'sell'"; $params[] = 'sell'; }

		if ($status === 'active') {
			$sql .= " AND status IN ('pending','processing')";
		} elseif ($status === 'pending') {
			$sql .= " AND status = 'pending'";
		} elseif ($status === 'completed') {
			$sql .= " AND status = 'completed'";
		}
		
		$sql .= " ORDER BY created_at DESC LIMIT " . (int)$perPage . " OFFSET " . ((int)$page - 1) * (int)$perPage;
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
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
}
?>