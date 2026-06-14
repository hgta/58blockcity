<?php
// classes/Shop.php
class Shop {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 获取店铺今日/昨日核心数据对比
     */
    public function getShopDailyStats($shopId) {
        try {
            // 今日数据
            $todayStmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(DISTINCT user_id) as buyers
                FROM orders 
                WHERE shop_id = ? 
                    AND DATE(created_at) = CURDATE()
                    AND status != 'cancelled'
            ");
            $todayStmt->execute([$shopId]);
            $today = $todayStmt->fetch(PDO::FETCH_ASSOC);

            // 昨日数据
            $yesterdayStmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(DISTINCT user_id) as buyers
                FROM orders 
                WHERE shop_id = ? 
                    AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    AND status != 'cancelled'
            ");
            $yesterdayStmt->execute([$shopId]);
            $yesterday = $yesterdayStmt->fetch(PDO::FETCH_ASSOC);

            // 本月累计
            $monthStmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as revenue
                FROM orders 
                WHERE shop_id = ? 
                    AND YEAR(created_at) = YEAR(CURDATE())
                    AND MONTH(created_at) = MONTH(CURDATE())
                    AND status = 'completed'
            ");
            $monthStmt->execute([$shopId]);
            $month = $monthStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'today' => [
                    'orders' => (int)$today['orders'],
                    'revenue' => (float)$today['revenue'],
                    'buyers' => (int)$today['buyers'],
                ],
                'yesterday' => [
                    'orders' => (int)$yesterday['orders'],
                    'revenue' => (float)$yesterday['revenue'],
                    'buyers' => (int)$yesterday['buyers'],
                ],
                'month' => [
                    'orders' => (int)$month['orders'],
                    'revenue' => (float)$month['revenue'],
                ],
            ];
        } catch (Exception $e) {
            return [
                'today' => ['orders' => 0, 'revenue' => 0, 'buyers' => 0],
                'yesterday' => ['orders' => 0, 'revenue' => 0, 'buyers' => 0],
                'month' => ['orders' => 0, 'revenue' => 0],
            ];
        }
    }

    /**
     * 获取店铺订单状态分布
     */
    public function getShopOrderStatusDistribution($shopId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM orders 
                WHERE shop_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY status
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取店铺热销商品 TOP N
     */
    public function getShopTopProducts($shopId, $limit = 5) {
        try {
            $limit = intval($limit);
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.main_image,
                    p.price_bct,
                    p.price_cny,
                    p.stock,
                    p.status,
                    COALESCE(SUM(oi.quantity), 0) as sold_count,
                    COALESCE(SUM(oi.total_price), 0) as total_revenue
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
                WHERE p.shop_id = ?
                GROUP BY p.id
                ORDER BY sold_count DESC
                LIMIT $limit
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取店铺最近订单（带买家信息）
     */
    public function getShopRecentOrders($shopId, $limit = 8) {
        try {
            $limit = intval($limit);
            $stmt = $this->pdo->prepare("
                SELECT 
                    o.id,
                    o.order_no,
                    o.total_amount,
                    o.status,
                    o.created_at,
                    u.username as buyer_name,
                    u.avatar as buyer_avatar
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.shop_id = ?
                ORDER BY o.created_at DESC
                LIMIT $limit
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取近7天每日销售数据（用于图表）
     */
    public function getShopDailySalesChart($shopId, $days = 7) {
        try {
            $days = intval($days);
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as revenue
                FROM orders 
                WHERE shop_id = ? 
                    AND status != 'cancelled'
                    AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$shopId, $days - 1]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 补全缺失日期
            $data = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $found = false;
                foreach ($results as $r) {
                    if ($r['date'] === $date) {
                        $data[] = [
                            'date' => substr($date, 5),
                            'orders' => (int)$r['orders'],
                            'revenue' => (float)$r['revenue'],
                        ];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $data[] = ['date' => substr($date, 5), 'orders' => 0, 'revenue' => 0];
                }
            }
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * 获取用户的所有店铺
     */
    public function getUserShops($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shops 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 根据用户ID获取店铺（兼容旧代码）
     */
    public function getShopByUserId($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shops 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 创建新店铺
     */
    /*public function createShop($data) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO shops 
                (user_id, shop_name, shop_description, contact_info, status) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['shop_name'],
                $data['shop_description'],
                $data['contact_info'],
                $data['status']
            ]);
            
            $shopId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $shopId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }*/
    
    /**
     * 获取热门店铺 - 修复字段不存在的问题
     */
    public function getPopularShops($limit = 8) {
        $limit = intval($limit);
        
        // 检查表结构，动态构建查询字段
        $fields = $this->getTableFields('shops');
        $selectFields = ['s.*', 'u.username'];
        
        // 如果存在 rating 字段，就包含在查询中
        if (in_array('rating', $fields)) {
            $selectFields[] = 's.rating';
        } else {
            // 如果不存在，使用默认值
            $selectFields[] = '5.00 as rating';
        }
        
        // 如果存在 total_sales 字段，就包含在查询中
        if (in_array('total_sales', $fields)) {
            $orderBy = 's.total_sales DESC';
        } else {
            $orderBy = 's.created_at DESC';
        }
        
        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $selectFields) . " 
            FROM shops s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE s.status = 'active' 
            ORDER BY $orderBy 
            LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 根据ID获取店铺信息 - 修复字段不存在的问题
     */
    public function getShopById($shopId) {
        // 检查表结构，动态构建查询字段
        $fields = $this->getTableFields('shops');
        $selectFields = ['s.*', 'u.username', 'u.email'];
        
        // 如果存在 rating 字段，就包含在查询中
        if (in_array('rating', $fields)) {
            $selectFields[] = 's.rating';
        } else {
            $selectFields[] = '5.00 as rating';
        }
        
        // 如果存在 total_sales 字段，就包含在查询中
        if (!in_array('total_sales', $fields)) {
            $selectFields[] = '0 as total_sales';
        }
        
        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $selectFields) . " 
            FROM shops s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$shopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } 
    
	 /**
     * 根据ID获取店铺详情
     */
    /* public function getShopById($shopId) {
        try {
            $sql = "SELECT s.*, 
                           c.name as category_name,
                           u.username as owner_name,
                           (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active' ) as product_count
                    FROM shops s
                    LEFT JOIN product_categories c ON s.category_id = c.id
                    LEFT JOIN users u ON s.user_id = u.id
                    WHERE s.id = ? AND s.status = 'active' ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$shopId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺详情失败: " . $e->getMessage());
            return null;
        }
    } */
	
    /**
     * 更新店铺信息
     */
    public function updateShop($shopId, $data) {
        $allowedFields = ['shop_name', 'shop_description', 'contact_info', 'shop_logo', 'shop_banner', 'theme_color', 'announcement'];
        $setParts = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $setParts[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $params[] = $shopId;
        $sql = "UPDATE shops SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
     
    
    /**
     * 获取店铺的商品数量
     */
    public function getProductCount($shopId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM products 
            WHERE shop_id = ? AND status IN ('active', 'sold_out')
        ");
        $stmt->execute([$shopId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * 获取店铺的订单统计 - 修复字段不存在的问题
     */
    public function getOrderStats($shopId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue
                FROM orders 
                WHERE shop_id = ? AND status = 'completed'
            ");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 设置默认值
            if (!$result) {
                $result = [
                    'total_orders' => 0,
                    'total_revenue' => 0,
                    'avg_rating' => 5.0
                ];
            } else {
                $result['avg_rating'] = 5.0; // 默认评分
            }
            
            return $result;
        } catch (Exception $e) {
            // 如果查询失败，返回默认值
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'avg_rating' => 5.0
            ];
        }
    }
    
    /**
     * 更新店铺统计信息
     */
    public function updateShopStats($shopId) {
        try {
            // 检查表结构
            $fields = $this->getTableFields('shops');
            
            if (in_array('total_sales', $fields)) {
                // 更新销量
                $stmt = $this->pdo->prepare("
                    UPDATE shops s 
                    SET total_sales = (
                        SELECT COALESCE(SUM(oi.quantity), 0) 
                        FROM order_items oi 
                        JOIN orders o ON oi.order_id = o.id 
                        WHERE o.shop_id = s.id AND o.status = 'completed'
                    ) 
                    WHERE s.id = ?
                ");
                return $stmt->execute([$shopId]);
            }
            
            return true;
        } catch (Exception $e) {
            // 忽略错误
            return false;
        }
    }
    
    /**
     * 增加店铺浏览数
     
    public function incrementViewCount($shopId) {
        try {
            // 检查表结构
            $fields = $this->getTableFields('shops');
            
            if (in_array('view_count', $fields)) {
                $stmt = $this->pdo->prepare("UPDATE shops SET view_count = view_count + 1 WHERE id = ?");
                return $stmt->execute([$shopId]);
            }
            
            return true;
        } catch (Exception $e) {
            // 忽略错误
            return false;
        }
    }*/
    
    /**
     * 获取店铺的支付设置
     */
    /**
     * 获取店铺激活的支付设置（含区块详情）
     */
    public function getShopPaymentSettings($shopId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sps.*, b.zone as block_zone, b.block_number
                FROM shop_payment_settings sps
                LEFT JOIN blocks b ON CAST(sps.block_id AS UNSIGNED) = b.id
                WHERE sps.shop_id = ? AND sps.is_active = 1
                ORDER BY sps.created_at DESC
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺支付设置失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取表字段列表
     */
    private function getTableFields($tableName) {
        try {
            $stmt = $this->pdo->prepare("DESCRIBE $tableName");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $columns;
        } catch (Exception $e) {
            // 如果查询失败，返回空数组
            return [];
        }
    }
    
    /**
     * 获取所有店铺（用于后台管理）
     */
    public function getAllShops($filters = [], $limit = 20, $offset = 0) {
        $limit = intval($limit);
        $offset = intval($offset);
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(s.shop_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $whereConditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // 检查表结构，动态构建查询字段
        $fields = $this->getTableFields('shops');
        $selectFields = ['s.*', 'u.username', 'u.email'];
        
        // 如果存在 rating 字段，就包含在查询中
        if (in_array('rating', $fields)) {
            $selectFields[] = 's.rating';
        } else {
            $selectFields[] = '5.00 as rating';
        }
        
        // 如果存在 total_sales 字段，就包含在查询中
        if (!in_array('total_sales', $fields)) {
            $selectFields[] = '0 as total_sales';
        }
        
        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $selectFields) . " 
            FROM shops s 
            LEFT JOIN users u ON s.user_id = u.id 
            $whereClause 
            ORDER BY s.created_at DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取店铺总数（用于后台管理）
     */
    public function getShopsCount($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(s.shop_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $whereConditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM shops s 
            LEFT JOIN users u ON s.user_id = u.id 
            $whereClause
        ");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
	
	/**
     * 获取店铺支付设置
     */
    public function getPaymentSettings($shopId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sps.*, b.zone as block_zone, b.block_number
                FROM shop_payment_settings sps
                LEFT JOIN blocks b ON CAST(sps.block_id AS UNSIGNED) = b.id
                WHERE sps.shop_id = ?
                ORDER BY sps.created_at DESC
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺支付设置失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 更新店铺支付设置
     */
    public function updatePaymentSettings($shopId, $paymentSettings) {
        try {
            $this->pdo->beginTransaction();

            // 先删除旧的支付设置
            $deleteStmt = $this->pdo->prepare("DELETE FROM shop_payment_settings WHERE shop_id = ?");
            $deleteStmt->execute([$shopId]);

            // 插入新的支付设置
            $insertStmt = $this->pdo->prepare("
                INSERT INTO shop_payment_settings 
                (shop_id, city, block_id, is_active, min_amount, exchange_rate) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($paymentSettings as $setting) {
                $insertStmt->execute([
                    $shopId,
                    $setting['city'],
                    $setting['block_id'],
                    $setting['is_active'] ?? 1,
                    $setting['min_amount'] ?? 0.01,
                    $setting['exchange_rate'] ?? 1.0000
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    

    /**
     * 检查店铺是否有订单
     */
    public function hasOrders($shopId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ?");
            $stmt->execute([$shopId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取店铺的月销售额统计
     */
    public function getMonthlySales($shopId, $months = 6) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as order_count,
                    SUM(total_amount) as total_sales
                FROM orders 
                WHERE shop_id = ? 
                    AND status = 'completed'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT ?
            ");
            $stmt->execute([$shopId, $months, $months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
	
	/**
 * 获取支持的城市列表
 */
    /**
     * 获取支持的城市列表（从 cities 表读取）
     */
    public function getSupportedCities() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, pinyin FROM cities
                WHERE status = 'active'
                ORDER BY rank ASC, name ASC
            ");
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($cities as $city) {
                $key = $city['pinyin'] ?: strtolower($city['name']);
                $result[$key] = ['id' => (int)$city['id'], 'name' => $city['name']];
            }
            return $result;
        } catch (Exception $e) {
            // fallback 硬编码
            return [
                'beijing' => ['id' => 1, 'name' => '北京'],
                'shanghai' => ['id' => 2, 'name' => '上海'],
                'guangzhou' => ['id' => 3, 'name' => '广州'],
                'shenzhen' => ['id' => 4, 'name' => '深圳'],
                'hangzhou' => ['id' => 5, 'name' => '杭州'],
                'chengdu' => ['id' => 6, 'name' => '成都'],
                'wuhan' => ['id' => 7, 'name' => '武汉'],
                'xian' => ['id' => 8, 'name' => '西安'],
                'nanjing' => ['id' => 9, 'name' => '南京'],
                'chongqing' => ['id' => 10, 'name' => '重庆'],
                'tianjin' => ['id' => 11, 'name' => '天津'],
                'suzhou' => ['id' => 12, 'name' => '苏州'],
                'dalian' => ['id' => 13, 'name' => '大连'],
                'qingdao' => ['id' => 14, 'name' => '青岛'],
                'xiamen' => ['id' => 15, 'name' => '厦门']
            ];
        }
    }

/**
     * 检查用户是否有店铺
     */
    public function userHasShop($userId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM shops 
                    WHERE user_id = ? AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("检查用户店铺失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取店铺列表
     */
    public function getShops($filters = []) {
        try {
            $params = [];
            $whereConditions = ["s.status = 'active'"];
            
            // 搜索筛选
            if (!empty($filters['search'])) {
                $whereConditions[] = "s.shop_name LIKE ?";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
            }
            
            // 分类筛选
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "s.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            // 排序
            $orderBy = "s.created_at DESC";
            switch ($filters['sort'] ?? 'newest') {
                case 'popular':
                    $orderBy = "s.total_sales DESC, s.created_at DESC";
                    break;
                case 'product_count':
                    $orderBy = "product_count DESC";
                    break;
                case 'sales':
                    $orderBy = "s.total_sales DESC";
                    break;
                default:
                    $orderBy = "s.created_at DESC";
            }
            
            // 分页
            $limit = "";
            if (!empty($filters['page']) && !empty($filters['per_page'])) {
                $offset = ($filters['page'] - 1) * $filters['per_page'];
                $limit = "LIMIT {$offset}, {$filters['per_page']}";
            }
            
            $sql = "SELECT s.*, 
                           c.name as category_name,
                           (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active' ) as product_count
                    FROM shops s
                    LEFT JOIN product_categories c ON s.category_id = c.id
                    WHERE {$whereClause}
                    ORDER BY {$orderBy}
                    {$limit}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取店铺总数
     */
    public function getShopCount($filters = []) {
        try {
            $params = [];
            $whereConditions = ["s.status = 'active'"];
            
            // 搜索筛选
            if (!empty($filters['search'])) {
                $whereConditions[] = "s.shop_name LIKE ?";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
            }
            
            // 分类筛选
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "s.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            $sql = "SELECT COUNT(*) as total 
                    FROM shops s
                    WHERE {$whereClause}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取店铺总数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取特色店铺
     */
    public function getFeaturedShops($limit = 6) {
        try {
            $limit = intval($limit);
            $fields = $this->getTableFields('shops');
            
            // 动态构建排序：优先用total_sales，否则用created_at
            if (in_array('total_sales', $fields)) {
                $orderBy = 's.total_sales DESC';
            } else {
                $orderBy = 's.created_at DESC';
            }
            if (in_array('rating', $fields)) {
                $orderBy .= ', s.rating DESC';
            }
            
            $sql = "SELECT s.*, 
                           c.name as category_name,
                           (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active') as product_count
                    FROM shops s
                    LEFT JOIN product_categories c ON s.category_id = c.id
                    WHERE s.status = 'active'
                    ORDER BY {$orderBy}
                    LIMIT {$limit}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取特色店铺失败: " . $e->getMessage());
            return [];
        }
    }
    
   
    
    /**
     * 获取用户的店铺
     */
    public function getUserShop($userId) {
        try {
            $sql = "SELECT s.*, 
                           c.name as category_name,
                           (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active' ) as product_count
                    FROM shops s
                    LEFT JOIN product_categories c ON s.category_id = c.id
                    WHERE s.user_id = ? AND s.status = 'active'  ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取用户店铺失败: " . $e->getMessage());
            return null;
        }
    }
    
     /**
     * 创建店铺
     */
    public function createShop($userId, $data) {
        try {
            // 检查用户是否已有店铺
            if ($this->userHasShop($userId)) {
                throw new Exception("您已经拥有一个店铺了");
            }
            
            // 验证必要字段
            if (empty($data['shop_name'])) {
                throw new Exception("店铺名称不能为空");
            }
            
            if (empty($data['category_id'])) {
                throw new Exception("请选择店铺分类");
            }
            
            $sql = "INSERT INTO shops (user_id, shop_name, shop_description, category_id, shop_logo, shop_banner, contact_info, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            $result = $stmt->execute([
                $userId,
                $data['shop_name'],
                $data['description'] ?? '',
                $data['category_id'],
                $data['avatar_url'] ?? null,
                $data['cover_url'] ?? null,
                $data['contact_info'] ?? null
            ]);
            
            if ($result) {
                return $this->pdo->lastInsertId(); // 返回新创建的店铺ID
            } else {
                throw new Exception("数据库插入失败");
            }
            
        } catch (PDOException $e) {
            // 处理数据库错误
            if ($e->getCode() == 23000) { // 唯一约束违反
                throw new Exception("店铺名称已存在，请换一个名称");
            }
            throw new Exception("创建店铺失败: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("创建店铺失败: " . $e->getMessage());
        }
    }
    
    /**
     * 更新店铺信息
     
    public function updateShop($shopId, $userId, $data) {
        try {
            $allowedFields = ['shop_name', 'description', 'category_id', 'avatar_url', 'cover_url', 'contact_info'];
            $updates = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $updates[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $params[] = $shopId;
            $params[] = $userId;
            
            $sql = "UPDATE shops SET " . implode(', ', $updates) . ", updated_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("更新店铺信息失败: " . $e->getMessage());
            return false;
        }
    }*/
    
    /**
     * 增加店铺浏览量
     */
    public function incrementViewCount($shopId) {
        try {
            // shops 表无 view_count 列，使用 total_sales 作为热度近似替代
            // 如需真正的浏览量统计，可在 shops 表新增 view_count 列
            return true;
        } catch (Exception $e) {
            error_log("增加店铺浏览量失败: " . $e->getMessage());
            return false;
        }
    }
	
	/**
     * 获取店铺统计
     */
    public function getShopStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_shops,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_shops,
                        AVG(total_sales) as avg_sales,
                        SUM(product_count) as total_products
                    FROM (
                        SELECT s.*, 
                               (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active') as product_count
                        FROM shops s
                    ) as shop_stats";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺统计失败: " . $e->getMessage());
            return [
                'total_shops' => 0,
                'active_shops' => 0,
                'avg_views' => 0,
                'avg_sales' => 0,
                'total_products' => 0
            ];
        }
    }
}
?>