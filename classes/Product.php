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
     * 获取店铺商品统计
     */
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
    public function getProductById($productId) {
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
                    WHERE p.id = ? AND p.status = 'active'";
            
            $stmt = $this->pdo->prepare($sql);
            //$stmt->execute([$productId]);
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
                (shop_id, category_id, name, description, main_image, images, 
                 price_type, price_bct, price_cny, stock, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['shop_id'],
                $data['category_id'],
                $data['name'],
                $data['description'],
                $data['main_image'],
                $data['images'] ?? null,
                $data['price_type'],
                $data['price_bct'],
                $data['price_cny'],
                $data['stock'],
                $data['status']
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
        $allowedFields = ['name', 'description', 'main_image', 'images', 'price_type', 
                         'price_bct', 'price_cny', 'stock', 'status', 'is_recommended', 'sort_order'];
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
     * 获取店铺商品（支持状态筛选和搜索）
     */
    public function getProductsByShopWithFilter($shopId, $filter = 'all', $search = '', $limit = 50) {
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

            $limit = intval($limit);
            $stmt = $this->pdo->prepare("
                SELECT * FROM products 
                WHERE $where 
                ORDER BY created_at DESC 
                LIMIT $limit
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺商品失败: " . $e->getMessage());
            return [];
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
}
?>