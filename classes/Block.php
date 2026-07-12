<?php
class Block {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getBlockById($id) {
        $stmt = $this->pdo->prepare("SELECT b.*, c.name as city_name, u.username as owner_name 
                                    FROM blocks b 
                                    JOIN cities c ON b.city_id = c.id 
                                    LEFT JOIN users u ON b.owner_id = u.id 
                                    WHERE b.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /*public function getBlocksByCityZone($cityId, $zone) {
        $stmt = $this->pdo->prepare("SELECT * FROM blocks WHERE city_id = ? AND zone = ? ORDER BY block_number");
        $stmt->execute([$cityId, $zone]);
        return $stmt->fetchAll();
    }*/
    
    public function getUserBlocks($userId) {
        $stmt = $this->pdo->prepare("SELECT b.*, c.name as city_name 
                                    FROM blocks b 
                                    JOIN cities c ON b.city_id = c.id 
                                    WHERE b.owner_id = ? 
                                    ORDER BY b.city_id, b.zone, b.block_number");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function purchaseBlock($blockId, $buyerId, $price) {
        $this->pdo->beginTransaction();
        
        try {
            // 创建交易记录
            $stmt = $this->pdo->prepare("INSERT INTO transactions (block_id, seller_id, buyer_id, price, transaction_type, status) 
                                        SELECT id, owner_id, ?, ?, 'purchase', 'pending' 
                                        FROM blocks WHERE id = ? AND status = 'available'");
            $stmt->execute([$buyerId, $price, $blockId]);
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("区块不可用或已被购买");
            }
            
            // 更新区块状态
            $stmt = $this->pdo->prepare("UPDATE blocks SET owner_id = ?, status = 'sold', updated_at = NOW() 
                                        WHERE id = ? AND status = 'available'");
            $stmt->execute([$buyerId, $blockId]);
            
            // 更新交易状态
            $stmt = $this->pdo->prepare("UPDATE transactions SET status = 'completed' 
                                        WHERE block_id = ? AND buyer_id = ? AND status = 'pending'");
            $stmt->execute([$blockId, $buyerId]);
            
            // 检查是否需要触发扩容投票
            $this->checkExpansionVote($blockId);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    private function checkExpansionVote($blockId) {
        // 获取区块信息
        $stmt = $this->pdo->prepare("SELECT city_id, zone FROM blocks WHERE id = ?");
        $stmt->execute([$blockId]);
        $block = $stmt->fetch();
        
        if (!$block) return;
        
        // 检查当前区已售区块数量
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as sold_count FROM blocks 
                                    WHERE city_id = ? AND zone = ? AND status = 'sold'");
        $stmt->execute([$block['city_id'], $block['zone']]);
        $result = $stmt->fetch();
        
        // 如果达到793个区块，触发扩容投票
        if ($result['sold_count'] == 793) {
            $nextZone = $this->getNextZone($block['zone']);
            if ($nextZone) {
                $this->createExpansionVote($block['city_id'], $nextZone);
            }
        }
    }
    
    private function getNextZone($currentZone) {
        $zones = ['A'=>'B', 'B'=>'C', 'C'=>'D', 'D'=>'E', 'E'=>'F', 'F'=>'G', 'G'=>'H', 'H'=>'Z'];
        return $zones[$currentZone] ?? null;
    }
    
    /* private function createExpansionVote($cityId, $zone) {
        // 检查是否已有进行中的投票
        $stmt = $this->pdo->prepare("SELECT id FROM expansion_votes 
                                    WHERE city_id = ? AND zone = ? AND result = 'pending'");
        $stmt->execute([$cityId, $zone]);
        
        if ($stmt->rowCount() == 0) {
            $startTime = date('Y-m-d H:i:s');
            $endTime = date('Y-m-d H:i:s', strtotime('+3 days'));
            
            $stmt = $this->pdo->prepare("INSERT INTO expansion_votes 
                                        (city_id, zone, round, start_time, end_time, result) 
                                        VALUES (?, ?, 1, ?, ?, 'pending')");
            $stmt->execute([$cityId, $zone, $startTime, $endTime]);
            
            // 通知所有城市区块拥有者
            $this->notifyCityResidents($cityId, "城市扩容投票已开始，请参与投票决定是否扩容{$zone}区");
        }
    } */
    
    private function notifyCityResidents($cityId, $message) {
        // 实际实现中可以通过邮件、站内消息等方式通知
        // 这里简化为记录日志
        error_log("Notification to city {$cityId}: {$message}");
    }
	
	public function getUserPurchaseRequests($userId) {
		$stmt = $this->pdo->prepare("SELECT pr.*, c.name as city_name
									FROM purchase_requests pr
									JOIN cities c ON pr.city_id = c.id
									WHERE pr.user_id = ? AND pr.status = 'active'
									ORDER BY pr.created_at DESC");
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
	}

	/* public function getActiveExpansionVote($cityId, $zone) {
		$stmt = $this->pdo->prepare("SELECT * FROM expansion_votes 
									WHERE city_id = ? AND zone = ? AND result = 'pending'");
		$stmt->execute([$cityId, $zone]);
		return $stmt->fetch();
	}

	public function getUserActiveVotes($userId) {
		$stmt = $this->pdo->prepare("SELECT ev.*, c.name as city_name
									FROM expansion_votes ev
									JOIN cities c ON ev.city_id = c.id
									JOIN blocks b ON c.id = b.city_id
									WHERE b.owner_id = ? AND ev.result = 'pending'
									GROUP BY ev.id
									ORDER BY ev.end_time ASC");
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
	} */
	
	// 在Block类中添加以下方法

	/**
     * 获取合并区块数据
     */
    public function getMergedBlocks($cityId, $zone) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM merged_blocks WHERE city_id = ? AND zone = ?");
            $stmt->execute([$cityId, $zone]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取合并区块失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 计算合并区块的尺寸
     */
    private function calculateMergeSize($blockNumbers) {
        $rows = [];
        $cols = [];
        
        foreach ($blockNumbers as $blockNumber) {
            $col = intval(substr($blockNumber, 0, 2));
            $row = intval(substr($blockNumber, 2, 2));
            $rows[] = $row;
            $cols[] = $col;
        }
        
        $rowSpan = max($rows) - min($rows) + 1;
        $colSpan = max($cols) - min($cols) + 1;
        
        return "{$colSpan}x{$rowSpan}";
    }

    /**
     * 认领单个区块
     */
    public function claimBlock($userId, $cityId, $zone, $blockNumber) {
        try {
            $this->pdo->beginTransaction();
            
            // 计算区块价格
            $blockPrice = $this->calculateBlockPrice($zone, $blockNumber);
            
            // 检查区块是否已存在
            $stmt = $this->pdo->prepare("SELECT id, status FROM blocks WHERE city_id = ? AND zone = ? AND block_number = ?");
            $stmt->execute([$cityId, $zone, $blockNumber]);
            $existingBlock = $stmt->fetch();
            
            if ($existingBlock) {
                // 区块已存在，检查是否可用
                if ($existingBlock['status'] !== 'available') {
                    $this->pdo->rollBack();
                    return false;
                }
                
                // 更新现有区块
                $stmt = $this->pdo->prepare("UPDATE blocks SET owner_id = ?, status = 'sold', price = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$userId, $blockPrice, $existingBlock['id']]);
            } else {
                // 区块不存在，创建新记录
                $stmt = $this->pdo->prepare("INSERT INTO blocks (city_id, zone, block_number, price, owner_id, status, created_at, updated_at) 
                                            VALUES (?, ?, ?, ?, ?, 'sold', NOW(), NOW())");
                $stmt->execute([$cityId, $zone, $blockNumber, $blockPrice, $userId]);
            }
            
            // 更新城市统计数据
            $this->updateCityStats($cityId);
            
            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("认领区块失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 认领多个相邻区块
     */
    public function claimMultipleBlocks($userId, $cityId, $zone, $blockNumbers) {
        try {
            $this->pdo->beginTransaction();
            
            // 检查所有区块是否都可认领
            foreach ($blockNumbers as $blockNumber) {
                $stmt = $this->pdo->prepare("SELECT status FROM blocks WHERE city_id = ? AND zone = ? AND block_number = ?");
                $stmt->execute([$cityId, $zone, $blockNumber]);
                $block = $stmt->fetch();
                
                // 如果区块存在且状态不是available，则不可认领
                if ($block && $block['status'] !== 'available') {
                    $this->pdo->rollBack();
                    return false;
                }
            }
            
            // 认领所有区块
            foreach ($blockNumbers as $blockNumber) {
                $blockPrice = $this->calculateBlockPrice($zone, $blockNumber);
                
                $stmt = $this->pdo->prepare("SELECT id FROM blocks WHERE city_id = ? AND zone = ? AND block_number = ?");
                $stmt->execute([$cityId, $zone, $blockNumber]);
                $existingBlock = $stmt->fetch();
                
                if ($existingBlock) {
                    // 更新现有区块
                    $stmt = $this->pdo->prepare("UPDATE blocks SET owner_id = ?, status = 'sold', price = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$userId, $blockPrice, $existingBlock['id']]);
                } else {
                    // 创建新区块记录
                    $stmt = $this->pdo->prepare("INSERT INTO blocks (city_id, zone, block_number, price, owner_id, status, created_at, updated_at) 
                                                VALUES (?, ?, ?, ?, ?, 'sold', NOW(), NOW())");
                    $stmt->execute([$cityId, $zone, $blockNumber, $blockPrice, $userId]);
                }
            }
            
            // 创建合并区块记录
            $mergedBlocks = implode(',', $blockNumbers);
            $mergeSize = $this->calculateMergeSize($blockNumbers);
            
            $stmt = $this->pdo->prepare("INSERT INTO merged_blocks (city_id, zone, merged_blocks, merge_size, owner_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$cityId, $zone, $mergedBlocks, $mergeSize, $userId]);
            
            // 更新城市统计数据
            $this->updateCityStats($cityId);
            
            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("认领多区块失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据区块号计算价格
     * 使用从正确价格表提取的公式和查找表
     */
    private function calculateBlockPrice($zone, $blockNumber) {
        // 载入价格查找表
        require_once __DIR__ . '/../config/block_prices.php';
        return calculateBlockPriceNew($zone, $blockNumber);
    }

    /**
     * 更新城市统计数据
     */
    private function updateCityStats($cityId) {
        try {
            // 计算已激活区块数量
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as activated_count FROM blocks WHERE city_id = ? AND status = 'sold'");
            $stmt->execute([$cityId]);
            $activatedCount = $stmt->fetchColumn();
            
            // 计算居民数量（去重的区块拥有者）
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT owner_id) as resident_count FROM blocks WHERE city_id = ? AND status = 'sold'");
            $stmt->execute([$cityId]);
            $residentCount = $stmt->fetchColumn();
            
            // 更新城市表
            $stmt = $this->pdo->prepare("UPDATE cities SET activated_blocks = ?, resident_count = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$activatedCount, $residentCount, $cityId]);
            
        } catch (PDOException $e) {
            error_log("更新城市统计数据失败: " . $e->getMessage());
        }
    }

    /**
     * 获取城市区块数据
     */
    public function getBlocksByCityZone($cityId, $zone) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM blocks WHERE city_id = ? AND zone = ?");
            $stmt->execute([$cityId, $zone]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取城市区块数据失败: " . $e->getMessage());
            return [];
        }
    }
	
	/**
     * 根据城市和区块号获取区块信息
     */
    public function getBlockByCityAndNumber($cityId, $blockNumber) {
        $sql = "SELECT * FROM blocks WHERE city_id = ? AND block_number = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cityId, $blockNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 创建新区块
     */
    /*public function createBlock($cityId, $zone, $blockNumber, $ownerId) {
        $sql = "INSERT INTO blocks (city_id, zone, block_number, price, owner_id, status, created_at, updated_at) 
                VALUES (?, ?, ?, 0.00, ?, 'sold', NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$cityId, $zone, $blockNumber, $ownerId]);
    }*/

    /**
     * 获取用户在某城市的已认领区块
     */
    public function getUserBlocksByCity($userId, $cityId) {
        $sql = "SELECT block_number FROM blocks WHERE owner_id = ? AND city_id = ? AND status = 'sold' AND is_large_block = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $cityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取用户在某城市已认领的区块（含 id/zone/number，用于支付设置联动）
     */
    public function getUserClaimedBlocksByCityId($userId, $cityId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, zone, block_number
                FROM blocks
                WHERE owner_id = ? AND city_id = ? AND status = 'sold'
                ORDER BY zone ASC, CAST(block_number AS UNSIGNED) ASC
            ");
            $stmt->execute([$userId, $cityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取用户城市区块失败: " . $e->getMessage());
            return [];
        }
    }
	
	/**
	 * 根据区块ID判断所属区域
	 * 使用统一配置 config/zones.php
	 */
	public function determineZoneByBlockId($blockId) {
		$blockNum = intval($blockId);
		$zoneConfig = require __DIR__ . '/../config/zones.php';
		
		foreach ($zoneConfig as $zone => $cfg) {
			if ($zone === 'Z') {
				// Z区三段式范围
				foreach ($cfg['parts'] as $part) {
					if ($blockNum >= $part['start'] && $blockNum <= $part['end']) {
						return 'Z';
					}
				}
			} else {
				if ($blockNum >= $cfg['block_start'] && $blockNum <= $cfg['block_end']) {
					return $zone;
				}
			}
		}
		
		return 'Z';
	}

	/**
	 * 计算基础价格
	 */
	public function calculateBasePrice($blockId, $zone) {
		require_once __DIR__ . '/../config/block_prices.php';
		$blockNo = str_pad($blockId, 4, '0', STR_PAD_LEFT);
		return calculateBlockPriceNew($zone, $blockNo);
	}

	/**
	 * 创建新区块（更新后的方法）
	 */
	public function createBlock($cityId, $zone, $blockNumber, $ownerId, $basePrice = null) {
		// 如果没有提供价格，则计算价格
		if ($basePrice === null) {
			$basePrice = $this->calculateBasePrice($blockNumber, $zone);
		}
		
		$sql = "INSERT INTO blocks (city_id, zone, block_number, price, owner_id, status, created_at, updated_at) 
				VALUES (?, ?, ?, ?, ?, 'sold', NOW(), NOW())";
		$stmt = $this->pdo->prepare($sql);
		return $stmt->execute([$cityId, $zone, $blockNumber, $basePrice, $ownerId]);
	}
}
?>