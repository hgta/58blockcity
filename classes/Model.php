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

    /**
     * 关注 / 取消关注（事务维护 follower_count）
     * @return string 'followed' | 'unfollowed'
     */
    public function follow($modelId, $userId)
    {
        $modelId = intval($modelId);
        $userId = intval($userId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM model_follows WHERE model_id = ? AND user_id = ?"
            );
            $stmt->execute([$modelId, $userId]);

            if ($stmt->fetch()) {
                $this->pdo->prepare(
                    "DELETE FROM model_follows WHERE model_id = ? AND user_id = ?"
                )->execute([$modelId, $userId]);
                $this->pdo->prepare(
                    "UPDATE models SET follower_count = GREATEST(follower_count - 1, 0) WHERE id = ?"
                )->execute([$modelId]);
                $action = 'unfollowed';
            } else {
                $this->pdo->prepare(
                    "INSERT INTO model_follows (model_id, user_id) VALUES (?, ?)"
                )->execute([$modelId, $userId]);
                $this->pdo->prepare(
                    "UPDATE models SET follower_count = follower_count + 1 WHERE id = ?"
                )->execute([$modelId]);
                $action = 'followed';
            }
            $this->pdo->commit();
            return $action;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 是否已关注
     */
    public function isFollowed($modelId, $userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_follows WHERE model_id = ? AND user_id = ?"
        );
        $stmt->execute([intval($modelId), intval($userId)]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * 用户关注的模特列表（用户中心「我的关注」）
     */
    public function getFollowedModels($userId, $page = 1, $perPage = 24)
    {
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT m.*, u.username, u.avatar as user_avatar
             FROM model_follows f
             LEFT JOIN models m ON f.model_id = m.id
             LEFT JOIN users u ON m.user_id = u.id
             WHERE f.user_id = ? AND m.status = 'active'
             ORDER BY f.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 发现页叠加筛选 + 排序
     * @return array ['list'=>..., 'total'=>..., 'pages'=>...]
     */
    public function getFilteredList($filters = [], $page = 1, $perPage = 24)
    {
        $where = ["m.status = 'active'"];
        $params = [];
        if (!empty($filters['gender']) && in_array($filters['gender'], ['男', '女', '保密'], true)) {
            $where[] = "m.gender = ?";
            $params[] = $filters['gender'];
        }
        if (!empty($filters['zodiac'])) {
            $where[] = "m.zodiac = ?";
            $params[] = $filters['zodiac'];
        }
        if (!empty($filters['city'])) {
            $where[] = "m.city = ?";
            $params[] = $filters['city'];
        }
        if (!empty($filters['q'])) {
            $where[] = "m.nickname LIKE ?";
            $params[] = "%" . $filters['q'] . "%";
        }

        $sortMap = [
            'follower' => 'm.follower_count DESC',
            'like'     => 'm.like_count DESC',
            'product'  => 'm.product_count DESC',
            'new'      => 'm.created_at DESC',
        ];
        $orderBy = $sortMap[$filters['sort'] ?? 'follower'] ?? $sortMap['follower'];

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM models m WHERE " . $whereSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $pages = $total > 0 ? ceil($total / $perPage) : 0;

        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT m.*, u.username, u.avatar as user_avatar
             FROM models m LEFT JOIN users u ON m.user_id = u.id
             WHERE {$whereSql}
             ORDER BY {$orderBy}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'list'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * 城市 / 星座 维度去重计数（喂筛选 chips）
     */
    public function getFacets()
    {
        $cities = $this->pdo->query(
            "SELECT city, COUNT(*) AS c FROM models
             WHERE status = 'active' AND city IS NOT NULL AND city <> ''
             GROUP BY city ORDER BY c DESC, city ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $zodiacs = $this->pdo->query(
            "SELECT zodiac, COUNT(*) AS c FROM models
             WHERE status = 'active' AND zodiac IS NOT NULL AND zodiac <> ''
             GROUP BY zodiac ORDER BY c DESC, zodiac ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['cities' => $cities, 'zodiacs' => $zodiacs];
    }

    /**
     * 详情页「相关模特」：同 城市+星座+性别 加权推荐
     */
    public function getRelated($modelId, $limit = 6)
    {
        $m = $this->getById($modelId);
        if (!$m) {
            return [];
        }
        $gender = $m['gender'] ?? '';
        $city = $m['city'] ?? '';
        $zodiac = $m['zodiac'] ?? '';

        $sql = "SELECT m.id, m.nickname, m.gender, m.city, m.zodiac, m.avatar,
                       m.follower_count, m.like_count, m.product_count,
                       u.avatar as user_avatar
                FROM models m LEFT JOIN users u ON m.user_id = u.id
                WHERE m.status = 'active' AND m.id <> ?
                  AND (m.gender = ? OR m.city = ? OR m.zodiac = ?)
                ORDER BY ((m.city = ?) + (m.zodiac = ?) + (m.gender = ?)) DESC,
                         m.follower_count DESC
                LIMIT " . intval($limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([intval($modelId), $gender, $city, $zodiac, $city, $zodiac, $gender]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 批量获取多个模特的图集缩略（避免 N+1），每张卡最多 $perModel 张
     * 来源：关联商品 main_image + images JSON；不含日常照片（避免重复）
     * @return array [model_id => [img1, img2, ...]]
     */
    public function getModelImageStrips($modelIds, $perModel = 4)
    {
        $ids = array_filter(array_map('intval', (array)$modelIds));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', $ids);
        $stmt = $this->pdo->prepare(
            "SELECT model_id, main_image, images FROM products
             WHERE model_id IN ({$placeholders}) AND status = 'active'
             ORDER BY created_at DESC"
        );
        $stmt->execute();
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mid = $row['model_id'];
            if (!isset($map[$mid])) {
                $map[$mid] = [];
            }
            if (count($map[$mid]) >= $perModel) {
                continue;
            }
            if (!empty($row['main_image'])) {
                $map[$mid][] = $row['main_image'];
            }
            if (count($map[$mid]) < $perModel && !empty($row['images'])) {
                $dec = json_decode($row['images'], true);
                if (is_array($dec)) {
                    foreach ($dec as $img) {
                        if (count($map[$mid]) >= $perModel) {
                            break;
                        }
                        $map[$mid][] = $img;
                    }
                }
            }
        }
        return $map;
    }

}
