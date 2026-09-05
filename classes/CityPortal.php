<?php
/**
 * CityPortal — 58 生态「城市门户」聚合装配层
 *
 * 输入一条 cities 行，输出六个子站模块的聚合数据：
 *   blocks  区块街景（最新命名区块，按 city_id）
 *   bct     BCT 行情（城市名）
 *   nft     NFT 挂售（按 city_id）
 *   club    同城动态（posts，城市名）
 *   circles 互访圈（circles，城市名）
 *   mall    城市好店（models/authors，城市名）
 *
 * 每个模块独立容错：任何异常/缺表/无数据 → 归一为 `{ok,count,items,msg}` 空态，
 * 绝不向上抛 500。全部 SQL PDO 预处理。
 *
 * 城市名口径：模块按 `cities.name` **原值**过滤（club/index.php 的城市下拉/发帖
 * 即取 `cities.name`，故 posts/circles.city 与之同口径）；mall 的 city 来自
 * `models.city` 自身集合，按 cities.name 命中为准、空则空态。展示层直接用原值。
 *
 * 图片/深链 URL 约定（子站真实路由，见各子站页面）：
 *   NFT 原图        https://nft.58.tl/avatar/{base_image}
 *   mall 头像       以 http 开头原样用，否则 https://mall.58.tl/{avatar}
 *   block 皮肤图    以 http 开头原样用，否则 https://block.58.tl/{display_image}
 */
class CityPortal {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* ================= 入口：装配 ================= */

    /**
     * @param array $city cities 表一行
     * @return array 六个模块 + 城市键信息
     */
    public function assemble(array $city) {
        $cityId   = (int)($city['id'] ?? 0);
        $cityName = trim((string)($city['name'] ?? ''));

        return [
            'city_id'   => $cityId,
            'city_name' => $cityName,
            'blocks'    => $this->blocks($cityId),
            'bct'       => $this->bct($cityName),
            'nft'       => $this->nft($cityId),
            'club'      => $this->club($cityName),
            'circles'   => $this->circles($cityName),
            'mall'      => $this->mall($cityName),
        ];
    }

    /** 城市真实资料卡（city_profiles 单行；表缺失/无记录 → null，不抛异常） */
    public function profile($cityId) {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM city_profiles WHERE city_id = ? LIMIT 1');
            $stmt->execute([(int)$cityId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('[CityPortal::profile] city_profiles 读取失败: ' . $e->getMessage());
            return null;
        }
    }

    /* ================= 各模块 getter ================= */

    /** 🏘 区块街景：最新命名区块（有名字 + 已售出/预留） */
    public function blocks($cityId) {
        return $this->guard(function () use ($cityId) {
            $sql = "SELECT zone, block_number, name, status, display_type,
                           display_image, display_text, display_color, updated_at
                    FROM blocks
                    WHERE city_id = ? AND name IS NOT NULL AND name <> ''
                      AND status IN ('sold','reserved')
                    ORDER BY (status = 'sold') DESC, updated_at DESC
                    LIMIT 6";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([(int)$cityId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $r) {
                $img = $r['display_image'] ?? '';
                if ($img !== '' && strpos($img, 'http') !== 0) {
                    $img = 'https://block.58.tl/' . ltrim($img, '/');
                }
                $items[] = [
                    'zone'       => $r['zone'] ?? '',
                    'number'     => $r['block_number'] ?? '',
                    'name'       => $r['name'] ?? '',
                    'status'     => $r['status'] ?? 'sold',
                    'display_text' => $r['display_text'] ?? '',
                    'display_color' => $r['display_color'] ?? 'red',
                    'img'        => ($r['display_type'] ?? 'none') === 'image' ? $img : '',
                    'updated_at' => $r['updated_at'] ?? '',
                ];
            }
            return $this->pack($items);
        });
    }

    /** 💰 BCT 行情（city_bct 单行） */
    public function bct($cityName) {
        return $this->guard(function () use ($cityName) {
            if ($cityName === '') {
                return $this->pack([]);
            }
            $this->_load('CityBCT');
            $row = (new CityBCT($this->pdo))->getCityBCT($cityName);
            if (!$row) {
                return $this->pack([]);
            }
            return [
                'ok'    => true,
                'count' => 1,
                'items' => [[
                    'city'              => $cityName,
                    'current_price'     => $row['current_price'] ?? 0,
                    'base_price'        => $row['base_price'] ?? 0,
                    'circulating_supply'=> $row['circulating_supply'] ?? 0,
                    'total_supply'      => $row['total_supply'] ?? 0,
                    'last_updated'      => $row['last_updated'] ?? '',
                ]],
                'msg'   => '',
            ];
        });
    }

    /** 🖼 NFT 热卖：该城正在挂售的 NFT（nft_transactions status=listed） */
    public function nft($cityId) {
        return $this->guard(function () use ($cityId) {
            $this->_load('NFT');
            $nft = new NFT($this->pdo);
            $rows = $nft->getAvatarsByCity((int)$cityId, 6, 'listed', 0);
            if (!is_array($rows) || !$rows) {
                return $this->pack([]);
            }
            $items = [];
            foreach ($rows as $r) {
                $img = $r['base_image'] ?? '';
                if ($img !== '' && strpos($img, 'http') !== 0) {
                    $img = 'https://nft.58.tl/avatar/' . ltrim($img, '/');
                }
                $items[] = [
                    'nft_id'    => (int)($r['id'] ?? 0),
                    'code'      => $r['code'] ?? '',
                    'img'       => $img,
                    'price'     => $r['price'] ?? 0,
                    'currency'  => $r['currency'] ?? 'popularity',
                    'seller'    => $r['seller_name'] ?? '',
                    'list_time' => $r['list_time'] ?? '',
                ];
            }
            return $this->pack($items);
        });
    }

    /** 💬 同城动态：posts（club）按城市取最新 */
    public function club($cityName) {
        return $this->guard(function () use ($cityName) {
            if ($cityName === '') {
                return $this->pack([]);
            }
            $this->_load('Post');
            $post = new Post($this->pdo);
            $feed = $post->getFeed(1, 5, $cityName);
            $rows = is_array($feed) ? ($feed['list'] ?? []) : [];
            if (!$rows) {
                return $this->pack([]);
            }
            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'id'            => (int)$r['id'],
                    'type'          => $r['type'] ?? 'post',
                    'title'         => $r['title'] ?? '',
                    'content'       => $r['content'] ?? '',
                    'topic'         => $r['topic'] ?? '',
                    'username'      => $r['username'] ?? '',
                    'like_count'    => $r['like_count'] ?? 0,
                    'comment_count' => $r['comment_count'] ?? 0,
                    'created_at'    => $r['created_at'] ?? '',
                ];
            }
            return $this->pack($items);
        });
    }

    /** 🔄 互访圈：circles 圈子卡 + 城市聚合统计（city_rankings 视图可选） */
    public function circles($cityName) {
        return $this->guard(function () use ($cityName) {
            if ($cityName === '') {
                return $this->pack([]);
            }
            $this->_load('Circle');
            $circle = new Circle($this->pdo);
            $rows = $circle->getCirclesByCity($cityName, 6, '');
            if (!is_array($rows)) {
                $rows = [];
            }

            $stat = ['circle_count' => null, 'user_count' => null, 'visit_count' => null];
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT circle_count, user_count, visit_count FROM city_rankings WHERE city = ? LIMIT 1'
                );
                $stmt->execute([$cityName]);
                $s = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($s) {
                    $stat = $s;
                }
            } catch (PDOException $e) {
                // 视图不存在/不可用时忽略，只展示圈子
                error_log('[CityPortal::circles] city_rankings 读取失败: ' . $e->getMessage());
            }

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'id'          => (int)$r['id'],
                    'name'        => $r['name'] ?? '',
                    'description' => $r['description'] ?? '',
                    'category'    => $r['category'] ?? '',
                    'block_count' => $r['block_count'] ?? 0,
                    'username'    => $r['username'] ?? '',
                    'created_at'  => $r['created_at'] ?? '',
                ];
            }
            $res = $this->pack($items);
            $res['stat'] = $stat;
            return $res;
        });
    }

    /** 🛍 城市好店：models / authors（各取 active 3，含真实城市字段） */
    public function mall($cityName) {
        return $this->guard(function () use ($cityName) {
            if ($cityName === '') {
                return $this->pack([]);
            }
            $items = [];

            $stmt = $this->pdo->prepare(
                "SELECT id, nickname, avatar, gender, like_count
                 FROM models
                 WHERE city = ? AND status = 'active'
                 ORDER BY like_count DESC, id ASC
                 LIMIT 3"
            );
            $stmt->execute([$cityName]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = $this->mallItem('model', $r);
            }

            $stmt = $this->pdo->prepare(
                "SELECT id, nickname, avatar, gender, like_count
                 FROM authors
                 WHERE city = ? AND status = 'active'
                 ORDER BY like_count DESC, id ASC
                 LIMIT 3"
            );
            $stmt->execute([$cityName]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = $this->mallItem('author', $r);
            }

            return $this->pack($items);
        });
    }

    /* ================= 内部工具 ================= */

    private function mallItem($kind, array $r) {
        $avatar = $r['avatar'] ?? '';
        if ($avatar !== '' && strpos($avatar, 'http') !== 0) {
            $avatar = 'https://mall.58.tl/' . ltrim($avatar, '/');
        }
        return [
            'kind'     => $kind,
            'id'       => (int)$r['id'],
            'nickname' => $r['nickname'] ?? '',
            'gender'   => $r['gender'] ?? '保密',
            'like_count' => (int)($r['like_count'] ?? 0),
            'img'      => $avatar,
        ];
    }

    /** 包成统一模块结构 */
    private function pack(array $items) {
        $items = array_values($items);
        return [
            'ok'    => count($items) > 0,
            'count' => count($items),
            'items' => $items,
            'msg'   => '',
        ];
    }

    /** 统一容错包装：内部任何 Throwable 归一为空态 */
    private function guard(callable $fn) {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('[CityPortal] 模块聚合失败: ' . $e->getMessage());
            return $this->pack([]);
        }
    }

    /** 按需加载 classes 文件（文件缺失不致命） */
    private function _load($class) {
        if (class_exists($class)) {
            return;
        }
        $f = __DIR__ . '/' . $class . '.php';
        if (is_file($f)) {
            require_once $f;
        }
    }
}
