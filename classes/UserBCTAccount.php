<?php
class UserBCTAccount {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 获取投资组合总览
     */
    public function getPortfolioSummary($userId) {
        $accounts = $this->getUserAccounts($userId);

        $summary = [
            'total_valuation' => 0,
            'total_balance' => 0,
            'total_frozen' => 0,
            'city_count' => count($accounts)
        ];

        foreach ($accounts as $account) {
            $summary['total_valuation'] += $account['valuation'];
            $summary['total_balance'] += $account['balance'];
            $summary['total_frozen'] += $account['frozen'];
        }

        return $summary;
    }

    /**
     * 获取用户所有城市的BCT账户信息
     */
    public function getUserAccounts($userId) {
        $sql = "SELECT uba.*, c.bct_current_price AS current_price 
                FROM user_bct_account uba
                JOIN cities c ON uba.city = c.name COLLATE utf8mb4_general_ci
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
    
    /**
     * BCT 转账：从转出方扣除并累加到接收方
     *
     * 重要：本方法不自行开启/提交事务。若在已有事务中调用（例如撮合成交），
     * 会复用外层事务，避免嵌套 beginTransaction 导致外层事务被提前提交；
     * 若当前无事务，则由本方法自行包裹一个事务保证原子性。
     *
     * 失败时抛出异常（而非静默返回 false），由调用方决定是否回滚。
     *
     * @throws Exception 余额不足或账户异常
     */
    public function transfer($fromUserId, $toUserId, $city, $amount) {
        $amount = (int)$amount;
        if ($amount <= 0) {
            throw new Exception("转账数量必须大于 0");
        }

        // 复用外层事务；仅在无事务时才自行开启
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            // 扣除转出方余额（带 balance >= ? 条件，防止并发超扣）
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

            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            // 只回滚自己开启的事务；外层事务交由外层负责
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>