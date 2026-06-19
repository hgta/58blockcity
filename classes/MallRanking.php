<?php
/**
 * Mall 排行榜数据类
 * 
 * 提供商品和店铺的排行榜查询
 */
class MallRanking {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 商品排行榜
     * @param string $type 排行类型: popular|sales|rating|reviews|newest
     * @param int $limit 返回数量
     * @return array
     */
    public function getProductRanking($type = 'popular', $limit = 20) {
        $orderMap = [
            'popular' => 'p.view_count DESC, p.sold_count DESC',
            'sales'   => 'p.sold_count DESC, p.view_count DESC',
            'rating'  => 'p.rating DESC, p.review_count DESC',
            'reviews' => 'p.review_count DESC, p.rating DESC',
            'newest'  => 'p.created_at DESC',
        ];
        $orderBy = $orderMap[$type] ?? $orderMap['popular'];

        $sql = "SELECT p.id, p.name, p.main_image, p.thumb_image, p.price_bct, p.price_cny,
                       p.sold_count, p.view_count, p.rating, p.review_count, p.created_at,
                       p.shop_id, s.shop_name
                FROM products p
                LEFT JOIN shops s ON p.shop_id = s.id
                WHERE p.status = 'active'
                ORDER BY {$orderBy}
                LIMIT " . intval($limit);

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 店铺排行榜
     * @param string $type 排行类型: sales|rating
     * @param int $limit 返回数量
     * @return array
     */
    public function getShopRanking($type = 'sales', $limit = 20) {
        $orderMap = [
            'sales'  => 's.total_sales DESC, s.rating DESC',
            'rating' => 's.rating DESC, s.review_count DESC',
        ];
        $orderBy = $orderMap[$type] ?? $orderMap['sales'];

        $sql = "SELECT s.id, s.shop_name, s.shop_logo, s.description,
                       s.rating, s.review_count, s.total_sales, s.product_count,
                       s.created_at,
                       (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active') as active_products
                FROM shops s
                WHERE s.status = 'active'
                ORDER BY {$orderBy}
                LIMIT " . intval($limit);

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取排行榜汇总统计（用于页面头部展示）
     * @return array
     */
    public function getRankingStats() {
        $stats = [];

        $row = $this->pdo->query("SELECT COUNT(*) as total, SUM(view_count) as views, SUM(sold_count) as sales FROM products WHERE status='active'")->fetch(PDO::FETCH_ASSOC);
        $stats['total_products'] = (int)($row['total'] ?? 0);
        $stats['total_views'] = (int)($row['views'] ?? 0);
        $stats['total_sales'] = (int)($row['sales'] ?? 0);

        $row2 = $this->pdo->query("SELECT COUNT(*) as total FROM shops WHERE status='active'")->fetch(PDO::FETCH_ASSOC);
        $stats['total_shops'] = (int)($row2['total'] ?? 0);

        return $stats;
    }
}
