<?php
class NFT {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
	/*
    public function getAvatarsByCity($city, $limit = 12, $status = 'listed') {
        $sql = "SELECT n.*, t.price, t.currency, u.username as owner_name 
                FROM nft_avatars n
                LEFT JOIN nft_transactions t ON n.last_transaction_id = t.id
                LEFT JOIN users u ON n.owner_id = u.id
                WHERE n.city = ? AND (t.status = ? OR ? IS NULL)
                ORDER BY t.created_at DESC
                LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$city, $status, $status, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getUserCollection($userId) {
        $sql = "SELECT n.*, t.price, t.currency, c.is_profile_avatar
                FROM user_nft_collections c
                JOIN nft_avatars n ON c.nft_id = n.id
                LEFT JOIN nft_transactions t ON n.last_transaction_id = t.id
                WHERE c.user_id = ?
                ORDER BY c.acquired_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }*/
    
    public function listForSale($nftId, $userId, $price, $currency, $transactionType) {
        $this->pdo->beginTransaction();
        
        try {
            // 创建交易记录
            $stmt = $this->pdo->prepare("INSERT INTO nft_transactions 
                (nft_id, seller_id, price, currency, transaction_type, status) 
                VALUES (?, ?, ?, ?, ?, 'listed')");
            $stmt->execute([$nftId, $userId, $price, $currency, $transactionType]);
            $transactionId = $this->pdo->lastInsertId();
            
            // 更新NFT最后交易ID
            $stmt = $this->pdo->prepare("UPDATE nft_avatars SET last_transaction_id = ? WHERE id = ?");
            $stmt->execute([$transactionId, $nftId]);
            
            $this->pdo->commit();
            return $transactionId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    /* // 其他NFT相关方法...
	public function getAvatarsByCity($city, $limit = 12, $status = 'listed') {
        try {
            $sql = "SELECT n.*, t.price, t.currency, u.username as owner_name 
                    FROM nft_avatars n
                    LEFT JOIN nft_transactions t ON n.last_transaction_id = t.id
                    LEFT JOIN users u ON n.owner_id = u.id
                    WHERE n.city = ? AND (t.status = ? OR ? IS NULL)
                    ORDER BY t.created_at DESC
                    LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$city, $status, $status, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("数据库查询失败: " . $e->getMessage());
            return [];
        }
    } */
	
	/**
	 * 获取指定城市的NFT总数
	 */
	public function getTotalAvatarsByCity($cityId) {
		try {
			$stmt = $this->pdo->prepare("
				SELECT COUNT(*) 
				FROM nft_transactions t
				WHERE t.city_id = ? 
				AND t.status = 'listed'
			");
			$stmt->execute([$cityId]);
			return $stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("[getTotalAvatarsByCity] 查询失败: " . $e->getMessage());
			return 0;
		}
	}

	public function getAvatarsByCity($cityId, $limit = 12, $status = 'listed', $offset = 0) {
		try {
			$sql = "SELECT 
						n.id,
						n.code,
						n.base_image,
						t.price,
						t.currency,
						t.transaction_type,
						u.username as seller_name,
						c.name as city_name,
						t.created_at as list_time
					FROM nft_transactions t
					JOIN nft_avatars n ON t.nft_id = n.id
					JOIN cities c ON t.city_id = c.id
					JOIN users u ON t.seller_id = u.id
					WHERE t.city_id = ? 
					AND t.status = ?
					ORDER BY t.created_at DESC
					LIMIT ? OFFSET ?";
								
		
			$stmt = $this->pdo->prepare($sql);
			
			
			// 明确参数类型绑定
			$stmt->bindValue(1, $cityId, PDO::PARAM_INT);
			$stmt->bindValue(2, $status, PDO::PARAM_STR);
			$stmt->bindValue(3, $limit, PDO::PARAM_INT);
			$stmt->bindValue(4, $offset, PDO::PARAM_INT);
			$stmt->execute();
			
			//$stmt->execute([$cityId, $status, $limit]);
			return $stmt->fetchAll();
			
		} catch (PDOException $e) {
			error_log("[getAvatarsByCity] 查询失败: " . $e->getMessage());
			return [];
		}
	}
	
	/**
	 * 获取用户挂售的NFT列表
	 * @param int $userId 用户ID
	 * @param string $status 交易状态 (listed/pending/completed/canceled)
	 * @return array NFT列表
	 */
	public function getUserListings($userId, $status = 'listed') {
		try {
			$sql = "SELECT 
						n.id AS nft_id,
						n.code,
						n.base_image,
						t.price,
						t.currency,
						t.transaction_type,
						t.status AS transaction_status,
						t.created_at AS list_time,
						c.id AS city_id,
						c.name AS city_name
					FROM nft_transactions t
					JOIN nft_avatars n ON t.nft_id = n.id
					JOIN nft_city_user ncu ON n.id = ncu.nft_id AND t.city_id = ncu.city_id
					JOIN cities c ON t.city_id = c.id
					WHERE t.seller_id = ? 
					AND t.status = ?
					AND ncu.user_id = ?
					AND ncu.is_current = 1
					ORDER BY t.created_at DESC";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->bindValue(1, $userId, PDO::PARAM_INT);
			$stmt->bindValue(2, $status, PDO::PARAM_STR);
			$stmt->bindValue(3, $userId, PDO::PARAM_INT);
			$stmt->execute();
			
			return $stmt->fetchAll();
		} catch (PDOException $e) {
			error_log("获取用户挂售列表失败: " . $e->getMessage());
			return [];
		}
	}
	
	/**
     * 获取所有NFT列表（分页）
     */
    /**
	 * 获取所有NFT列表（不关联城市）
	 */
	/* public function getAllNfts(int $limit = 12, int $offset = 0): array {
		$stmt = $this->pdo->prepare("
			SELECT 
				id,
				code,
				base_image AS image_url, 
				created_at
			FROM nft_avatars
			ORDER BY created_at DESC
			LIMIT :limit OFFSET :offset
		");
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	} */

	/**
	 * 获取NFT在指定城市的认领状态
	 */
	public function getNftCityClaims(int $nftId): array {
		$stmt = $this->pdo->prepare("
			SELECT 
				c.id AS city_id,
				c.name AS city_name,
				IFNULL(ncu.user_id, 0) AS claimed_user_id,
				u.username AS claimed_username,
				IFNULL(ns.id, 0) AS sale_id
			FROM cities c
			LEFT JOIN nft_city_user ncu ON c.id = ncu.city_id 
				AND ncu.nft_id = ? 
				AND ncu.is_current = 1
			LEFT JOIN users u ON ncu.user_id = u.id
			LEFT JOIN nft_sales ns ON ns.nft_id = ? 
				AND ns.city_id = c.id 
				AND ns.status = 'active'
			ORDER BY c.rank ASC
		");
		$stmt->execute([$nftId, $nftId]);
		return $stmt->fetchAll();
	}

	/**
	 * 提交认领申诉
	 */
	public function submitClaimAppeal(
		int $nftId,
		int $cityId,
		int $userId,
		string $evidenceImages,
		string $reason
	): bool {
		$stmt = $this->pdo->prepare("
			INSERT INTO nft_claim_appeals
			(nft_id, city_id, user_id, evidence_images, reason, status)
			VALUES (?, ?, ?, ?, ?, 'pending')
		");
		return $stmt->execute([
			$nftId, $cityId, $userId, $evidenceImages, $reason
		]);
	}
    /**
     * 获取NFT总数
     */
    /* public function getTotalNftCount(): int {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM nft_avatars");
        return (int)$stmt->fetchColumn();
    } */
    
    /**
	 * 根据ID获取NFT详情
	 */
	public function getNftById(int $id): ?array {
		$stmt = $this->pdo->prepare("
			SELECT * FROM nft_avatars 
			WHERE id = ?
		");
		$stmt->execute([$id]);
		return $stmt->fetch() ?: null;
	}
    
    /**
     * 检查NFT在指定城市是否可认领
     */
    public function isNftAvailableInCity(int $nftId, int $cityId): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM nft_city_user 
            WHERE nft_id = ? AND city_id = ? AND is_current = 1
        ");
        $stmt->execute([$nftId, $cityId]);
        return $stmt->fetchColumn() == 0;
    }
    
    /**
     * 认领NFT
     */
    /*public function claimNft(int $nftId, int $userId, int $cityId, $blockId): bool {
        $this->pdo->beginTransaction();
        try {
            // 1. 标记用户之前的NFT为非当前
            //$stmt = $this->pdo->prepare("
            //    UPDATE nft_city_user 
            //    SET is_current = 0 
            //    WHERE user_id = ? AND is_current = 1
            //");
            //$stmt->execute([$userId]);
            
            // 2. 创建新关联
            $stmt = $this->pdo->prepare("
                INSERT INTO nft_city_user 
                (nft_id, city_id, user_id, block_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$nftId, $cityId, $userId,$blockId]);
            
            // 3. 更新城市居民数
            //$stmt = $this->pdo->prepare("
            //    UPDATE cities 
            //    SET resident_count = resident_count + 1 
            //    WHERE id = ?
            //");
            //$stmt->execute([$cityId]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("认领NFT失败: " . $e->getMessage());
			//echo $e->getMessage();exit;
            return false;
        }
    }*/
	
	/**
	 * 认领NFT
	 */
	public function claimNft(int $nftId, int $userId, int $cityId, $blockId): bool {
		$this->pdo->beginTransaction();
		try {
			// 检查是否已经认领过（可选，根据业务需求）
			$checkStmt = $this->pdo->prepare("
				SELECT id FROM nft_city_user 
				WHERE nft_id = ? AND city_id = ? AND user_id = ?
			");
			$checkStmt->execute([$nftId, $cityId, $userId]);
			$existingClaim = $checkStmt->fetch();
			
			if ($existingClaim) {
				// 如果已经认领过，可以选择更新或者返回错误
				// 这里选择更新区块ID
				$updateStmt = $this->pdo->prepare("
					UPDATE nft_city_user 
					SET block_id = ?, updated_at = NOW() 
					WHERE id = ?
				");
				$updateStmt->execute([$blockId, $existingClaim['id']]);
			} else {
				// 创建新关联
				$stmt = $this->pdo->prepare("
					INSERT INTO nft_city_user 
					(nft_id, city_id, user_id, block_id, created_at)
					VALUES (?, ?, ?, ?, NOW())
				");
				$stmt->execute([$nftId, $cityId, $userId, $blockId]);
			}
			
			$this->pdo->commit();
			return true;
		} catch (Exception $e) {
			$this->pdo->rollBack();
			error_log("认领NFT失败: " . $e->getMessage());
			return false;
		}
	}
    
    /**
     * 获取NFT在指定城市的出售信息
     */
    public function getNftSaleInfo(int $nftId, int $cityId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM nft_sales 
            WHERE nft_id = ? AND city_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$nftId, $cityId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * 创建求购请求
     */
    public function createPurchaseRequest(
        int $nftId, 
        int $cityId, 
        int $userId, 
        string $priceType, 
        float $amount, 
        string $message
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO nft_purchase_requests 
            (nft_id, city_id, user_id, price_type, amount, message, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");
        return $stmt->execute([
            $nftId, $cityId, $userId, $priceType, $amount, $message
        ]);
    }
    
    /**
     * 获取用户收藏的NFT
     */
    public function getUserCollection(int $userId, $limit = null): array {
        /* $stmt = $this->pdo->prepare("
            SELECT n.*, c.name AS city_name
            FROM nft_avatars n
            JOIN nft_city_user ncu ON n.id = ncu.nft_id
            JOIN cities c ON ncu.city_id = c.id
            WHERE ncu.user_id = ? AND ncu.is_current = 1
            ORDER BY ncu.created_at DESC
        "); */
		
		$sql = "
            SELECT n.*, c.name AS city_name, c.id AS city_id, n.id AS nft_id,c.rank AS city_rank
            FROM nft_avatars n
            JOIN nft_city_user ncu ON n.id = ncu.nft_id
            JOIN cities c ON ncu.city_id = c.id
            WHERE ncu.user_id = ? AND ncu.is_current = 1
            ORDER BY ncu.created_at DESC
        ";
		if ($limit !== null) {
			$sql .= " LIMIT " . (int)$limit;
		}
		
		$stmt = $this->pdo->prepare($sql);
	
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取用户挂售的NFT
     */
	 /*
    public function getUserListings(int $userId, string $status = 'listed'): array {
        $stmt = $this->pdo->prepare("
            SELECT n.*, s.price, s.currency, s.id AS sale_id
            FROM nft_avatars n
            JOIN nft_sales s ON n.id = s.nft_id
            WHERE s.seller_id = ? AND s.status = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$userId, $status]);
        return $stmt->fetchAll();
    }*/
	
	public function getClaimStatus(int $nftId, int $cityId): array {
		try {
			$stmt = $this->pdo->prepare("
				SELECT 
					u.username,
					c.name AS city_name,
					IFNULL(ncu.block_id, '') AS block_id,
					COUNT(ncu.id) > 0 AS claimed
				FROM nft_city_user ncu
				LEFT JOIN users u ON ncu.user_id = u.id
				LEFT JOIN cities c ON ncu.city_id = c.id
				WHERE ncu.nft_id = ? AND ncu.city_id = ? AND ncu.is_current = 1
				GROUP BY ncu.id
				LIMIT 1
			");
			$stmt->execute([$nftId, $cityId]);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			
			return $result ?: [
				'claimed' => false,
				'username' => null,
				'city_name' => null,
				'block_id' => null
			];
		} catch (PDOException $e) {
			error_log("getClaimStatus error: " . $e->getMessage());
			return [
				'claimed' => false,
				'error' => $e->getMessage()
			];
		}
	}	
	
	// 在classes/NFT.php中添加以下方法

	/**
	 * 获取所有标签
	 */
	public function getAllTags() {
		$sql = "SELECT name FROM tags ORDER BY name ASC";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	/**
	 * 获取NFT列表（带搜索条件）
	 */
	public function getAllNfts($limit = 100, $offset = 0, $code = '', $tag = '') {
		$sql = "SELECT n.* FROM nft_avatars n";
		
		// 如果按标签搜索，需要连接标签表
		if (!empty($tag)) {
			$sql .= " JOIN nft_tags nt ON n.id = nt.nft_avatar_id 
					 JOIN tags t ON nt.tag_id = t.id AND t.name = :tag";
		}
		
		$sql .= " WHERE 1=1";
		
		// 添加编号搜索条件
		if (!empty($code)) {
			$sql .= " AND n.code LIKE :code";
		}
		
		$sql .= " ORDER BY n.code ASC LIMIT :limit OFFSET :offset";
		
		$stmt = $this->pdo->prepare($sql);
		
		// 绑定参数
		if (!empty($tag)) {
			$stmt->bindValue(':tag', $tag);
		}
		
		if (!empty($code)) {
			$stmt->bindValue(':code', '%' . $code . '%');
		}
		
		$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
		
		$stmt->execute();
		return $stmt->fetchAll();
	}

	/**
	 * 获取NFT总数（带搜索条件）
	 */
	public function getTotalNftCount($code = '', $tag = '') {
		$sql = "SELECT COUNT(*) FROM nft_avatars n";
		
		// 如果按标签搜索，需要连接标签表
		if (!empty($tag)) {
			$sql .= " JOIN nft_tags nt ON n.id = nt.nft_avatar_id 
					 JOIN tags t ON nt.tag_id = t.id AND t.name = :tag";
		}
		
		$sql .= " WHERE 1=1";
		
		// 添加编号搜索条件
		if (!empty($code)) {
			$sql .= " AND n.code LIKE :code";
		}
		
		$stmt = $this->pdo->prepare($sql);
		
		// 绑定参数
		if (!empty($tag)) {
			$stmt->bindValue(':tag', $tag);
		}
		
		if (!empty($code)) {
			$stmt->bindValue(':code', '%' . $code . '%');
		}
		
		$stmt->execute();
		return (int)$stmt->fetchColumn();
	}
	
	/**
	 * 获取NFT的标签
	 */
	public function getNftTags($nftId) {
		/* $sql = "SELECT t.name FROM tags t
				JOIN nft_tags nt ON t.id = nt.tag_id
				WHERE nt.nft_avatar_id = :nftId";
			 
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(':nftId', $nftId, PDO::PARAM_INT); */
		
		$sql = "SELECT t.name FROM tags t
				JOIN nft_tags nt ON t.id = nt.tag_id
				WHERE nt.nft_avatar_id = ?";
			 
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(1, $nftId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();//$stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}
	
	public function getSaleList($city = '', $minPrice = '', $maxPrice = '', $currency = 'all', $sort = 'newest', $limit = 24, $offset = 0) {
		$sql = "SELECT 
					n.id AS nft_id,
					n.code,
					n.base_image, 
					t.price,
					t.currency,
					t.created_at,
					t.id AS transaction_id,
					t.seller_id,
					u.username AS seller_name,
					c.id AS city_id,
					c.name AS city_name,
					(SELECT COUNT(*) FROM nft_purchase_requests WHERE nft_id = n.id AND status = 'active') AS purchase_count
				FROM nft_avatars n
				JOIN nft_transactions t ON n.id = t.nft_id
				JOIN users u ON t.seller_id = u.id
				JOIN cities c ON t.city_id = c.id
				WHERE t.status = 'listed'";
		
		// 添加筛选条件
		$conditions = [];
		$params = [];
		
		if (!empty($city)) {
			$conditions[] = "c.name = ?";
			$params[] = $city;
		}
		
		if (is_numeric($minPrice)) {
			$conditions[] = "t.price >= ?";
			$params[] = $minPrice;
		}
		
		if (is_numeric($maxPrice)) {
			$conditions[] = "t.price <= ?";
			$params[] = $maxPrice;
		}
		
		if ($currency != 'all') {
			$conditions[] = "t.currency = ?";
			$params[] = $currency;
		}
		
		if (!empty($conditions)) {
			$sql .= " AND " . implode(" AND ", $conditions);
		}
		
		// 添加排序
		switch ($sort) {
			case 'price_asc':
				$sql .= " ORDER BY t.price ASC";
				break;
			case 'price_desc':
				$sql .= " ORDER BY t.price DESC";
				break;
			case 'hot':
				$sql .= " ORDER BY purchase_count DESC, t.created_at DESC";
				break;
			default:
				$sql .= " ORDER BY t.created_at DESC";
		}
		
		// 添加分页
		$sql .= " LIMIT ? OFFSET ?";
		
		$stmt = $this->pdo->prepare($sql);
		
		// 绑定所有参数
		$paramIndex = 1;
		foreach ($params as $param) {
			$stmt->bindValue($paramIndex++, $param);
		}
		
		// 明确指定LIMIT和OFFSET为整数
		$stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
		$stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
		
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getTotalSaleCount($city = '', $minPrice = '', $maxPrice = '', $currency = 'all') {
		$sql = "SELECT COUNT(*)
				FROM nft_avatars n
				JOIN nft_transactions t ON n.id = t.nft_id
				JOIN cities c ON t.city_id = c.id
				WHERE t.status = 'listed'";
		
		$conditions = [];
		$params = [];
		
		if (!empty($city)) {
			$conditions[] = "c.name = ?";
			$params[] = $city;
		}
		
		if (is_numeric($minPrice)) {
			$conditions[] = "t.price >= ?";
			$params[] = $minPrice;
		}
		
		if (is_numeric($maxPrice)) {
			$conditions[] = "t.price <= ?";
			$params[] = $maxPrice;
		}
		
		if ($currency != 'all') {
			$conditions[] = "t.currency = ?";
			$params[] = $currency;
		}
		
		if (!empty($conditions)) {
			$sql .= " AND " . implode(" AND ", $conditions);
		}
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchColumn();
	}

	public function getAllRarities() {
		return ['common', 'rare', 'epic', 'legendary'];
	}
	
	public function getUserPurchases($userId) {
		$sql = "SELECT 
					n.id AS nft_id,
					n.code,
					n.base_image,
					t.price,
					t.currency,
					t.created_at AS transaction_date,
					u.username AS seller_name
				FROM nft_avatars n
				JOIN nft_transactions t ON n.id = t.nft_id
				JOIN users u ON t.seller_id = u.id
				WHERE t.buyer_id = ? AND t.status = 'completed'
				ORDER BY t.created_at DESC";
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
	}
	
	public function getUserCollectionWithCity($userId) {
		$sql = "SELECT 
					n.id AS nft_id,
					n.code,
					n.base_image, 
					c.id AS city_id,
					c.name AS city_name,
					u.is_profile_avatar
				FROM nft_avatars n
				JOIN nft_city_user ncu ON n.id = ncu.nft_id
				JOIN cities c ON ncu.city_id = c.id
				JOIN user_nft_collections u ON n.id = u.nft_id
				WHERE u.user_id = ?
				ORDER BY u.acquired_at DESC";
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
	}
	
	public function verifyOwnership($nftId, $cityId, $userId) {
		$sql = "SELECT c.name AS city_name
				FROM nft_city_user ncu
				JOIN cities c ON ncu.city_id = c.id
				WHERE ncu.nft_id = ? 
				AND ncu.city_id = ?
				AND ncu.user_id = ?";
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$nftId, $cityId, $userId]);
		return $stmt->fetch();
	}

	public function getNftDetails($nftId) {
		$sql = "SELECT code, base_image FROM nft_avatars WHERE id = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$nftId]);
		return $stmt->fetch();
	}
	
	// 在NFT类中添加以下方法

	/**
	 * 获取可求购的NFT列表
	 */
	public function getAvailableNftsForPurchase($limit = 20, $offset = 0, $code = '', $city = '') {
		$sql = "SELECT n.*, u.username as owner_name
				FROM nft_avatars n
				LEFT JOIN users u ON n.owner_id = u.id
				WHERE n.owner_id IS NOT NULL";
		
		$params = [];
		
		if (!empty($code)) {
			$sql .= " AND n.code LIKE ?";
			$params[] = "%$code%";
		}
		
		if (!empty($city)) {
			$sql .= " AND n.city = ?";
			$params[] = $city;
		}
		
		$sql .= " ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset";
		
		$stmt = $this->pdo->prepare($sql);
		
		// 绑定参数
		foreach ($params as $key => $value) {
			$stmt->bindValue($key + 1, $value);
		}
		
		// 特别注意：这里使用bindValue而不是直接传递参数
		$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
		
		$stmt->execute();
		return $stmt->fetchAll();
	}

	/**
	 * 获取可求购的NFT总数
	 */
	public function getTotalAvailableNftCount($code = '', $city = '') {
		$sql = "SELECT COUNT(*) FROM nft_avatars WHERE owner_id IS NOT NULL";
		
		$params = [];
		
		if (!empty($code)) {
			$sql .= " AND code LIKE ?";
			$params[] = "%$code%";
		}
		
		if (!empty($city)) {
			$sql .= " AND city = ?";
			$params[] = $city;
		}
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchColumn();
	}


	/**
	 * 获取用户拥有的NFT总数
	 * 
	 * @param int $userId 用户ID
	 * @return int NFT总数
	 */
	public function getUserCollectionCount(int $userId): int {
		try {
			$sql = "SELECT COUNT(*)
					FROM nft_city_user
					WHERE user_id = ? AND is_current = 1";

			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$userId]);

			return (int)$stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("获取用户NFT总数失败: " . $e->getMessage());
			return 0;
		}
	}
	
	// public function getTotalNftCount() {
    // $stmt = $this->pdo->query("SELECT COUNT(*) FROM nft_avatars");
    // return (int)$stmt->fetchColumn();
	// }

	public function getRecentlyListedNfts($limit = 5) {
		$sql = "SELECT n.*, t.price, t.currency, t.created_at, c.name as city_name
				FROM nft_avatars n
				JOIN nft_transactions t ON n.id = t.nft_id
				JOIN cities c ON t.city_id = c.id
				WHERE t.status = 'listed'
				ORDER BY t.created_at DESC
				LIMIT ?";
		$stmt = $this->pdo->prepare($sql);
		//$stmt->execute([$limit]);
		$stmt->bindValue(1, $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getPopularCities($limit = 5) {
		$sql = "SELECT c.id, c.name, 
					   COUNT(DISTINCT n.id) as total_nfts,
					   SUM(CASE WHEN t.status = 'listed' THEN 1 ELSE 0 END) as active_listings,
					   COUNT(DISTINCT CASE WHEN t.status = 'completed' AND t.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN t.id END) as recent_transactions,
					   c.popularity
				FROM cities c
				LEFT JOIN nft_transactions t ON c.id = t.city_id
				LEFT JOIN nft_avatars n ON t.nft_id = n.id
				GROUP BY c.id
				ORDER BY c.popularity DESC
				LIMIT ?";
		$stmt = $this->pdo->prepare($sql);
		//$stmt->execute([$limit]);
		$stmt->bindValue(1, $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}
	
	/**
	 * 获取NFT的最近认领记录
	 */
	public function getRecentClaims($nftId, $limit = 10) {
		$sql = "SELECT ncu.*, c.name as city_name, u.username, ncu.block_id
				FROM nft_city_user ncu 
				LEFT JOIN cities c ON ncu.city_id = c.id 
				LEFT JOIN users u ON ncu.user_id = u.id 
				WHERE ncu.nft_id = ? 
				ORDER BY ncu.id DESC 
				LIMIT ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(1, $nftId, PDO::PARAM_INT);
		$stmt->bindValue(2, $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	/**
 * 获取综合排名前N的NFT
 */
public function getTopRankedNfts($limit = 100) {
    $sql = "
        SELECT 
            na.id,
            na.code,
            na.base_image,
            na.avatar_id,
            na.created_at,
            
            -- 认领城市数（从nft_city_user表）
            (SELECT COUNT(DISTINCT city_id) FROM nft_city_user 
             WHERE nft_id = na.id AND is_current = 1) as claim_city_count,
            
            -- 售卖城市数（从nft_sales表，活跃状态的销售）
            (SELECT COUNT(DISTINCT city_id) FROM nft_sales 
             WHERE nft_id = na.id AND status = 'active') as sale_city_count,
            
            -- 求购数量（从nft_purchase_requests表，待处理的求购）
            (SELECT COUNT(*) FROM nft_purchase_requests 
             WHERE nft_id = na.id AND status = 'pending') as purchase_count,
            
            -- 评论数量（从comments表）
            (SELECT COUNT(*) FROM comments 
             WHERE nft_id = na.id) as comment_count,
            
            -- 综合得分（加权计算）
            (
                (SELECT COUNT(DISTINCT city_id) FROM nft_city_user 
                 WHERE nft_id = na.id AND is_current = 1) * 3 +
                (SELECT COUNT(DISTINCT city_id) FROM nft_sales 
                 WHERE nft_id = na.id AND status = 'active') * 2 +
                (SELECT COUNT(*) FROM nft_purchase_requests 
                 WHERE nft_id = na.id AND status = 'pending') * 1.5 +
                (SELECT COUNT(*) FROM comments 
                 WHERE nft_id = na.id) * 1
            ) as total_score
            
        FROM nft_avatars na
        WHERE na.id IS NOT NULL
        HAVING total_score > 0
        ORDER BY total_score DESC, na.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取NFT的认领城市
 */
public function getClaimedCities($nftId) {
    $sql = "SELECT DISTINCT c.id, c.name 
            FROM nft_city_user ncu 
            JOIN cities c ON ncu.city_id = c.id 
            WHERE ncu.nft_id = ? AND ncu.is_current = 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$nftId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取NFT的售卖城市
 */
public function getSaleCities($nftId) {
    $sql = "SELECT DISTINCT c.id, c.name 
            FROM nft_sales ns 
            JOIN cities c ON ns.city_id = c.id 
            WHERE ns.nft_id = ? AND ns.status = 'active'";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$nftId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取用户拥有的NFT记录
 */
public function getUserClaims($userId, $nftId) {
    $sql = "SELECT ncu.*, c.name as city_name 
            FROM nft_city_user ncu 
            JOIN cities c ON ncu.city_id = c.id 
            WHERE ncu.user_id = ? AND ncu.nft_id = ? AND ncu.is_current = 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$userId, $nftId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取NFT最近活动记录
 */
public function getRecentActivities($nftId, $limit = 10) {
    // 认领记录
    $sql1 = "SELECT 'claim' as type, CONCAT(u.username, ' 在 ', c.name, ' 认领了该NFT') as description, 
                    ncu.created_at
             FROM nft_city_user ncu 
             JOIN users u ON ncu.user_id = u.id 
             JOIN cities c ON ncu.city_id = c.id 
             WHERE ncu.nft_id = ? 
             ORDER BY ncu.created_at DESC 
             LIMIT ?";
    
    // 上架记录
    $sql2 = "SELECT 'sale' as type, CONCAT(u.username, ' 在 ', c.name, ' 上架了该NFT') as description, 
                    ns.created_at
             FROM nft_sales ns 
             JOIN users u ON ns.seller_id = u.id 
             JOIN cities c ON ns.city_id = c.id 
             WHERE ns.nft_id = ? AND ns.status = 'active'
             ORDER BY ns.created_at DESC 
             LIMIT ?";
    
    // 求购记录
    $sql3 = "SELECT 'purchase' as type, CONCAT(u.username, ' 发起了求购') as description, 
                    npr.created_at
             FROM nft_purchase_requests npr 
             JOIN users u ON npr.user_id = u.id 
             WHERE npr.nft_id = ? AND npr.status = 'pending'
             ORDER BY npr.created_at DESC 
             LIMIT ?";
    
    $activities = [];
    
    // 获取认领记录
    $stmt = $this->pdo->prepare($sql1);
    $stmt->bindValue(1, $nftId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // 获取上架记录
    $stmt = $this->pdo->prepare($sql2);
    $stmt->bindValue(1, $nftId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // 获取求购记录
    $stmt = $this->pdo->prepare($sql3);
    $stmt->bindValue(1, $nftId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // 按时间排序并限制数量
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, $limit);
}

// 将这个部分添加到你的 NFT.php 类的末尾，在最后一个方法后面

    /**
     * 获取全站统计数据
     */
    public function getGlobalStats(): array {
        try {
            $stats = [];
            
            // 获取全站头像认领数（从nft_city_user表）
            $sql = "SELECT COUNT(*) as count FROM nft_city_user WHERE is_current = 1";
            $stmt = $this->pdo->query($sql);
            $stats['total_claims'] = (int)$stmt->fetchColumn();
            
            // 获取全站头像挂售数（从nft_sales表，活跃状态）
            //$sql = "SELECT COUNT(*) as count FROM nft_sales WHERE status = 'active'";
            //$stmt = $this->pdo->query($sql);
            $stats['total_sales'] = $this->getTotalSaleCount();//(int)$stmt->fetchColumn();
            
            // 获取全站头像求购数（从nft_purchase_requests表，待处理状态）
            $sql = "SELECT COUNT(*) as count FROM nft_purchase_requests WHERE status = 'pending'";
            $stmt = $this->pdo->query($sql);
            $stats['total_purchases'] = (int)$stmt->fetchColumn();
            
            // 获取全站评论数（从comments表）
            $sql = "SELECT COUNT(*) as count FROM comments";
            $stmt = $this->pdo->query($sql);
            $stats['total_comments'] = (int)$stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("获取全局统计失败: " . $e->getMessage());
            return [
                'total_claims' => 0,
                'total_sales' => 1,
                'total_purchases' => 0,
                'total_comments' => 0
            ];
        }
    }

    /**
     * 获取排名NFT总数
     * 用于分页计算（有排名的NFT）
     */
    public function getTotalTopNftsCount(): int {
        try {
            // 只计算有排名的NFT（total_score > 0）
            $sql = "
                SELECT COUNT(*) 
                FROM (
                    SELECT 
                        na.id,
                        (
                            (SELECT COUNT(DISTINCT city_id) FROM nft_city_user 
                             WHERE nft_id = na.id AND is_current = 1) * 3 +
                            (SELECT COUNT(DISTINCT city_id) FROM nft_sales 
                             WHERE nft_id = na.id AND status = 'active') * 2 +
                            (SELECT COUNT(*) FROM nft_purchase_requests 
                             WHERE nft_id = na.id AND status = 'pending') * 1.5 +
                            (SELECT COUNT(*) FROM comments 
                             WHERE nft_id = na.id) * 1
                        ) as total_score
                    FROM nft_avatars na
                ) as ranked_nfts
                WHERE total_score > 0
            ";
            $stmt = $this->pdo->query($sql);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("获取排名NFT总数失败: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取分页的排名NFT数据
     * 修改现有的getTopRankedNfts方法，添加分页支持
     */
    public function getPaginatedTopNfts(int $offset = 0, int $limit = 20): array {
        try {
            $sql = "
                SELECT 
                    na.id,
                    na.code,
                    na.base_image,
                    na.avatar_id,
                    na.created_at,
                    
                    -- 认领城市数（从nft_city_user表）
                    (SELECT COUNT(DISTINCT city_id) FROM nft_city_user 
                     WHERE nft_id = na.id AND is_current = 1) as claim_city_count,
                    
                    -- 售卖城市数（从nft_sales表，活跃状态的销售）
                    (SELECT COUNT(DISTINCT city_id) FROM nft_sales 
                     WHERE nft_id = na.id AND status = 'active') as sale_city_count,
                    
                    -- 求购数量（从nft_purchase_requests表，待处理的求购）
                    (SELECT COUNT(*) FROM nft_purchase_requests 
                     WHERE nft_id = na.id AND status = 'pending') as purchase_count,
                    
                    -- 评论数量（从comments表）
                    (SELECT COUNT(*) FROM comments 
                     WHERE nft_id = na.id) as comment_count,
                    
                    -- 综合得分（加权计算）
                    (
                        (SELECT COUNT(DISTINCT city_id) FROM nft_city_user 
                         WHERE nft_id = na.id AND is_current = 1) * 3 +
                        (SELECT COUNT(DISTINCT city_id) FROM nft_sales 
                         WHERE nft_id = na.id AND status = 'active') * 2 +
                        (SELECT COUNT(*) FROM nft_purchase_requests 
                         WHERE nft_id = na.id AND status = 'pending') * 1.5 +
                        (SELECT COUNT(*) FROM comments 
                         WHERE nft_id = na.id) * 1
                    ) as total_score
                    
                FROM nft_avatars na
                WHERE na.id IS NOT NULL
                HAVING total_score > 0
                ORDER BY total_score DESC, na.created_at DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取分页排名NFT失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取所有NFT总数（包括没有排名的）
     * 用于分页计算所有NFT
     */
    /* public function getTotalNftCount(): int {
        try {
            $sql = "SELECT COUNT(*) FROM nft_avatars";
            $stmt = $this->pdo->query($sql);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("获取NFT总数失败: " . $e->getMessage());
            return 0;
        }
    } */


    public function createNft($data) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO nft_avatars (code, base_image, avatar_id, avatar_key) VALUES (?,?,?,?)"
        );
        $stmt->execute([$data['code'], $data['base_image'] ?? '', $data['avatar_id'] ?? '', $data['avatar_key'] ?? '']);
        $id = $this->pdo->lastInsertId();
        if ($id && !empty($data['tag_ids'])) $this->setNftTags($id, $data['tag_ids']);
        return $id;
    }

    public function updateNft($id, $data) {
        $sets = []; $vals = [];
        foreach (['code','base_image','avatar_id','avatar_key'] as $f) {
            if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $vals[] = $data[$f]; }
        }
        if (empty($sets)) return false;
        $vals[] = intval($id);
        $stmt = $this->pdo->prepare("UPDATE nft_avatars SET " . implode(', ', $sets) . " WHERE id = ?");
        $r = $stmt->execute($vals);
        if ($r && isset($data['tag_ids'])) $this->setNftTags($id, $data['tag_ids']);
        return $r;
    }

    public function deleteNft($id) {
        $id = intval($id);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM nft_tags WHERE nft_avatar_id = $id");
            $this->pdo->exec("DELETE FROM comments WHERE nft_id = $id");
            $this->pdo->exec("DELETE FROM nft_city_user WHERE nft_id = $id");
            $this->pdo->exec("DELETE FROM nft_sales WHERE nft_id = $id");
            $this->pdo->exec("DELETE FROM nft_purchase_requests WHERE nft_id = $id");
            $this->pdo->exec("DELETE FROM nft_transactions WHERE nft_id = $id");
            $stmt = $this->pdo->prepare("DELETE FROM nft_avatars WHERE id = ?");
            $stmt->execute([$id]);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) { $this->pdo->rollBack(); return false; }
    }

    public function setNftTags($nftId, $tagIds) {
        $nftId = intval($nftId);
        $this->pdo->exec("DELETE FROM nft_tags WHERE nft_avatar_id = $nftId");
        if (empty($tagIds)) return;
        $stmt = $this->pdo->prepare("INSERT INTO nft_tags (nft_avatar_id, tag_id) VALUES (?, ?)");
        foreach ($tagIds as $tid) $stmt->execute([$nftId, intval($tid)]);
    }

    public function createTag($name) {
        $stmt = $this->pdo->prepare("INSERT INTO tags (name) VALUES (?)");
        $stmt->execute([trim($name)]);
        return $this->pdo->lastInsertId();
    }

    public function updateTag($id, $name) {
        $stmt = $this->pdo->prepare("UPDATE tags SET name = ? WHERE id = ?");
        return $stmt->execute([trim($name), intval($id)]);
    }

    public function deleteTag($id) {
        $id = intval($id);
        $this->pdo->exec("DELETE FROM nft_tags WHERE tag_id = $id");
        $stmt = $this->pdo->prepare("DELETE FROM tags WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getTagsWithCount() {
        $stmt = $this->pdo->query(
            "SELECT t.*, COUNT(nt.nft_avatar_id) as nft_count 
             FROM tags t LEFT JOIN nft_tags nt ON t.id = nt.tag_id 
             GROUP BY t.id ORDER BY nft_count DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 后台标签列表（带分页）
     */
    public function getTagsForAdmin($page = 1, $perPage = 20) {
        $page = max(1, intval($page));
        $perPage = max(1, intval($perPage));
        $offset = ($page - 1) * $perPage;
        $total = $this->pdo->query("SELECT COUNT(*) FROM tags")->fetchColumn();
        $stmt = $this->pdo->prepare(
            "SELECT t.*, COUNT(nt.nft_avatar_id) as nft_count
             FROM tags t LEFT JOIN nft_tags nt ON t.id = nt.tag_id
             GROUP BY t.id ORDER BY nft_count DESC, t.id DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['list' => $list, 'total' => (int)$total, 'pages' => ceil($total / $perPage)];
    }

    public function getNftWithTags($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM nft_avatars WHERE id = ?");
        $stmt->execute([intval($id)]);
        $nft = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($nft) $nft['tags'] = $this->getNftTags(intval($id));
        return $nft;
    }

    public function getAllNftsAdmin($page = 1, $perPage = 20, $search = '', $tagId = 0) {
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $where = ''; $params = [];
        if ($search) {
            $where .= " AND (n.code LIKE ? OR n.avatar_id LIKE ?)";
            $t = "%$search%";
            $params = [$t, $t];
        }
        if ($tagId) {
            $where .= " AND EXISTS(SELECT 1 FROM nft_tags WHERE nft_avatar_id = n.id AND tag_id = ?)";
            $params[] = intval($tagId);
        }
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM nft_avatars n WHERE 1=1$where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $sql = "SELECT n.*,
                       (SELECT COUNT(*) FROM nft_purchase_requests WHERE nft_id = n.id AND status = 'pending') as buy_count,
                       (SELECT COUNT(*) FROM nft_sales WHERE nft_id = n.id AND status = 'active') as sale_count,
                       (SELECT COUNT(*) FROM comments WHERE nft_id = n.id) as comment_count
                FROM nft_avatars n WHERE 1=1$where
                ORDER BY n.id DESC LIMIT $perPage OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['list' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'pages' => ceil($total / $perPage)];
    }
}
