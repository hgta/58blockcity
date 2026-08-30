<?php
/**
 * 模特 / 作者合作申请
 * 前台用户自助提交（申请 + 照片上传），后台审核（标记已联系 / 驳回 / 录入关联）
 */
class Application
{
    private $pdo;

    // 状态定义
    const STATUS_PENDING   = 'pending';    // 待处理
    const STATUS_CONTACTED = 'contacted';  // 已联系
    const STATUS_APPROVED  = 'approved';   // 已通过（已录入）
    const STATUS_REJECTED  = 'rejected';   // 已驳回

    const TYPE_MODEL  = 'model';
    const TYPE_AUTHOR = 'author';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function statusLabel($status)
    {
        $map = [
            self::STATUS_PENDING   => '待处理',
            self::STATUS_CONTACTED => '已联系',
            self::STATUS_APPROVED  => '已通过',
            self::STATUS_REJECTED  => '已驳回',
        ];
        return $map[$status] ?? $status;
    }

    public static function typeLabel($type)
    {
        return $type === self::TYPE_MODEL ? '模特申请' : '作者合作申请';
    }

    /**
     * 是否存在进行中的申请（pending/contacted/approved）
     * @return bool
     */
    public function hasActive($type, $userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM applications
             WHERE type = ? AND user_id = ? AND status IN ('pending','contacted','approved')
             LIMIT 1"
        );
        $stmt->execute([$type, intval($userId)]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * 创建申请（含重复校验）
     * @return int 新申请 ID；重复申请返回 0
     */
    public function create($type, $userId, array $data, array $photos)
    {
        if ($this->hasActive($type, $userId)) return 0;

        $fields = ['type', 'user_id', 'nickname', 'gender', 'phone', 'qq', 'weixin', 'weibo', 'xiaohongshu', 'photos'];
        $values = [$type, intval($userId), trim($data['nickname']), $data['gender'] ?? '保密', $data['phone'] ?? '', $data['qq'] ?? '', $data['weixin'] ?? '', $data['weibo'] ?? '', $data['xiaohongshu'] ?? '', json_encode($photos, JSON_UNESCAPED_SLASHES)];

        $optional = ['age', 'height', 'weight', 'measurements', 'city', 'zodiac', 'hobbies', 'style', 'bio'];
        foreach ($optional as $f) {
            if (isset($data[$f]) && $data[$f] !== '' && $data[$f] !== null) {
                $fields[] = $f;
                $values[] = $data[$f];
            }
        }

        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO applications (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute($values)) return 0;
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * 我的申请列表（倒序）
     */
    public function getMyApplications($userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute([intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 申请详情
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([intval($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['photos']) {
            $row['photos_arr'] = json_decode($row['photos'], true) ?: [];
        } else {
            $row['photos_arr'] = [];
        }
        return $row;
    }

    /**
     * 后台列表（type/status 筛选 + 分页）
     */
    public function getList($type = '', $status = '', $page = 1, $perPage = 20)
    {
        $where = [];
        $params = [];
        if ($type !== '') {
            $where[] = "type = ?";
            $params[] = $type;
        }
        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM applications" . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = (max(1, intval($page)) - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.username, u.email
             FROM applications a
             LEFT JOIN users u ON a.user_id = u.id" . $whereSql . "
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT " . intval($perPage) . " OFFSET " . intval($offset)
        );
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$row) {
            $row['photos_arr'] = $row['photos'] ? (json_decode($row['photos'], true) ?: []) : [];
        }
        unset($row);

        return [
            'list'  => $list,
            'total' => $total,
            'pages' => max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * 各状态数量（后台 tab 角标）
     */
    public function getStats($type = '')
    {
        $where = $type !== '' ? " WHERE type = ?" : '';
        $params = $type !== '' ? [$type] : [];
        $stmt = $this->pdo->prepare(
            "SELECT status, COUNT(*) as c FROM applications" . $where . " GROUP BY status"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats = ['pending' => 0, 'contacted' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($rows as $r) {
            if (isset($stats[$r['status']])) $stats[$r['status']] = (int)$r['c'];
        }
        return $stats;
    }

    /**
     * 状态流转
     * @param string $status contacted | rejected | approved
     * @param array  $extra ['reject_reason' => ...] 或 ['model_id'|'author_id' => ...]
     * @return bool
     */
    public function updateStatus($id, $status, array $extra = [])
    {
        $allowed = [self::STATUS_CONTACTED, self::STATUS_REJECTED, self::STATUS_APPROVED];
        if (!in_array($status, $allowed)) return false;

        $sets = ["status = ?", "reviewed_at = NOW()"];
        $values = [$status];

        if ($status === self::STATUS_REJECTED) {
            $sets[] = "reject_reason = ?";
            $values[] = trim($extra['reject_reason'] ?? '');
        }
        if (!empty($extra['model_id'])) {
            $sets[] = "model_id = ?";
            $values[] = intval($extra['model_id']);
        }
        if (!empty($extra['author_id'])) {
            $sets[] = "author_id = ?";
            $values[] = intval($extra['author_id']);
        }

        $values[] = intval($id);
        $stmt = $this->pdo->prepare("UPDATE applications SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    /**
     * 保存后台备注
     */
    public function updateRemark($id, $remark)
    {
        $stmt = $this->pdo->prepare("UPDATE applications SET admin_remark = ? WHERE id = ?");
        return $stmt->execute([trim($remark), intval($id)]);
    }

    /**
     * 图片上传（GD 压缩 + 原图直存），照片直接进入正式目录
     * @param array  $files $_FILES['photos']（多文件结构）
     * @param string $type  model | author → assets/uploads/models|authors/YYYYMM/
     * @return array 成功保存的相对路径数组
     */
    public static function uploadPhotos($files, $type)
    {
        $base = ($type === self::TYPE_AUTHOR) ? 'authors' : 'models';
        $subDir = date('Ym') . '/';
        $dir = __DIR__ . '/../assets/uploads/' . $base . '/' . $subDir;
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $saved = [];

        if (empty($files) || empty($files['name']) || !is_array($files['name'])) return $saved;

        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i])) continue;
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > 5 * 1024 * 1024) continue;
            if (!in_array($files['type'][$i], $allowed)) continue;

            // 第一张：GD 居中裁剪 400×400 压缩为 jpg（作头像）
            if ($i === 0) {
                $fname = uniqid() . '_' . time() . '.jpg';
                $path = $dir . $fname;
                $src = null;
                if (!($files['type'][$i] === 'image/webp' && !function_exists('imagecreatefromwebp'))) {
                    switch ($files['type'][$i]) {
                        case 'image/jpeg':
                        case 'image/jpg': $src = @imagecreatefromjpeg($files['tmp_name'][$i]); break;
                        case 'image/png':  $src = @imagecreatefrompng($files['tmp_name'][$i]); break;
                        case 'image/gif':  $src = @imagecreatefromgif($files['tmp_name'][$i]); break;
                        case 'image/webp': $src = @imagecreatefromwebp($files['tmp_name'][$i]); break;
                    }
                }
                if ($src) {
                    $w = imagesx($src); $h = imagesy($src);
                    $size = min($w, $h);
                    $dst = imagecreatetruecolor(400, 400);
                    imagecopyresampled($dst, $src, 0, 0, ($w - $size) / 2, ($h - $size) / 2, 400, 400, $size, $size);
                    imagejpeg($dst, $path, 80);
                    imagedestroy($src); imagedestroy($dst);
                    $saved[] = 'assets/uploads/' . $base . '/' . $subDir . $fname;
                    continue;
                }
            }

            // 其余 / GD 失败：原图直存
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!preg_match('/^(jpg|jpeg|png|gif|webp)$/', $ext)) $ext = 'jpg';
            $fname = 'up_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dir . $fname)) {
                $saved[] = 'assets/uploads/' . $base . '/' . $subDir . $fname;
            }
        }
        return $saved;
    }
}
