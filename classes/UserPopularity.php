<?php
class UserPopularity {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取用户在特定城市的人气值
     * @param int $userId 用户ID
     * @param string $city 城市名称
     * @return int 人气值
     */
    public function getUserPopularity($userId, $city) {
        $stmt = $this->pdo->prepare("SELECT popularity FROM user_city_popularity 
                                    WHERE user_id = ? AND city = ?");
        $stmt->execute([$userId, $city]);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['popularity'] : 0;
    }
    
    /**
     * 获取用户在所有城市的总人气值
     * @param int $userId 用户ID
     * @return int 总人气值
     */
    public function getUserTotalPopularity($userId) {
        $stmt = $this->pdo->prepare("SELECT SUM(popularity) as total FROM user_city_popularity 
                                    WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * 更新用户在特定城市的人气值
     * @param int $userId 用户ID
     * @param string $city 城市名称
     * @param int $amount 变化量（正数为增加，负数为减少）
     * @return bool 操作是否成功
     */
    public function updateUserPopularity($userId, $city, $amount) {
        $this->pdo->beginTransaction();
        
        try {
            // 检查记录是否存在
            $stmt = $this->pdo->prepare("SELECT popularity FROM user_city_popularity 
                                        WHERE user_id = ? AND city = ? FOR UPDATE");
            $stmt->execute([$userId, $city]);
            $result = $stmt->fetch();
            
            if ($result) {
                // 更新现有记录
                $newPopularity = (int)$result['popularity'] + $amount;
                if ($newPopularity < 0) {
                    $newPopularity = 0;
                }
                
                $stmt = $this->pdo->prepare("UPDATE user_city_popularity 
                                            SET popularity = ?, updated_at = NOW() 
                                            WHERE user_id = ? AND city = ?");
                $stmt->execute([$newPopularity, $userId, $city]);
            } else {
                // 插入新记录（确保amount不为负）
                $newPopularity = max(0, $amount);
                $stmt = $this->pdo->prepare("INSERT INTO user_city_popularity 
                                            (user_id, city, popularity) 
                                            VALUES (?, ?, ?)");
                $stmt->execute([$userId, $city, $newPopularity]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("更新用户人气值失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 转移人气值（用户间交易使用）
     * @param int $fromUserId 转出用户ID
     * @param int $toUserId 转入用户ID
     * @param string $city 城市名称
     * @param int $amount 转移数量
     * @return bool 操作是否成功
     */
    public function transferPopularity($fromUserId, $toUserId, $city, $amount) {
        if ($amount <= 0) {
            return false;
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // 检查转出用户是否有足够人气值
            $stmt = $this->pdo->prepare("SELECT popularity FROM user_city_popularity 
                                        WHERE user_id = ? AND city = ? FOR UPDATE");
            $stmt->execute([$fromUserId, $city]);
            $fromResult = $stmt->fetch();
            
            if (!$fromResult || (int)$fromResult['popularity'] < $amount) {
                $this->pdo->rollBack();
                return false;
            }
            
            // 减少转出用户的人气值
            $stmt = $this->pdo->prepare("UPDATE user_city_popularity 
                                        SET popularity = popularity - ?, updated_at = NOW() 
                                        WHERE user_id = ? AND city = ?");
            $stmt->execute([$amount, $fromUserId, $city]);
            
            // 增加转入用户的人气值
            $stmt = $this->pdo->prepare("INSERT INTO user_city_popularity 
                                        (user_id, city, popularity) 
                                        VALUES (?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                        popularity = popularity + VALUES(popularity), 
                                        updated_at = NOW()");
            $stmt->execute([$toUserId, $city, $amount]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("转移人气值失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取城市人气排行榜
     * @param string $city 城市名称
     * @param int $limit 返回数量
     * @return array 排行榜数据
     */
    public function getCityPopularityRanking($city, $limit = 10) {
        $stmt = $this->pdo->prepare("SELECT u.id, u.username, u.avatar, up.popularity 
                                    FROM user_city_popularity up
                                    JOIN users u ON up.user_id = u.id
                                    WHERE up.city = ?
                                    ORDER BY up.popularity DESC
                                    LIMIT ?");
        $stmt->execute([$city, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取用户人气值最高的城市
     * @param int $userId 用户ID
     * @return array|null 城市和人气值信息
     */
    public function getUserTopCity($userId) {
        $stmt = $this->pdo->prepare("SELECT city, popularity 
                                    FROM user_city_popularity 
                                    WHERE user_id = ? 
                                    ORDER BY popularity DESC 
                                    LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}
?>