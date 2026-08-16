<?php
/**
 * 58区块社区 — 帖子/心情业务类
 *
 * 单表 posts + type 区分「帖子(post)」和「一句话心情(moment)」，
 * 评论(post_comments)、点赞(post_likes)共用。
 */

require_once __DIR__ . '/Notification.php';

class Post {
    private $pdo;
    private $notify;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notify = new Notification($pdo);
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
        return $ok ? intval($this->pdo->lastInsertId()) : '发布失败';
    }

    /* ========== 查询 ========== */

    /**
     * 信息流（城市/话题/类型筛选 + 分页）
     */
    public function getFeed($page = 1, $perPage = 20, $city = '', $topic = '', $type = '') {
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

        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.avatar
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereSql}
            ORDER BY p.created_at DESC
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
     */
    public function getComments($postId, $limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.username, u.avatar
            FROM post_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC
            LIMIT " . intval($limit));
        $stmt->execute([intval($postId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    '../club/post.php?id=' . $postId
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
                        '../club/post.php?id=' . $postId
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
}
