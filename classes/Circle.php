<?php
class Circle {
    private $pdo;

    public function __construct($pdo) {
        if (!$pdo instanceof PDO) {
            throw new InvalidArgumentException('必须提供有效的PDO数据库连接');
        }
        $this->pdo = $pdo;
    }

    public function create($userId, $name, $description, $city, $category, $blockCount = 0) {
        // 参数验证
        $this->validateParameters([
            'userId' => [filter_var($userId, FILTER_VALIDATE_INT), '用户ID必须是整数'],
            'name' => [!empty(trim($name)), '圈子名称不能为空'],
            'city' => [!empty(trim($city)), '城市不能为空'],
            'blockCount' => [filter_var($blockCount, FILTER_VALIDATE_INT), '区块数必须是整数']
        ]);

        try {
            $stmt = $this->pdo->prepare("INSERT INTO circles 
                (user_id, name, description, city, category, block_count) 
                VALUES (?, ?, ?, ?, ?, ?)");
            
            return $stmt->execute([
                (int)$userId, 
                htmlspecialchars(trim($name)), 
                htmlspecialchars(trim($description)), 
                htmlspecialchars(trim($city)), 
                htmlspecialchars(trim($category)), 
                (int)$blockCount
            ]);
        } catch (PDOException $e) {
            $this->logError($e);
            return false;
        }
    }

	
    public function getCirclesByCity($city, $limit = 10, $search = '') {
        // 参数验证
        $this->validateParameters([
            'city' => [!empty(trim($city)), '城市不能为空'],
            'limit' => [filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), '限制数必须是大于0的整数']
        ]);

        try {
            $sql = "SELECT c.*, u.username, u.avatar 
                   FROM circles c 
                   JOIN users u ON c.user_id = u.id 
                   WHERE c.city = ? AND c.status = 'active'";
            
            $params = [htmlspecialchars(trim($city))];
            
            if (!empty($search)) {
                $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
                $searchTerm = "%".htmlspecialchars(trim($search))."%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
			//vt 改造
            //$sql .= " ORDER BY c.created_at DESC LIMIT ?";
            //$params[] = (int)$limit;
			$sql .= " ORDER BY c.created_at DESC LIMIT ";
			$sql .= $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError($e);
            return [];
        }
    }
	/*
	public function getCirclesByCity($city, $limit = 10, $search = '') {
        $sql = "SELECT c.*, u.username, u.avatar 
               FROM circles c 
               JOIN users u ON c.user_id = u.id 
               WHERE c.city = ? AND c.status = 'active'";
        
        $params = [$city];
        
        if (!empty($search)) {
            $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT ";
		$sql .= $limit;
        //$params[] = (int)$limit;
        
		//echo $sql;
		//var_dump($params);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }*/

    public function getUserCircles($userId) {
        $this->validateParameters([
            'userId' => [filter_var($userId, FILTER_VALIDATE_INT), '用户ID必须是整数']
        ]);

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM circles WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([(int)$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError($e);
            return [];
        }
    }

    public function getCircleById($id) {
        $this->validateParameters([
            'id' => [filter_var($id, FILTER_VALIDATE_INT), '圈子ID必须是整数']
        ]);

        try {
            $stmt = $this->pdo->prepare("SELECT c.*, u.username, u.avatar 
                                       FROM circles c 
                                       JOIN users u ON c.user_id = u.id 
                                       WHERE c.id = ?");
            $stmt->execute([(int)$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError($e);
            return false;
        }
    }

    public function getCirclesByConditions($conditions) {
		if (!is_array($conditions)) {
			throw new InvalidArgumentException('条件必须是数组');
		}

		try {
			$where = [];
			$params = [];
			
			if (isset($conditions['user_id'])) {
				$this->validateParameters([
					'user_id' => [filter_var($conditions['user_id'], FILTER_VALIDATE_INT), '用户ID必须是整数']
				]);
				$where[] = "user_id = ?";
				$params[] = (int)$conditions['user_id'];
			}
			
			if (isset($conditions['city'])) {
				$this->validateParameters([
					'city' => [!empty(trim($conditions['city'])), '城市不能为空']
				]);
				$where[] = "city = ?";
				$params[] = htmlspecialchars(trim($conditions['city']));
			}
			
			$sql = "SELECT * FROM circles";
			if (!empty($where)) {
				$sql .= " WHERE " . implode(" AND ", $where);
			}
			$sql .= " ORDER BY created_at DESC";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->logError($e);
			return [];
		}
	}
    
    public function getUserCirclesForDisplay($userId) {
        $this->validateParameters([
            'userId' => [filter_var($userId, FILTER_VALIDATE_INT), '用户ID必须是整数']
        ]);

        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       COUNT(b.id) AS block_count
                FROM circles c
                LEFT JOIN blocks b ON c.id = b.circle_id
                WHERE c.user_id = ?
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([(int)$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError($e);
            return [];
        }
    }

    public function deleteCircle($circleId) {
        $this->validateParameters([
            'circleId' => [filter_var($circleId, FILTER_VALIDATE_INT), '圈子ID必须是整数']
        ]);

        try {
            $stmt = $this->pdo->prepare("DELETE FROM circles WHERE id = ?");
            return $stmt->execute([(int)$circleId]);
        } catch (PDOException $e) {
            $this->logError($e);
            return false;
        }
    }
    
    public function getRecentCircles($limit = 5) {
        $this->validateParameters([
            'limit' => [filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), '限制数必须是大于0的整数']
        ]);

        try {
            $stmt = $this->pdo->prepare("SELECT c.id, c.name, c.city, c.created_at, u.username 
                                        FROM circles c 
                                        JOIN users u ON c.user_id = u.id 
                                        ORDER BY c.created_at DESC LIMIT :limit");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError($e);
            return [];
        }
    }
    
    public function getCirclesWithPagination($page = 1, $perPage = 15, $search = '', $status = 'all') {
        $this->validateParameters([
            'page' => [filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), '页码必须是大于0的整数'],
            'perPage' => [filter_var($perPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), '每页数量必须是大于0的整数']
        ]);

        try {
            $offset = ($page - 1) * $perPage;
            
            $sql = "SELECT c.*, u.username 
                    FROM circles c 
                    JOIN users u ON c.user_id = u.id";
            $params = [];
            
            $where = [];
            if (!empty($search)) {
                $where[] = "(c.name LIKE ? OR c.description LIKE ? OR c.city LIKE ?)";
                $searchTerm = "%".htmlspecialchars(trim($search))."%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if ($status !== 'all') {
                $where[] = "c.status = ?";
                $params[] = htmlspecialchars(trim($status));
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
            $params = array_merge($params, [$perPage, $offset]);
            
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($params as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key + 1, $value, $paramType);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError($e);
            return [];
        }
    }

    public function getTotalCirclesCount($search = '', $status = 'all') {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM circles c";
            $params = [];
            
            $where = [];
            if (!empty($search)) {
                $where[] = "(c.name LIKE ? OR c.description LIKE ? OR c.city LIKE ?)";
                $searchTerm = "%".htmlspecialchars(trim($search))."%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if ($status !== 'all') {
                $where[] = "c.status = ?";
                $params[] = htmlspecialchars(trim($status));
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (PDOException $e) {
            $this->logError($e);
            return 0;
        }
    }

    public function updateCircleStatus($circleId, $status) {
        $this->validateParameters([
            'circleId' => [filter_var($circleId, FILTER_VALIDATE_INT), '圈子ID必须是整数'],
            'status' => [in_array($status, ['active', 'inactive']), '状态必须是active或inactive']
        ]);

        try {
            $stmt = $this->pdo->prepare("UPDATE circles SET status = ? WHERE id = ?");
            return $stmt->execute([htmlspecialchars(trim($status)), (int)$circleId]);
        } catch (PDOException $e) {
            $this->logError($e);
            return false;
        }
    }

    /**
     * 验证参数有效性
     * @param array $params 参数数组，格式为 ['参数名' => [验证结果, 错误信息], ...]
     * @throws InvalidArgumentException 当参数验证失败时抛出
     */
    private function validateParameters($params) {
        foreach ($params as $name => $validation) {
            if (!$validation[0]) {
                throw new InvalidArgumentException("参数 {$name} 无效: {$validation[1]}");
            }
        }
    }

    /**
     * 记录错误日志
     * @param PDOException $e 数据库异常
     */
    private function logError(PDOException $e) {
        // 实际项目中应该使用日志系统如Monolog
        error_log("数据库错误: " . $e->getMessage());
    }
	
	/**
	 * 获取用户圈子及正确的访问统计（使用子查询避免重复计算）
	 */
	public function getUserCirclesWithAccurateStats($userId) {
		$stmt = $this->pdo->prepare("SELECT 
					c.*,
					COALESCE(visit_stats.total_visits, 0) AS total_visits,
					COALESCE(visit_stats.completed_visits, 0) AS completed_visits,
					COALESCE(visit_stats.unique_visitors, 0) AS unique_visitors
				FROM circles c
				LEFT JOIN (
					SELECT 
						circle_id,
						COUNT(DISTINCT id) AS total_visits,
						COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) AS completed_visits,
						COUNT(DISTINCT visitor_id) AS unique_visitors
					FROM visits
					GROUP BY circle_id
				) AS visit_stats ON c.id = visit_stats.circle_id
				WHERE c.user_id = ?
				ORDER BY c.created_at DESC");
		$stmt->execute([$userId]);
		return $stmt->fetchAll();
	}

	/**
	 * 获取单个圈子的详细统计信息
	 */
	public function getCircleStats($circleId) {
		$stmt = $this->pdo->prepare("SELECT 
					COUNT(DISTINCT id) AS total_visits,
					COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) AS completed_visits,
					COUNT(DISTINCT visitor_id) AS unique_visitors
				FROM visits
				WHERE circle_id = ?");
		$stmt->execute([$circleId]);
		return $stmt->fetch();
	}

	/**
	 * 分页获取城市圈子
	 */
	public function getCirclesByCityPaginated($city, $page = 1, $perPage = 20, $search = '') {
		$offset = ($page - 1) * $perPage;
		$sql = "SELECT c.*, u.username, u.avatar 
				FROM circles c 
				JOIN users u ON c.user_id = u.id 
				WHERE c.city = ? AND c.status = 'active'";
		$params = [$city];
		if (!empty($search)) {
			$sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
			$params[] = "%$search%";
			$params[] = "%$search%";
		}
		$sql .= " ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * 获取城市圈子总数
	 */
	public function getCircleCountByCity($city, $search = '') {
		$sql = "SELECT COUNT(*) FROM circles WHERE city = ? AND status = 'active'";
		$params = [$city];
		if (!empty($search)) {
			$sql .= " AND (name LIKE ? OR description LIKE ?)";
			$params[] = "%$search%";
			$params[] = "%$search%";
		}
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return (int)$stmt->fetchColumn();
	}

	/**
	 * 获取热门城市（圈子数最多的前N个）
	 */
	public function getHotCities($limit = 20) {
		$sql = "SELECT c.city, COUNT(*) as cnt 
				FROM circles c
				LEFT JOIN cities ci ON c.city = ci.name
				WHERE c.status = 'active' 
				GROUP BY c.city 
				ORDER BY COALESCE(ci.rank, 999999) ASC, cnt DESC 
				LIMIT $limit";
		$stmt = $this->pdo->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
}
?>