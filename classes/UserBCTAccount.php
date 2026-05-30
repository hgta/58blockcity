<?php
class UserBCTAccount {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 获取用户所有城市的BCT账户信息
     */
    public function getUserAccounts($userId) {
        $sql = "SELECT uba.*, cb.current_price 
                FROM user_bct_account uba
                JOIN city_bct cb ON uba.city = cb.city
                WHERE uba.user_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $accounts = $stmt->fetchAll();
        
        // 计算每个账户的总估值
        foreach ($accounts as &$account) {
            $account['valuation'] = $account['balance'] * $account['current_price'];
        }
        
        return $accounts;
    }

    // 其他现有方法保持不变...
    public function getAccount($userId, $city) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_bct_account WHERE user_id = ? AND city = ?");
        $stmt->execute([$userId, $city]);
        return $stmt->fetch();
    }
    
    public function createAccount($userId, $city) {
        $stmt = $this->pdo->prepare("INSERT INTO user_bct_account (user_id, city) VALUES (?, ?)");
        return $stmt->execute([$userId, $city]);
    }
    
    public function updateBalance($userId, $city, $amount, $isFrozen = false) {
        $field = $isFrozen ? 'frozen' : 'balance';
        $stmt = $this->pdo->prepare("UPDATE user_bct_account SET $field = $field + ? WHERE user_id = ? AND city = ?");
        return $stmt->execute([$amount, $userId, $city]);
    }
    
    public function transfer($fromUserId, $toUserId, $city, $amount) {
        try {
            $this->pdo->beginTransaction();
            
            // 扣除转出方余额
            $stmt1 = $this->pdo->prepare("UPDATE user_bct_account SET balance = balance - ? WHERE user_id = ? AND city = ? AND balance >= ?");
            $stmt1->execute([$amount, $fromUserId, $city, $amount]);
            
            if($stmt1->rowCount() == 0) {
                throw new Exception("余额不足");
            }
            
            // 检查接收方账户是否存在
            $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM user_bct_account WHERE user_id = ? AND city = ?");
            $stmt2->execute([$toUserId, $city]);
            
            if($stmt2->fetchColumn() == 0) {
                $this->createAccount($toUserId, $city);
            }
            
            $stmt3 = $this->pdo->prepare("UPDATE user_bct_account SET balance = balance + ? WHERE user_id = ? AND city = ?");
            $stmt3->execute([$amount, $toUserId, $city]);
            
            $this->pdo->commit();
            return true;
        } catch(Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
?>