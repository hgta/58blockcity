<?php
class BCTTransaction {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 生成交易流水号
     * 格式: TX + YmdHis + 4位随机数
     */
    private function generateTxNo() {
        return 'TX' . date('YmdHis') . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * 创建交易流水
     * 
     * @param int $orderId     关联订单ID
     * @param int $fromUser    卖方用户ID
     * @param int $toUser      买方用户ID
     * @param string $city     城市名
     * @param int $amount      交易数量(BCT)
     * @param float $price     交易单价
     * @param float $fee       手续费
     * @param string|null $feeType 手续费类型
     * @param float $netAmount 净额
     * @param string $txType   交易类型: trade/transfer
     * @return int 交易ID
     */
    public function create($orderId, $fromUser, $toUser, $city, $amount, $price, $fee, $feeType, $netAmount, $txType = 'trade') {
        $txNo = $this->generateTxNo();
        
        $stmt = $this->pdo->prepare("INSERT INTO bct_transactions 
            (tx_no, order_id, from_user, to_user, city, amount, price, fee, fee_type, net_amount, tx_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->execute([
            $txNo,
            $orderId,
            $fromUser,
            $toUser,
            $city,
            $amount,
            $price,
            $fee,
            $feeType,
            $netAmount,
            $txType
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 按订单ID查询交易记录
     * 
     * @param int $orderId
     * @return array
     */
    public function getByOrderId($orderId) {
        $stmt = $this->pdo->prepare("SELECT * FROM bct_transactions WHERE order_id = ? ORDER BY created_at DESC");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * 按用户ID查询交易记录
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUserId($userId, $limit = 50) {
        $stmt = $this->pdo->prepare("SELECT * FROM bct_transactions 
            WHERE from_user = ? OR to_user = ? 
            ORDER BY created_at DESC LIMIT " . (int)$limit);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }
}
