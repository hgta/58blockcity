<?php

class City {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
     public function getAllCities() {
        $stmt = $this->pdo->query("SELECT * FROM cities ORDER BY rank ASC");
        return $stmt->fetchAll();
    } 
    
    /*public function getCityById($cityId) {
        $stmt = $this->pdo->prepare("SELECT * FROM cities WHERE id = ?");
        $stmt->execute([$cityId]);
        return $stmt->fetch();
    }*/	 
    
    public function getPopularCities($limit = 20) {
        // Cast limit to integer to ensure safety
        $limit = (int)$limit;
        $stmt = $this->pdo->prepare("SELECT * FROM cities ORDER BY rank ASC LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
	
	/**
	 * 根据城市ID获取城市名称
	 */
	public function getCityName($cityId) {
		try {
			$stmt = $this->pdo->prepare("SELECT name FROM cities WHERE id = ?");
			$stmt->execute([$cityId]);
			return $stmt->fetchColumn() ?? '未知城市';
		} catch (PDOException $e) {
			error_log("[getCityName] 查询失败: " . $e->getMessage());
			return '未知城市';
		}
	}
	
	/**
	 * 获取所有城市列表（推荐版本）
	 */
	public function searchAllCities($page = 1, $perPage = 20, $search = '') {
		try {
			$offset = ($page - 1) * $perPage;
			$sql = "SELECT * FROM cities";
			$params = [];
			
			if (!empty($search)) {
				$sql .= " WHERE name LIKE ? OR pinyin LIKE ? OR area_code LIKE ?";
				$searchTerm = "%{$search}%";
				$params = [$searchTerm, $searchTerm, $searchTerm];
			}
			
			$sql .= " ORDER BY rank ASC, created_at DESC LIMIT ? OFFSET ?";
			$params[] = $perPage;
			$params[] = $offset;
			
			$stmt = $this->pdo->prepare($sql);
			
			// 绑定参数
			foreach ($params as $key => $value) {
				$paramNumber = $key + 1;
				$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
				$stmt->bindValue($paramNumber, $value, $paramType);
			}
			
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		} catch (PDOException $e) {
			error_log("获取城市列表失败: " . $e->getMessage());
			return [];
		}
	}
    
    /**
     * 获取城市总数
     */
    public function getTotalCitiesCount($search = '') {
        try {
            $sql = "SELECT COUNT(*) FROM cities WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (name LIKE ? OR pinyin LIKE ? OR area_code LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("获取城市总数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 根据ID获取城市详情
     */
    public function getCityById($id) {
        try {
            $sql = "SELECT * FROM cities WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("获取城市详情失败: " . $e->getMessage());
            return null;
        }
    }
    
	
	
    /**
     * 添加城市
     */
    public function addCity($data) {
        try {
            $sql = "INSERT INTO cities (name, pinyin, is_hot, area_code, rank, resident_count, activated_blocks, popularity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $data['name'],
                $data['pinyin'],
                $data['is_hot'] ?? 0,
                $data['area_code'] ?? null,
                $data['rank'] ?? 0,
                $data['resident_count'] ?? 0,
                $data['activated_blocks'] ?? 0,
                $data['popularity'] ?? 0
            ]);
        } catch (PDOException $e) {
            error_log("添加城市失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新城市信息
     */
    public function updateCity($id, $data) {
        try {
            $sql = "UPDATE cities SET 
                    name = ?, pinyin = ?, is_hot = ?, area_code = ?, 
                    rank = ?, resident_count = ?, activated_blocks = ?, popularity = ? 
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $data['name'],
                $data['pinyin'],
                $data['is_hot'] ?? 0,
                $data['area_code'] ?? null,
                $data['rank'] ?? 0,
                $data['resident_count'] ?? 0,
                $data['activated_blocks'] ?? 0,
                $data['popularity'] ?? 0,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("更新城市失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除城市
     */
    public function deleteCity($id) {
        try {
            $sql = "DELETE FROM cities WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("删除城市失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取热门城市
     */
    public function getHotCities() {
        try {
            $sql = "SELECT * FROM cities WHERE is_hot = 1 ORDER BY rank ASC, popularity DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("获取热门城市失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 更新城市排名
     */
    public function updateCityRank($id, $rank) {
        try {
            $sql = "UPDATE cities SET rank = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$rank, $id]);
        } catch (PDOException $e) {
            error_log("更新城市排名失败: " . $e->getMessage());
            return false;
        }
    }
 
	 /**
     * 获取热门城市数据（兼容性版本）
     */
    public function getHotCitiesList($limit = 18) {
        try {
            $limit = (int)$limit;
            $stmt = $this->pdo->prepare("SELECT * FROM cities WHERE is_hot = 1 ORDER BY rank LIMIT ?");
            $stmt->bindParam(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取热门城市列表失败: " . $e->getMessage());
            return [];
        }
    }
	
	/**
     * 获取按字母分组的城市数据
     */
	public function getCitiesByLetter() {			
		try {
            $stmt = $this->pdo->query("SELECT * FROM cities ORDER BY pinyin, rank");
            $all_cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $cities_by_letter = [];
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z'];
            
            // 初始化所有字母组
            foreach ($letters as $letter) {
                $cities_by_letter[$letter] = [];
            }
            
            // 分组城市数据
            foreach ($all_cities as $city) {
                $first_letter = strtoupper(substr($city['pinyin'], 0, 1));
                if (in_array($first_letter, $letters)) {
                    $cities_by_letter[$first_letter][] = $city;
                }
            }
            
            // 移除空字母组
            foreach ($letters as $letter) {
                if (empty($cities_by_letter[$letter])) {
                    unset($cities_by_letter[$letter]);
                }
            }
            
            return $cities_by_letter;
            
        } catch (PDOException $e) {
            error_log("获取按字母分组的城市数据失败: " . $e->getMessage());
            return [];
        }
	}	
	
	/**
	 * 根据拼音获取城市数据
	 */
	public function getCityByPinyin($pinyin) {
		try {
			$stmt = $this->pdo->prepare("SELECT * FROM cities WHERE pinyin = ? LIMIT 1");
			$stmt->execute([$pinyin]);
			$city = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($city) {
				return $city;
			}
			
			// 如果精确匹配失败，尝试模糊匹配（处理大小写或特殊字符）
			$stmt = $this->pdo->prepare("SELECT * FROM cities WHERE LOWER(pinyin) = LOWER(?) LIMIT 1");
			$stmt->execute([$pinyin]);
			return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
			
		} catch (PDOException $e) {
			error_log("[getCityByPinyin] 查询失败: " . $e->getMessage());
			return null;
		}
	}
	
	/**
	 * 根据拼音获取城市ID
	 */
	public function getCityIdByPinyin($pinyin) {
		try {
			$stmt = $this->pdo->prepare("SELECT id FROM cities WHERE pinyin = ? LIMIT 1");
			$stmt->execute([$pinyin]);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			return $result ? $result['id'] : null;
		} catch (PDOException $e) {
			error_log("[getCityIdByPinyin] 查询失败: " . $e->getMessage());
			return null;
		}
	}
	
	/**
	 * 根据拼音检查城市是否存在
	 */
	public function cityExistsByPinyin($pinyin) {
		try {
			$stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cities WHERE pinyin = ?");
			$stmt->execute([$pinyin]);
			return $stmt->fetchColumn() > 0;
		} catch (PDOException $e) {
			error_log("[cityExistsByPinyin] 查询失败: " . $e->getMessage());
			return false;
		}
	}
}

?>