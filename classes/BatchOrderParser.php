<?php
/**
 * 批量订单文本解析器
 *
 * 统一「城市 数量 价格」多行文本的解析规则，供 BCT 批量发布交易
 * 与管理端批量设置单价共用，避免规则在两个入口之间漂移。
 *
 * 解析规则：
 *  - 全角空格归一为半角
 *  - 按 CRLF / CR / LF 切行
 *  - 忽略空行、以 # 或 // 开头的注释行
 *  - 以连续空白（空格或 Tab）分列
 *  - 每行必须恰好三列：城市 数量 价格
 */
class BatchOrderParser
{
    /** 单笔交易数量上限（单条与批量统一取值来源） */
    const MIN_AMOUNT = 1;
    const MAX_AMOUNT = 1000000;

    /** 行分隔符正则：CRLF / CR / LF */
    const LINE_SPLIT = '/[\r\n]+/';

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 解析文本为结构化行
     *
     * @param string $text 原始文本
     * @return array{items: array, invalid: array} items 为有效行，invalid 为解析失败行
     */
    public function parseText($text)
    {
        $items = [];
        $invalid = [];

        $text = str_replace('　', ' ', (string)$text);
        $text = trim($text);
        if ($text === '') {
            return ['items' => [], 'invalid' => []];
        }

        $lines = preg_split(self::LINE_SPLIT, $text);
        foreach ($lines as $lineNo => $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }
            if ($line[0] === '#' || strpos($line, '//') === 0) {
                continue;
            }

            $lineLabel = $lineNo + 1;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) !== 3) {
                $invalid[] = [
                    'line' => $lineLabel,
                    'text' => mb_substr($line, 0, 60),
                    'reason' => '应为「城市 数量 价格」三列',
                ];
                continue;
            }

            $name = trim($parts[0]);
            $qty = trim($parts[1]);
            $price = trim($parts[2]);

            if ($name === '') {
                $invalid[] = ['line' => $lineLabel, 'text' => $line, 'reason' => '城市名为空'];
                continue;
            }
            if (!ctype_digit($qty) || (int)$qty < self::MIN_AMOUNT || (int)$qty > self::MAX_AMOUNT) {
                $invalid[] = [
                    'line' => $lineLabel,
                    'text' => $name,
                    'reason' => '数量必须是 ' . number_format(self::MIN_AMOUNT) . ' - ' . number_format(self::MAX_AMOUNT) . ' 的整数',
                ];
                continue;
            }
            if (!is_numeric($price) || (float)$price <= 0) {
                $invalid[] = ['line' => $lineLabel, 'text' => $name, 'reason' => '价格必须是正数'];
                continue;
            }

            $items[] = [
                'line' => $lineLabel,
                'input' => $name,
                'amount' => (int)$qty,
                'price' => (float)$price,
            ];
        }

        return ['items' => $items, 'invalid' => $invalid];
    }

    /**
     * 按「城市 + 价格」合并累加数量
     *
     * 同名同价视为同一条挂单并累加数量；同名不同价保留为多条独立挂单。
     * 合并必须先于校验，否则多条小额行会各自通过余额校验却累加出超额挂单。
     *
     * @param array $items parseText() 返回的 items
     * @return array 合并后的挂单列表，每项含 city/price/amount/lines
     */
    public function mergeByNameAndPrice(array $items)
    {
        $merged = [];
        $order = [];

        foreach ($items as $item) {
            $city = isset($item['city']) ? $item['city'] : $item['input'];
            $key = $city . "\x00" . number_format((float)$item['price'], 2, '.', '');

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'city' => $city,
                    'price' => (float)$item['price'],
                    'amount' => 0,
                    'lines' => [],
                ];
                $order[] = $key;
            }
            $merged[$key]['amount'] += (int)$item['amount'];
            $merged[$key]['lines'][] = $item['line'];
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $merged[$key];
        }
        return $result;
    }

    /**
     * 城市匹配：先按 cities.name 精确匹配，再按 cities.pinyin 匹配
     *
     * @param string $input 用户输入的城市名或拼音
     * @return string|null 命中时返回 cities.name，未命中返回 null
     */
    public function matchCity($input)
    {
        $input = trim((string)$input);
        if ($input === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT name FROM cities WHERE name = ? LIMIT 1");
        $stmt->execute([$input]);
        $name = $stmt->fetchColumn();
        if ($name !== false && $name !== null) {
            return (string)$name;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT name FROM cities WHERE pinyin = ? LIMIT 1");
            $stmt->execute([strtolower($input)]);
            $name = $stmt->fetchColumn();
            if ($name !== false && $name !== null) {
                return (string)$name;
            }
        } catch (Exception $e) {
            // cities 表无 pinyin 列时忽略拼音匹配
        }

        return null;
    }

    /**
     * 解析 + 城市匹配 + 合并，产出待校验挂单与未命中城市
     *
     * @param string $text 原始文本
     * @return array{
     *   orders: array,     合并后的挂单（每项含 city/price/amount/lines）
     *   missing: array,    城市不存在的行
     *   invalid: array,    解析失败的行
     *   duplicate: array,  同名同价被累加的挂单（用于提示）
     * }
     */
    public function parseAndResolve($text)
    {
        $parsed = $this->parseText($text);

        $resolved = [];
        $missing = [];
        foreach ($parsed['items'] as $item) {
            $city = $this->matchCity($item['input']);
            if ($city === null) {
                $missing[] = [
                    'line' => $item['line'],
                    'text' => $item['input'],
                    'reason' => '城市不存在',
                ];
                continue;
            }
            $item['city'] = $city;
            $resolved[] = $item;
        }

        $orders = $this->mergeByNameAndPrice($resolved);

        $duplicate = [];
        foreach ($orders as $o) {
            if (count($o['lines']) > 1) {
                $duplicate[] = [
                    'city' => $o['city'],
                    'price' => $o['price'],
                    'amount' => $o['amount'],
                    'lines' => $o['lines'],
                ];
            }
        }

        return [
            'orders' => $orders,
            'missing' => $missing,
            'invalid' => $parsed['invalid'],
            'duplicate' => $duplicate,
        ];
    }
}
