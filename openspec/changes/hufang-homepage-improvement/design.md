# 设计文档

## Visit 状态水印

```
visits.status:
  pending/confirmed → "访问中" (橙色)
  visited/returned  → "已访"   (蓝色)
  completed         → "已互访" (绿色)
```

## Circle::getCirclesByCityPaginated()

```php
public function getCirclesByCityPaginated($city, $page=1, $perPage=20, $search='') {
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT c.*, u.username, u.avatar 
            FROM circles c JOIN users u ON c.user_id = u.id
            WHERE c.city = ? AND c.status = 'active'";
    // + search + ORDER BY + LIMIT/OFFSET
}

public function getCircleCountByCity($city, $search='') {
    // SELECT COUNT(*) ...
}
```

## Visit::getUserVisitedCircleIds()

```php
// 批量获取当前用户访问过的圈子ID及状态
public function getUserVisitedCircleIds($userId) {
    $sql = "SELECT circle_id, status FROM visits WHERE visitor_id = ?";
    // 返回 [circle_id => status] 映射
}
```

## 城市标签折叠

```php
// 热门城市（有圈子且圈子数最多的前20个）
$hotCities = $pdo->query("SELECT city, COUNT(*) as cnt 
    FROM circles WHERE status='active' 
    GROUP BY city ORDER BY cnt DESC LIMIT 20")->fetchAll();
```

## 列表模式表格

| 圈名 | 城市 | 区块数 | 圈主 | 状态 | 操作 |
