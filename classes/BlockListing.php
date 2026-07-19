<?php
require_once __DIR__ . '/Block.php';
require_once __DIR__ . '/Notification.php';

class BlockListing {
    private $pdo;
    private $block;
    private $notify;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->block = new Block($pdo);
        $this->notify = new Notification($pdo);
    }

    /**
     * 上架挂牌（单块或合并块）
     * @param array $data city_id, seller_id, price, currency, block_id(单块), merged_block_id(合并块), contact_phone, contact_wechat
     * @return int|false 成功返回 listing id
     */
    public function createListing($data) {
        $cityId    = intval($data['city_id']);
        $sellerId  = intval($data['seller_id']);
        $price     = floatval($data['price']);
        $currency  = in_array($data['currency'], ['popularity', 'cny']) ? $data['currency'] : 'cny';
        $blockId   = !empty($data['block_id']) ? intval($data['block_id']) : null;
        $mergedId  = !empty($data['merged_block_id']) ? intval($data['merged_block_id']) : null;
        $phone     = $data['contact_phone'] ?? null;
        $wechat    = $data['contact_wechat'] ?? null;

        if ($price <= 0) return false;
        if ($blockId === null && $mergedId === null) return false;

        // 合并块：解析组内区块，取首个 block id 作为定位块
        if ($mergedId !== null) {
            $ids = $this->block->getMergedBlockIds($mergedId);
            if (empty($ids)) return false;
            if ($blockId === null) $blockId = $ids[0];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO block_listings
                (city_id, seller_id, block_id, merged_block_id, price, currency, status, buyer_id, contact_phone, contact_wechat, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'listed', NULL, ?, ?, NOW())");
            $stmt->execute([$cityId, $sellerId, $blockId, $mergedId, $price, $currency, $phone, $wechat]);
            $id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("createListing 失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取挂牌详情（含代表区块的皮肤与卖家信息）
     */
    public function getListingDetail($id) {
        $stmt = $this->pdo->prepare("
            SELECT l.*,
                   b.city_id, b.zone, b.block_number,
                   b.display_type, b.display_image, b.display_text, b.display_color,
                   u.username AS seller_name, u.city AS seller_city
            FROM block_listings l
            LEFT JOIN blocks b ON l.block_id = b.id
            LEFT JOIN users u ON l.seller_id = u.id
            WHERE l.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // 合并块：解析尺寸与组内最小编号
        if (!empty($row['merged_block_id'])) {
            $mstmt = $this->pdo->prepare("SELECT merged_blocks, merge_size FROM merged_blocks WHERE id = ?");
            $mstmt->execute([$row['merged_block_id']]);
            $m = $mstmt->fetch(PDO::FETCH_ASSOC);
            if ($m) {
                $nums = array_map('trim', explode(',', $m['merged_blocks']));
                $row['merged_size'] = $m['merge_size'];
                $row['merged_min_number'] = min($nums);
                $row['is_merged'] = true;
            }
        }
        return $row;
    }

    /**
     * 按城市获取挂牌列表（默认仅 listed）
     */
    public function getListingsByCity($cityId, $status = 'listed') {
        $stmt = $this->pdo->prepare("
            SELECT l.*, u.username AS seller_name,
                   b.zone, b.block_number, b.display_type, b.display_image, b.display_text, b.display_color
            FROM block_listings l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN blocks b ON l.block_id = b.id
            WHERE l.city_id = ? AND l.status = ?
            ORDER BY l.created_at DESC");
        $stmt->execute([$cityId, $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 卖家自己的挂牌（listed + pending）
     */
    public function getUserListings($userId) {
        $stmt = $this->pdo->prepare("
            SELECT l.*, b.zone, b.block_number, b.display_type, b.display_image, b.display_text, b.display_color
            FROM block_listings l
            LEFT JOIN blocks b ON l.block_id = b.id
            WHERE l.seller_id = ? AND l.status IN ('listed','pending')
            ORDER BY l.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 买家待处理（已下单 pending 的购买）
     */
    public function getBuyerPending($userId) {
        $stmt = $this->pdo->prepare("
            SELECT l.*, u.username AS seller_name,
                   b.zone, b.block_number, b.display_type, b.display_image, b.display_text, b.display_color
            FROM block_listings l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN blocks b ON l.block_id = b.id
            WHERE l.buyer_id = ? AND l.status = 'pending'
            ORDER BY l.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取消挂牌（仅卖家在 listed/pending 时可取消）
     */
    public function cancelListing($id, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE block_listings SET status = 'canceled', updated_at = NOW()
            WHERE id = ? AND seller_id = ? AND status IN ('listed','pending')");
        return $stmt->execute([$id, $userId]);
    }

    /**
     * 买家下单（pending），不扣任何余额
     */
    public function placeOrder($id, $buyerId) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM block_listings WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$listing) throw new Exception("挂牌不存在");
            if ($listing['status'] !== 'listed') throw new Exception("该区块当前不可购买");
            if ($listing['seller_id'] == $buyerId) throw new Exception("不能购买自己的区块");

            $stmt = $this->pdo->prepare("
                UPDATE block_listings SET status = 'pending', buyer_id = ?, updated_at = NOW()
                WHERE id = ? AND status = 'listed'");
            $stmt->execute([$buyerId, $id]);

            // 通知卖家有新购买意向
            $this->notify->sendSystemNotify(
                $listing['seller_id'], 'block_buy_intent', $id,
                '您的区块挂牌收到新的购买意向，请到「区块管理」确认收款后完成交易。',
                '../block/block/confirm_sale.php?listing=' . $id
            );
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("placeOrder 失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 买家点击"我已支付"（人气值模式）：仅通知卖家，状态维持 pending
     */
    public function markPaid($id, $buyerId) {
        $stmt = $this->pdo->prepare("SELECT * FROM block_listings WHERE id = ?");
        $stmt->execute([$id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$listing || $listing['buyer_id'] != $buyerId || $listing['status'] !== 'pending') {
            return false;
        }
        return $this->notify->sendSystemNotify(
            $listing['seller_id'], 'block_buyer_paid', $id,
            '买家表示已完成付款，请核对后确认完成交易。',
            '../block/block/confirm_sale.php?listing=' . $id
        );
    }

    /**
     * 卖家确认完成交易（所有权转移 + 写流水）
     */
    public function confirmSale($id, $sellerId) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM block_listings WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$listing) throw new Exception("挂牌不存在");
            if ($listing['seller_id'] != $sellerId) throw new Exception("无权操作该交易");
            if ($listing['status'] !== 'pending') throw new Exception("该交易当前不可确认");
            if (empty($listing['buyer_id'])) throw new Exception("尚未有买家下单");

            $buyerId = intval($listing['buyer_id']);
            $cityId  = intval($listing['city_id']);

            // 1. 转移所有权
            if (!empty($listing['merged_block_id'])) {
                $ids = $this->block->getMergedBlockIds($listing['merged_block_id']);
                if (!empty($ids)) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $params = array_merge($ids, [$buyerId]);
                    $stmt = $this->pdo->prepare("UPDATE blocks SET owner_id = ? WHERE id IN ($ph)");
                    $stmt->execute($params);
                }
                $stmt = $this->pdo->prepare("UPDATE merged_blocks SET owner_id = ? WHERE id = ?");
                $stmt->execute([$buyerId, $listing['merged_block_id']]);
                $repBlockId = $listing['block_id'];
            } else {
                $repBlockId = $listing['block_id'];
                $stmt = $this->pdo->prepare("UPDATE blocks SET owner_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$buyerId, $repBlockId]);
            }

            // 2. 挂牌完成
            $stmt = $this->pdo->prepare("
                UPDATE block_listings SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$id]);

            // 3. 写交易流水（transactions 表约定字段）
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (block_id, seller_id, buyer_id, price, transaction_type, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'resale', 'completed', NOW(), NOW())");
            $stmt->execute([$repBlockId, $sellerId, $buyerId, $listing['price']]);

            // 4. 更新城市统计（居民数可能变化）
            $this->block->updateCityStats($cityId);

            // 5. 通知买家
            $this->notify->sendSystemNotify(
                $buyerId, 'block_sale_done', $id,
                '恭喜！您购买的人气值区块已完成交易，所有权已转移给您。',
                '../block/block/view.php?id=' . $repBlockId
            );

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("confirmSale 失败: " . $e->getMessage());
            return false;
        }
    }
}
