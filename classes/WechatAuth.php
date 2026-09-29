<?php
/**
 * 微信公众号扫码登录 / 重置密码 验证码逻辑
 *
 * 适用「未认证个人订阅号」方案：
 *   用户关注公众号（或已关注后发任意消息）→ 微信把事件推送到 api/wechat.php
 *   → 签发 6 位验证码并通过被动回复发给用户
 *   → 用户在登录页输入验证码 → 核销并按 openid 登录/建号
 *
 * 验证码规则：6 位数字、5 分钟有效、一次性核销、同 openid 同用途新码覆盖旧码。
 */
class WechatAuth {
    /** @var PDO */
    private $pdo;

    const CODE_TTL_SECONDS = 300; // 5 分钟
    const CODE_PURPOSE_LOGIN = 'login';
    const CODE_PURPOSE_RESET = 'reset';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* ------------------------------------------------------------------
     * 验证码：签发 / 核销
     * ------------------------------------------------------------------ */

    /**
     * 为 openid 签发一个 6 位验证码（旧的同用途未用码全部作废）
     *
     * @param string $openid
     * @param string $purpose self::CODE_PURPOSE_*
     * @return string 6 位数字
     */
    public function issueCode($openid, $purpose = self::CODE_PURPOSE_LOGIN) {
        $openid = trim((string)$openid);
        if ($openid === '') {
            throw new InvalidArgumentException('openid 不能为空');
        }

        // 作废该 openid 同用途的旧码（新码覆盖旧码，避免用户拿错）
        $stmt = $this->pdo->prepare(
            "UPDATE wechat_login_codes SET is_used = 1
             WHERE openid = ? AND purpose = ? AND is_used = 0"
        );
        $stmt->execute([$openid, $purpose]);

        // 生成不与当前有效码冲突的 6 位数字
        for ($try = 0; $try < 10; $try++) {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare(
                "SELECT id FROM wechat_login_codes
                 WHERE code = ? AND is_used = 0 AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$code]);
            if (!$stmt->fetch()) {
                break;
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO wechat_login_codes (code, openid, purpose, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . self::CODE_TTL_SECONDS . " SECOND))"
        );
        $stmt->execute([$code, $openid, $purpose]);

        return $code;
    }

    /**
     * 核销验证码，返回对应 openid；无效/过期/已用返回 null（一次性）
     *
     * @param string $code
     * @param string $purpose
     * @return string|null
     */
    public function consumeCode($code, $purpose = self::CODE_PURPOSE_LOGIN) {
        $code = trim((string)$code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, openid FROM wechat_login_codes
                 WHERE code = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$code, $purpose]);
            $row = $stmt->fetch();

            if (!$row) {
                return null;
            }

            // 原子核销：只有第一次 UPDATE 成功的请求视为有效
            $stmt = $this->pdo->prepare(
                "UPDATE wechat_login_codes SET is_used = 1 WHERE id = ? AND is_used = 0"
            );
            $ok = $stmt->execute([$row['id']]) && $stmt->rowCount() === 1;

            return $ok ? $row['openid'] : null;
        } catch (PDOException $e) {
            error_log('WechatAuth::consumeCode 错误: ' . $e->getMessage());
            return null;
        }
    }

    /* ------------------------------------------------------------------
     * 用户绑定
     * ------------------------------------------------------------------ */

    /**
     * 按 openid 查询已绑定用户
     * @return array|false
     */
    public function getUserByOpenid($openid) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, username, email, role, status, city, avatar, wechat_openid
                 FROM users WHERE wechat_openid = ? LIMIT 1"
            );
            $stmt->execute([trim((string)$openid)]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('WechatAuth::getUserByOpenid 错误: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 绑定 openid 到已有账号（一人只能绑一个微信号，一号只能被一人绑）
     * @return bool
     */
    public function bindOpenid($userId, $openid) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users SET wechat_openid = ? WHERE id = ? AND wechat_openid IS NULL"
            );
            $ok = $stmt->execute([trim((string)$openid), (int)$userId]);
            if ($ok && $stmt->rowCount() === 0) {
                // 该账号已绑定过其他微信
                return false;
            }
            return (bool)$ok;
        } catch (PDOException $e) {
            // 唯一键冲突：这个 openid 已被其他账号绑定
            return false;
        }
    }

    /**
     * 解绑（用户中心/管理员用）
     */
    public function unbindOpenid($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET wechat_openid = NULL WHERE id = ?");
        return $stmt->execute([(int)$userId]);
    }

    /**
     * 生成不冲突的微信默认用户名（wx_ 开头）
     * @return string
     */
    public function generateUsername() {
        for ($try = 0; $try < 10; $try++) {
            $name = 'wx_' . strtolower(bin2hex(random_bytes(3))); // wx_a1b2c3，共10字符
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$name]);
            if (!$stmt->fetch()) {
                return $name;
            }
        }
        return 'wx_' . time() . mt_rand(100, 999);
    }

    /**
     * 扫码注册建号：邮箱/手机至少补全一项
     *
     * @param string $openid
     * @param string $username
     * @param string|null $email   为空传 null（列已改为可空，勿传 ''）
     * @param string|null $phone
     * @param string|null $password 明文密码；不设则生成随机不可登录密码（仅微信登录）
     * @param string $city
     * @return int 新用户ID（失败抛异常）
     * @throws Exception
     */
    public function createUserFromWechat($openid, $username, $email, $phone, $password, $city = '') {
        $openid  = trim((string)$openid);
        $username = trim((string)$username);
        $email   = $email !== null ? strtolower(trim($email)) : null;
        $phone   = $phone !== null ? trim($phone) : null;

        if ($email === '') $email = null;
        if ($phone === '') $phone = null;

        if ($openid === '') {
            throw new Exception('缺少微信身份，请重新扫码');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
            throw new Exception('用户名需4-20位字母/数字/下划线');
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('邮箱格式不正确');
        }
        if ($phone !== null && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw new Exception('手机号格式不正确');
        }
        if ($email === null && $phone === null) {
            throw new Exception('请至少填写邮箱或手机号其中一项');
        }
        if ($password !== null && $password !== '') {
            if (strlen($password) < 6) {
                throw new Exception('密码至少6位');
            }
            if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                throw new Exception('密码需包含字母和数字');
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
        } else {
            // 未设密码：写入随机哈希（无法通过账密登录，只能扫码登录；后续可在个人中心设置）
            $hashed = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (username, email, phone, password, city, wechat_openid)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$username, $email, $phone, $hashed, $city, $openid]);
            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    throw new Exception('该邮箱已被注册');
                }
                if (strpos($e->getMessage(), 'username') !== false) {
                    throw new Exception('该用户名已被使用');
                }
                if (strpos($e->getMessage(), 'wechat_openid') !== false) {
                    throw new Exception('该微信已绑定其他账号，请直接用验证码登录');
                }
                if (strpos($e->getMessage(), 'phone') !== false) {
                    throw new Exception('该手机号已被使用');
                }
            }
            throw $e;
        }
    }
}
