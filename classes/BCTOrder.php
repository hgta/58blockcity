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
    public function createOrder($userId, $city, $type, $amount, $tradeType, $contactInfo = null) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取当前城市价格
            $cityBCT = new CityBCT($this->pdo);
            $cityInfo = $cityBCT->getCityBCT($city);
            $price = $cityInfo['current_price'];
            
            // 计算总金额
            $total = $type == 'buy' ? $amount * $price : $amount;
            
            // 如果是出售订单，检查并冻结余额
            if($type == 'sell') {
                $account = new UserBCTAccount($this->pdo);
                $userAccount = $account->getAccount($userId, $city);
                
                if(!$userAccount || $userAccount['balance'] - $userAccount['frozen'] < $amount) {
                    throw new Exception("可用余额不足");
                }
                
                $account->updateBalance($userId, $city, $amount, true);
            }
            
            // 创建订单
            $orderNo = $this->generateOrderNo();
            $stmt = $this->pdo->prepare("INSERT INTO bct_orders 
                (order_no, user_id, city, type, amount, price, total_amount, trade_type, contact_info) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            $stmt->execute([
                $orderNo, 
                $userId, 
                $city, 
                $type, 
                $amount, 
                $price, 
                $total, 
                $tradeType,
                $contactInfo
            ]);
            
            $orderId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $orderId;
        } catch(Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
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
            
            // 查找匹配订单
            $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';
            $stmt = $this->pdo->prepare("SELECT * FROM bct_orders 
                WHERE city = ? AND type = ? AND status = 'pending' 
                AND trade_type = 'platform' 
                ORDER BY created_at 
                LIMIT 1 FOR UPDATE");
                
            $stmt->execute([$order['city'], $matchType]);
            $matchOrder = $stmt->fetch();
            
            if(!$matchOrder) {
                throw new Exception("暂无匹配订单");
            }
            
            // 确定交易数量
            $tradeAmount = min($order['amount'], $matchOrder['amount']);
            
            // 执行交易
            $this->executeTrade($order, $matchOrder, $tradeAmount, 'platform');
            
            $this->pdo->commit();
            return true;
        } catch(Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    // 执行交易
    private function executeTrade($order1, $order2, $amount, $tradeType) {
        $account = new UserBCTAccount($this->pdo);
        $tx = new BCTTransaction($this->pdo);
        
        // 确定买卖双方
        $buyOrder = $order1['type'] == 'buy' ? $order1 : $order2;
        $sellOrder = $order1['type'] == 'sell' ? $order1 : $order2;
        
        // 计算手续费
        $feeRate = $tradeType == 'platform' ? 0.10 : ($tradeType == 'mediator' ? 0.02 : 0);
        $fee = $amount * $sellOrder['price'] * $feeRate;
        $netAmount = $amount * $sellOrder['price'] - $fee;
        
        // 转账流程
        // 1. 解冻卖方冻结的BCT
        $account->updateBalance($sellOrder['user_id'], $sellOrder['city'], -$amount, true);
        
        // 2. 将BCT从卖方转到买方
        $account->transfer($sellOrder['user_id'], $buyOrder['user_id'], $sellOrder['city'], $amount);
        
        // 3. 将资金从买方转到卖方
        // 实际项目中这里应该对接支付系统，简化版只记录交易
        
        // 创建交易记录
        $tx->createTransaction(
            $buyOrder['id'], 
            $buyOrder['user_id'], 
            $sellOrder['user_id'], 
            $sellOrder['city'], 
            $amount, 
            $sellOrder['price'], 
            $fee, 
            $feeRate > 0 ? ($tradeType == 'platform' ? 'platform_fee' : 'mediator_fee') : null,
            $netAmount,
            'trade'
        );
        
        // 更新订单状态
        $this->updateOrderAfterTrade($buyOrder['id'], $amount);
        $this->updateOrderAfterTrade($sellOrder['id'], $amount);
    }
    
    private function updateOrderAfterTrade($orderId, $tradedAmount) {
        // 检查订单是否全部完成
        $stmt = $this->pdo->prepare("SELECT amount FROM bct_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $originalAmount = $stmt->fetchColumn();
        
        if($tradedAmount >= $originalAmount) {
            // 全部完成
            $stmt = $this->pdo->prepare("UPDATE bct_orders SET amount = 0, status = 'completed' WHERE id = ?");
        } else {
            // 部分完成
            $stmt = $this->pdo->prepare("UPDATE bct_orders SET amount = amount - ?, status = 'processing' WHERE id = ?");
            $stmt->execute([$tradedAmount, $orderId]);
        }
    }
	
	/**
	 * 获取用户订单
	 * 
	 * @param int $userId 用户ID
	 * @param string $type 订单类型: all/buy/sell
	 * @return array
	 */
	public function getUserOrders($userId, $type = 'all') {
		$sql = "SELECT * FROM bct_orders WHERE user_id = ?";
		$params = [$userId];
		
		// 根据类型添加条件
		if ($type === 'buy') {
			$sql .= " AND type = 'buy'";
		} elseif ($type === 'sell') {
			$sql .= " AND type = 'sell'";
		}
		
		$sql .= " ORDER BY created_at DESC LIMIT 50";
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
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