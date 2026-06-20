<?php
// classes/Product.php
class Product {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取推荐商品 - 修复 LIMIT 参数绑定问题
     */
    /* public function getRecommendedProducts($limit = 12) {
        $limit = intval($limit);
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE p.status = 'active' AND p.is_recommended = 1 
            AND s.status = 'active'
            ORDER BY p.sort_order DESC, p.created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */
    
    /**
     * 获取最新商品 - 修复 LIMIT 参数绑定问题
     */
    /* public function getNewProducts($limit = 12) {
        $limit = intval($limit);
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE p.status = 'active' 
            AND s.status = 'active'
            ORDER BY p.created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */
    
    /**
     * 根据店铺ID获取商品 - 修复 LIMIT 参数绑定问题
     */
    public function getProductsByShop($shopId, $limit = 10) {
        $limit = intval($limit);
        $stmt = $this->pdo->prepare("
            SELECT * FROM products 
            WHERE shop_id = ? 
            ORDER BY created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute([$shopId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取店铺商品（支持分页和排序）
     */
    public function getProductsByShopPaged($shopId, $page = 1, $perPage = 12, $sort = 'newest') {
        try {
            $page = max(1, intval($page));
            $perPage = max(1, intval($perPage));
            $offset = ($page - 1) * $perPage;

            $orderBy = 'created_at DESC';
            switch ($sort) {
                case 'price_asc':
                    $orderBy = 'price_bct ASC, price_cny ASC, created_at DESC';
                    break;
                case 'price_desc':
                    $orderBy = 'price_bct DESC, price_cny DESC, created_at DESC';
                    break;
                case 'sales':
                    $orderBy = 'sold_count DESC, created_at DESC';
                    break;
                case 'newest':
                default:
                    $orderBy = 'created_at DESC';
                    break;
            }

            $stmt = $this->pdo->prepare("
                SELECT * FROM products
                WHERE shop_id = ?
                ORDER BY $orderBy
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺商品分页失败: " . $e->getMessage());
            return [];
        }
    }

    public function getShopProductStats($shopId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
                SUM(CASE WHEN status = 'sold_out' THEN 1 ELSE 0 END) as sold_out_products,
                SUM(sold_count) as total_sales
            FROM products 
            WHERE shop_id = ?
        ");
        $stmt->execute([$shopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取最近商品（用于推荐）- 修复 LIMIT 参数绑定问题
     */
    public function getRecentProducts($limit = 6) {
        $limit = intval($limit);
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE p.status = 'active' AND s.status = 'active'
            ORDER BY p.created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 根据分类获取商品 - 修复 LIMIT 参数绑定问题
     */
    public function getProductsByCategory($categoryId, $limit = 12) {
        $limit = intval($limit);
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE p.category_id = ? AND p.status = 'active' 
            AND s.status = 'active'
            ORDER BY p.sort_order DESC, p.created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 搜索商品 - 修复 LIMIT 参数绑定问题
     */
    public function searchProducts($keyword, $limit = 12) {
        $limit = intval($limit);
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE (p.name LIKE ? OR p.description LIKE ?) 
            AND p.status = 'active' 
            AND s.status = 'active'
            ORDER BY p.sort_order DESC, p.created_at DESC 
            LIMIT $limit
        ");
        $searchTerm = "%$keyword%";
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 分页获取商品
     */
    public function getProductsPaginated($page = 1, $perPage = 12, $filters = []) {
        $page = max(1, intval($page));
        $perPage = max(1, intval($perPage));
        $offset = ($page - 1) * $perPage;
        
        $where = "p.status = 'active' AND s.status = 'active'";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $where .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['shop_id'])) {
            $where .= " AND p.shop_id = ?";
            $params[] = $filters['shop_id'];
        }
        
        if (!empty($filters['is_recommended'])) {
            $where .= " AND p.is_recommended = 1";
        }
        
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE $where 
            ORDER BY p.sort_order DESC, p.created_at DESC 
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取商品总数
     */
    public function getProductsCount($filters = []) {
        $where = "p.status = 'active' AND s.status = 'active'";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $where .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['shop_id'])) {
            $where .= " AND p.shop_id = ?";
            $params[] = $filters['shop_id'];
        }
        
        if (!empty($filters['is_recommended'])) {
            $where .= " AND p.is_recommended = 1";
        }
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE $where
        ");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * 根据ID获取商品详情
     */
  
	/* function getProductById($productId) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.shop_name, s.shop_logo, s.contact_info, s.rating as shop_rating
            FROM products p 
            LEFT JOIN shops s ON p.shop_id = s.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }  */
	
	/**
     * 根据ID获取商品详情 - 适配实际表结构
     */
    public function getProductById($productId, $includeInactive = false) {
        try {
            $sql = "SELECT p.*, 
                           p.main_image as image_url,
                           p.sold_count as sales_count,
                           p.price_bct as price,
                           c.name as category_name,
                           s.shop_name, s.user_id as shop_owner_id, s.id as shop_id
                    FROM products p
                    LEFT JOIN product_categories c ON p.category_id = c.id
                    LEFT JOIN shops s ON p.shop_id = s.id
                    WHERE p.id = ?";
            if (!$includeInactive) {
                $sql .= " AND p.status = 'active'";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(1, $productId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取商品详情失败: " . $e->getMessage());
            return null;
        }
    } 
    
    /**
     * 创建商品
     */
    public function createProduct($data) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO products
                (shop_id, category_id, name, description, main_image, thumb_image, images, video_url,
                 price_type, price_bct, price_cny, stock, status, is_recommended)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['shop_id'],
                $data['category_id'],
                $data['name'],
                $data['description'],
                $data['main_image'],
                $data['thumb_image'] ?? null,
                $data['images'] ?? null,
                $data['video_url'] ?? null,
                $data['price_type'],
                $data['price_bct'],
                $data['price_cny'],
                $data['stock'],
                $data['status'],
                $data['is_recommended'] ?? 0
            ]);
            
            $productId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $productId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * 更新商品信息
     */
    public function updateProduct($productId, $data) {
        $allowedFields = ['name', 'description', 'main_image', 'thumb_image', 'images', 'video_url', 'price_type', 
                         'price_bct', 'price_cny', 'stock', 'status', 'is_recommended', 'sort_order', 'shop_id'];
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
        
        $params[] = $productId;
        $sql = "UPDATE products SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * 增加商品浏览数
     */
    public function incrementViewCount($productId) {
        $stmt = $this->pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
        return $stmt->execute([$productId]);
    }
    
    /**
     * 更新商品库存
     */
    public function updateStock($productId, $quantity) {
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET stock = stock - ?, sold_count = sold_count + ?, updated_at = NOW() 
            WHERE id = ? AND stock >= ?
        ");
        return $stmt->execute([$quantity, $quantity, $productId, $quantity]);
    }
	
	/**
     * 获取商品列表 - 适配实际表结构
     */
    public function getProducts($filters = []) {
        try {
            $params = [];
            $whereConditions = ["p.status = 'active'"]; // 使用正确的状态值
            
            // 分类筛选
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "p.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            // 搜索筛选
            if (!empty($filters['search'])) {
                $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // 价格范围筛选 - 使用 price_bct 字段
            if (!empty($filters['min_price'])) {
                $whereConditions[] = "p.price_bct >= ?";
                $params[] = $filters['min_price'];
            }
            
            if (!empty($filters['max_price'])) {
                $whereConditions[] = "p.price_bct <= ?";
                $params[] = $filters['max_price'];
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            // 排序
            $orderBy = "p.created_at DESC";
            switch ($filters['sort'] ?? 'newest') {
                case 'price_asc':
                    $orderBy = "p.price_bct ASC";
                    break;
                case 'price_desc':
                    $orderBy = "p.price_bct DESC";
                    break;
                case 'popular':
                    $orderBy = "p.sold_count DESC, p.view_count DESC"; // 使用 sold_count
                    break;
                default:
                    $orderBy = "p.created_at DESC";
            }
            
            // 分页
            $limit = "";
            if (!empty($filters['page']) && !empty($filters['per_page'])) {
                $offset = ($filters['page'] - 1) * $filters['per_page'];
                $limit = "LIMIT {$offset}, {$filters['per_page']}";
            }
            
            // 使用正确的字段名
            $sql = "SELECT p.*, 
                           p.main_image as image_url,  -- 映射字段名
                           p.thumb_image,
                           p.sold_count as sales_count, -- 映射字段名
                           p.price_bct as price,        -- 使用人气值价格
                           c.name as category_name,
                           s.shop_name, s.user_id as shop_owner_id
                    FROM products p
                    LEFT JOIN product_categories c ON p.category_id = c.id
                    LEFT JOIN shops s ON p.shop_id = s.id
                    WHERE {$whereClause}
                    ORDER BY {$orderBy}
                    {$limit}";
            
            error_log("执行SQL: " . $sql);
            error_log("参数: " . implode(', ', $params));
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("查询结果数量: " . count($results));
            
            return $results;
        } catch (Exception $e) {
            error_log("获取商品列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取商品总数
     */
    public function getProductCount($filters = []) {
        try {
            $params = [];
            $whereConditions = ["p.status = 'active'"];
            
            // 分类筛选
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "p.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            // 搜索筛选
            if (!empty($filters['search'])) {
                $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
                $searchTerm = "%" . $filters['search'] . "%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // 价格范围筛选
            if (!empty($filters['min_price'])) {
                $whereConditions[] = "p.price_bct >= ?";
                $params[] = $filters['min_price'];
            }
            
            if (!empty($filters['max_price'])) {
                $whereConditions[] = "p.price_bct <= ?";
                $params[] = $filters['max_price'];
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            $sql = "SELECT COUNT(*) as total 
                    FROM products p
                    WHERE {$whereClause}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("获取商品总数失败: " . $e->getMessage());
            return 0;
        }
    }
	
	/**
     * 获取推荐商品 - 适配实际表结构
     */
    public function getRecommendedProducts($limit = 8) {
        try {
            $sql = "SELECT p.*, 
                           p.main_image as image_url,
                           p.thumb_image,
                           p.sold_count as sales_count,
                           p.price_bct as price,
                           s.shop_name, c.name as category_name
                    FROM products p
                    LEFT JOIN shops s ON p.shop_id = s.id
                    LEFT JOIN product_categories c ON p.category_id = c.id
                    WHERE p.status = 'active'
                    AND p.stock > 0
                    AND p.is_recommended = 1  -- 使用推荐字段
                    ORDER BY p.sort_order ASC, p.sold_count DESC
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            //$stmt->execute([$limit]);
			$stmt->bindValue(1, $limit, PDO::PARAM_INT);
			$stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取推荐商品失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取新品
     */
    public function getNewProducts($limit = 8) {
        try {
            $sql = "SELECT p.*, 
                           p.main_image as image_url,
                           p.thumb_image,
                           p.price_bct as price,
                           s.shop_name, c.name as category_name
                    FROM products p
                    LEFT JOIN shops s ON p.shop_id = s.id
                    LEFT JOIN product_categories c ON p.category_id = c.id
                    WHERE p.status = 'active'  
                    AND p.stock > 0
                    ORDER BY p.created_at DESC
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            
			//$stmt->execute([$limit]);
			$stmt->bindValue(1, $limit, PDO::PARAM_INT);
			$stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取新品失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取热门商品
     */
    public function getPopularProducts($limit = 8) {
        try {
            $sql = "SELECT p.*, s.shop_name, c.name as category_name
                    FROM products p
                    LEFT JOIN shops s ON p.shop_id = s.id
                    LEFT JOIN product_categories c ON p.category_id = c.id
                    WHERE p.status = 'active'  
                    AND p.stock > 0
                    ORDER BY p.view_count DESC, p.sold_count DESC
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取热门商品失败: " . $e->getMessage());
            return [];
        }
    }
	
	/**
     * 获取商品统计
     */
    public function getProductStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_products,
                        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                        AVG(price_bct) as avg_price,
                        SUM(sold_count) as total_sales,
                        SUM(view_count) as total_views
                    FROM products";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取商品统计失败: " . $e->getMessage());
            return [
                'total_products' => 0,
                'active_products' => 0,
                'inactive_products' => 0,
                'out_of_stock' => 0,
                'avg_price' => 0,
                'total_sales' => 0,
                'total_views' => 0
            ];
        }
    }
	
	/**
     * 获取相关商品
     */
    public function getRelatedProducts($categoryId, $excludeProductId, $limit = 4) {
        try {
            $sql = "SELECT p.*, 
                           p.main_image as image_url,
                           p.sold_count as sales_count,
                           p.price_bct as price,
                           s.shop_name
                    FROM products p
                    LEFT JOIN shops s ON p.shop_id = s.id
                    WHERE p.category_id = ? 
                    AND p.id != ? 
                    AND p.status = 'active'
                    AND p.stock > 0
                    ORDER BY p.sold_count DESC, p.view_count DESC
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$categoryId, $excludeProductId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取相关商品失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺商品（支持状态筛选、搜索和分页）
     */
    public function getProductsByShopWithFilter($shopId, $filter = 'all', $search = '', $page = 1, $perPage = 20) {
        try {
            $where = "shop_id = ?";
            $params = [$shopId];

            if ($filter === 'active') {
                $where .= " AND status = 'active'";
            } elseif ($filter === 'inactive') {
                $where .= " AND status = 'inactive'";
            } elseif ($filter === 'draft') {
                $where .= " AND status = 'draft'";
            } elseif ($filter === 'sold_out') {
                $where .= " AND status = 'sold_out'";
            }

            if (!empty($search)) {
                $where .= " AND name LIKE ?";
                $params[] = "%$search%";
            }

            $perPage = max(1, intval($perPage));
            $page = max(1, intval($page));
            $offset = ($page - 1) * $perPage;

            $stmt = $this->pdo->prepare("
                SELECT * FROM products 
                WHERE $where 
                ORDER BY created_at DESC 
                LIMIT $offset, $perPage
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺商品失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺商品总数（支持状态筛选和搜索）
     */
    public function getProductsCountByShopWithFilter($shopId, $filter = 'all', $search = '') {
        try {
            $where = "shop_id = ?";
            $params = [$shopId];
            $this->buildWhereFilter($where, $params, $filter, $search);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("获取店铺商品总数失败: " . $e->getMessage());
            return 0;
        }
    }

    private function buildWhereFilter(&$where, &$params, $filter, $search) {
        $filterMap = ['active' => 'active', 'inactive' => 'inactive', 'draft' => 'draft', 'sold_out' => 'sold_out'];
        if (isset($filterMap[$filter])) {
            $where .= " AND status = '{$filterMap[$filter]}'";
        }
        if (!empty($search)) {
            $where .= " AND name LIKE ?";
            $params[] = "%$search%";
        }
    }

    /**
     * 批量更新商品状态
     */
    public function batchUpdateStatus($productIds, $shopId, $status) {
        try {
            if (empty($productIds)) return false;
            // 验证状态值
            $allowed = ['active', 'inactive', 'draft', 'sold_out'];
            if (!in_array($status, $allowed)) return false;

            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $params = array_merge($productIds, [$shopId]);

            $stmt = $this->pdo->prepare("
                UPDATE products 
                SET status = ?, updated_at = NOW() 
                WHERE id IN ($placeholders) AND shop_id = ?
            ");
            array_unshift($params, $status);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("批量更新商品状态失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量删除商品（软删除：设为 inactive）
     */
    public function batchDeleteProducts($productIds, $shopId) {
        return $this->batchUpdateStatus($productIds, $shopId, 'inactive');
    }

    /**
     * 获取商品支持的支付城市
     */
    public function getProductPaymentCities($productId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT city, price_adjust 
                FROM product_payment_cities 
                WHERE product_id = ? AND is_active = 1 
                ORDER BY city ASC
            ");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取商品支付城市失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 彻底删除商品（含文件清理）
     * 如果商品存在未完成订单，则拒绝删除
     */
    public function deleteProduct($productId, $shopId) {
        try {
            $this->pdo->beginTransaction();

            // 验证商品属于该店铺
            $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND shop_id = ?");
            $stmt->execute([$productId, $shopId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => '商品不存在或无权操作'];
            }

            // 检查是否有未完成订单（pending, paid, shipped）
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = ? AND o.status IN ('pending', 'paid', 'shipped')
            ");
            $stmt->execute([$productId]);
            $pendingCount = $stmt->fetchColumn();
            if ($pendingCount > 0) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => '该商品存在未完成订单，无法删除'];
            }

            // 收集需要删除的文件路径
            $filesToDelete = [];
            if (!empty($product['main_image'])) {
                $filesToDelete[] = $product['main_image'];
            }
            if (!empty($product['images'])) {
                $extraImages = json_decode($product['images'], true);
                if (is_array($extraImages)) {
                    foreach ($extraImages as $img) {
                        if (!empty($img)) $filesToDelete[] = $img;
                    }
                }
            }
            if (!empty($product['video_url']) && strpos($product['video_url'], '://') === false) {
                $filesToDelete[] = $product['video_url'];
            }

            // 删除商品记录
            $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ? AND shop_id = ?");
            $stmt->execute([$productId, $shopId]);

            $this->pdo->commit();

            // 删除物理文件（在事务外执行，避免文件系统错误影响数据库）
            $baseDir = dirname(__DIR__); // 项目根目录
            foreach ($filesToDelete as $filePath) {
                $fullPath = $baseDir . '/' . ltrim($filePath, '/');
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("删除商品失败: " . $e->getMessage());
            return ['success' => false, 'error' => '删除失败：' . $e->getMessage()];
        }
    }
}
?>