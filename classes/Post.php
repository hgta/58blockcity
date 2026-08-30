<?php
/**
 * 58区块社区 — 帖子/心情业务类
 *
 * 单表 posts + type 区分「帖子(post)」和「一句话心情(moment)」，
 * 评论(post_comments)、点赞(post_likes)共用。
 */

require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/SeoHelper.php';

class Post {
    private $pdo;
    private $notify;
    private $hasSticky = false;   // is_sticky 列是否存在（migration 幂等）
    private $hasView   = false;   // view_count 列是否存在

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notify = new Notification($pdo);
        $this->hasSticky = $this->columnExists('posts', 'is_sticky');
        $this->hasView   = $this->columnExists('posts', 'view_count');
    }

    private function columnExists($table, $col)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $col]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /* ========== 发布 ========== */

    /**
     * 发布帖子/心情
     * @param string $type    post|moment
     * @param array  $data    city, title, content, images(array), topic
     * @return int|string 成功返回帖子 id，失败返回错误信息
     */
    public function create($userId, $type, $data) {
        $userId = intval($userId);
        if ($userId <= 0) return '请先登录';

        if (!in_array($type, ['post', 'moment'], true)) return '内容类型无效';

        $title   = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $city    = trim($data['city'] ?? '');
        $topic   = in_array($data['topic'] ?? '', ['block', 'nft', 'bct'], true) ? $data['topic'] : null;
        $images  = $data['images'] ?? [];

        if ($content === '') return '内容不能为空';
        if ($type === 'post' && $title === '') return '请填写标题';

        // 图片数量校验：心情限 1 图
        $images = is_array($images) ? array_values(array_filter($images)) : [];
        if ($type === 'moment' && count($images) > 1) return '心情最多只能配 1 张图片';
        $imagesJson = !empty($images) ? json_encode($images, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (user_id, city, type, title, content, images, topic, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([
            $userId, $city, $type,
            $type === 'post' ? $title : null,
            $content, $imagesJson, $topic,
        ]);
        if (!$ok) return '发布失败';
        $postId = intval($this->pdo->lastInsertId());

        // 发布成功后推送百度（仅当 config 开启 auto_push）
        if (class_exists('SeoHelper')) {
            SeoHelper::pushContentUrl(SeoHelper::postUrl($postId, $title !== '' ? $title : $content));
        }
        return $postId;
    }

    /* ========== 查询 ========== */

    /**
     * 信息流（城市/话题/类型筛选 + 分页）
     * @param string $sort new|hot（hot：热度加权，置顶帖始终优先）
     */
    public function getFeed($page = 1, $perPage = 20, $city = '', $topic = '', $type = '', $sort = 'new') {
        $offset = (max(1, intval($page)) - 1) * intval($perPage);
        $where = ["p.status = 'active'"];
        $params = [];

        if ($city !== '') { $where[] = "p.city = ?"; $params[] = $city; }
        if (in_array($topic, ['block', 'nft', 'bct'], true)) { $where[] = "p.topic = ?"; $params[] = $topic; }
        if (in_array($type, ['post', 'moment'], true)) { $where[] = "p.type = ?"; $params[] = $type; }
        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts p WHERE " . $whereSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        // 排序：热帖按 赞+评论*2+浏览*0.1 加权；置顶帖始终排最前（列不存在时降级）
        if ($sort === 'hot') {
            $heat = $this->hasView
                ? "(p.like_count + p.comment_count * 2 + p.view_count * 0.1)"
                : "(p.like_count + p.comment_count * 2)";
            $orderBy = ($this->hasSticky ? "p.is_sticky DESC, " : "") . $heat . " DESC, p.created_at DESC";
        } else {
            $orderBy = ($this->hasSticky ? "p.is_sticky DESC, " : "") . "p.created_at DESC";
        }

        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return [
            'list'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $total > 0 ? ceil($total / $perPage) : 0,
        ];
    }

    /**
     * 帖子详情（含作者信息）
     */
    public function getPostById($id) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = ?");
        $stmt->execute([intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 评论列表（含回复，二级结构由前端按 parent_id 组织）
     * @param int $limit 每页条数
     * @param int $offset 分页偏移
     * @return array ['list'=>..., 'total'=>int, 'pages'=>int]
     */
    public function getComments($postId, $limit = 50, $offset = 0) {
        $limit = max(1, intval($limit));
        $offset = max(0, intval($offset));

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM post_comments WHERE post_id = ?");
        $countStmt->execute([intval($postId)]);
        $total = intval($countStmt->fetchColumn());

        $stmt = $this->pdo->prepare("
            SELECT c.*, u.username, u.avatar
            FROM post_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute([intval($postId)]);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'list'  => $list,
            'total' => $total,
            'pages' => $total > 0 ? ceil($total / $limit) : 0,
        ];
    }

    /**
     * 获取全部评论（兼容旧调用，返回列表数组）
     */
    public function getAllComments($postId, $limit = 200) {
        return $this->getComments($postId, $limit, 0)['list'];
    }

    /**
     * 用户发布的帖子/心情
     */
    public function getUserPosts($userId, $page = 1, $perPage = 20) {
        $offset = (max(1, intval($page)) - 1) * intval($perPage);
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute([intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ========== 评论/回复 ========== */

    /**
     * 发表评论/回复
     */
    public function addComment($postId, $userId, $content, $parentId = 0) {
        $postId = intval($postId);
        $userId = intval($userId);
        $parentId = intval($parentId);
        $content = trim($content);

        if ($userId <= 0) return ['ok' => false, 'msg' => '请先登录'];
        if ($content === '') return ['ok' => false, 'msg' => '评论内容不能为空'];

        $post = $this->getPostById($postId);
        if (!$post) return ['ok' => false, 'msg' => '帖子不存在'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO post_comments (post_id, user_id, parent_id, content, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$postId, $userId, $parentId, $content]);

            // 更新评论数
            $stmt = $this->pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?");
            $stmt->execute([$postId]);

            // 通知帖子作者（自己评论自己的帖子不通知）
            if (intval($post['user_id']) !== $userId) {
                $this->notify->sendSystemNotify(
                    intval($post['user_id']), 'new_comment', $postId,
                    '有人评论了您的内容',
                    SeoHelper::postUrl($postId, $post['title'] ?: $post['content'])
                );
            }

            $this->pdo->commit();
            return ['ok' => true, 'msg' => '评论成功'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'msg' => '评论失败'];
        }
    }

    /* ========== 点赞 ========== */

    /**
     * 点赞/取消点赞（幂等）
     * @return string 'liked'|'unliked'
     */
    public function toggleLike($postId, $userId) {
        $postId = intval($postId);
        $userId = intval($userId);
        if ($userId <= 0) return 'unliked';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);

            if ($stmt->fetch()) {
                $this->pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
                $this->pdo->prepare("UPDATE posts SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?")->execute([$postId]);
                $action = 'unliked';
            } else {
                $this->pdo->prepare("INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$postId, $userId]);
                $this->pdo->prepare("UPDATE posts SET like_count = like_count + 1 WHERE id = ?")->execute([$postId]);
                $action = 'liked';

                // 通知作者
                $post = $this->getPostById($postId);
                if ($post && intval($post['user_id']) !== $userId) {
                    $this->notify->sendSystemNotify(
                        intval($post['user_id']), 'new_like', $postId,
                        '有人赞了您的内容',
                        SeoHelper::postUrl($postId, $post['title'] ?: $post['content'])
                    );
                }
            }
            $this->pdo->commit();
            return $action;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return 'unliked';
        }
    }

    /**
     * 是否已点赞
     */
    public function isLiked($postId, $userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([intval($postId), intval($userId)]);
        return $stmt->fetchColumn() > 0;
    }

    /* ========== 搜索 ========== */

    /**
     * 标题/正文搜索（LIKE），复用 feed 行结构
     */
    public function search($q, $page = 1, $perPage = 20) {
        $q = trim((string)$q);
        if ($q === '') {
            return ['list' => [], 'total' => 0, 'pages' => 0];
        }
        // 转义 LIKE 通配符
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $offset = (max(1, intval($page)) - 1) * intval($perPage);
        $whereSql = "p.status = 'active' AND (p.title LIKE ? OR p.content LIKE ?)";

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts p WHERE " . $whereSql);
        $countStmt->execute([$like, $like]);
        $total = intval($countStmt->fetchColumn());

        $orderBy = ($this->hasSticky ? "p.is_sticky DESC, " : "") . "p.created_at DESC";
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute([$like, $like]);

        return [
            'list'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $total > 0 ? ceil($total / $perPage) : 0,
        ];
    }

    /* ========== 侧栏聚合 ========== */

    /**
     * 本周热帖（7 天内，按 赞+评论*2+浏览*0.1 加权）
     */
    public function getHotPosts($limit = 10) {
        $limit = max(1, min(50, intval($limit)));
        $heat = $this->hasView
            ? "(p.like_count + p.comment_count * 2 + p.view_count * 0.1)"
            : "(p.like_count + p.comment_count * 2)";
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = 'active' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY {$heat} DESC
            LIMIT {$limit}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 活跃用户（按发帖+评论数聚合）
     */
    public function getActiveUsers($limit = 8) {
        $limit = max(1, min(50, intval($limit)));
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.avatar,
                   (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.status = 'active') AS post_cnt,
                   (SELECT COUNT(*) FROM post_comments c WHERE c.user_id = u.id) AS comment_cnt
            FROM users u
            WHERE u.status = 'active'
            HAVING (post_cnt + comment_cnt) > 0
            ORDER BY (post_cnt + comment_cnt) DESC
            LIMIT {$limit}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 相关推荐：同话题优先、同城市次之，排除自身
     */
    public function getRelatedPosts($post, $limit = 5) {
        $limit = max(1, min(20, intval($limit)));
        $where = ["p.status = 'active'", "p.id != " . intval($post['id'])];
        $params = [];
        $orderSql = "p.created_at DESC";

        if (!empty($post['topic'])) {
            $where[] = "p.topic = ?";
            $params[] = $post['topic'];
        } elseif (!empty($post['city'])) {
            $where[] = "p.city = ?";
            $params[] = $post['city'];
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$limit}");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ========== 浏览/置顶 ========== */

    /**
     * 详情页浏览 +1
     */
    public function incrementView($id) {
        if (!$this->hasView) return;
        try {
            $stmt = $this->pdo->prepare("UPDATE posts SET view_count = view_count + 1 WHERE id = ?");
            $stmt->execute([intval($id)]);
        } catch (Exception $e) {
            // 列不存在时静默忽略
        }
    }

    /**
     * 置顶/取消置顶（幂等切换）
     * @return bool
     */
    public function toggleSticky($id) {
        if (!$this->hasSticky) return false;
        try {
            $stmt = $this->pdo->prepare("UPDATE posts SET is_sticky = 1 - is_sticky WHERE id = ?");
            $stmt->execute([intval($id)]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /* ========== 关注作者 ========== */

    public function isFollowing($userId, $targetId) {
        $userId = intval($userId);
        $targetId = intval($targetId);
        if ($userId <= 0 || $targetId <= 0 || $userId === $targetId) return false;
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM club_follows WHERE user_id = ? AND target_id = ?");
            $stmt->execute([$userId, $targetId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 关注/取消关注（幂等切换）
     * @return bool 当前是否已关注
     */
    public function toggleFollow($userId, $targetId) {
        $userId = intval($userId);
        $targetId = intval($targetId);
        if ($userId <= 0 || $targetId <= 0 || $userId === $targetId) return false;
        try {
            if ($this->isFollowing($userId, $targetId)) {
                $stmt = $this->pdo->prepare("DELETE FROM club_follows WHERE user_id = ? AND target_id = ?");
                $stmt->execute([$userId, $targetId]);
                return false;
            }
            $stmt = $this->pdo->prepare("INSERT INTO club_follows (user_id, target_id) VALUES (?, ?)");
            $stmt->execute([$userId, $targetId]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getFollowerCount($targetId) {
        $targetId = intval($targetId);
        if ($targetId <= 0) return 0;
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM club_follows WHERE target_id = ?");
            $stmt->execute([$targetId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}
