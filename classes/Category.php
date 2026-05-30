<?php
// classes/Category.php
class Category {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取所有分类
     */
    /* public function getAllCategories() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_categories 
            WHERE status = 'active' 
            ORDER BY parent_id, sort_order, name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */
	
	public function getAllCategories() {
        try {
            $sql = "SELECT c.*, COUNT(p.id) as product_count
                    FROM product_categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                    WHERE c.status = 'active'
                    GROUP BY c.id
                    ORDER BY c.sort_order ASC, c.name ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取分类列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据ID获取分类
     */
    public function getCategoryById($categoryId) {
        $stmt = $this->pdo->prepare("SELECT * FROM product_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取子分类
     */
    public function getChildCategories($parentId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_categories 
            WHERE parent_id = ? AND status = 'active' 
            ORDER BY sort_order, name
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取顶级分类
     */
    public function getTopLevelCategories() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_categories 
            WHERE parent_id = 0 AND status = 'active' 
            ORDER BY sort_order, name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
	
	 /**
     * 获取热门分类
     */
    public function getPopularCategories($limit = 8) {
        try {
            $sql = "SELECT c.*, COUNT(p.id) as product_count
                    FROM product_categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                    WHERE c.status = 'active'
                    GROUP BY c.id
                    ORDER BY product_count DESC, c.sort_order ASC
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取热门分类失败: " . $e->getMessage());
            // 如果查询失败，返回默认分类
            return $this->getDefaultCategories();
        }
    }
	
	 /**
     * 获取默认分类（当数据库没有数据时使用）
     */
    private function getDefaultCategories() {
        $defaultCategories = [
            ['id' => 1, 'name' => '数码电子', 'product_count' => 0],
            ['id' => 2, 'name' => '服装鞋帽', 'product_count' => 0],
            ['id' => 3, 'name' => '家居百货', 'product_count' => 0],
            ['id' => 4, 'name' => '美妆个护', 'product_count' => 0],
            ['id' => 5, 'name' => '食品生鲜', 'product_count' => 0],
            ['id' => 6, 'name' => '图书文具', 'product_count' => 0],
            ['id' => 7, 'name' => '运动户外', 'product_count' => 0],
            ['id' => 8, 'name' => '母婴玩具', 'product_count' => 0]
        ];
        return $defaultCategories;
    }
}
?>