<?php

class Address {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取用户地址列表
     */
    public function getUserAddresses($userId) {
        try {
            $sql = "SELECT * FROM user_addresses 
                    WHERE user_id = ? AND deleted_at IS NULL 
                    ORDER BY is_default DESC, created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取用户地址失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 添加地址
     */
    public function addAddress($userId, $data) {
        try {
            // 如果设置为默认地址，先取消其他默认地址
            if ($data['is_default']) {
                $this->clearDefaultAddress($userId);
            }
            
            $sql = "INSERT INTO user_addresses (user_id, name, phone, province, city, district, detail, is_default, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([
                $userId,
                $data['name'],
                $data['phone'],
                $data['province'],
                $data['city'],
                $data['district'],
                $data['detail'],
                $data['is_default']
            ]);
        } catch (Exception $e) {
            error_log("添加地址失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新地址
     */
    public function updateAddress($addressId, $userId, $data) {
        try {
            // 如果设置为默认地址，先取消其他默认地址
            if ($data['is_default']) {
                $this->clearDefaultAddress($userId);
            }
            
            $sql = "UPDATE user_addresses 
                    SET name = ?, phone = ?, province = ?, city = ?, district = ?, detail = ?, is_default = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['province'],
                $data['city'],
                $data['district'],
                $data['detail'],
                $data['is_default'],
                $addressId,
                $userId
            ]);
        } catch (Exception $e) {
            error_log("更新地址失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除地址
     */
    public function deleteAddress($addressId, $userId) {
        try {
            $sql = "UPDATE user_addresses SET deleted_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$addressId, $userId]);
        } catch (Exception $e) {
            error_log("删除地址失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 设置默认地址
     */
    public function setDefaultAddress($addressId, $userId) {
        try {
            // 开始事务
            $this->pdo->beginTransaction();
            
            // 先取消所有默认地址
            $sql1 = "UPDATE user_addresses SET is_default = 0 
                     WHERE user_id = ? AND is_default = 1";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute([$userId]);
            
            // 设置新的默认地址
            $sql2 = "UPDATE user_addresses SET is_default = 1, updated_at = NOW() 
                     WHERE id = ? AND user_id = ?";
            $stmt2 = $this->pdo->prepare($sql2);
            $result = $stmt2->execute([$addressId, $userId]);
            
            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("设置默认地址失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 清除用户的默认地址
     */
    private function clearDefaultAddress($userId) {
        try {
            $sql = "UPDATE user_addresses SET is_default = 0 
                    WHERE user_id = ? AND is_default = 1";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("清除默认地址失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 根据ID获取地址
     */
    public function getAddressById($addressId, $userId = null) {
        try {
            $sql = "SELECT * FROM user_addresses 
                    WHERE id = ? AND deleted_at IS NULL";
            
            $params = [$addressId];
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取地址详情失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取用户默认地址
     */
    public function getDefaultAddress($userId) {
        try {
            $sql = "SELECT * FROM user_addresses 
                    WHERE user_id = ? AND is_default = 1 AND deleted_at IS NULL 
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取默认地址失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 验证地址是否属于用户
     */
    public function validateAddressOwnership($addressId, $userId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM user_addresses 
                    WHERE id = ? AND user_id = ? AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$addressId, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("验证地址所有权失败: " . $e->getMessage());
            return false;
        }
    }
}
?> 