<?php
// classes/Coupon.php
class Coupon {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 创建优惠券
     */
    public function createCoupon($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO coupons 
                (shop_id, title, code, type, value, min_order_amount, max_discount, total_quantity, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['shop_id'],
                $data['title'],
                $data['code'] ?? null,
                $data['type'],
                $data['value'],
                $data['min_order_amount'] ?? 0,
                $data['max_discount'] ?? null,
                $data['total_quantity'] ?? 0,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['status'] ?? 'active'
            ]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("创建优惠券失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新优惠券
     */
    public function updateCoupon($id, $data) {
        try {
            $allowed = ['title', 'code', 'type', 'value', 'min_order_amount', 'max_discount', 'total_quantity', 'start_date', 'end_date', 'status'];
            $sets = [];
            $params = [];
            foreach ($data as $k => $v) {
                if (in_array($k, $allowed)) {
                    $sets[] = "$k = ?";
                    $params[] = $v;
                }
            }
            if (empty($sets)) return false;
            $params[] = $id;
            $sql = "UPDATE coupons SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("更新优惠券失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除优惠券
     */
    public function deleteCoupon($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM coupons WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("删除优惠券失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据ID获取优惠券
     */
    public function getCouponById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM coupons WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取优惠券失败: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取店铺优惠券列表
     */
    public function getShopCoupons($shopId, $status = 'all') {
        try {
            $where = "shop_id = ?";
            $params = [$shopId];
            if ($status !== 'all') {
                $where .= " AND status = ?";
                $params[] = $status;
            }
            $stmt = $this->pdo->prepare("SELECT * FROM coupons WHERE $where ORDER BY created_at DESC");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取店铺优惠券失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺优惠券统计
     */
    public function getShopCouponStats($shopId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(used_quantity) as total_used
                FROM coupons
                WHERE shop_id = ?
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取优惠券统计失败: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'expired' => 0, 'total_used' => 0];
        }
    }

    /**
     * 验证优惠券是否可用
     */
    public function validateCoupon($code, $shopId, $orderAmount = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM coupons 
                WHERE code = ? AND shop_id = ? AND status = 'active'
            ");
            $stmt->execute([$code, $shopId]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$coupon) return ['valid' => false, 'message' => '优惠券不存在或已失效'];

            // 检查有效期
            $today = date('Y-m-d');
            if ($coupon['start_date'] && $today < $coupon['start_date']) {
                return ['valid' => false, 'message' => '优惠券尚未生效'];
            }
            if ($coupon['end_date'] && $today > $coupon['end_date']) {
                return ['valid' => false, 'message' => '优惠券已过期'];
            }

            // 检查最低订单金额
            if ($coupon['min_order_amount'] > 0 && $orderAmount < $coupon['min_order_amount']) {
                return ['valid' => false, 'message' => '订单金额未满 ' . $coupon['min_order_amount'] . ' 元'];
            }

            // 检查剩余数量
            if ($coupon['total_quantity'] > 0 && $coupon['used_quantity'] >= $coupon['total_quantity']) {
                return ['valid' => false, 'message' => '优惠券已领完'];
            }

            // 计算优惠金额
            $discount = 0;
            if ($coupon['type'] === 'fixed') {
                $discount = min((float)$coupon['value'], (float)$orderAmount);
            } else {
                $discount = $orderAmount * ($coupon['value'] / 100);
                if ($coupon['max_discount'] > 0) {
                    $discount = min($discount, (float)$coupon['max_discount']);
                }
            }

            return [
                'valid' => true,
                'coupon' => $coupon,
                'discount' => round($discount, 2),
                'final_amount' => max(0, round($orderAmount - $discount, 2))
            ];
        } catch (Exception $e) {
            error_log("验证优惠券失败: " . $e->getMessage());
            return ['valid' => false, 'message' => '验证失败'];
        }
    }

    /**
     * 使用优惠券（增加已用数量）
     */
    public function applyCoupon($couponId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE coupons 
                SET used_quantity = used_quantity + 1 
                WHERE id = ? AND (total_quantity = 0 OR used_quantity < total_quantity)
            ");
            $stmt->execute([$couponId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("使用优惠券失败: " . $e->getMessage());
            return false;
        }
    }
}
?>