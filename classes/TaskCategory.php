<?php
/**
 * 任务类别（task_categories）
 * 发布/广场筛选/技能卡共用同一组「启用中」类别；后台可增改停用。
 */
class TaskCategory {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /** 全部类别（后台使用，含停用） */
    public function all() {
        return $this->pdo->query(
            "SELECT * FROM task_categories ORDER BY sort_order ASC, id ASC"
        )->fetchAll();
    }

    /** 启用中的类别（发布/广场/技能卡可选集合） */
    public function listActive() {
        return $this->pdo->query(
            "SELECT * FROM task_categories WHERE status = 'active'
             ORDER BY sort_order ASC, id ASC"
        )->fetchAll();
    }

    /** 启用类别 id 集合（校验/聚合用） */
    public function activeIds() {
        $ids = [];
        foreach ($this->listActive() as $row) {
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM task_categories WHERE id = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 新增类别（名称唯一）
     * @return array [bool, message|id]
     */
    public function add($name, $sortOrder = 0) {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 50) {
            return [false, '类别名称不能为空且不超过 50 字'];
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO task_categories (name, sort_order) VALUES (?, ?)"
            );
            $stmt->execute([$name, max(0, (int)$sortOrder)]);
            return [true, (int)$this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [false, '该类别名称已存在'];
            }
            throw $e;
        }
    }

    public function update($id, $name, $sortOrder = 0) {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 50) {
            return [false, '类别名称不能为空且不超过 50 字'];
        }
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE task_categories SET name = ?, sort_order = ? WHERE id = ?"
            );
            $stmt->execute([$name, max(0, (int)$sortOrder), (int)$id]);
            return [true, ''];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [false, '该类别名称已存在'];
            }
            throw $e;
        }
    }

    /** 停用/启用 */
    public function setStatus($id, $status) {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE task_categories SET status = ? WHERE id = ?"
        );
        return $stmt->execute([$status, (int)$id]);
    }
}
