<?php
// classes/Order.php
class Order {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取用户订单统计
     */
    public function getUserOrderStats($userId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders
            FROM orders 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
     
    
    /**
     * 获取店铺订单列表 - 修复 LIMIT 和 OFFSET 参数绑定问题
     */
    public function getShopOrders($shopId, $page = 1, $perPage = 10) {
        $page = max(1, intval($page));
        $perPage = max(1, intval($perPage));
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.username as buyer_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.shop_id = ? 
            ORDER BY o.created_at DESC 
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute([$shopId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取店铺订单总数
     */
    public function getShopOrderCount($shopId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE shop_id = ?
        ");
        $stmt->execute([$shopId]);
        return $stmt->fetchColumn();
    }
     
    /**
     * 获取订单商品项
     */
    public function getOrderItems($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT oi.*, p.main_image as product_image 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 创建订单
     */
    public function createOrder($data) {
        try {
            $this->pdo->beginTransaction();
            
            // 生成订单号
            $orderNo = 'O' . date('YmdHis') . mt_rand(1000, 9999);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO orders 
                (order_no, user_id, shop_id, total_amount, payment_city, payment_amount, buyer_note, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $orderNo,
                $data['user_id'],
                $data['shop_id'],
                $data['total_amount'],
                $data['payment_city'],
                $data['payment_amount'],
                $data['buyer_note'] ?? '',
                'pending'
            ]);
            
            $orderId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $orderId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * 添加订单商品项
     */
    public function addOrderItem($orderId, $item) {
        $stmt = $this->pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, product_name, product_image, quantity, unit_price, total_price) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['product_image'],
            $item['quantity'],
            $item['unit_price'],
            $item['total_price']
        ]);
    }
    
    /**
     * 更新订单状态
     */
    public function updateOrderStatus($orderId, $status, $notes = '') {
        $updates = ['status = ?'];
        $params = [$status];
        
        // 根据状态设置相应的时间戳
        switch ($status) {
            case 'paid':
                $updates[] = 'paid_at = NOW()';
                break;
            case 'shipped':
                $updates[] = 'shipped_at = NOW()';
                break;
            case 'completed':
                $updates[] = 'completed_at = NOW()';
                break;
        }
        
        if (!empty($notes)) {
            $updates[] = 'seller_note = ?';
            $params[] = $notes;
        }
        
        $params[] = $orderId;
        $sql = "UPDATE orders SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
     
    
    /**
     * 获取订单支付信息
     */
    public function getOrderPaymentInfo($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT payment_city, payment_amount, payment_block_id 
            FROM orders 
            WHERE id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 更新支付信息
     */
    public function updatePaymentInfo($orderId, $blockId) {
        $stmt = $this->pdo->prepare("
            UPDATE orders 
            SET payment_block_id = ?, status = 'paid', paid_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$blockId, $orderId]);
    }
	
		
	/**
     * 获取订单总数
     */
    public function getOrderCount() {
        try {
            $sql = "SELECT COUNT(*) as total FROM orders";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取订单总数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取今日订单数
     */
    public function getTodayOrderCount($date) {
        try {
            $sql = "SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取今日订单数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取今日收入
     */
    public function getTodayRevenue($date) {
        try {
            $sql = "SELECT SUM(total_amount) as revenue FROM orders WHERE DATE(created_at) = ? AND status IN ('paid', 'shipped', 'completed')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['revenue'] ?? 0;
        } catch (Exception $e) {
            error_log("获取今日收入失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取最近订单
     */
    public function getRecentOrders($limit = 10) {
        try {
            $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取最近订单失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取用户订单列表
     */
    public function getUserOrders($userId, $filters = []) {
        try {
            $params = [$userId];
            $whereConditions = ["o.user_id = ?"];
            
            // 状态筛选
            if (!empty($filters['status']) && $filters['status'] != 'all') {
                $whereConditions[] = "o.status = ?";
                $params[] = $filters['status'];
            }
            
            // 搜索筛选
            if (!empty($filters['search'])) {
                $whereConditions[] = "(o.order_no LIKE ? OR oi.product_name LIKE ?)";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            // 分页
            $limit = "";
            if (!empty($filters['page']) && !empty($filters['per_page'])) {
                $offset = ($filters['page'] - 1) * $filters['per_page'];
                $limit = "LIMIT {$offset}, {$filters['per_page']}";
            }
            
            $sql = "SELECT DISTINCT o.*
                    FROM orders o
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE {$whereClause}
                    ORDER BY o.created_at DESC
                    {$limit}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取用户订单失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取用户订单总数
     */
    public function getUserOrderCount($userId, $filters = []) {
        try {
            $params = [$userId];
            $whereConditions = ["o.user_id = ?"];
            
            // 状态筛选
            if (!empty($filters['status']) && $filters['status'] != 'all') {
                $whereConditions[] = "o.status = ?";
                $params[] = $filters['status'];
            }
            
            // 搜索筛选
            if (!empty($filters['search'])) {
                $whereConditions[] = "(o.order_no LIKE ? OR oi.product_name LIKE ?)";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            $sql = "SELECT COUNT(DISTINCT o.id) as total
                    FROM orders o
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE {$whereClause}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取用户订单总数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取订单详情
     */
    public function getOrderDetails($orderId) {
        try {
            $sql = "SELECT oi.*, p.main_image as image_url
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                    ORDER BY oi.id ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$orderId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取订单详情失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据ID获取订单
     */
    public function getOrderById($orderId, $userId = null) {
        try {
            $sql = "SELECT o.*, u.username, u.email, u.phone
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE o.id = ?";
            
            $params = [$orderId];
            if ($userId) {
                $sql .= " AND o.user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取订单失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 取消订单
     */
    public function cancelOrder($orderId, $userId) {
        try {
            $sql = "UPDATE orders SET status = 'cancelled', updated_at = NOW() 
                    WHERE id = ? AND user_id = ? AND status = 'pending'";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$orderId, $userId]);
        } catch (Exception $e) {
            error_log("取消订单失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 确认收货
     */
    public function confirmReceipt($orderId, $userId) {
        try {
            $sql = "UPDATE orders SET status = 'completed', updated_at = NOW() 
                    WHERE id = ? AND user_id = ? AND status = 'shipped'";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$orderId, $userId]);
        } catch (Exception $e) {
            error_log("确认收货失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 生成订单号
     */
    public function generateOrderNumber() {
        return date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * 创建订单
     */
    /* public function createOrder($userId, $data) {
        try {
            $orderNumber = $this->generateOrderNumber();
            
            $sql = "INSERT INTO orders (order_number, user_id, total_amount, shipping_address, payment_method, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            $result = $stmt->execute([
                $orderNumber,
                $userId,
                $data['total_amount'],
                $data['shipping_address'] ?? '',
                $data['payment_method'] ?? 'bct'
            ]);
            
            if ($result) {
                return $this->pdo->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log("创建订单失败: " . $e->getMessage());
            return false;
        }
    } */
    
    /**
     * 添加订单详情
     */
    public function addOrderDetail($orderId, $productData) {
        try {
            $sql = "INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, total_price, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            $unitPrice = $productData['price'] ?? 0;
            $quantity = $productData['quantity'] ?? 1;
            
            return $stmt->execute([
                $orderId,
                $productData['product_id'],
                $productData['product_name'],
                $productData['image_url'] ?? '',
                $quantity,
                $unitPrice,
                $unitPrice * $quantity
            ]);
        } catch (Exception $e) {
            error_log("添加订单详情失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新订单状态
     */
    /* public function updateOrderStatus($orderId, $status) {
        try {
            $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$status, $orderId]);
        } catch (Exception $e) {
            error_log("更新订单状态失败: " . $e->getMessage());
            return false;
        }
    } */
    
    /**
     * 获取订单统计
     */
    public function getOrderStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                        SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                        SUM(total_amount) as total_revenue
                    FROM orders";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取订单统计失败: " . $e->getMessage());
            return [
                'total_orders' => 0,
                'pending_orders' => 0,
                'paid_orders' => 0,
                'shipped_orders' => 0,
                'completed_orders' => 0,
                'cancelled_orders' => 0,
                'total_revenue' => 0
            ];
        }
    }
    
    /**
     * 获取月度订单统计
     */
    public function getMonthlyOrderStats($year = null, $month = null) {
        try {
            if (!$year) $year = date('Y');
            if (!$month) $month = date('m');
            
            $startDate = "{$year}-{$month}-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $sql = "SELECT 
                        DATE(created_at) as order_date,
                        COUNT(*) as order_count,
                        SUM(total_amount) as daily_revenue
                    FROM orders 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY order_date";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取月度订单统计失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺订单列表（支持状态筛选和搜索）
     */
    public function getShopOrdersWithFilter($shopId, $filters = []) {
        try {
            $params = [$shopId];
            $whereConditions = ["o.shop_id = ?"];

            // 状态筛选
            if (!empty($filters['status']) && $filters['status'] != 'all') {
                $whereConditions[] = "o.status = ?";
                $params[] = $filters['status'];
            }

            // 搜索筛选（订单号或买家名）
            if (!empty($filters['search'])) {
                $whereConditions[] = "(o.order_no LIKE ? OR u.username LIKE ? OR oi.product_name LIKE ?)";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(" AND ", $whereConditions);

            // 分页
            $limit = "";
            if (!empty($filters['page']) && !empty($filters['per_page'])) {
                $offset = ($filters['page'] - 1) * $filters['per_page'];
                $limit = "LIMIT {$offset}, {$filters['per_page']}";
            }

            $sql = "SELECT DISTINCT o.*, u.username as buyer_name, u.avatar as buyer_avatar, u.phone as buyer_phone
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE {$whereClause}
                    ORDER BY o.created_at DESC
                    {$limit}";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺订单失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺订单总数（支持状态筛选和搜索）
     */
    public function getShopOrderCountWithFilter($shopId, $filters = []) {
        try {
            $params = [$shopId];
            $whereConditions = ["o.shop_id = ?"];

            if (!empty($filters['status']) && $filters['status'] != 'all') {
                $whereConditions[] = "o.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $whereConditions[] = "(o.order_no LIKE ? OR u.username LIKE ? OR oi.product_name LIKE ?)";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(" AND ", $whereConditions);

            $sql = "SELECT COUNT(DISTINCT o.id) as total
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE {$whereClause}";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取店铺订单总数失败: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取店铺各状态订单数量统计
     */
    public function getShopOrderStatusStats($shopId) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                        SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                    FROM orders
                    WHERE shop_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$shopId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺订单状态统计失败: " . $e->getMessage());
            return ['total' => 0, 'pending' => 0, 'paid' => 0, 'shipped' => 0, 'completed' => 0, 'cancelled' => 0];
        }
    }
}
?>





 