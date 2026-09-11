<?php
/**
 * 用户持仓（城市人气值）数据层
 *
 * 设计前提：站内人气值是一份「用户自管记录」，真实交易发生在站外 blockcity.vip。
 * 本类只负责个人面板的读写：
 *   - getUserCities()           取用户「持有区块」的城市（含城市名与城市单价）
 *   - getUserPopularityMap()    批量读取用户各城市人气值
 *   - saveUserPopularities()    整表一次性 upsert 人气值（白名单 + 非负整数校验）
 *
 * 说明：合并块（merged_blocks）在认领时已把组内每个单块写入 blocks 并置为 sold，
 * 因此持区块城市只需查询 blocks 单表，不需要再关联 merged_blocks。
 */
class UserHoldings {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 取用户持有区块的城市（去重），附带城市名与城市单价
     *
     * @param int $userId
     * @return array [['city_id'=>int,'name'=>string,'bct_current_price'=>float], ...]
     */
    public function getUserCities($userId) {
        $sql = "SELECT DISTINCT c.id AS city_id, c.name AS name, c.bct_current_price AS bct_current_price
                FROM blocks b
                JOIN cities c ON c.id = b.city_id
                WHERE b.owner_id = ? AND b.status = 'sold'
                ORDER BY c.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([(int)$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * 批量读取用户在「全部城市」的人气值
     *
     * @param int $userId
     * @return array city => popularity（无记录的城市不返回，调用方按 0 处理）
     */
    public function getUserPopularityMap($userId) {
        $stmt = $this->pdo->prepare(
            "SELECT city, popularity FROM user_city_popularity WHERE user_id = ?"
        );
        $stmt->execute([(int)$userId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string)$row['city']] = (int)$row['popularity'];
        }
        return $map;
    }

    /**
     * 整表保存用户各城市人气值
     *
     * 规则：
     *   - 白名单：仅处理「当前用户持有区块的城市」，其余 city 键直接忽略（越权防护）
     *   - 空串 → 0；非负整数字符串 → 原值；负数 / 非整数 → 记为该行非法，跳过并返回错误
     *   - 合法的城市在同一事务内一次性 upsert，任一写入失败则全部回滚
     *
     * @param int   $userId
     * @param array $input  city => value（value 为表单原始值，可为空串）
     * @return array ['saved'=>int, 'errors'=>array(city=>string)]
     */
    public function saveUserPopularities($userId, array $input) {
        $cities = $this->getUserCities($userId);

        $whitelist = [];
        foreach ($cities as $c) {
            $whitelist[(string)$c['name']] = true;
        }

        $toSave = [];
        $errors = [];
        foreach ($input as $city => $value) {
            $city = (string)$city;
            // 越权防护：非持有城市直接忽略，不写入也不报错
            if (!isset($whitelist[$city])) {
                continue;
            }
            if (is_array($value)) {
                $errors[$city] = $city . ' 输入不合法，未保存';
                continue;
            }
            $raw = trim((string)$value);
            if ($raw === '') {
                $toSave[$city] = 0;
                continue;
            }
            if (!preg_match('/^\d+$/', $raw)) {
                $errors[$city] = $city . ' 需填写非负整数，未保存';
                continue;
            }
            $toSave[$city] = (int)$raw;
        }

        $saved = 0;
        if (!empty($toSave)) {
            // 嵌套事务安全：若调用方已开启事务，则加入现有事务由其统一提交
            $nested = $this->pdo->inTransaction();
            if (!$nested) {
                $this->pdo->beginTransaction();
            }
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO user_city_popularity (user_id, city, popularity)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE popularity = VALUES(popularity), updated_at = NOW()"
                );
                foreach ($toSave as $city => $qty) {
                    $stmt->execute([(int)$userId, $city, $qty]);
                    $saved++;
                }
                if (!$nested) {
                    $this->pdo->commit();
                }
            } catch (Exception $e) {
                if (!$nested && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('保存用户持仓失败: ' . $e->getMessage());
                return ['saved' => 0, 'errors' => ['__all__' => '保存失败，请稍后再试']];
            }
        }

        return ['saved' => $saved, 'errors' => $errors];
    }
}
