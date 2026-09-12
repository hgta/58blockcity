<?php
/**
 * 58拍卖 — 拍卖业务类
 *
 * 拍卖品支持区块(blocks)和 NFT 头像(nft_city_user 持有记录)。
 * 与 block.58.tl / nft.58.tl 联动：成交后转移对应物品所有权。
 *
 * v2 增强（auction-hall-redesign）：
 *  - 真实出价次数 / 竞拍人数 / 关注数统计
 *  - 自动延时防狙击（复用惰性状态机，不改结算逻辑）
 *  - 「被超越」通知
 *  - 关注 / 取关
 */

require_once __DIR__ . '/Block.php';
require_once __DIR__ . '/NFT.php';
require_once __DIR__ . '/Notification.php';

class Auction {
    private $pdo;
    private $block;
    private $nft;
    private $notify;

    /** 自动延时默认参数（秒 / 次） */
    const AUTO_EXTEND_SECONDS   = 120;   // 每次顺延时长
    const EXTEND_WINDOW_SECONDS = 120;   // 触发窗口：结束前 N 秒内出价才顺延
    const MAX_EXTEND_TIMES      = 10;    // 单场最多顺延次数
    const MAX_EXTEND_SECONDS    = 1800;  // 单场累计最长顺延

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
                 start_time, end_time, current_price, currency, accept_cities, status,
                 bid_count, bidder_count, watch_count, extend_count,
                 auto_extend_seconds, extend_window_seconds, max_extend_times, max_extend_seconds,
                 created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([
            $itemType, $itemId, $sellerId, $startPrice, $reservePrice, $bidIncrement,
            $startTime, $endTime, $startPrice, $currency, $acceptCitiesJson, $status,
            self::AUTO_EXTEND_SECONDS, self::EXTEND_WINDOW_SECONDS, self::MAX_EXTEND_TIMES, self::MAX_EXTEND_SECONDS,
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
     * 下一口最低可出价金额
     */
    public function nextMinBid($auction) {
        if (empty($auction['current_bidder_id'])) {
            return floatval($auction['start_price']);
        }
        return floatval($auction['current_price']) + floatval($auction['bid_increment']);
    }

    /**
     * 出价
     * @return array ['ok'=>bool,'msg'=>string,'current_price'=>float,'next_min'=>float,'end_time'=>string,'extended'=>bool]
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

            $prevBidderId = intval($a['current_bidder_id'] ?? 0);

            // 该用户是否首次对本拍品出价（用于竞拍人数）
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM auction_bids WHERE auction_id = ? AND bidder_id = ?");
            $chk->execute([$auctionId, $bidderId]);
            $isFirstBidByUser = intval($chk->fetchColumn()) === 0;

            // 写入出价记录（记录被超越的原领先者）
            $stmt = $this->pdo->prepare("
                INSERT INTO auction_bids (auction_id, bidder_id, amount, prev_bidder_id, created_at)
                VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$auctionId, $bidderId, $amount, $prevBidderId > 0 ? $prevBidderId : null]);

            // 自动延时：结束前窗口内出价 → 顺延（复用惰性状态机，仅推后 end_time）
            $endTs        = strtotime($a['end_time']);
            $extendWindow = intval($a['extend_window_seconds'] ?? 0) ?: self::EXTEND_WINDOW_SECONDS;
            $extendStep   = intval($a['auto_extend_seconds'] ?? 0) ?: self::AUTO_EXTEND_SECONDS;
            $maxTimes     = intval($a['max_extend_times'] ?? self::MAX_EXTEND_TIMES);
            $maxSeconds   = intval($a['max_extend_seconds'] ?? self::MAX_EXTEND_SECONDS);
            $extendCount  = intval($a['extend_count'] ?? 0);

            $newEndTs  = $endTs;
            $extended  = false;
            if ($extendStep > 0 && $maxTimes > 0 && $now >= $endTs - $extendWindow) {
                $accumulated = $extendCount * $extendStep;
                if ($extendCount < $maxTimes && ($accumulated + $extendStep) <= $maxSeconds) {
                    $newEndTs = $endTs + $extendStep;
                    $extendCount++;
                    $extended = true;
                }
            }

            // 更新当前价 + 计数 + 结束时间
            $stmt = $this->pdo->prepare("
                UPDATE auctions
                SET current_price = ?, current_bidder_id = ?,
                    bid_count = bid_count + 1,
                    bidder_count = bidder_count + ?,
                    end_time = ?, extend_count = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $amount, $bidderId,
                $isFirstBidByUser ? 1 : 0,
                date('Y-m-d H:i:s', $newEndTs), $extendCount,
                $auctionId,
            ]);

            $this->pdo->commit();

            // 通知：原领先者被超越（同人加价 / 卖家 / 无原领先者不通知）
            if ($prevBidderId > 0 && $prevBidderId !== $bidderId && $prevBidderId !== intval($a['seller_id'])) {
                $this->notify->sendSystemNotify(
                    $prevBidderId, 'auction_outbid', $auctionId,
                    '您的出价已被超越，当前价 ' . number_format($amount, 2) . '，快去夺回领先！',
                    'https://bid.58.tl/view.php?id=' . $auctionId
                );
            }

            return [
                'ok'            => true,
                'msg'           => $extended ? '出价成功，已自动延时' : '出价成功',
                'current_price' => $amount,
                'next_min'      => $amount + floatval($a['bid_increment']),
                'end_time'      => date('Y-m-d H:i:s', $newEndTs),
                'extended'      => $extended,
            ];
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

                // 流拍通知（仅在本次由 active → ended 时产生一次）
                $this->notify->sendSystemNotify(
                    intval($a['seller_id']), 'auction_failed', $auctionId,
                    '您的拍卖已流拍，未达到成交条件。',
                    'https://bid.58.tl/view.php?id=' . $auctionId
                );
                return;
            }

            // 成交：转移所有权
            $this->transferOwnership($a['item_type'], intval($a['item_id']), $bidderId, $price);

            $stmt = $this->pdo->prepare("
                UPDATE auctions SET status = 'sold', final_price = ?, sold_at = NOW(), updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$price, $auctionId]);

            // 通知买卖双方
            $this->notify->sendSystemNotify(
                $bidderId, 'auction_won', $auctionId,
                '恭喜！您已赢得拍卖，物品所有权已转移给您。',
                'https://bid.58.tl/view.php?id=' . $auctionId
            );
            $this->notify->sendSystemNotify(
                intval($a['seller_id']), 'auction_sold', $auctionId,
                '您的拍卖已成交，成交价 ' . number_format($price, 2) . '。',
                'https://bid.58.tl/view.php?id=' . $auctionId
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

    /* ========== 关注 / 收藏 ========== */

    /**
     * 关注 / 取关（幂等，切换式）
     * @return array ['ok'=>bool,'watching'=>bool,'watch_count'=>int,'msg'=>string]
     */
    public function toggleWatch($auctionId, $userId) {
        $auctionId = intval($auctionId);
        $userId    = intval($userId);
        if ($auctionId <= 0) return ['ok' => false, 'msg' => '参数无效'];
        if ($userId <= 0)  return ['ok' => false, 'msg' => '请先登录'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT id, watch_count FROM auctions WHERE id = ? FOR UPDATE");
            $stmt->execute([$auctionId]);
            $a = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$a) throw new Exception('拍卖不存在');

            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM auction_watches WHERE auction_id = ? AND user_id = ?");
            $chk->execute([$auctionId, $userId]);
            $exists = intval($chk->fetchColumn()) > 0;

            if ($exists) {
                $stmt = $this->pdo->prepare("DELETE FROM auction_watches WHERE auction_id = ? AND user_id = ?");
                $stmt->execute([$auctionId, $userId]);
                $stmt = $this->pdo->prepare("UPDATE auctions SET watch_count = GREATEST(watch_count - 1, 0) WHERE id = ?");
                $stmt->execute([$auctionId]);
                $watching = false;
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO auction_watches (auction_id, user_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$auctionId, $userId]);
                $stmt = $this->pdo->prepare("UPDATE auctions SET watch_count = watch_count + 1 WHERE id = ?");
                $stmt->execute([$auctionId]);
                $watching = true;
            }

            $cnt = $this->pdo->prepare("SELECT watch_count FROM auctions WHERE id = ?");
            $cnt->execute([$auctionId]);
            $watchCount = intval($cnt->fetchColumn());

            $this->pdo->commit();
            return ['ok' => true, 'watching' => $watching, 'watch_count' => $watchCount,
                    'msg' => $watching ? '已关注' : '已取消关注'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 是否已关注
     */
    public function isWatching($auctionId, $userId) {
        $userId = intval($userId);
        if ($userId <= 0) return false;
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM auction_watches WHERE auction_id = ? AND user_id = ?");
        $stmt->execute([intval($auctionId), $userId]);
        return intval($stmt->fetchColumn()) > 0;
    }

    /* ========== 查询 ========== */

    /**
     * 拍卖中列表
     * @param string $sort 排序维度：ending(即将结束) | new(最新) | hot(热拍) | price(价格)
     */
    public function getActiveAuctions($page = 1, $perPage = 20, $itemType = '', $currency = '', $sort = 'ending') {
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

        $orderMap = [
            'ending' => 'a.end_time ASC',
            'new'    => 'a.created_at DESC',
            'hot'    => 'a.bid_count DESC, a.watch_count DESC, a.end_time ASC',
            'price'  => 'a.current_price DESC, a.end_time ASC',
        ];
        $orderSql = $orderMap[$sort] ?? $orderMap['ending'];

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM auctions a WHERE " . $whereSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username AS seller_name, u.avatar AS seller_avatar
            FROM auctions a
            LEFT JOIN users u ON a.seller_id = u.id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachItemInfo($list);
        $this->attachStats($list);

        return [
            'list' => $list,
            'total' => $total,
            'pages' => $total > 0 ? ceil($total / $perPage) : 0,
        ];
    }

    /**
     * 已结束拍卖列表（sold + ended）
     */
    public function getEndedAuctions($page = 1, $perPage = 20, $itemType = '', $currency = '') {
        $offset = (max(1, intval($page)) - 1) * intval($perPage);
        $where = ["a.status IN ('sold','ended')"];
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
            SELECT a.*,
                   s.username AS seller_name, s.avatar AS seller_avatar,
                   w.username AS winner_name, w.avatar AS winner_avatar
            FROM auctions a
            LEFT JOIN users s ON a.seller_id = s.id
            LEFT JOIN users w ON a.current_bidder_id = w.id
            WHERE {$whereSql}
            ORDER BY a.end_time DESC
            LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachItemInfo($list);
        $this->attachStats($list);

        return [
            'list' => $list,
            'total' => $total,
            'pages' => $total > 0 ? ceil($total / $perPage) : 0,
        ];
    }

    /**
     * 刚落槌成交榜（社会证明）
     */
    public function getRecentlySold($limit = 6) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, w.username AS winner_name
            FROM auctions a
            LEFT JOIN users w ON a.current_bidder_id = w.id
            WHERE a.status = 'sold'
            ORDER BY COALESCE(a.sold_at, a.updated_at) DESC
            LIMIT " . intval($limit));
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachItemInfo($list);
        $this->attachStats($list);
        return $list;
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
                // 区块皮肤图片在 block 子站，用跨站绝对路径；无图时返回空，由展示层回退默认图
                $a['item_image'] = !empty($b['display_image']) ? 'https://block.58.tl/' . ltrim($b['display_image'], '/') : null;
                // 保留 blocks.id 作为详情页参数（ auctions.item_id 已等同 blocks.id，显式同步防后续变动）
                $a['block_id'] = intval($b['id'] ?? $a['item_id']);
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
                // 暴露 nft 子站详情页所需主键
                $a['nft_id'] = $rec['nft_id'] ?? null;
            }
        }
        return $a;
    }

    /**
     * 批量补充物品信息（避免 N+1）
     */
    private function attachItemInfo(array &$rows) {
        if (empty($rows)) return;

        $blockIds = [];
        $ncuIds   = [];
        foreach ($rows as $r) {
            if ($r['item_type'] === 'block') $blockIds[] = intval($r['item_id']);
            else $ncuIds[] = intval($r['item_id']);
        }

        $blockMap = [];
        $nftMap   = [];

        if (!empty($blockIds)) {
            $in = implode(',', array_fill(0, count($blockIds), '?'));
            $stmt = $this->pdo->prepare("
                SELECT b.id, b.zone, b.block_number, b.display_image, c.name AS city_name
                FROM blocks b
                LEFT JOIN cities c ON b.city_id = c.id
                WHERE b.id IN ($in)");
            $stmt->execute($blockIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $blockMap[intval($b['id'])] = $b;
            }
        }

        if (!empty($ncuIds)) {
            $in = implode(',', array_fill(0, count($ncuIds), '?'));
            $stmt = $this->pdo->prepare("
                SELECT ncu.id, ncu.nft_id, ncu.city_id, c.name AS city_name, n.code, n.base_image
                FROM nft_city_user ncu
                LEFT JOIN cities c ON ncu.city_id = c.id
                LEFT JOIN nft_avatars n ON ncu.nft_id = n.id
                WHERE ncu.id IN ($in)");
            $stmt->execute($ncuIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
                $nftMap[intval($n['id'])] = $n;
            }
        }

        foreach ($rows as &$r) {
            if ($r['item_type'] === 'block') {
                $b = $blockMap[intval($r['item_id'])] ?? null;
                $r['item_title'] = $b
                    ? (($b['city_name'] ?? '') . ' ' . ($b['zone'] ?? '') . '区 #' . ($b['block_number'] ?? ''))
                    : ('区块 #' . $r['item_id']);
                $r['item_image'] = (!empty($b['display_image'])) ? 'https://block.58.tl/' . ltrim($b['display_image'], '/') : null;
                $r['block_id']   = intval($r['item_id']);
            } else {
                $n = $nftMap[intval($r['item_id'])] ?? null;
                $r['item_title'] = $n ? ('NFT头像 #' . $n['code'] . '（' . $n['city_name'] . '）') : ('NFT头像 #' . $r['item_id']);
                $r['item_image'] = (!empty($n['base_image'])) ? 'https://nft.58.tl/avatar/' . $n['base_image'] : null;
                $r['nft_id']     = $n['nft_id'] ?? null;
            }
        }
        unset($r);
    }

    /**
     * 批量校准真实统计（出价次数 / 竞拍人数 / 关注数）
     * 两条聚合 SQL 覆盖整页数据，避免逐条查询（N+1）
     */
    private function attachStats(array &$rows) {
        if (empty($rows)) return;

        $ids = [];
        foreach ($rows as $r) {
            $id = intval($r['id']);
            if ($id > 0) $ids[$id] = true;
        }
        $ids = array_keys($ids);
        if (empty($ids)) return;

        $in = implode(',', array_fill(0, count($ids), '?'));

        $bidMap = [];
        $stmt = $this->pdo->prepare("
            SELECT auction_id, COUNT(*) AS c, COUNT(DISTINCT bidder_id) AS d
            FROM auction_bids WHERE auction_id IN ($in) GROUP BY auction_id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bidMap[intval($r['auction_id'])] = $r;
        }

        $watchMap = [];
        $stmt = $this->pdo->prepare("
            SELECT auction_id, COUNT(*) AS c
            FROM auction_watches WHERE auction_id IN ($in) GROUP BY auction_id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $watchMap[intval($r['auction_id'])] = intval($r['c']);
        }

        foreach ($rows as &$r) {
            $id = intval($r['id']);
            $r['bid_count']    = intval($bidMap[$id]['c'] ?? 0);
            $r['bidder_count'] = intval($bidMap[$id]['d'] ?? 0);
            $r['watch_count']  = $watchMap[$id] ?? 0;
        }
        unset($r);
    }

    /**
     * 出价记录
     * @param string $order time(时间倒序，默认，用于实时叫价流) | amount(金额倒序)
     */
    public function getBids($auctionId, $limit = 50, $order = 'time') {
        $orderSql = ($order === 'amount') ? 'b.amount DESC, b.created_at ASC' : 'b.created_at DESC, b.id DESC';
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.username AS bidder_name, u.avatar AS bidder_avatar
            FROM auction_bids b
            LEFT JOIN users u ON b.bidder_id = u.id
            WHERE b.auction_id = ?
            ORDER BY {$orderSql}
            LIMIT " . intval($limit));
        $stmt->execute([intval($auctionId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 出价统计（一条 SQL：出价条数 + 去重竞拍人数）
     * @return array ['bid_count'=>int,'bidder_count'=>int]
     */
    public function getBidStats($auctionId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS bid_count, COUNT(DISTINCT bidder_id) AS bidder_count
            FROM auction_bids WHERE auction_id = ?");
        $stmt->execute([intval($auctionId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'bid_count'    => intval($row['bid_count'] ?? 0),
            'bidder_count' => intval($row['bidder_count'] ?? 0),
        ];
    }

    /**
     * 取计数（列表类查询已由 attachStats 批量校准）
     */
    public function getCounters($auction) {
        return [
            'bid_count'    => intval($auction['bid_count'] ?? 0),
            'bidder_count' => intval($auction['bidder_count'] ?? 0),
            'watch_count'  => intval($auction['watch_count'] ?? 0),
        ];
    }

    /**
     * 详情页轮询快照（只读，轻量）
     */
    public function getAuctionSnapshot($auctionId, $viewerId = 0) {
        $auctionId = intval($auctionId);
        $viewerId  = intval($viewerId);

        $stmt = $this->pdo->prepare("
            SELECT id, status, currency, current_price, start_price, current_bidder_id, seller_id,
                   end_time, start_time, bid_increment, reserve_price,
                   bid_count, bidder_count, watch_count, extend_count,
                   auto_extend_seconds, extend_window_seconds
            FROM auctions WHERE id = ?");
        $stmt->execute([$auctionId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;

        $counters = $this->getCounters($a);
        $currentBidderId = intval($a['current_bidder_id'] ?? 0);

        // 本人状态：leading(领先) / outbid(被超越) / watching / none
        $myState = 'none';
        if ($viewerId > 0) {
            if ($currentBidderId === $viewerId) {
                $myState = 'leading';
            } else {
                $isSeller = intval($a['seller_id']) === $viewerId;
                if (!$isSeller) {
                    $mineStmt = $this->pdo->prepare("SELECT COUNT(*) FROM auction_bids WHERE auction_id = ? AND bidder_id = ?");
                    $mineStmt->execute([$auctionId, $viewerId]);
                    if (intval($mineStmt->fetchColumn()) > 0) {
                        $myState = 'outbid';
                    }
                }
            }
            if ($myState === 'none' && $this->isWatching($auctionId, $viewerId)) {
                $myState = 'watching';
            }
        }

        return [
            'id'             => intval($a['id']),
            'status'         => $a['status'],
            'currency'       => $a['currency'],
            'current_price'  => floatval($a['current_price'] ?? $a['start_price']),
            'start_price'    => floatval($a['start_price']),
            'current_bidder_id' => $currentBidderId,
            'bid_increment'  => floatval($a['bid_increment']),
            'reserve_price'  => $a['reserve_price'] !== null ? floatval($a['reserve_price']) : null,
            'next_min'       => $this->nextMinBid($a),
            'end_time'       => $a['end_time'],
            'start_time'     => $a['start_time'],
            'extend_count'   => intval($a['extend_count'] ?? 0),
            'extend_seconds' => intval($a['auto_extend_seconds'] ?? 0) ?: self::AUTO_EXTEND_SECONDS,
            'extend_window'  => intval($a['extend_window_seconds'] ?? 0) ?: self::EXTEND_WINDOW_SECONDS,
            'bid_count'      => $counters['bid_count'],
            'bidder_count'   => $counters['bidder_count'],
            'watch_count'    => $counters['watch_count'],
            'my_state'       => $myState,
            'bids'           => $this->getBids($auctionId, 8, 'time'),
        ];
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachItemInfo($rows);
        $this->attachStats($rows);
        return $rows;
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachItemInfo($rows);
        $this->attachStats($rows);
        return $rows;
    }

    /**
     * 我关注的拍卖
     */
    public function getMyWatched($userId) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username AS seller_name
            FROM auction_watches w
            JOIN auctions a ON w.auction_id = a.id
            LEFT JOIN users u ON a.seller_id = u.id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC");
        $stmt->execute([intval($userId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachItemInfo($rows);
        $this->attachStats($rows);
        return $rows;
    }
}
