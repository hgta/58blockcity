<?php
/**
 * 作者库 - Author 类
 * 平行模特（Model）功能：同样的表结构风格、互动逻辑、图集聚合、筛选排序。
 * 差异：无三围/体重/身高；新增 bio（简介）、style（创作风格）、author_works（原创作品图集）。
 */
class Author
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 粉丝数展示格式化：整数 → "5.4万"（1万以下原样显示）
     * 与 Model::formatFollower 逻辑一致。
     */
    public static function formatFollower($n)
    {
        $n = intval($n);
        if ($n >= 10000) {
            $s = number_format($n / 10000, 1, '.', '');
            $s = rtrim(rtrim($s, '0'), '.');
            return $s . '万';
        }
        return (string) $n;
    }

    /**
     * 根据 ID 获取作者（JOIN users 取 username/avatar）
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.username, u.avatar as user_avatar
             FROM authors a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.id = ?"
        );
        $stmt->execute([intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 根据 user_id 查找作者
     */
    public function getByUserId($userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.username, u.avatar as user_avatar
             FROM authors a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.user_id = ?"
        );
        $stmt->execute([intval($userId)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 创建作者（nickname 必填，其余可选）
     */
    public function create($data)
    {
        $fields = ['nickname'];
        $values = [trim($data['nickname'])];

        if (!empty($data['user_id'])) {
            $fields[] = 'user_id';
            $values[] = intval($data['user_id']);
        }

        $optional = ['gender', 'city', 'zodiac', 'style', 'bio', 'qq', 'weixin', 'weibo', 'xiaohongshu', 'avatar', 'author_works', 'follower_count'];
        foreach ($optional as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $values[] = $data[$f];
            }
        }

        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO authors (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * 更新作者信息
     */
    public function update($id, $data)
    {
        $sets = [];
        $values = [];
        $allowed = ['nickname', 'gender', 'city', 'zodiac', 'style', 'bio', 'qq', 'weixin', 'weibo', 'xiaohongshu', 'avatar', 'author_works', 'follower_count', 'status'];
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
        $sql = "UPDATE authors SET " . implode(', ', $sets) . " WHERE id = ?";
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
            $where = " WHERE a.nickname LIKE ? OR u.username LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm];
        }

        $countSql = "SELECT COUNT(*) FROM authors a LEFT JOIN users u ON a.user_id = u.id" . $where;
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        $sql = "SELECT a.*, u.username, u.avatar as user_avatar
                FROM authors a
                LEFT JOIN users u ON a.user_id = u.id" . $where . "
                ORDER BY a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['list' => $list, 'total' => $total, 'pages' => ceil($total / $perPage)];
    }

    /**
     * 获取全部活跃作者（商品编辑下拉用）
     */
    public function getAll($status = 'active')
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.nickname, u.username
             FROM authors a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.status = ?
             ORDER BY a.nickname ASC"
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 点赞/取消点赞（事务）
     * @return string 'liked' | 'unliked'
     */
    public function like($authorId, $userId)
    {
        $authorId = intval($authorId);
        $userId = intval($userId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM author_likes WHERE author_id = ? AND user_id = ?"
            );
            $stmt->execute([$authorId, $userId]);

            if ($stmt->fetch()) {
                $this->pdo->prepare(
                    "DELETE FROM author_likes WHERE author_id = ? AND user_id = ?"
                )->execute([$authorId, $userId]);
                $this->pdo->prepare(
                    "UPDATE authors SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?"
                )->execute([$authorId]);
                $action = 'unliked';
            } else {
                $this->pdo->prepare(
                    "INSERT INTO author_likes (author_id, user_id) VALUES (?, ?)"
                )->execute([$authorId, $userId]);
                $this->pdo->prepare(
                    "UPDATE authors SET like_count = like_count + 1 WHERE id = ?"
                )->execute([$authorId]);
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
    public function isLiked($authorId, $userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM author_likes WHERE author_id = ? AND user_id = ?"
        );
        $stmt->execute([intval($authorId), intval($userId)]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * 获取作者关联的商品列表
     */
    public function getAuthorProducts($authorId, $page = 1, $perPage = 12)
    {
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.main_image, p.price_bct, p.price_cny, p.sold_count, s.shop_name
             FROM products p
             LEFT JOIN shops s ON p.shop_id = s.id
             WHERE p.author_id = ? AND p.status = 'active'
             ORDER BY p.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([intval($authorId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取作者关联的商品总数
     */
    public function getProductCount($authorId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM products WHERE author_id = ? AND status = 'active'"
        );
        $stmt->execute([intval($authorId)]);
        return intval($stmt->fetchColumn());
    }

    /**
     * 获取作者关联商品的图片聚合（详情页图集备用）
     */
    public function getAuthorProductImages($authorId, $limit = 50)
    {
        $limitNum = intval($limit);
        $stmt = $this->pdo->prepare(
            "SELECT main_image, thumb_image, images
             FROM products
             WHERE author_id = ? AND status = 'active'
             ORDER BY created_at DESC LIMIT {$limitNum}"
        );
        $stmt->execute([intval($authorId)]);
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
     * 解析 author_works JSON 为数组
     */
    public function getAuthorWorks($authorId)
    {
        $author = $this->getById($authorId);
        if (!$author || empty($author['author_works'])) {
            return [];
        }
        $decoded = json_decode($author['author_works'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 重新计算 product_count 和 review_count
     */
    public function refreshCounts($authorId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM products WHERE author_id = ? AND status = 'active'"
        );
        $stmt->execute([intval($authorId)]);
        $productCount = intval($stmt->fetchColumn());

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM reviews r
             JOIN products p ON r.product_id = p.id
             WHERE p.author_id = ?"
        );
        $stmt->execute([intval($authorId)]);
        $reviewCount = intval($stmt->fetchColumn());

        $stmt = $this->pdo->prepare(
            "UPDATE authors SET product_count = ?, review_count = ? WHERE id = ?"
        );
        return $stmt->execute([$productCount, $reviewCount, intval($authorId)]);
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
            "SELECT a.id, a.nickname, a.gender, a.city, a.style, a.like_count, a.product_count, a.review_count, a.{$type} as sort_value,
                    u.username, u.avatar as user_avatar
             FROM authors a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.status = 'active'
             ORDER BY a.{$type} DESC
             LIMIT " . intval($limit)
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 关注 / 取消关注（事务维护 follower_count）
     * @return string 'followed' | 'unfollowed'
     */
    public function follow($authorId, $userId)
    {
        $authorId = intval($authorId);
        $userId = intval($userId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM author_follows WHERE author_id = ? AND user_id = ?"
            );
            $stmt->execute([$authorId, $userId]);

            if ($stmt->fetch()) {
                $this->pdo->prepare(
                    "DELETE FROM author_follows WHERE author_id = ? AND user_id = ?"
                )->execute([$authorId, $userId]);
                $this->pdo->prepare(
                    "UPDATE authors SET follower_count = GREATEST(follower_count - 1, 0) WHERE id = ?"
                )->execute([$authorId]);
                $action = 'unfollowed';
            } else {
                $this->pdo->prepare(
                    "INSERT INTO author_follows (author_id, user_id) VALUES (?, ?)"
                )->execute([$authorId, $userId]);
                $this->pdo->prepare(
                    "UPDATE authors SET follower_count = follower_count + 1 WHERE id = ?"
                )->execute([$authorId]);
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
    public function isFollowed($authorId, $userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM author_follows WHERE author_id = ? AND user_id = ?"
        );
        $stmt->execute([intval($authorId), intval($userId)]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * 用户关注的作者列表（用户中心「我的关注」）
     */
    public function getFollowedAuthors($userId, $page = 1, $perPage = 24)
    {
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.username, u.avatar as user_avatar
             FROM author_follows f
             LEFT JOIN authors a ON f.author_id = a.id
             LEFT JOIN users u ON a.user_id = u.id
             WHERE f.user_id = ? AND a.status = 'active'
             ORDER BY f.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 发现页叠加筛选 + 排序（性别/城市/星座/风格/搜索）
     * @return array ['list'=>..., 'total'=>..., 'pages'=>...]
     */
    public function getFilteredList($filters = [], $page = 1, $perPage = 24)
    {
        $where = ["a.status = 'active'"];
        $params = [];
        if (!empty($filters['gender']) && in_array($filters['gender'], ['男', '女', '保密'], true)) {
            $where[] = "a.gender = ?";
            $params[] = $filters['gender'];
        }
        if (!empty($filters['zodiac'])) {
            $where[] = "a.zodiac = ?";
            $params[] = $filters['zodiac'];
        }
        if (!empty($filters['city'])) {
            $where[] = "a.city = ?";
            $params[] = $filters['city'];
        }
        if (!empty($filters['style'])) {
            $where[] = "a.style = ?";
            $params[] = $filters['style'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(a.nickname LIKE ? OR a.bio LIKE ?)";
            $term = "%" . $filters['q'] . "%";
            $params[] = $term;
            $params[] = $term;
        }

        $sortMap = [
            'follower' => 'a.follower_count DESC',
            'like'     => 'a.like_count DESC',
            'product'  => 'a.product_count DESC',
            'new'      => 'a.created_at DESC',
        ];
        $orderBy = $sortMap[$filters['sort'] ?? 'follower'] ?? $sortMap['follower'];

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM authors a WHERE " . $whereSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $pages = $total > 0 ? ceil($total / $perPage) : 0;

        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.username, u.avatar as user_avatar
             FROM authors a LEFT JOIN users u ON a.user_id = u.id
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
     * 城市 / 星座 / 风格 维度去重计数（喂筛选 chips）
     */
    public function getFacets()
    {
        $cities = $this->pdo->query(
            "SELECT city, COUNT(*) AS c FROM authors
             WHERE status = 'active' AND city IS NOT NULL AND city <> ''
             GROUP BY city ORDER BY c DESC, city ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $zodiacs = $this->pdo->query(
            "SELECT zodiac, COUNT(*) AS c FROM authors
             WHERE status = 'active' AND zodiac IS NOT NULL AND zodiac <> ''
             GROUP BY zodiac ORDER BY c DESC, zodiac ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $styles = $this->pdo->query(
            "SELECT style, COUNT(*) AS c FROM authors
             WHERE status = 'active' AND style IS NOT NULL AND style <> ''
             GROUP BY style ORDER BY c DESC, style ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['cities' => $cities, 'zodiacs' => $zodiacs, 'styles' => $styles];
    }

    /**
     * 详情页「相关作者」：同 风格+城市+星座+性别 加权推荐
     */
    public function getRelated($authorId, $limit = 6)
    {
        $a = $this->getById($authorId);
        if (!$a) {
            return [];
        }
        $gender = $a['gender'] ?? '';
        $city = $a['city'] ?? '';
        $zodiac = $a['zodiac'] ?? '';
        $style = $a['style'] ?? '';

        $sql = "SELECT a.id, a.nickname, a.gender, a.city, a.zodiac, a.style, a.avatar, a.bio,
                       a.follower_count, a.like_count, a.product_count,
                       u.avatar as user_avatar
                FROM authors a LEFT JOIN users u ON a.user_id = u.id
                WHERE a.status = 'active' AND a.id <> ?
                  AND (a.gender = ? OR a.city = ? OR a.zodiac = ? OR a.style = ?)
                ORDER BY ((a.style = ?) + (a.city = ?) + (a.zodiac = ?) + (a.gender = ?)) DESC,
                         a.follower_count DESC
                LIMIT " . intval($limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([intval($authorId), $gender, $city, $zodiac, $style, $style, $city, $zodiac, $gender]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 批量获取多个作者的图集缩略（避免 N+1），每张卡最多 $perModel 张
     * 来源优先级：author_works（原创作品，权威）→ 关联商品 main_image + images JSON
     * @return array [author_id => [img1, img2, ...]]
     */
    public function getAuthorImageStrips($authorIds, $perModel = 4)
    {
        $ids = array_filter(array_map('intval', (array)$authorIds));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', $ids);

        // 1. 原创作品（author_works JSON）优先
        $map = [];
        $stmt = $this->pdo->prepare(
            "SELECT id, author_works FROM authors WHERE id IN ({$placeholders})"
        );
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dec = json_decode($row['author_works'] ?? '', true);
            if (is_array($dec)) {
                $works = [];
                foreach ($dec as $img) {
                    if (count($works) >= $perModel) break;
                    $works[] = $img;
                }
                if (!empty($works)) {
                    $map[$row['id']] = $works;
                }
            }
        }

        // 2. 关联商品图补齐
        $stmt = $this->pdo->prepare(
            "SELECT author_id, main_image, images FROM products
             WHERE author_id IN ({$placeholders}) AND status = 'active'
             ORDER BY created_at DESC"
        );
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $aid = $row['author_id'];
            if (!isset($map[$aid])) {
                $map[$aid] = [];
            }
            if (count($map[$aid]) >= $perModel) {
                continue;
            }
            if (!empty($row['main_image'])) {
                $map[$aid][] = $row['main_image'];
            }
            if (count($map[$aid]) < $perModel && !empty($row['images'])) {
                $dec = json_decode($row['images'], true);
                if (is_array($dec)) {
                    foreach ($dec as $img) {
                        if (count($map[$aid]) >= $perModel) break;
                        $map[$aid][] = $img;
                    }
                }
            }
        }
        return $map;
    }

    /**
     * 记录作者个人页访问（view_count +1，轻量累加不做去重）
     */
    public function recordView($authorId)
    {
        $this->pdo->prepare("UPDATE authors SET view_count = view_count + 1 WHERE id = ?")
                  ->execute([intval($authorId)]);
    }
}
