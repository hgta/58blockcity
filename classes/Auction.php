<?php
/**
 * 58拍卖 — 拍卖业务类
 *
 * 拍卖品支持区块(blocks)和 NFT 头像(nft_city_user 持有记录)。
 * 与 block.58.tl / nft.58.tl 联动：成交后转移对应物品所有权。
 */

require_once __DIR__ . '/Block.php';
require_once __DIR__ . '/NFT.php';
require_once __DIR__ . '/Notification.php';

class Auction {
    private $pdo;
    private $block;
    private $nft;
    private $notify;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->block = new Block($pdo);
        $this->nft = new NFT($pdo);
        $this->notify = new Notification($pdo);
    }

    /* ========== 发布拍卖 ========== */

    /**
     * 发布拍卖
     * @param int    $sellerId  卖家
     * @param string $itemType  block|nft
     * @param int    $itemId    blocks.id 或 nft_city_user.id
     * @param array  $data      start_price, reserve_price, bid_increment, start_time, end_time, currency, accept_cities
     * @return int|string 成功返回拍卖 id，失败返回错误信息字符串
     */
    public function createAuction($sellerId, $itemType, $itemId, $data) {
        $sellerId = intval($sellerId);
        $itemId   = intval($itemId);

        if (!in_array($itemType, ['block', 'nft'], true)) return '拍卖品类型无效';

        $startPrice    = floatval($data['start_price'] ?? 0);
        $reservePrice  = isset($data['reserve_price']) && $data['reserve_price'] !== '' ? floatval($data['reserve_price']) : null;
        $bidIncrement  = floatval($data['bid_increment'] ?? 0);
        $startTime     = $data['start_time'] ?? '';
        $endTime       = $data['end_time'] ?? '';
        $currency      = in_array($data['currency'] ?? '', ['popularity', 'cny'], true) ? $data['currency'] : 'cny';
        $acceptCities  = $data['accept_cities'] ?? [];

        if ($startPrice <= 0) return '请填写有效的起拍价';
        if ($bidIncrement <= 0) return '请填写有效的加价幅度';
        if (empty($startTime) || empty($endTime)) return '请设置起止时间';
        if (strtotime($endTime) <= strtotime($startTime)) return '截止时间必须晚于开始时间';
        if ($reservePrice !== null && $reservePrice < $startPrice) return '底价不能低于起拍价';

        // 归属校验
        if (!$this->verifyOwnership($itemType, $itemId, $sellerId)) {
            return '您不拥有该物品，无法发起拍卖';
        }

        // 互斥校验：是否已有 active 拍卖 / 一口价挂牌
        if ($this->isItemInActiveAuction($itemType, $itemId)) {
            return '该物品已在拍卖中';
        }
        if ($this->isItemListed($itemType, $itemId)) {
            return '该物品已在一口价挂牌中，请先取消挂牌';
        }

        // 接受城市（仅人气值货币时有效）
        $acceptCitiesJson = null;
        if ($currency === 'popularity') {
            $cities = is_array($acceptCities) ? $acceptCities : json_decode($acceptCities, true);
            if (is_array($cities) && !empty($cities)) {
                $acceptCitiesJson = json_encode(array_values(array_filter(array_map('intval', $cities))));
            }
        }

        // 判断状态：开始时间在未来则 pending，否则 active
        $now = time();
        $status = strtotime($startTime) > $now ? 'pending' : 'active';

        $stmt = $this->pdo->prepare("
            INSERT INTO auctions
                (item_type, item_id, seller_id, start_price, reserve_price, bid_increment,
                 start_time, end_time, current_price, currency, accept_cities, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([
            $itemType, $itemId, $sellerId, $startPrice, $reservePrice, $bidIncrement,
            $startTime, $endTime, $startPrice, $currency, $acceptCitiesJson, $status,
        ]);
        return $ok ? intval($this->pdo->lastInsertId()) : '创建拍卖失败';
    }

    /* ========== 归属 / 互斥校验 ========== */

    /**
     * 校验物品归属
     */
    private function verifyOwnership($itemType, $itemId, $userId) {
        if ($itemType === 'block') {
            $b = $this->block->getBlockById($itemId);
            return $b && intval($b['owner_id']) === intval($userId);
        }
        // nft：item_id 指向 nft_city_user.id
        $stmt = $this->pdo->prepare("SELECT user_id FROM nft_city_user WHERE id = ? AND is_current = 1");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && intval($row['user_id']) === intval($userId);
    }

    /**
     * 是否已有 active/pending 拍卖
     */
    public function isItemInActiveAuction($itemType, $itemId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM auctions
            WHERE item_type = ? AND item_id = ? AND status IN ('pending','active')");
        $stmt->execute([$itemType, $itemId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * 是否已在一口价挂牌中
     */
    public function isItemListed($itemType, $itemId) {
        if ($itemType === 'block') {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM block_listings
                WHERE (block_id = ? OR merged_block_id = ?) AND status IN ('listed','pending')");
            $stmt->execute([$itemId, $itemId]);
            return $stmt->fetchColumn() > 0;
        }
        // nft：item_id 指向 nft_city_user.id，需先取 nft_id + city_id
        $ncu = $this->pdo->prepare("SELECT nft_id, city_id FROM nft_city_user WHERE id = ?");
        $ncu->execute([$itemId]);
        $rec = $ncu->fetch(PDO::FETCH_ASSOC);
        if (!$rec) return false;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM nft_sales
            WHERE nft_id = ? AND city_id = ? AND status IN ('active','pending')");
        $stmt->execute([$rec['nft_id'], $rec['city_id']]);
        return $stmt->fetchColumn() > 0;
    }

    /* ========== 出价 ========== */

    /**
     * 出价
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public function placeBid($auctionId, $bidderId, $amount) {
        $auctionId = intval($auctionId);
        $bidderId  = intval($bidderId);
        $amount    = floatval($amount);

        if ($bidderId <= 0) return ['ok' => false, 'msg' => '请先登录'];
        if ($amount <= 0) return ['ok' => false, 'msg' => '出价金额无效'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM auctions WHERE id = ? FOR UPDATE");
            $stmt->execute([$auctionId]);
            $a = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$a) throw new Exception('拍卖不存在');
            if ($a['status'] !== 'active') throw new Exception('该拍卖已结束或未开始');
            if (intval($a['seller_id']) === $bidderId) throw new Exception('不能出价自己拍卖的物品');

            $now = time();
            if ($now < strtotime($a['start_time'])) throw new Exception('拍卖尚未开始');
            if ($now > strtotime($a['end_time'])) throw new Exception('拍卖已截止');

            $currentPrice = floatval($a['current_price'] ?? $a['start_price']);
            $minBid = $currentPrice + floatval($a['bid_increment']);
            // 首次出价只需 ≥ 起拍价
            if ($a['current_bidder_id'] === null) {
                $minBid = floatval($a['start_price']);
            }
            if ($amount < $minBid) {
                throw new Exception('出价需 ≥ ' . number_format($minBid, 2));
            }

            // 写入出价记录
            $stmt = $this->pdo->prepare("INSERT INTO auction_bids (auction_id, bidder_id, amount, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$auctionId, $bidderId, $amount]);

            // 更新当前价
            $stmt = $this->pdo->prepare("UPDATE auctions SET current_price = ?, current_bidder_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$amount, $bidderId, $auctionId]);

            $this->pdo->commit();
            return ['ok' => true, 'msg' => '出价成功'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /* ========== 状态推进 ========== */

    /**
     * 惰性推进状态机：先激活到点的 pending，再结算到点的 active。
     * 保持纯惰性、无 cron，与现有机制一致。
     */
    public function tick() {
        $this->activateStarted();
        $this->settleExpired();
    }

    /**
     * pending → active：开始时间已到的拍卖自动开拍
     */
    public function activateStarted() {
        $stmt = $this->pdo->prepare("
            UPDATE auctions SET status = 'active', updated_at = NOW()
            WHERE status = 'pending' AND start_time <= NOW()");
        $stmt->execute();
    }

    /* ========== 惰性结算 ========== */

    /**
     * 结算所有已到期的 active 拍卖（惰性调用）
     */
    public function settleExpired() {
        $stmt = $this->pdo->prepare("SELECT id FROM auctions WHERE status = 'active' AND end_time < NOW()");
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->settle(intval($id));
        }
    }

    /**
     * 结算单个拍卖：成交转移所有权 / 流拍置 ended
     */
    public function settle($auctionId) {
        $auctionId = intval($auctionId);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM auctions WHERE id = ? FOR UPDATE");
            $stmt->execute([$auctionId]);
            $a = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$a || $a['status'] !== 'active') {
                $this->pdo->rollBack();
                return;
            }

            $bidderId = intval($a['current_bidder_id'] ?? 0);
            $price    = floatval($a['current_price'] ?? $a['start_price']);
            $reserve  = $a['reserve_price'] !== null ? floatval($a['reserve_price']) : null;

            // 流拍：无出价，或低于底价
            if ($bidderId <= 0 || ($reserve !== null && $price < $reserve)) {
                $stmt = $this->pdo->prepare("UPDATE auctions SET status = 'ended', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$auctionId]);
                $this->pdo->commit();
                return;
            }

            // 成交：转移所有权
            $this->transferOwnership($a['item_type'], intval($a['item_id']), $bidderId, $price);

            $stmt = $this->pdo->prepare("UPDATE auctions SET status = 'sold', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$auctionId]);

            // 通知买卖双方
            $this->notify->sendSystemNotify(
                $bidderId, 'auction_won', $auctionId,
                '恭喜！您已赢得拍卖，物品所有权已转移给您。',
                '../bid/view.php?id=' . $auctionId
            );
            $this->notify->sendSystemNotify(
                intval($a['seller_id']), 'auction_sold', $auctionId,
                '您的拍卖已成交，成交价 ' . number_format($price, 2) . '。',
                '../bid/view.php?id=' . $auctionId
            );

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Auction::settle 失败: " . $e->getMessage());
        }
    }

    /**
     * 成交转移所有权
     */
    private function transferOwnership($itemType, $itemId, $buyerId, $price) {
        if ($itemType === 'block') {
            $stmt = $this->pdo->prepare("UPDATE blocks SET owner_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$buyerId, $itemId]);
            // 写流水
            $b = $this->block->getBlockById($itemId);
            $sellerId = $b ? intval($b['owner_id']) : 0;
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (block_id, seller_id, buyer_id, price, transaction_type, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'resale', 'completed', NOW(), NOW())");
            $stmt->execute([$itemId, $sellerId, $buyerId, $price]);
        } else {
            // nft：item_id 指向 nft_city_user.id
            $ncu = $this->pdo->prepare("SELECT nft_id, city_id, user_id FROM nft_city_user WHERE id = ?");
            $ncu->execute([$itemId]);
            $rec = $ncu->fetch(PDO::FETCH_ASSOC);
            if (!$rec) return;
            $sellerId = intval($rec['user_id']);
            // 转移当前持有给买家
            $stmt = $this->pdo->prepare("UPDATE nft_city_user SET user_id = ?, is_listed = 0 WHERE id = ?");
            $stmt->execute([$buyerId, $itemId]);
            // 写 NFT 交易流水
            $stmt = $this->pdo->prepare("
                INSERT INTO nft_transactions (nft_id, seller_id, buyer_id, price, currency, transaction_type, status, city_id, completed_at, created_at)
                VALUES (?, ?, ?, ?, 'popularity', 'platform', 'completed', ?, NOW(), NOW())");
            $stmt->execute([$rec['nft_id'], $sellerId, $buyerId, $price, $rec['city_id']]);
        }
    }

    /* ========== 卖家管理 ========== */

    /**
     * 取消拍卖（仅卖家本人）
     * 授权：pending 可取消；active 且无人出价可取消；active 有人出价/终态拒绝。
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public function cancelAuction($auctionId, $sellerId) {
        $auctionId = intval($auctionId);
        $sellerId  = intval($sellerId);
        if ($auctionId <= 0 || $sellerId <= 0) return ['ok' => false, 'msg' => '参数无效'];

        $stmt = $this->pdo->prepare("SELECT * FROM auctions WHERE id = ?");
        $stmt->execute([$auctionId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return ['ok' => false, 'msg' => '拍卖不存在'];
        if (intval($a['seller_id']) !== $sellerId) return ['ok' => false, 'msg' => '无权操作该拍卖'];

        $status = $a['status'];
        if ($status === 'pending') {
            // 未开始，允许
        } elseif ($status === 'active') {
            if (intval($a['current_bidder_id'] ?? 0) > 0) {
                return ['ok' => false, 'msg' => '该拍卖已有人出价，无法取消'];
            }
            // 无人出价，允许
        } else {
            return ['ok' => false, 'msg' => '该拍卖已结束，无法取消'];
        }

        // 条件更新防并发：取消瞬间若已有人出价，则不满足条件，rowCount=0
        $stmt = $this->pdo->prepare("
            UPDATE auctions SET status = 'canceled', updated_at = NOW()
            WHERE id = ? AND status IN ('pending','active') AND current_bidder_id IS NULL");
        $stmt->execute([$auctionId]);
        if ($stmt->rowCount() > 0) {
            return ['ok' => true, 'msg' => '已取消该拍卖'];
        }
        return ['ok' => false, 'msg' => '取消失败，拍卖状态已变化，请刷新后重试'];
    }

    /**
     * 编辑拍卖（仅卖家本人且仅 pending 状态；物品不可更换）
     * 允许修改：起拍价/底价/加价幅度/起止时间/货币/接受城市。
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public function updateAuction($auctionId, $sellerId, $data) {
        $auctionId = intval($auctionId);
        $sellerId  = intval($sellerId);
        if ($auctionId <= 0 || $sellerId <= 0) return ['ok' => false, 'msg' => '参数无效'];

        $stmt = $this->pdo->prepare("SELECT * FROM auctions WHERE id = ?");
        $stmt->execute([$auctionId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return ['ok' => false, 'msg' => '拍卖不存在'];
        if (intval($a['seller_id']) !== $sellerId) return ['ok' => false, 'msg' => '无权操作该拍卖'];
        if ($a['status'] !== 'pending') return ['ok' => false, 'msg' => '仅「未开始」的拍卖可编辑'];

        // 校验（与 createAuction 一致）
        $startPrice    = floatval($data['start_price'] ?? 0);
        $reservePrice  = isset($data['reserve_price']) && $data['reserve_price'] !== '' ? floatval($data['reserve_price']) : null;
        $bidIncrement  = floatval($data['bid_increment'] ?? 0);
        $startTime     = $data['start_time'] ?? '';
        $endTime       = $data['end_time'] ?? '';
        $currency      = in_array($data['currency'] ?? '', ['popularity', 'cny'], true) ? $data['currency'] : 'cny';

        if ($startPrice <= 0) return ['ok' => false, 'msg' => '请填写有效的起拍价'];
        if ($bidIncrement <= 0) return ['ok' => false, 'msg' => '请填写有效的加价幅度'];
        if (empty($startTime) || empty($endTime)) return ['ok' => false, 'msg' => '请设置起止时间'];
        if (strtotime($endTime) <= strtotime($startTime)) return ['ok' => false, 'msg' => '截止时间必须晚于开始时间'];
        if ($reservePrice !== null && $reservePrice < $startPrice) return ['ok' => false, 'msg' => '底价不能低于起拍价'];

        // 接受城市（仅人气值货币时有效）
        $acceptCitiesJson = null;
        if ($currency === 'popularity') {
            $cities = isset($data['accept_cities']) ? (is_array($data['accept_cities']) ? $data['accept_cities'] : json_decode($data['accept_cities'], true)) : [];
            if (is_array($cities) && !empty($cities)) {
                $acceptCitiesJson = json_encode(array_values(array_filter(array_map('intval', $cities))));
            }
        }

        // 保存后重算状态：编辑到点时间即直接开拍
        $status = strtotime($startTime) > time() ? 'pending' : 'active';

        $stmt = $this->pdo->prepare("
            UPDATE auctions
            SET start_price = ?, reserve_price = ?, bid_increment = ?,
                start_time = ?, end_time = ?, currency = ?, accept_cities = ?,
                current_price = ?, status = ?, updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([
            $startPrice, $reservePrice, $bidIncrement,
            $startTime, $endTime, $currency, $acceptCitiesJson,
            $startPrice, $status, $auctionId,
        ]);
        return ['ok' => true, 'msg' => '修改已保存'];
    }

    /* ========== 查询 ========== */

    /**
     * 拍卖中列表
     */
    public function getActiveAuctions($page = 1, $perPage = 20, $itemType = '', $currency = '') {
        $offset = (max(1, intval($page)) - 1) * intval($perPage);
        $where = ["a.status IN ('pending','active')"];
        $params = [];
        if (in_array($itemType, ['block', 'nft'], true)) {
            $where[] = "a.item_type = ?";
            $params[] = $itemType;
        }
        if (in_array($currency, ['popularity', 'cny'], true)) {
            $where[] = "a.currency = ?";
            $params[] = $currency;
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM auctions a WHERE " . $whereSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username AS seller_name, u.avatar AS seller_avatar
            FROM auctions a
            LEFT JOIN users u ON a.seller_id = u.id
            WHERE {$whereSql}
            ORDER BY a.end_time ASC
            LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        return [
            'list' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $total > 0 ? ceil($total / $perPage) : 0,
        ];
    }

    /**
     * 拍卖详情（含物品信息）
     */
    public function getAuctionById($id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username AS seller_name, u.avatar AS seller_avatar
            FROM auctions a
            LEFT JOIN users u ON a.seller_id = u.id
            WHERE a.id = ?");
        $stmt->execute([intval($id)]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;

        // 补充物品信息
        if ($a['item_type'] === 'block') {
            $b = $this->block->getBlockById(intval($a['item_id']));
            if ($b) {
                $a['item_title'] = ($b['city_name'] ?? '') . ' ' . ($b['zone'] ?? '') . '区 #' . ($b['block_number'] ?? '');
                // 区块皮肤图片在 block 子站，用跨站绝对路径
                $a['item_image'] = !empty($b['display_image']) ? 'https://block.58.tl/' . ltrim($b['display_image'], '/') : null;
            }
        } else {
            $ncu = $this->pdo->prepare("
                SELECT ncu.id, ncu.nft_id, ncu.city_id, c.name AS city_name, n.code, n.base_image
                FROM nft_city_user ncu
                LEFT JOIN cities c ON ncu.city_id = c.id
                LEFT JOIN nft_avatars n ON ncu.nft_id = n.id
                WHERE ncu.id = ?");
            $ncu->execute([intval($a['item_id'])]);
            $rec = $ncu->fetch(PDO::FETCH_ASSOC);
            if ($rec) {
                $a['item_title'] = 'NFT头像 #' . $rec['code'] . '（' . $rec['city_name'] . '）';
                // NFT 图片在 nft 子站，用跨站绝对路径
                $a['item_image'] = !empty($rec['base_image']) ? 'https://nft.58.tl/avatar/' . $rec['base_image'] : null;
            }
        }
        return $a;
    }

    /**
     * 出价记录
     */
    public function getBids($auctionId, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.username AS bidder_name, u.avatar AS bidder_avatar
            FROM auction_bids b
            LEFT JOIN users u ON b.bidder_id = u.id
            WHERE b.auction_id = ?
            ORDER BY b.amount DESC, b.created_at ASC
            LIMIT " . intval($limit));
        $stmt->execute([intval($auctionId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 我发布的拍卖
     */
    public function getMyAuctions($userId) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username AS seller_name
            FROM auctions a
            LEFT JOIN users u ON a.seller_id = u.id
            WHERE a.seller_id = ?
            ORDER BY a.created_at DESC");
        $stmt->execute([intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 我出价的拍卖
     */
    public function getMyBids($userId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT a.*, u.username AS seller_name,
                   (SELECT MAX(amount) FROM auction_bids WHERE auction_id = a.id AND bidder_id = ?) AS my_max_bid
            FROM auction_bids b
            JOIN auctions a ON b.auction_id = a.id
            LEFT JOIN users u ON a.seller_id = u.id
            WHERE b.bidder_id = ?
            ORDER BY b.created_at DESC");
        $stmt->execute([intval($userId), intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
