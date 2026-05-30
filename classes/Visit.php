<?php
class Visit {
	// 状态常量
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
	
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /*public function requestVisit($circleId, $visitorId) {
        $stmt = $this->pdo->prepare("INSERT INTO visits (circle_id, visitor_id, status) VALUES (?, ?, 'pending')");
        return $stmt->execute([$circleId, $visitorId]);
    }
     
    
    public function recordReturn($visitId, $returnDate) {
        $stmt = $this->pdo->prepare("UPDATE visits SET return_date = ?, status = 'completed' WHERE id = ?");
        return $stmt->execute([$returnDate, $visitId]);
    }*/
	
	public function recordReturn($visitId, $returnDate, $notes = '', $screenshotPath = null) {
		$stmt = $this->pdo->prepare("UPDATE visits 
									SET return_date = ?, 
										status = 'completed',
										notes = ?,
										screenshot_path = ?,
										updated_at = NOW()
									WHERE id = ?");
		return $stmt->execute([$returnDate, $notes, $screenshotPath, $visitId]);
	}
    
    public function getUserVisits($userId) {
        $stmt = $this->pdo->prepare("SELECT v.*, c.name as circle_name, c.city, u.username as circle_owner
                                   FROM visits v
                                   JOIN circles c ON v.circle_id = c.id
                                   JOIN users u ON c.user_id = u.id
                                   WHERE v.visitor_id = ?
                                   ORDER BY v.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    // 修改后的方法：支持按用户ID和状态筛选
    public function getCircleVisits($userId, $status = null) {
        $sql = "SELECT v.*, c.name as circle_name, c.user_id as owner_id,
                u.username as circle_owner, u.avatar
                FROM visits v
                JOIN circles c ON v.circle_id = c.id
                JOIN users u ON v.visitor_id = u.id
                WHERE c.user_id = ?";
        
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND v.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY v.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function updateNotes($id, $notes) {
        $stmt = $this->pdo->prepare("UPDATE visits SET notes = ? WHERE id = ?");
        return $stmt->execute([$notes, $id]);
    }
    
    public function confirmVisit($visitId, $visitDate, $nextSuggestDate, $notes = '', $screenshotPath = null) {
		$stmt = $this->pdo->prepare("UPDATE visits 
									SET visit_date = ?, 
										next_suggest_date = ?,
										status = 'confirmed',
										notes = ?,
										screenshot_path = ?,
										updated_at = NOW()
									WHERE id = ?");
		return $stmt->execute([$visitDate, $nextSuggestDate, $notes, $screenshotPath, $visitId]);
	}

    public function getVisitById($id) {
        $stmt = $this->pdo->prepare("SELECT v.*, c.name as circle_name, c.user_id as owner_id,
                                    u.username as visitor_name, u.avatar as visitor_avatar
                                    FROM visits v
                                    JOIN circles c ON v.circle_id = c.id
                                    JOIN users u ON v.visitor_id = u.id
                                    WHERE v.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
	
	/**
     * 访问者确认回访完成
     */
    public function completeVisit($visitId) {
        $stmt = $this->pdo->prepare("UPDATE visits SET  
            status = ? 
            WHERE id = ?");
        return $stmt->execute([
            self::STATUS_COMPLETED,
            $visitId
        ]);
    }
	
	public function requestVisit($circleId, $visitorId, $applicantCircleId) {
		$stmt = $this->pdo->prepare("INSERT INTO visits 
			(circle_id, visitor_id, applicant_circle_id, status) 
			VALUES (?, ?, ?, 'pending')");
		return $stmt->execute([$circleId, $visitorId, $applicantCircleId]);
	}
	
	public function getCircleVisitsV2($circleId) {
		$stmt = $this->pdo->prepare("SELECT v.*, 
			u.username, u.avatar,
			c.name as applicant_circle_name
			FROM visits v
			JOIN users u ON v.visitor_id = u.id
			LEFT JOIN circles c ON v.applicant_circle_id = c.id
			WHERE v.circle_id = ?
			ORDER BY v.created_at DESC");
		$stmt->execute([$circleId]);
		return $stmt->fetchAll();
	}
	
	public function getVisitCircleById($id) {
        $stmt = $this->pdo->prepare("SELECT v.*, c.name as circle_name, c.user_id as owner_id,
                                    u.username as visitor_name, u.avatar as visitor_avatar
                                    FROM visits v
                                    JOIN circles c ON v.applicant_circle_id = c.id
                                    JOIN users u ON v.visitor_id = u.id
                                    WHERE v.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
	public function deleteVisitsByCircle($circleId) {
		$stmt = $this->pdo->prepare("DELETE FROM visits WHERE circle_id = ?");
		return $stmt->execute([$circleId]);
	}
	
	/**
     * 获取指定互访圈的已完成访问次数
     * @param int $circleId 互访圈ID
     * @return int 已完成访问次数
     */
    public function getCompletedVisitsCount($circleId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count 
                                    FROM visits 
                                    WHERE circle_id = ? 
                                    AND status = ?");
        $stmt->execute([$circleId, self::STATUS_COMPLETED]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
	 

	public function getRecentVisits($limit = 5) {
		$stmt = $this->pdo->prepare("SELECT v.id, v.status, v.created_at, 
									u1.username as visitor_username, 
									c.name as circle_name 
									FROM visits v 
									JOIN users u1 ON v.visitor_id = u1.id 
									JOIN circles c ON v.circle_id = c.id 
									ORDER BY v.created_at DESC LIMIT :limit");
		$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}
	
	public function getVisitsWithPagination($page = 1, $perPage = 15, $search = '', $status = 'all') {
		$offset = ($page - 1) * $perPage;
		
		$sql = "SELECT v.*, 
					   u1.username AS visitor_username, u1.avatar AS visitor_avatar,
					   c.name AS circle_name, c.city AS circle_city,
					   u2.username AS owner_username
				FROM visits v
				JOIN users u1 ON v.visitor_id = u1.id
				JOIN circles c ON v.circle_id = c.id
				JOIN users u2 ON c.user_id = u2.id";
		$params = [];
		
		$where = [];
		if (!empty($search)) {
			$where[] = "(u1.username LIKE ? OR c.name LIKE ? OR c.city LIKE ?)";
			$searchTerm = "%$search%";
			$params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
		}
		
		if ($status !== 'all') {
			$where[] = "v.status = ?";
			$params[] = $status;
		}
		
		if (!empty($where)) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}
		
		$sql .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
		$params = array_merge($params, [$perPage, $offset]);
		
		$stmt = $this->pdo->prepare($sql);
		
		// 绑定参数
		foreach ($params as $key => $value) {
			$paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$stmt->bindValue($key + 1, $value, $paramType);
		}
		
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getTotalVisitsCount($search = '', $status = 'all') {
		$sql = "SELECT COUNT(*) as count 
				FROM visits v
				JOIN users u1 ON v.visitor_id = u1.id
				JOIN circles c ON v.circle_id = c.id";
		$params = [];
		
		$where = [];
		if (!empty($search)) {
			$where[] = "(u1.username LIKE ? OR c.name LIKE ? OR c.city LIKE ?)";
			$searchTerm = "%$search%";
			$params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
		}
		
		if ($status !== 'all') {
			$where[] = "v.status = ?";
			$params[] = $status;
		}
		
		if (!empty($where)) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetch();
		return $result['count'];
	}

	public function updateVisitStatus($visitId, $status) {
		$stmt = $this->pdo->prepare("UPDATE visits SET status = ? WHERE id = ?");
		return $stmt->execute([$status, $visitId]);
	}
	
	 
	
	public function getCircleVisitsById($circleId) {
		$stmt = $this->pdo->prepare("SELECT v.*, 
			u.username, u.avatar,
			c.name as applicant_circle_name
			FROM visits v
			JOIN users u ON v.visitor_id = u.id
			LEFT JOIN circles c ON v.applicant_circle_id = c.id
			WHERE v.circle_id = ?
			ORDER BY v.created_at DESC");
		$stmt->execute([$circleId]);
		return $stmt->fetchAll();
	}
 
 public function getVisitDetailForAdmin($visitId) {
    $stmt = $this->pdo->prepare("SELECT 
                v.*,
                c.name AS circle_name,
                c.city AS circle_city,
                c.user_id AS owner_id,
                u.username AS visitor_name,
                u.avatar AS visitor_avatar
            FROM visits v
            JOIN circles c ON v.circle_id = c.id
            JOIN users u ON v.visitor_id = u.id
            WHERE v.id = ?");
    $stmt->execute([$visitId]);
    return $stmt->fetch();
}

public function adminConfirmVisit($visitId, $visitDate, $adminNotes) {
    $nextDate = date('Y-m-d', strtotime($visitDate . ' +6 months'));
    $stmt = $this->pdo->prepare("UPDATE visits 
                                SET visit_date = ?,
                                    next_suggest_date = ?,
                                    status = 'confirmed',
                                    admin_notes = ?,
                                    updated_at = NOW()
                                WHERE id = ?");
    return $stmt->execute([$visitDate, $nextDate, $adminNotes, $visitId]);
}

public function adminCompleteVisit($visitId, $returnDate, $adminNotes) {
    $stmt = $this->pdo->prepare("UPDATE visits 
                                SET return_date = ?,
                                    status = 'completed',
                                    admin_notes = ?,
                                    updated_at = NOW()
                                WHERE id = ?");
    return $stmt->execute([$returnDate, $adminNotes, $visitId]);
}

public function adminCancelVisit($visitId, $adminNotes) {
    $stmt = $this->pdo->prepare("UPDATE visits 
                                SET status = 'cancelled',
                                    admin_notes = ?,
                                    updated_at = NOW()
                                WHERE id = ?");
    return $stmt->execute([$adminNotes, $visitId]);
}
}
?>