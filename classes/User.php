<?php
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function register($username, $email, $password, $city) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, city) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$username, $email, $hashedPassword, $city]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    throw new Exception('该邮箱已被注册，请使用其他邮箱或直接登录');
                } elseif (strpos($e->getMessage(), 'username') !== false) {
                    throw new Exception('该用户名已被使用，请更换用户名');
                }
            }
            throw $e;
        }
    }
    
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // 使用统一的 handleLogin() 入口（session_regenerate + remember token）
            handleLogin($user['id'], $user['username'], $user['email'] ?? '', $user['role'] ?? 'user', true);
            return true;
        }
        return false;
    }
    
    /* public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } */
    

	public function getRecentUsers($limit = 5) {
		$stmt = $this->pdo->prepare("SELECT id, username, city, created_at FROM users ORDER BY created_at DESC LIMIT :limit");
		$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}
	
	public function getUsersWithPagination($page = 1, $perPage = 15, $search = '') {
		$offset = ($page - 1) * $perPage;
		
		$sql = "SELECT * FROM users";
		$params = [];
		
		if (!empty($search)) {
			$sql .= " WHERE username LIKE ? OR email LIKE ? OR city LIKE ?";
			$searchTerm = "%$search%";
			$params = [$searchTerm, $searchTerm, $searchTerm];
		}
		
		$sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
		
		$stmt = $this->pdo->prepare($sql);
		
		// 绑定命名参数
		foreach ($params as $key => $value) {
			$stmt->bindValue($key + 1, $value);
		}
		$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
		$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
		
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getTotalUsersCount($search = '') {
		$sql = "SELECT COUNT(*) as count FROM users";
		$params = [];
		
		if (!empty($search)) {
			$sql .= " WHERE username LIKE ? OR email LIKE ? OR city LIKE ?";
			$searchTerm = "%$search%";
			$params = [$searchTerm, $searchTerm, $searchTerm];
		}
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetch();
		return $result['count'];
	}

	public function updateUserStatus($userId, $status) {
		$stmt = $this->pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
		return $stmt->execute([$status, $userId]);
	}

	public function deleteUser($userId) {
		$stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
		return $stmt->execute([$userId]);
	}
	
	public function updateUser($data) {
		$sql = "UPDATE users SET 
				username = ?, 
				email = ?, 
				phone = ?, 
				city = ?, 
				avatar = ? 
				". (isset($data['password']) ? ", password = ?" : "") ."
				WHERE id = ?";
		
		$params = [
			$data['username'],
			$data['email'],
			$data['phone'],
			$data['city'],
			$data['avatar']
		];
		
		if (isset($data['password'])) {
			$params[] = $data['password'];
		}
		
		$params[] = $data['id'];
		
		$stmt = $this->pdo->prepare($sql);
		return $stmt->execute($params);
	}										
	
	public function updateAvatar($userId, $avatarPath) {
		$stmt = $this->pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
		return $stmt->execute([$avatarPath, $userId]);
	}
	
	public function searchUsers($query) {
		$sql = "SELECT id, username, city, created_at 
				FROM users 
				WHERE username LIKE ? OR id = ?
				LIMIT 10";
		
		$searchTerm = '%' . $query . '%';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$searchTerm, $query]);
		
		$users = $stmt->fetchAll();
		
		// 格式化日期
		foreach ($users as &$user) {
			$user['created_at'] = date('Y-m-d', strtotime($user['created_at']));
		}
		
		return $users;
	}
	
	public function updateUserPassword($userId, $hashedPassword) {
		$stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
		return $stmt->execute([$hashedPassword, $userId]);
	}
	
	public function getUserCityPopularity($userId, $cityName) {
		$sql = "SELECT popularity FROM user_city_popularity 
				WHERE user_id = ? AND city = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$userId, $cityName]);
		return $stmt->fetchColumn() ?? 0;
	}
	
	public function updateUserById($userId, $data) {
		$allowedFields = ['username', 'email', 'password', 'role', 'status', 'city'];
		$updates = [];
		$params = [':id' => $userId];
		
		foreach ($data as $key => $value) {
			if (in_array($key, $allowedFields)) {
				$updates[] = "$key = :$key";
				$params[":$key"] = $value;
			}
		}
		
		if (empty($updates)) {
			return false;
		}
		
		$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
		$stmt = $this->pdo->prepare($sql);
		
		return $stmt->execute($params);
	}
	
	public function getUserByUsername($username) {
		try {
			$stmt = $this->pdo->prepare("
				SELECT id, username, email, password, role, status, created_at, last_login 
				FROM users 
				WHERE username = :username AND status = 'active'
			");
			$stmt->execute([':username' => $username]);
			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log("获取用户信息错误: " . $e->getMessage());
			return false;
		}
	}

	public function getUserById($userId) {
		try {
			$stmt = $this->pdo->prepare("
				SELECT id, username, email, role, status, city, created_at, last_login , avatar
				FROM users 
				WHERE id = :id
			");
			$stmt->execute([':id' => $userId]);
			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log("获取用户信息错误: " . $e->getMessage());
			return false;
		}
	}

	public function updateLastLogin($userId) {
		try {
			$stmt = $this->pdo->prepare("
				UPDATE users 
				SET last_login = NOW() 
				WHERE id = :id
			");
			return $stmt->execute([':id' => $userId]);
		} catch (PDOException $e) {
			error_log("更新最后登录时间错误: " . $e->getMessage());
			return false;
		}
	}
	
	/**
     * 获取用户总数
     */
    public function getUserCount() {
        try {
            $sql = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取用户总数失败: " . $e->getMessage());
            return 0;
        }
    }
}
?>