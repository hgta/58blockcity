<?php
/**
 * 模特库 - Model 类
 */
class Model
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 根据 ID 获取模特（JOIN users 取 username/avatar）
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, u.username, u.avatar as user_avatar 
             FROM models m 
             LEFT JOIN users u ON m.user_id = u.id 
             WHERE m.id = ?"
        );
        $stmt->execute([intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 根据 user_id 查找模特
     */
    public function getByUserId($userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, u.username, u.avatar as user_avatar 
             FROM models m 
             LEFT JOIN users u ON m.user_id = u.id 
             WHERE m.user_id = ?"
        );
        $stmt->execute([intval($userId)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 创建模特
     */
    public function create($data)
    {
        // 昵称必填
        $fields = ['nickname'];
        $values = [trim($data['nickname'])];

        // user_id 可选
        if (!empty($data['user_id'])) {
            $fields[] = 'user_id';
            $values[] = intval($data['user_id']);
        }

        $optional = ['gender', 'age', 'qq', 'weixin', 'weibo', 'xiaohongshu', 'city', 'avatar', 'height', 'weight', 'measurements', 'hobbies'];
        foreach ($optional as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $values[] = $data[$f];
            }
        }

        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO models (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * 更新模特信息
     */
    public function update($id, $data)
    {
        $sets = [];
        $values = [];
        $allowed = ['nickname', 'gender', 'age', 'qq', 'weixin', 'weibo', 'xiaohongshu', 'city', 'avatar', 'height', 'weight', 'measurements', 'hobbies', 'zodiac', 'follower_count', 'status', 'daily_photos'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = ?";
                $values[] = $data[$f];
            }
        }
        if (empty($sets)) {
            return false;
        }
        $values[] = intval($id);
        $sql = "UPDATE models SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * 软删除（status = inactive）
     */
    public function delete($id)
    {
        return $this->update($id, ['status' => 'inactive']);
    }

    /**
     * 后台分页列表
     */
    public function getList($page = 1, $perPage = 20, $search = '')
    {
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $where = '';
        $params = [];
        if (!empty($search)) {
            $where = " WHERE m.nickname LIKE ? OR u.username LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm];
        }

        $countSql = "SELECT COUNT(*) FROM models m LEFT JOIN users u ON m.user_id = u.id" . $where;
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        $sql = "SELECT m.*, u.username, u.avatar as user_avatar 
                FROM models m 
                LEFT JOIN users u ON m.user_id = u.id" . $where . " 
                ORDER BY m.id DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['list' => $list, 'total' => $total, 'pages' => ceil($total / $perPage)];
    }

    /**
     * 获取全部活跃模特（商品编辑下拉用）
     */
    public function getAll($status = 'active')
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.nickname, u.username 
             FROM models m 
             LEFT JOIN users u ON m.user_id = u.id 
             WHERE m.status = ? 
             ORDER BY m.nickname ASC"
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 点赞/取消点赞（事务）
     * @return string 'liked' | 'unliked'
     */
    public function like($modelId, $userId)
    {
        $modelId = intval($modelId);
        $userId = intval($userId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM model_likes WHERE model_id = ? AND user_id = ?"
            );
            $stmt->execute([$modelId, $userId]);

            if ($stmt->fetch()) {
                // 取消点赞
                $this->pdo->prepare(
                    "DELETE FROM model_likes WHERE model_id = ? AND user_id = ?"
                )->execute([$modelId, $userId]);
                $this->pdo->prepare(
                    "UPDATE models SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?"
                )->execute([$modelId]);
                $action = 'unliked';
            } else {
                // 点赞
                $this->pdo->prepare(
                    "INSERT INTO model_likes (model_id, user_id) VALUES (?, ?)"
                )->execute([$modelId, $userId]);
                $this->pdo->prepare(
                    "UPDATE models SET like_count = like_count + 1 WHERE id = ?"
                )->execute([$modelId]);
                $action = 'liked';
            }
            $this->pdo->commit();
            return $action;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 检查是否已点赞
     */
    public function isLiked($modelId, $userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_likes WHERE model_id = ? AND user_id = ?"
        );
        $stmt->execute([intval($modelId), intval($userId)]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * 获取模特关联的商品列表
     */
    public function getModelProducts($modelId, $page = 1, $perPage = 12)
    {
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.main_image, p.price_bct, p.price_cny, p.sold_count, s.shop_name
             FROM products p
             LEFT JOIN shops s ON p.shop_id = s.id
             WHERE p.model_id = ? AND p.status = 'active'
             ORDER BY p.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([intval($modelId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取模特关联的商品总数
     */
    public function getProductCount($modelId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM products WHERE model_id = ? AND status = 'active'"
        );
        $stmt->execute([intval($modelId)]);
        return intval($stmt->fetchColumn());
    }

    /**
     * 获取模特关联商品的图片聚合
     */
    public function getModelProductImages($modelId, $limit = 50)
    {
        $limitNum = intval($limit);
        $stmt = $this->pdo->prepare(
            "SELECT main_image, thumb_image, images 
             FROM products 
             WHERE model_id = ? AND status = 'active' 
             ORDER BY created_at DESC LIMIT {$limitNum}"
        );
        $stmt->execute([intval($modelId)]);
        $allImages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['main_image'])) {
                $allImages[] = $row['main_image'];
            }
            if (!empty($row['images'])) {
                $decoded = json_decode($row['images'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $img) {
                        $allImages[] = $img;
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($allImages)));
    }

    /**
     * 重新计算 product_count 和 review_count
     */
    public function refreshCounts($modelId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM products WHERE model_id = ? AND status = 'active'"
        );
        $stmt->execute([intval($modelId)]);
        $productCount = intval($stmt->fetchColumn());

        $reviewCount = 0;
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM reviews r 
             JOIN products p ON r.product_id = p.id 
             WHERE p.model_id = ?"
        );
        $stmt->execute([intval($modelId)]);
        $reviewCount = intval($stmt->fetchColumn());

        $stmt = $this->pdo->prepare(
            "UPDATE models SET product_count = ?, review_count = ? WHERE id = ?"
        );
        return $stmt->execute([$productCount, $reviewCount, intval($modelId)]);
    }

    /**
     * 排行榜查询
     * @param string $type product_count | like_count | review_count
     */
    public function getRanking($type = 'product_count', $limit = 20)
    {
        $allowed = ['product_count', 'like_count', 'review_count'];
        if (!in_array($type, $allowed)) {
            $type = 'product_count';
        }
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.nickname, m.gender, m.height, m.like_count, m.product_count, m.review_count, m.{$type} as sort_value, 
                    u.username, u.avatar as user_avatar
             FROM models m 
             LEFT JOIN users u ON m.user_id = u.id 
             WHERE m.status = 'active' 
             ORDER BY m.{$type} DESC 
             LIMIT " . intval($limit)
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
