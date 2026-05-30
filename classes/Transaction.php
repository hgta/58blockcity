<?php
class Transaction {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 创建新的交易记录
     * @param int $nftId NFT ID
     * @param int $sellerId 卖家ID
     * @param float $price 价格
     * @param string $currency 货币类型 (popularity/cny)
     * @param string $transactionType 交易类型 (platform/intermediary/direct)
     * @return int|false 成功返回交易ID，失败返回false
     */
    public function createTransaction($nftId, $sellerId, $price, $currency, $transactionType) {
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO nft_transactions 
                                        (nft_id, seller_id, price, currency, transaction_type, status) 
                                        VALUES (?, ?, ?, ?, ?, 'listed')");
            $stmt->execute([$nftId, $sellerId, $price, $currency, $transactionType]);
            $transactionId = $this->pdo->lastInsertId();
            
            // 更新NFT的最后交易ID
            $stmt = $this->pdo->prepare("UPDATE nft_avatars SET last_transaction_id = ? WHERE id = ?");
            $stmt->execute([$transactionId, $nftId]);
            
            $this->pdo->commit();
            return $transactionId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("创建交易失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 完成交易
     * @param int $transactionId 交易ID
     * @param int $buyerId 买家ID
     * @return bool 是否成功
     */
    public function completeTransaction($transactionId, $buyerId) {
        $this->pdo->beginTransaction();
        
        try {
            // 获取交易详情
            $stmt = $this->pdo->prepare("SELECT nft_id, seller_id, price, currency 
                                        FROM nft_transactions 
                                        WHERE id = ? AND status = 'pending'");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                throw new Exception("交易不存在或状态不正确");
            }
            
            // 更新交易状态
            $stmt = $this->pdo->prepare("UPDATE nft_transactions 
                                        SET buyer_id = ?, status = 'completed', completed_at = NOW() 
                                        WHERE id = ?");
            $stmt->execute([$buyerId, $transactionId]);
            
            // 更新NFT所有者
            $stmt = $this->pdo->prepare("UPDATE nft_avatars SET owner_id = ? WHERE id = ?");
            $stmt->execute([$buyerId, $transaction['nft_id']]);
            
            // 如果是人气值交易，转移人气值
            if ($transaction['currency'] === 'popularity') {
                $stmt = $this->pdo->prepare("SELECT city FROM nft_avatars WHERE id = ?");
                $stmt->execute([$transaction['nft_id']]);
                $nft = $stmt->fetch();
                
                if (!$nft) {
                    throw new Exception("NFT信息获取失败");
                }
                
                $popularity = new UserPopularity($this->pdo);
                if (!$popularity->transferPopularity($buyerId, $transaction['seller_id'], $nft['city'], $transaction['price'])) {
                    throw new Exception("人气值转移失败");
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("完成交易失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 取消交易
     * @param int $transactionId 交易ID
     * @return bool 是否成功
     */
    public function cancelTransaction($transactionId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE nft_transactions 
                                        SET status = 'canceled' 
                                        WHERE id = ? AND status IN ('listed', 'pending')");
            return $stmt->execute([$transactionId]);
        } catch (PDOException $e) {
            error_log("取消交易失败: " . $e->getMessage());
            return false;
        }
    }
    
	/**
     * 获取用户的出售记录
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array 出售记录
     */
    public function getUserSales($userId, $limit = 10) {
    try {
        $sql = "SELECT t.*, n.code as nft_code, u.username as buyer_name 
               FROM nft_transactions t
               JOIN nft_avatars n ON t.nft_id = n.id
               LEFT JOIN users u ON t.buyer_id = u.id
               WHERE t.seller_id = ? AND t.status = 'completed'
               ORDER BY t.completed_at DESC";
        
        // 如果有limit参数则添加LIMIT子句
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit; // 强制转换为整数
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("获取用户销售记录失败: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * 获取用户的购买记录
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array 购买记录
     */
    public function getUserPurchases($userId, $limit = 10) {
    try {
        $sql = "SELECT t.*, n.code as nft_code, u.username as seller_name 
               FROM nft_transactions t
               JOIN nft_avatars n ON t.nft_id = n.id
               JOIN users u ON t.seller_id = u.id
               WHERE t.buyer_id = ? AND t.status = 'completed'
               ORDER BY t.completed_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("获取用户购买记录失败: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * 获取需要用户处理的待处理交易
     * @param int $userId 用户ID
     * @return array 待处理交易列表
     */
    public function getPendingTransactions($userId) {
        $stmt = $this->pdo->prepare("SELECT t.*, n.code as nft_code, 
                                    CASE 
                                        WHEN t.seller_id = ? THEN u_buyer.username
                                        ELSE u_seller.username
                                    END as counterparty_name,
                                    CASE 
                                        WHEN t.seller_id = ? THEN 'seller'
                                        ELSE 'buyer'
                                    END as user_role
                                    FROM nft_transactions t
                                    JOIN nft_avatars n ON t.nft_id = n.id
                                    LEFT JOIN users u_buyer ON t.buyer_id = u_buyer.id
                                    LEFT JOIN users u_seller ON t.seller_id = u_seller.id
                                    WHERE (t.seller_id = ? OR t.buyer_id = ?) 
                                    AND t.status = 'pending'");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取NFT的交易历史
     * @param int $nftId NFT ID
     * @param int $limit 限制数量
     * @return array 交易历史
     */
    public function getNftTransactionHistory($nftId, $limit = 5) {
    try {
        $sql = "SELECT t.*, u_seller.username as seller_name, u_buyer.username as buyer_name
               FROM nft_transactions t
               LEFT JOIN users u_seller ON t.seller_id = u_seller.id
               LEFT JOIN users u_buyer ON t.buyer_id = u_buyer.id
               WHERE t.nft_id = ? AND t.status = 'completed'
               ORDER BY t.completed_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$nftId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("获取NFT交易历史失败: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * 获取用户当前挂售的NFT
     * @param int $userId 用户ID
     * @param string $status 交易状态 (listed/pending/completed/canceled)
     * @return array 挂售的NFT列表
     */
    public function getUserListings($userId, $status = 'listed') {
        $stmt = $this->pdo->prepare("SELECT t.*, n.code as nft_code, n.city, n.image_url
                                    FROM nft_transactions t
                                    JOIN nft_avatars n ON t.nft_id = n.id
                                    WHERE t.seller_id = ? AND t.status = ?
                                    ORDER BY t.created_at DESC");
        $stmt->execute([$userId, $status]);
        return $stmt->fetchAll();
    }
	
	
	public function getTransactionsByNft(int $nftId, int $limit = 10): array {
		try {
			$stmt = $this->pdo->prepare("
				SELECT 
					t.*,
					u1.username AS seller_name,
					u2.username AS buyer_name
				FROM nft_transactions t
				LEFT JOIN users u1 ON t.seller_id = u1.id
				LEFT JOIN users u2 ON t.buyer_id = u2.id
				WHERE t.nft_id = :nft_id
				ORDER BY t.created_at DESC
				LIMIT :limit
			");
			
			// 使用命名参数并明确绑定类型
			$stmt->bindValue(':nft_id', $nftId, PDO::PARAM_INT);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();
			
			return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		} catch (PDOException $e) {
			error_log("Transaction query error: " . $e->getMessage());
			return [];
		}
	}
	
	public function listForSale(
		int $nftId, 
		int $userId, 
		float $price, 
		string $currency, 
		string $transactionType
	): bool {
		$this->pdo->beginTransaction();
		try {
			// 1. 创建交易记录
			$stmt = $this->pdo->prepare("
				INSERT INTO nft_transactions (
					nft_id, seller_id, price, currency, 
					transaction_type, status, created_at
				) VALUES (
					:nft_id, :seller_id, :price, :currency, 
					:transaction_type, 'listed', NOW()
				)
			");
			
			$stmt->execute([
				':nft_id' => $nftId,
				':seller_id' => $userId,
				':price' => $price,
				':currency' => $currency,
				':transaction_type' => $transactionType
			]);
			
			$transactionId = $this->pdo->lastInsertId();
			
			// 2. 更新NFT的最后交易ID
			$stmt = $this->pdo->prepare("
				UPDATE nft_avatars 
				SET last_transaction_id = :transaction_id 
				WHERE id = :nft_id
			");
			$stmt->execute([
				':transaction_id' => $transactionId,
				':nft_id' => $nftId
			]);
			
			$this->pdo->commit();
			return true;
		} catch (PDOException $e) {
			$this->pdo->rollBack();
			error_log("List for sale error: " . $e->getMessage());
			return false;
		}
	}
	
	public function getUserTransactions($userId, $limit = null) {
        $sql = "SELECT t.*, 
                c.name as city_name, 
                b.zone, 
                b.block_number,
                seller.username as seller_name,
                buyer.username as buyer_name
                FROM transactions t
                JOIN blocks b ON t.block_id = b.id
                JOIN cities c ON b.city_id = c.id
                LEFT JOIN users seller ON t.seller_id = seller.id
                JOIN users buyer ON t.buyer_id = buyer.id
                WHERE t.seller_id = ? OR t.buyer_id = ?
                ORDER BY t.created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }
    
    public function createBlockTransaction($blockId, $sellerId, $buyerId, $price, $type) {
        $stmt = $this->pdo->prepare("INSERT INTO transactions 
                                    (block_id, seller_id, buyer_id, price, transaction_type, status) 
                                    VALUES (?, ?, ?, ?, ?, 'pending')");
        return $stmt->execute([$blockId, $sellerId, $buyerId, $price, $type]);
    }
    
    /* public function completeTransaction($transactionId) {
        $stmt = $this->pdo->prepare("UPDATE transactions SET status = 'completed' 
                                    WHERE id = ? AND status = 'pending'");
        return $stmt->execute([$transactionId]);
    }
    
    public function cancelTransaction($transactionId) {
        $stmt = $this->pdo->prepare("UPDATE transactions SET status = 'cancelled' 
                                    WHERE id = ? AND status = 'pending'");
        return $stmt->execute([$transactionId]);
    } */
	
	public function createSale($nftId, $sellerId, $price, $currency, $transactionType, $cityId) {
		$this->pdo->beginTransaction();
		
		try {
			// 1. 创建交易记录
			$sql = "INSERT INTO nft_transactions 
					(nft_id, seller_id, price, currency, transaction_type, status, city_id)
					VALUES (?, ?, ?, ?, ?, 'listed', ?)";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$nftId, $sellerId, $price, $currency, $transactionType, $cityId]);
			$transactionId = $this->pdo->lastInsertId();
			
			// 2. 更新NFT状态
			$sql = "UPDATE nft_city_user 
					SET is_listed = 1, transaction_id = ?
					WHERE nft_id = ? AND city_id = ? AND user_id = ?";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$transactionId, $nftId, $cityId, $sellerId]);
			
			$this->pdo->commit();
			return true;
		} catch (Exception $e) {
			$this->pdo->rollBack();
			error_log("Sale creation failed: " . $e->getMessage());
			echo $e->getMessage();die();
			return false;
		}
	}
	
	public function getTotalTransactionCount() {
		$stmt = $this->pdo->query("SELECT COUNT(*) FROM nft_transactions");
		return (int)$stmt->fetchColumn();
	}

	/* public function getRecentTransactions($limit = 5) {
		$sql = "SELECT t.*, n.code as nft_code
				FROM nft_transactions t
				JOIN nft_avatars n ON t.nft_id = n.id
				ORDER BY t.created_at DESC
				LIMIT ?";
		$stmt = $this->pdo->prepare($sql);
		//$stmt->execute([$limit]);
		$stmt->bindValue(1, $limit, PDO::PARAM_INT);
		$stmt->execute();
		
		return $stmt->fetchAll();
	} */
	
	// 在 Transaction 类中添加以下方法

	/**
	 * 获取今日交易数量
	 */
	public function getTodayTransactionCount($date = null) {
		if ($date === null) {
			$date = date('Y-m-d');
		}
		
		try {
			$sql = "SELECT COUNT(*) FROM nft_transactions 
					WHERE DATE(created_at) = ? 
					AND status = 'completed'";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$date]);
			return (int)$stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("获取今日交易数量失败: " . $e->getMessage());
			return 0;
		}
	}

	/**
	 * 获取今日收入
	 */
	public function getTodayRevenue($date = null) {
		if ($date === null) {
			$date = date('Y-m-d');
		}
		
		try {
			$sql = "SELECT COALESCE(SUM(price), 0) FROM nft_transactions 
					WHERE DATE(created_at) = ? 
					AND status = 'completed' 
					AND currency = 'CNY'";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$date]);
			return (float)$stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("获取今日收入失败: " . $e->getMessage());
			return 0;
		}
	}

	/**
	 * 获取总交易数量
	 */
	/* public function getTotalTransactionCount() {
		try {
			$sql = "SELECT COUNT(*) FROM nft_transactions WHERE status = 'completed'";
			$stmt = $this->pdo->query($sql);
			return (int)$stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("获取总交易数量失败: " . $e->getMessage());
			return 0;
		}
	} */

	/**
	 * 获取最近交易记录
	 */
	public function getRecentTransactions($limit = 10) {
		try {
			$sql = "SELECT t.*, n.code as nft_code, n.base_image as nft_image 
					FROM nft_transactions t
					LEFT JOIN nft_avatars n ON t.nft_id = n.id
					WHERE t.status = 'completed'
					ORDER BY t.created_at DESC 
					LIMIT ?";
			$stmt = $this->pdo->prepare($sql);
			$stmt->bindValue(1, $limit, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll();
		} catch (PDOException $e) {
			error_log("获取最近交易失败: " . $e->getMessage());
			return [];
		}
	}
	
	/**
     * 购买NFT
     */
    public function purchaseNft($nftId, $buyerId, $price, $currency) {
        $this->pdo->beginTransaction();
        try {
            // 获取NFT当前信息
            $nftStmt = $this->pdo->prepare("SELECT owner_id, price FROM nfts WHERE id = ?");
            $nftStmt->execute([$nftId]);
            $nft = $nftStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nft) {
                throw new Exception("NFT不存在");
            }
            
            $sellerId = $nft['owner_id'];
            
            // 计算手续费（2.5%）
            $feeRate = 0.025;
            $fee = $price * $feeRate;
            $sellerAmount = $price - $fee;
            
            // 更新买家余额
            if ($currency === 'popularity') {
                $updateBuyerStmt = $this->pdo->prepare(
                    "UPDATE users SET popularity = popularity - ? WHERE id = ? AND popularity >= ?"
                );
                $updateBuyerStmt->execute([$price, $buyerId, $price]);
            } else {
                $updateBuyerStmt = $this->pdo->prepare(
                    "UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?"
                );
                $updateBuyerStmt->execute([$price, $buyerId, $price]);
            }
            
            if ($updateBuyerStmt->rowCount() === 0) {
                throw new Exception("买家余额不足");
            }
            
            // 更新卖家余额
            if ($currency === 'popularity') {
                $updateSellerStmt = $this->pdo->prepare(
                    "UPDATE users SET popularity = popularity + ? WHERE id = ?"
                );
            } else {
                $updateSellerStmt = $this->pdo->prepare(
                    "UPDATE users SET balance = balance + ? WHERE id = ?"
                );
            }
            $updateSellerStmt->execute([$sellerAmount, $sellerId]);
            
            // 更新平台收入（手续费）
            if ($currency === 'popularity') {
                $updatePlatformStmt = $this->pdo->prepare(
                    "UPDATE system_settings SET value = value + ? WHERE name = 'platform_popularity_income'"
                );
            } else {
                $updatePlatformStmt = $this->pdo->prepare(
                    "UPDATE system_settings SET value = value + ? WHERE name = 'platform_balance_income'"
                );
            }
            $updatePlatformStmt->execute([$fee]);
            
            // 转移NFT所有权
            $updateNftStmt = $this->pdo->prepare(
                "UPDATE nfts SET owner_id = ?, is_for_sale = 0, price = 0, currency = NULL, listed_at = NULL WHERE id = ?"
            );
            $updateNftStmt->execute([$buyerId, $nftId]);
            
            // 记录交易
            $transactionStmt = $this->pdo->prepare("
                INSERT INTO transactions (nft_id, seller_id, buyer_id, price, currency, fee, transaction_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'purchase', NOW())
            ");
            $transactionStmt->execute([$nftId, $sellerId, $buyerId, $price, $currency, $fee]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("购买NFT失败: " . $e->getMessage());
            return false;
        }
    }
}
?>