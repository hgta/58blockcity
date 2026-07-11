<?php
class PurchaseRequest {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 创建求购请求
     */
    public function createPurchaseRequest(
    $userId, 
    $nftId, 
    $city_id, // 新增城市参数
    $price, 
    $currency, 
    $transactionType,
    $contactPhone,
    $contactWechat,
    $contactQQ,
    $contactEmail,
    $blockNumber
) {
    $stmt = $this->pdo->prepare("
        INSERT INTO nft_purchase_requests (
            user_id, nft_id, city_id, price, currency, transaction_type,
            contact_phone, contact_wechat, contact_qq, contact_email, block_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $userId, $nftId, $city_id, $price, $currency, $transactionType,
        $contactPhone, $contactWechat, $contactQQ, $contactEmail, $blockNumber
    ]);
}
    
    /**
     * 更新求购请求
     */
    public function updatePurchaseRequest(
        $requestId,
        $price, 
        $currency, 
        $transactionType,
        $contactPhone,
        $contactWechat,
        $contactQQ,
        $contactEmail,
        $blockNumber
    ) {
        $stmt = $this->pdo->prepare("
            UPDATE nft_purchase_requests SET
                price = ?,
                currency = ?,
                transaction_type = ?,
                contact_phone = ?,
                contact_wechat = ?,
                contact_qq = ?,
                contact_email = ?,
                block_number = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $price, $currency, $transactionType,
            $contactPhone, $contactWechat, $contactQQ, $contactEmail, $blockNumber,
            $requestId
        ]);
    }
    
    /**
     * 删除求购请求
     */
    public function deletePurchaseRequest($requestId) {
        $stmt = $this->pdo->prepare("DELETE FROM nft_purchase_requests WHERE id = ?");
        return $stmt->execute([$requestId]);
    }
    
    /**
     * 获取用户对特定NFT的求购请求
     */
    public function getUserPurchaseRequestForNft($userId, $nftId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM nft_purchase_requests 
            WHERE user_id = ? AND nft_id = ?
        ");
        $stmt->execute([$userId, $nftId]);
        return $stmt->fetch();
    }
    
    /**
     * 获取用户的所有求购请求
     */
    public function getUserPurchaseRequests($userId) {
        $stmt = $this->pdo->prepare("
            SELECT pr.*, n.code, n.base_image
            FROM nft_purchase_requests pr
            JOIN nft_avatars n ON pr.nft_id = n.id
            WHERE pr.user_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取特定NFT的所有求购请求
     */
    public function getPurchaseRequestsForNft($nftId) {
        $stmt = $this->pdo->prepare("
            SELECT pr.*, u.username
            FROM nft_purchase_requests pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.nft_id = ? AND pr.status = 'pending'
            ORDER BY pr.price DESC, pr.created_at ASC
        ");
        $stmt->execute([$nftId]);
        return $stmt->fetchAll();
    }
	

	/**
	 * 获取所有求购记录的总数（可筛选状态）
	 * @param string|null $status 筛选状态 (pending/completed/canceled) 或 null 表示全部
	 * @return int
	 */
	public function getTotalPurchaseRequestsCount($status = null) {
		try {
			$sql = "SELECT COUNT(*) FROM nft_purchase_requests";
			
			// 添加状态筛选条件
			if ($status !== null) {
				$sql .= " WHERE status = ?";
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute([$status]);
			} else {
				$stmt = $this->pdo->query($sql);
			}
			
			return (int)$stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("[getTotalPurchaseRequestsCount] 查询失败: " . $e->getMessage());
			return 0;
		}
	}
	
	/**
 * 获取NFT的求购数量
 */
public function getPurchaseRequestCountByNft($nftId) {
    $sql = "SELECT COUNT(*) as count 
            FROM nft_purchase_requests 
            WHERE nft_id = ? AND status = 'pending'";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$nftId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

    public function getNftPurchaseCounts() {
        $sql = "SELECT nft_id, COUNT(*) as count, MAX(CAST(price AS DECIMAL(10,2))) as max_price
                FROM nft_purchase_requests
                WHERE status = 'pending'
                GROUP BY nft_id";
        $stmt = $this->pdo->query($sql);
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[intval($row['nft_id'])] = [
                'count' => intval($row['count']),
                'max_price' => $row['max_price']
            ];
        }
        return $map;
    }
}