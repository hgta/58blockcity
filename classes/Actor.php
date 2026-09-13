<?php
/**
 * 短剧演员表 - Actor 类
 *
 * 职责：维护跨短剧复用的演职人员档案（姓名 / 头像 / 简介）。
 *
 * 设计要点：
 *   - actors 与 models 是两套独立实体：models 有主页/粉丝/关注，
 *     actors 仅为演职人员档案，无主页、不可关注
 *   - 头像支持两种来源：上传（相对路径）或外链（完整 URL），
 *     展示层统一由子站 model_media() 归一化，本类不做 URL 拼接
 *   - 参演关系的增删通过 Drama 类完成；本类只负责档案维护
 *     与 drama_count 冗余计数的重算
 */
class Actor
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /** 参演短剧数重算（供 Drama 关联增删后调用） */
    public function syncDramaCount($actorId)
    {
        $actorId = intval($actorId);
        if ($actorId <= 0) {
            return;
        }
        $this->pdo->prepare(
            "UPDATE actors a SET a.drama_count = (
                SELECT COUNT(*) FROM model_dramas md WHERE md.actor_id = a.id
             ) WHERE a.id = ?"
        )->execute([$actorId]);
    }

    /**
     * 演员列表（后台管理用）
     *
     * @param int    $page
     * @param int    $perPage
     * @param string $search  按姓名模糊搜索
     * @param string $status  active / inactive / 空（全部）
     */
    public function getList($page = 1, $perPage = 20, $search = '', $status = '')
    {
        $page    = max(1, intval($page));
        $perPage = max(1, intval($perPage));
        $offset  = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = 'nickname LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM actors " . $whereSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM actors " . $whereSql . "
             ORDER BY drama_count DESC, id DESC
             LIMIT " . $perPage . " OFFSET " . $offset
        );
        $stmt->execute($params);

        return [
            'list'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => (int)ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /** 按 ID 取演员 */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM actors WHERE id = ?");
        $stmt->execute([intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** 按姓名取演员（用于「新建时判重」） */
    public function getByNickname($nickname)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM actors WHERE nickname = ?");
        $stmt->execute([trim((string)$nickname)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** 按姓名搜索（供短剧管理页的演员选择器，含停用者标记） */
    public function searchByNickname($keyword, $limit = 15)
    {
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            return [];
        }
        $limit = max(1, intval($limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM actors WHERE nickname LIKE ? ORDER BY drama_count DESC, id DESC LIMIT " . $limit
        );
        $stmt->execute(['%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 创建演员
     *
     * @param array $data 支持 nickname(必填) / avatar / gender / city / bio / status
     * @return int 新 ID，失败返回 0（姓名重复也返回 0）
     */
    public function create($data)
    {
        $nickname = trim((string)($data['nickname'] ?? ''));
        if ($nickname === '') {
            return 0;
        }
        // 同名即视为同一人，直接拒绝（避免唯一键异常）
        if ($this->getByNickname($nickname)) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO actors (nickname, avatar, gender, city, bio, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ok = $stmt->execute([
            mb_substr($nickname, 0, 100),
            trim((string)($data['avatar'] ?? '')) ?: null,
            in_array($data['gender'] ?? '', ['男', '女', '保密'], true) ? $data['gender'] : '保密',
            trim((string)($data['city'] ?? '')) ?: null,
            mb_substr(trim((string)($data['bio'] ?? '')), 0, 500) ?: null,
            ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]);
        return $ok ? (int)$this->pdo->lastInsertId() : 0;
    }

    /**
     * 更新演员（仅更新传入的字段）
     *
     * @return bool 目标不存在或姓名为空返回 false
     */
    public function update($id, $data)
    {
        $id = intval($id);
        if ($id <= 0 || !$this->getById($id)) {
            return false;
        }

        $sets = [];
        $params = [];

        if (array_key_exists('nickname', $data)) {
            $nickname = trim((string)$data['nickname']);
            if ($nickname === '') {
                return false;
            }
            // 改名时排除自身判重
            $exist = $this->getByNickname($nickname);
            if ($exist && intval($exist['id']) !== $id) {
                return false;
            }
            $sets[] = 'nickname = ?';
            $params[] = mb_substr($nickname, 0, 100);
        }
        if (array_key_exists('avatar', $data)) {
            $sets[] = 'avatar = ?';
            $params[] = trim((string)$data['avatar']) ?: null;
        }
        if (array_key_exists('gender', $data)) {
            $sets[] = 'gender = ?';
            $params[] = in_array($data['gender'], ['男', '女', '保密'], true) ? $data['gender'] : '保密';
        }
        if (array_key_exists('city', $data)) {
            $sets[] = 'city = ?';
            $params[] = trim((string)$data['city']) ?: null;
        }
        if (array_key_exists('bio', $data)) {
            $sets[] = 'bio = ?';
            $params[] = mb_substr(trim((string)$data['bio']), 0, 500) ?: null;
        }
        if (array_key_exists('status', $data)) {
            $sets[] = 'status = ?';
            $params[] = $data['status'] === 'inactive' ? 'inactive' : 'active';
        }

        if (empty($sets)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare("UPDATE actors SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    /** 停用演员（软删除，保留历史参演关系） */
    public function softDelete($id)
    {
        return $this->update(intval($id), ['status' => 'inactive']);
    }

    /** 恢复上架 */
    public function restore($id)
    {
        return $this->update(intval($id), ['status' => 'active']);
    }

    /** 演员总数（后台概览用） */
    public function countAll()
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM actors")->fetchColumn();
    }
}
