<?php
/**
 * 短剧（Drama）数据层
 *
 * 支撑模特子站（model.58.tl）的「模特 × 短剧」双向导流：
 *   - dramas       短剧主表（剧名 / 封面 / 集数 / 题材标签 / 外部观看地址 / 简介）
 *   - model_dramas 参演关联（角色名 / 是否主演 / 排序）
 *                  · model_id  非空 = 模特库成员（可跳个人主页）
 *                  · actor_name 非空 = 普通演员（纯文本展示，不跳转）
 *                  · 约束：两者至少填一个
 *
 * 约定：
 *   - tags 以 JSON 数组存储（与 models.daily_photos 风格一致）
 *   - 参演关系增删与 models.drama_count 在同一事务内维护
 *   - 短剧删除为软删除（status = inactive）
 */
if (!class_exists('SeoHelper')) {
    require_once __DIR__ . '/SeoHelper.php';
}

class Drama
{
    private $pdo;

    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
     * 短剧 CRUD
     * ============================================================ */

    /**
     * 创建短剧
     * @param array $data title(必填) / cover / episodes / tags(数组或字符串) / hg_url / synopsis / status
     * @return int 新短剧 ID；失败返回 0
     */
    public function create(array $data)
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            return 0;
        }

        $fields = ['title', 'slug'];
        $values = [$title, $this->makeSlug($title)];

        $optional = ['cover', 'episodes', 'tags', 'hg_url', 'synopsis'];
        foreach ($optional as $f) {
            if (isset($data[$f]) && $data[$f] !== '' && $data[$f] !== null) {
                $fields[] = $f;
                $values[] = ($f === 'tags') ? $this->normalizeTags($data[$f]) : $data[$f];
            }
        }
        if (!empty($data['status']) && in_array($data['status'], [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            $fields[] = 'status';
            $values[] = $data['status'];
        }

        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO dramas (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute($values)) {
            return 0;
        }
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * 更新短剧
     * @return bool
     */
    public function update($id, array $data)
    {
        $allowed = ['title', 'slug', 'cover', 'episodes', 'tags', 'hg_url', 'synopsis', 'status'];
        $sets = [];
        $values = [];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            if ($f === 'tags') {
                $sets[] = "`tags` = ?";
                $values[] = $this->normalizeTags($data[$f]);
                continue;
            }
            $sets[] = "`{$f}` = ?";
            $values[] = $data[$f];
        }
        if (empty($sets)) {
            return false;
        }
        // 剧名变更时同步 slug（未显式指定 slug 的情况下）
        if (isset($data['title']) && !isset($data['slug'])) {
            $sets[] = "`slug` = ?";
            $values[] = $this->makeSlug($data['title']);
        }
        $values[] = intval($id);
        $stmt = $this->pdo->prepare("UPDATE dramas SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    /**
     * 按 ID 获取短剧（附带 tags_arr 与参演模特数）
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.*,
                    (SELECT COUNT(*) FROM model_dramas md WHERE md.drama_id = d.id) AS model_count
             FROM dramas d WHERE d.id = ?"
        );
        $stmt->execute([intval($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['tags_arr'] = $this->decodeTags($row['tags']);
        return $row;
    }

    /**
     * 软删除（status = inactive）
     */
    public function softDelete($id)
    {
        $stmt = $this->pdo->prepare("UPDATE dramas SET status = ? WHERE id = ?");
        return $stmt->execute([self::STATUS_INACTIVE, intval($id)]);
    }

    /**
     * 恢复上架
     */
    public function restore($id)
    {
        $stmt = $this->pdo->prepare("UPDATE dramas SET status = ? WHERE id = ?");
        return $stmt->execute([self::STATUS_ACTIVE, intval($id)]);
    }

    /**
     * 按剧名模糊查找（后台录入时「选已有剧」）
     * @return array
     */
    public function findByTitle($title, $onlyActive = true, $limit = 10)
    {
        $where = "title LIKE ?";
        $params = ['%' . trim($title) . '%'];
        if ($onlyActive) {
            $where .= " AND status = ?";
            $params[] = self::STATUS_ACTIVE;
        }
        $limit = max(1, intval($limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, title, cover, episodes, status FROM dramas
             WHERE {$where} ORDER BY updated_at DESC LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 后台分页列表
     * @return array ['list'=>, 'total'=>, 'pages'=>]
     */
    public function getList($page = 1, $perPage = 20, $search = '', $status = '')
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "d.title LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where[] = "d.status = ?";
            $params[] = $status;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM dramas d" . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT d.*,
                    (SELECT COUNT(*) FROM model_dramas md WHERE md.drama_id = d.id) AS model_count
             FROM dramas d" . $whereSql . "
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT " . intval($perPage) . " OFFSET " . intval($offset)
        );
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$row) {
            $row['tags_arr'] = $this->decodeTags($row['tags']);
        }
        unset($row);

        return [
            'list'  => $list,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    /* ============================================================
     * 参演关系
     * ============================================================ */

    /**
     * 关联模特到短剧（幂等：已存在则更新角色名/主演/排序）
     * @return bool
     */
    /**
     * 按参演关系 ID 更新角色名 / 主演 / 排序
     */
    public function updateCredit($creditId, $roleName, $isLead, $sortOrder)
    {
        $creditId = intval($creditId);
        if ($creditId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE model_dramas SET role_name = ?, is_lead = ?, sort_order = ? WHERE id = ?"
        );
        return $stmt->execute([
            mb_substr((string)$roleName, 0, 100),
            $isLead ? 1 : 0,
            intval($sortOrder),
            $creditId,
        ]);
    }

    public function attachModel($dramaId, $modelId, $roleName = '', $isLead = 0, $sortOrder = 0)
    {
        $dramaId = intval($dramaId);
        $modelId = intval($modelId);
        if ($dramaId <= 0 || $modelId <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM model_dramas WHERE drama_id = ? AND model_id = ?"
            );
            $stmt->execute([$dramaId, $modelId]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $this->pdo->prepare(
                    "UPDATE model_dramas SET role_name = ?, is_lead = ?, sort_order = ? WHERE id = ?"
                )->execute([$roleName, $isLead ? 1 : 0, intval($sortOrder), $existingId]);
            } else {
                $this->pdo->prepare(
                    "INSERT INTO model_dramas (drama_id, model_id, role_name, is_lead, sort_order)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$dramaId, $modelId, $roleName, $isLead ? 1 : 0, intval($sortOrder)]);
            }
            $this->syncDramaCount($modelId);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 关联「演员表成员」参演（优先使用，可展示头像、可跨剧复用）
     *
     * @param int $actorId actors.id
     * @return array|null 成功返回参演记录；已关联或参数非法返回 null
     */
    public function attachActorRecord($dramaId, $actorId, $roleName = '', $isLead = 0, $sortOrder = 0)
    {
        $dramaId = intval($dramaId);
        $actorId = intval($actorId);
        if ($dramaId <= 0 || $actorId <= 0) {
            return null;
        }

        // 同一部剧同一演员只能一条
        $stmt = $this->pdo->prepare(
            "SELECT id FROM model_dramas WHERE drama_id = ? AND actor_id = ?"
        );
        $stmt->execute([$dramaId, $actorId]);
        if ($stmt->fetchColumn()) {
            return null;
        }

        $this->pdo->beginTransaction();
        try {
            $ok = $this->pdo->prepare(
                "INSERT INTO model_dramas (drama_id, model_id, actor_id, actor_name, role_name, is_lead, sort_order)
                 VALUES (?, NULL, ?, NULL, ?, ?, ?)"
            )->execute([$dramaId, $actorId, mb_substr((string)$roleName, 0, 100), $isLead ? 1 : 0, intval($sortOrder)]);
            if (!$ok) {
                $this->pdo->rollBack();
                return null;
            }
            $creditId = (int)$this->pdo->lastInsertId();
            $this->syncActorDramaCount($actorId);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'id'         => $creditId,
            'model_id'   => null,
            'actor_id'   => $actorId,
            'role_name'  => $roleName,
            'is_lead'    => $isLead ? 1 : 0,
            'sort_order' => intval($sortOrder),
        ];
    }

    /** 演员参演短剧数重算（含停用短剧不计，与前端展示口径一致） */
    private function syncActorDramaCount($actorId)
    {
        $this->pdo->prepare(
            "UPDATE actors a SET a.drama_count = (
                SELECT COUNT(*) FROM model_dramas md
                JOIN dramas d ON md.drama_id = d.id
                WHERE md.actor_id = a.id AND d.status = 'active'
             ) WHERE a.id = ?"
        )->execute([intval($actorId)]);
    }

    /**
     * 登记「无档案演员」参演（仅存姓名，历史兼容 / 应急录入）
     * 新流程应优先使用 attachActorRecord()
     *
     * @return array|null 成功返回参演记录，重名或参数非法返回 null
     */
    public function attachActor($dramaId, $actorName, $roleName = '', $isLead = 0, $sortOrder = 0)
    {
        $dramaId   = intval($dramaId);
        $actorName = trim((string)$actorName);
        if ($dramaId <= 0 || $actorName === '') {
            return null;
        }
        $actorName = mb_substr($actorName, 0, 100);

        // 同剧重名：拒绝新增（避免唯一键冲突抛出异常）
        $stmt = $this->pdo->prepare(
            "SELECT id FROM model_dramas WHERE drama_id = ? AND actor_name = ?"
        );
        $stmt->execute([$dramaId, $actorName]);
        if ($stmt->fetchColumn()) {
            return null;
        }

        $ok = $this->pdo->prepare(
            "INSERT INTO model_dramas (drama_id, model_id, actor_id, actor_name, role_name, is_lead, sort_order)
             VALUES (?, NULL, NULL, ?, ?, ?, ?)"
        )->execute([$dramaId, $actorName, mb_substr((string)$roleName, 0, 100), $isLead ? 1 : 0, intval($sortOrder)]);

        if (!$ok) {
            return null;
        }
        return [
            'id'         => intval($this->pdo->lastInsertId()),
            'model_id'   => null,
            'actor_id'   => null,
            'actor_name' => $actorName,
            'role_name'  => $roleName,
            'is_lead'    => $isLead ? 1 : 0,
            'sort_order' => intval($sortOrder),
        ];
    }

    /**
     * 按参演关系 ID 解除（模特演员与非模特演员通用）
     */
    public function detachCredit($creditId)
    {
        $creditId = intval($creditId);
        if ($creditId <= 0) {
            return false;
        }

        // 先取出归属，便于同步冗余计数
        $stmt = $this->pdo->prepare("SELECT model_id, actor_id FROM model_dramas WHERE id = ?");
        $stmt->execute([$creditId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM model_dramas WHERE id = ?")->execute([$creditId]);
            if (!empty($row['model_id'])) {
                $this->syncDramaCount(intval($row['model_id']));
            }
            if (!empty($row['actor_id'])) {
                $this->syncActorDramaCount(intval($row['actor_id']));
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 解除参演关系（按模特维度，兼容旧调用）
     */
    public function detachModel($dramaId, $modelId)
    {
        $dramaId = intval($dramaId);
        $modelId = intval($modelId);

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "DELETE FROM model_dramas WHERE drama_id = ? AND model_id = ?"
            )->execute([$dramaId, $modelId]);
            $this->syncDramaCount($modelId);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 某短剧的全部参演模特（主演优先 + 自定义排序）
     * @param bool $onlyActiveModel 仅返回上架模特
     */
    public function getModelsByDrama($dramaId, $onlyActiveModel = true)
    {
        $sql = "SELECT md.id AS credit_id, md.role_name, md.is_lead, md.sort_order,
                      m.id, m.nickname, m.gender, m.city, m.zodiac, m.avatar,
                      m.follower_count, m.like_count, m.drama_count,
                      u.username, u.avatar AS user_avatar
               FROM model_dramas md
               JOIN models m ON md.model_id = m.id
               LEFT JOIN users u ON m.user_id = u.id
               WHERE md.drama_id = ?";
        if ($onlyActiveModel) {
            $sql .= " AND m.status = 'active'";
        }
        $sql .= " ORDER BY md.is_lead DESC, md.sort_order ASC, md.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([intval($dramaId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 某短剧的「普通演员」参演记录
     * 优先取演员表成员（带头像），兼容历史的纯文本 actor_name 记录
     */
    public function getActorsByDrama($dramaId, $onlyActiveActor = true)
    {
        $sql = "SELECT md.id AS credit_id, md.actor_id, md.actor_name AS raw_actor_name,
                       md.role_name, md.is_lead, md.sort_order,
                       a.nickname, a.avatar, a.gender, a.city, a.bio, a.status AS actor_status
                FROM model_dramas md
                LEFT JOIN actors a ON md.actor_id = a.id
                WHERE md.drama_id = ?
                  AND md.model_id IS NULL
                  AND (md.actor_id IS NOT NULL
                       OR (md.actor_name IS NOT NULL AND md.actor_name <> ''))";
        if ($onlyActiveActor) {
            $sql .= " AND (a.id IS NULL OR a.status = 'active')";
        }
        $sql .= " ORDER BY md.is_lead DESC, md.sort_order ASC, md.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([intval($dramaId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 某短剧的完整演职人员：模特演员（可跳主页） + 非模特演员（纯文本）
     * 按「主演优先 → 番位」合并排序，供详情页「主要演员」区使用
     *
     * @param int $limit 限制主要演员展示数量（红果为 5 位），0 = 不限
     */
    public function getCastByDrama($dramaId, $limit = 0, $onlyActiveModel = true)
    {
        $models = $this->getModelsByDrama($dramaId, $onlyActiveModel);
        $actors = $this->getActorsByDrama($dramaId);

        $cast = [];
        foreach ($models as $m) {
            $cast[] = [
                'credit_id'  => intval($m['credit_id']),
                'model_id'   => intval($m['id']),
                'name'       => $m['nickname'],
                'role_name'  => $m['role_name'] ?? '',
                'is_lead'    => intval($m['is_lead'] ?? 0),
                'sort_order' => intval($m['sort_order'] ?? 0),
                'avatar'     => $m['avatar'] ?: ($m['user_avatar'] ?? ''),
                'link'       => SeoHelper::modelUrl($m['id'], $m['nickname']),
                'raw'        => $m,
            ];
        }
        foreach ($actors as $a) {
            // 演员表成员用 nickname，无档案历史记录回退 actor_name
            $name = !empty($a['actor_id']) ? ($a['nickname'] ?? '') : ($a['raw_actor_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $cast[] = [
                'credit_id'  => intval($a['credit_id']),
                'model_id'   => null,
                'actor_id'   => !empty($a['actor_id']) ? intval($a['actor_id']) : null,
                'name'       => $name,
                'role_name'  => $a['role_name'] ?? '',
                'is_lead'    => intval($a['is_lead'] ?? 0),
                'sort_order' => intval($a['sort_order'] ?? 0),
                'avatar'     => $a['avatar'] ?? '',
                'link'       => '',
                'raw'        => $a,
            ];
        }

        // 主演优先 → 番位（sort_order）→ 模特优先
        usort($cast, function ($x, $y) {
            if ($x['is_lead'] !== $y['is_lead']) {
                return $y['is_lead'] - $x['is_lead'];
            }
            if ($x['sort_order'] !== $y['sort_order']) {
                return $x['sort_order'] - $y['sort_order'];
            }
            return ($y['model_id'] ? 1 : 0) - ($x['model_id'] ? 1 : 0);
        });

        if ($limit > 0) {
            $cast = array_slice($cast, 0, $limit);
        }
        return $cast;
    }

    /**
     * 某短剧的参演人员总数（模特 + 非模特演员）
     */
    public function getCastCount($dramaId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_dramas md
             JOIN models m ON md.model_id = m.id
             WHERE md.drama_id = ? AND m.status = 'active'"
        );
        $stmt->execute([intval($dramaId)]);
        $modelCount = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_dramas md
             LEFT JOIN actors a ON md.actor_id = a.id
             WHERE md.drama_id = ?
               AND md.model_id IS NULL
               AND ( (md.actor_id IS NOT NULL AND (a.id IS NULL OR a.status = 'active'))
                  OR (md.actor_id IS NULL AND md.actor_name IS NOT NULL AND md.actor_name <> '') )"
        );
        $stmt->execute([intval($dramaId)]);
        return $modelCount + (int)$stmt->fetchColumn();
    }

    /**
     * 某模特参演的全部短剧
     */
    public function getDramasByModel($modelId, $onlyActiveDrama = true)
    {
        $sql = "SELECT d.id, d.title, d.cover, d.episodes, d.tags, d.hg_url, d.synopsis, d.status,
                       md.role_name, md.is_lead, md.sort_order
                FROM model_dramas md
                JOIN dramas d ON md.drama_id = d.id
                WHERE md.model_id = ?";
        if ($onlyActiveDrama) {
            $sql .= " AND d.status = 'active'";
        }
        $sql .= " ORDER BY md.is_lead DESC, md.sort_order ASC, d.updated_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([intval($modelId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['tags_arr'] = $this->decodeTags($row['tags']);
        }
        unset($row);
        return $rows;
    }

    /**
     * 某演员参演的短剧（后台演员详情用）
     */
    public function getDramasByActor($actorId, $onlyActiveDrama = true)
    {
        $sql = "SELECT d.id, d.title, d.cover, d.episodes, d.tags, d.hg_url, d.synopsis, d.status,
                       md.role_name, md.is_lead, md.sort_order
                FROM model_dramas md
                JOIN dramas d ON md.drama_id = d.id
                WHERE md.actor_id = ?";
        if ($onlyActiveDrama) {
            $sql .= " AND d.status = 'active'";
        }
        $sql .= " ORDER BY md.is_lead DESC, md.sort_order ASC, d.updated_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([intval($actorId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['tags_arr'] = $this->decodeTags($row['tags']);
        }
        unset($row);
        return $rows;
    }

    /**
     * 某短剧的参演关系原始记录（后台管理用，含未上架的模特）
     */
    public function getCredits($dramaId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT md.*,
                    m.nickname, m.avatar, m.status AS model_status,
                    a.nickname AS actor_nickname, a.avatar AS actor_avatar, a.status AS actor_status
             FROM model_dramas md
             LEFT JOIN models m ON md.model_id = m.id
             LEFT JOIN actors a ON md.actor_id = a.id
             WHERE md.drama_id = ?
             ORDER BY md.is_lead DESC, md.sort_order ASC, md.id ASC"
        );
        $stmt->execute([intval($dramaId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!empty($row['model_id'])) {
                $row['display_name'] = $row['nickname'] ?? ('#' . intval($row['model_id']));
                $row['cast_type']    = 'model';
            } elseif (!empty($row['actor_id'])) {
                $row['display_name'] = $row['actor_nickname'] ?? ('#' . intval($row['actor_id']));
                $row['cast_type']    = 'actor';
            } else {
                $row['display_name'] = $row['actor_name'] ?? '';
                $row['cast_type']    = 'legacy';   // 无档案的历史记录
            }
            $row['is_model'] = $row['cast_type'] === 'model';
        }
        unset($row);
        return $rows;
    }

    /* ============================================================
     * 站点消费查询
     * ============================================================ */

    /**
     * 首页「正在热播短剧」：按参演模特数 + 最近更新排序
     */
    public function getTopDramas($limit = 8)
    {
        $limit = max(1, intval($limit));
        $stmt = $this->pdo->prepare(
            "SELECT d.id, d.title, d.cover, d.episodes, d.tags, d.updated_at,
                    (SELECT COUNT(*) FROM model_dramas md
                     LEFT JOIN models m ON md.model_id = m.id
                     LEFT JOIN actors a ON md.actor_id = a.id
                     WHERE md.drama_id = d.id
                       AND ( (md.model_id IS NOT NULL AND m.status = 'active')
                          OR (md.actor_id IS NOT NULL AND (a.id IS NULL OR a.status = 'active'))
                          OR (md.model_id IS NULL AND md.actor_id IS NULL
                              AND md.actor_name IS NOT NULL AND md.actor_name <> '') )
                    ) AS model_count
             FROM dramas d
             WHERE d.status = 'active'
             ORDER BY model_count DESC, d.updated_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 批量加载参演模特头像（避免 N+1）
        if ($rows) {
            $dramaIds = array_column($rows, 'id');
            $ph = implode(',', array_map('intval', $dramaIds));
            $mStmt = $this->pdo->prepare(
                "SELECT md.drama_id, m.id, m.nickname, m.avatar, u.avatar AS user_avatar
                 FROM model_dramas md
                 JOIN models m ON md.model_id = m.id
                 LEFT JOIN users u ON m.user_id = u.id
                 WHERE md.drama_id IN ({$ph}) AND m.status = 'active'
                 ORDER BY md.is_lead DESC, md.sort_order ASC"
            );
            $mStmt->execute();
            $map = [];
            while ($r = $mStmt->fetch(PDO::FETCH_ASSOC)) {
                $map[$r['drama_id']][] = $r;
            }
            foreach ($rows as &$row) {
                $row['models'] = $map[$row['id']] ?? [];
                $row['tags_arr'] = $this->decodeTags($row['tags']);
            }
            unset($row);
        }
        return $rows;
    }

    /**
     * 前台短剧列表页（按题材筛选 + 分页）
     * @return array ['list'=>, 'total'=>, 'pages'=>]
     */
    public function getPublicList($page = 1, $perPage = 24, $tag = '')
    {
        $where = ["d.status = 'active'"];
        $params = [];
        if ($tag !== '') {
            // tags 为 JSON 数组文本，用 LIKE 做包含匹配
            $where[] = "d.tags LIKE ?";
            $params[] = '%' . $tag . '%';
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM dramas d" . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT d.id, d.title, d.cover, d.episodes, d.tags, d.updated_at,
                    (SELECT COUNT(*) FROM model_dramas md
                     LEFT JOIN models m ON md.model_id = m.id
                     LEFT JOIN actors a ON md.actor_id = a.id
                     WHERE md.drama_id = d.id
                       AND ( (md.model_id IS NOT NULL AND m.status = 'active')
                          OR (md.actor_id IS NOT NULL AND (a.id IS NULL OR a.status = 'active'))
                          OR (md.model_id IS NULL AND md.actor_id IS NULL
                              AND md.actor_name IS NOT NULL AND md.actor_name <> '') )
                    ) AS model_count
             FROM dramas d" . $whereSql . "
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT " . intval($perPage) . " OFFSET " . intval($offset)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['tags_arr'] = $this->decodeTags($row['tags']);
        }
        unset($row);

        return [
            'list'  => $rows,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    /**
     * 题材标签聚合（短剧列表页筛选用）
     * @return array [['tag'=>'古风权谋', 'c'=>3], ...]
     */
    public function getTagFacets()
    {
        $stmt = $this->pdo->query("SELECT tags FROM dramas WHERE status = 'active' AND tags IS NOT NULL AND tags <> ''");
        $counter = [];
        while ($raw = $stmt->fetchColumn()) {
            foreach ($this->decodeTags($raw) as $t) {
                if ($t === '') continue;
                $counter[$t] = ($counter[$t] ?? 0) + 1;
            }
        }
        arsort($counter);
        $out = [];
        foreach ($counter as $tag => $c) {
            $out[] = ['tag' => $tag, 'c' => $c];
        }
        return $out;
    }

    /* ============================================================
     * 内部工具
     * ============================================================ */

    /**
     * 重算某模特的参演短剧数（调用方须在事务内）
     */
    public function syncDramaCount($modelId)
    {
        $modelId = intval($modelId);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_dramas md
             JOIN dramas d ON md.drama_id = d.id
             WHERE md.model_id = ? AND d.status = 'active'"
        );
        $stmt->execute([$modelId]);
        $count = (int)$stmt->fetchColumn();
        $this->pdo->prepare("UPDATE models SET drama_count = ? WHERE id = ?")
                  ->execute([$count, $modelId]);
        return $count;
    }

    /**
     * 全量重算 drama_count（对账，可重复执行）
     */
    public function rebuildAllDramaCounts()
    {
        $sql = "UPDATE models m
                SET m.drama_count = (
                    SELECT COUNT(*) FROM model_dramas md
                    JOIN dramas d ON md.drama_id = d.id
                    WHERE md.model_id = m.id AND d.status = 'active'
                )";
        return $this->pdo->exec($sql);
    }

    /**
     * tags 归一化为 JSON 数组字符串
     */
    private function normalizeTags($tags)
    {
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            if (is_array($decoded)) {
                $tags = $decoded;
            } else {
                // 逗号 / 顿号 / 中文逗号分隔
                $tags = preg_split('/[,、，]+/u', $tags);
            }
        }
        if (!is_array($tags)) {
            return json_encode([], JSON_UNESCAPED_UNICODE);
        }
        $clean = [];
        foreach ($tags as $t) {
            $t = trim((string)$t);
            if ($t !== '' && !in_array($t, $clean, true)) {
                $clean[] = $t;
            }
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /**
     * tags 解码为数组
     */
    private function decodeTags($raw)
    {
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), function ($v) {
                return $v !== '';
            }));
        }
        // 兼容历史逗号分隔写法
        return array_values(array_filter(array_map('trim', preg_split('/[,、，]+/u', (string)$raw)), function ($v) {
            return $v !== '';
        }));
    }

    /**
     * 生成 slug（保留中文、英文、数字）
     */
    private function makeSlug($title)
    {
        if (class_exists('SeoHelper')) {
            return SeoHelper::slug($title);
        }
        $s = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]+/u', '-', (string)$title);
        return trim(preg_replace('/-+/', '-', (string)$s), '-');
    }
}
