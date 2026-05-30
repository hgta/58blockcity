<?php
class Payment {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 验证支付能力
     */
    public function validatePayment($userId, $shopId, $paymentCity, $amount) {
        try {
            // 获取用户在该城市的BCT余额
            $userBalance = $this->getUserBCTBalance($userId, $paymentCity);
            if ($userBalance < $amount) {
                throw new Exception("{$paymentCity} 人气值余额不足");
            }
            
            // 获取店铺支付设置
            $shopPayment = $this->getShopPaymentSettings($shopId, $paymentCity);
            if (!$shopPayment || !$shopPayment['is_active']) {
                throw new Exception("该店铺不支持 {$paymentCity} 人气值支付");
            }
            
            // 验证区块ID
            if (empty($shopPayment['block_id'])) {
                throw new Exception("店铺未设置 {$paymentCity} 的收款区块ID");
            }
            
            return [
                'user_balance' => $userBalance,
                'shop_block_id' => $shopPayment['block_id'],
                'exchange_rate' => $shopPayment['exchange_rate'],
                'actual_amount' => $amount * $shopPayment['exchange_rate']
            ];
            
        } catch (Exception $e) {
            throw new Exception("支付验证失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取用户BCT余额
     */
    private function getUserBCTBalance($userId, $city) {
        // 这里需要连接人气值交易平台的数据库
        // 假设有一个统一的用户余额表
        $stmt = $this->pdo->prepare("
            SELECT balance 
            FROM user_bct_account 
            WHERE user_id = ? AND city = ?
        ");
        $stmt->execute([$userId, $city]);
        $result = $stmt->fetch();
        
        return $result ? $result['balance'] : 0;
    }
    
    /**
     * 获取店铺支付设置
     */
    private function getShopPaymentSettings($shopId, $city) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shop_payment_settings 
            WHERE shop_id = ? AND city = ?
        ");
        $stmt->execute([$shopId, $city]);
        return $stmt->fetch();
    }
    
    /**
     * 处理BCT支付
     */
    public function processBCTPayment($orderId, $fromUserId, $toShopId, $paymentCity, $amount) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取订单信息
            $order = $this->getOrder($orderId);
            if (!$order) {
                throw new Exception("订单不存在");
            }
            
            if ($order['status'] !== 'pending') {
                throw new Exception("订单状态异常");
            }
            
            // 获取店铺收款信息
            $shopPayment = $this->getShopPaymentSettings($toShopId, $paymentCity);
            if (!$shopPayment) {
                throw new Exception("店铺支付设置不存在");
            }
            
            // 执行BCT转账（这里需要调用人气值平台的转账接口）
            $transferResult = $this->transferBCT(
                $fromUserId,
                $shopPayment['block_id'], // 使用店铺的区块ID作为收款方
                $paymentCity,
                $amount
            );
            
            if (!$transferResult) {
                throw new Exception("BCT转账失败");
            }
            
            // 更新订单状态
            $this->updateOrderStatus($orderId, 'paid');
            
            // 记录支付交易
            $this->recordPaymentTransaction($orderId, $fromUserId, $toShopId, $paymentCity, $amount);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("支付处理失败: " . $e->getMessage());
        }
    }
    
    /**
     * BCT转账（模拟实现，实际需要调用区块链接口）
     */
    private function transferBCT($fromUserId, $toBlockId, $city, $amount) {
        // 这里应该调用人气值交易平台的转账接口
        // 由于是模拟实现，我们假设转账总是成功
        
        // 实际实现可能如下：
        // 1. 调用BCT平台的转账API
        // 2. 验证交易哈希
        // 3. 等待交易确认
        
        // 模拟实现
        sleep(1); // 模拟网络延迟
        return true;
    }
    
    /**
     * 记录支付交易
     */
    private function recordPaymentTransaction($orderId, $fromUserId, $toShopId, $city, $amount) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_transactions 
            (order_id, from_user_id, to_shop_id, city, amount, status) 
            VALUES (?, ?, ?, ?, ?, 'completed')
        ");
        return $stmt->execute([$orderId, $fromUserId, $toShopId, $city, $amount]);
    }
    
    /**
     * 获取订单信息
     */
    private function getOrder($orderId) {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * 更新订单状态
     */
    private function updateOrderStatus($orderId, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE orders 
            SET status = ?, paid_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$status, $orderId]);
    }
    
    /**
     * 获取支持的支付城市
     */
    public function getSupportedCities($shopId = null) {
        if ($shopId) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT city 
                FROM shop_payment_settings 
                WHERE shop_id = ? AND is_active = 1
            ");
            $stmt->execute([$shopId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT city 
                FROM cities 
                WHERE status = 'active'
            ");
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * 计算商品在不同城市的实际价格
     */
    public function calculateProductPrice($productId, $city) {
        // 获取商品基础价格
        $stmt = $this->pdo->prepare("SELECT price_bct, shop_id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return false;
        }
        
        // 获取店铺支付设置
        $shopPayment = $this->getShopPaymentSettings($product['shop_id'], $city);
        if (!$shopPayment || !$shopPayment['is_active']) {
            return false;
        }
        
        // 获取商品在该城市的价格调整
        $stmt = $this->pdo->prepare("
            SELECT price_adjust 
            FROM product_payment_cities 
            WHERE product_id = ? AND city = ? AND is_active = 1
        ");
        $stmt->execute([$productId, $city]);
        $priceAdjust = $stmt->fetchColumn();
        
        // 计算最终价格
        $basePrice = $product['price_bct'];
        $adjustedPrice = $basePrice * (1 + ($priceAdjust / 100));
        $finalPrice = $adjustedPrice * $shopPayment['exchange_rate'];
        
        return [
            'base_price' => $basePrice,
            'price_adjust' => $priceAdjust,
            'exchange_rate' => $shopPayment['exchange_rate'],
            'final_price' => $finalPrice,
            'shop_block_id' => $shopPayment['block_id']
        ];
    }
    
    /**
     * 验证支付回调
     */
    public function verifyPaymentCallback($data) {
        // 这里应该验证支付回调的签名等安全信息
        // 模拟实现
        
        if (!isset($data['order_id']) || !isset($data['tx_hash']) || !isset($data['amount'])) {
            return false;
        }
        
        // 验证交易哈希（实际应该查询区块链）
        if (!$this->verifyTransactionHash($data['tx_hash'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证交易哈希（模拟实现）
     */
    private function verifyTransactionHash($txHash) {
        // 实际应该查询区块链验证交易
        // 这里模拟验证
        return !empty($txHash) && strlen($txHash) === 64;
    }
    
    /**
     * 获取支付历史
     */
    public function getPaymentHistory($userId, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->pdo->prepare("
            SELECT pt.*, o.order_no, s.shop_name
            FROM payment_transactions pt
            LEFT JOIN orders o ON pt.order_id = o.id
            LEFT JOIN shops s ON pt.to_shop_id = s.id
            WHERE pt.from_user_id = ?
            ORDER BY pt.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $perPage, $offset]);
        return $stmt->fetchAll();
    }
}
?>