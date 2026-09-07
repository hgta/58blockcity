<?php
/**
 * hufang 后台 — 人气值同步 AJAX 端点
 * 支持 action=prepare / chunk / single
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/CitySyncer.php';

checkAdmin();

header('Content-Type: application/json; charset=utf-8');

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$success = false;
$msg     = '未知操作';
$extra   = [];

try {
    switch ($action) {
        case 'prepare':
            $areas    = CitySyncer::fetchAreaList();
            $areaStat = CitySyncer::applyOfficialAreas($areas, $pdo);
            $queue    = array_values(array_map('intval', array_column($areas, 'id')));

            $_SESSION['pop_sync_queue'] = $queue;

            $success = true;
            $msg     = "官方区域已同步：新增 {$areaStat['inserted']} 个城市，更新 {$areaStat['updated']} 个城市";
            $extra   = [
                'total'     => count($queue),
                'batchSize' => 25,
                'areaStat'  => $areaStat,
            ];
            break;

        case 'chunk':
            if (empty($_SESSION['pop_sync_queue']) || !is_array($_SESSION['pop_sync_queue'])) {
                $success = true;
                $extra   = ['finished' => true, 'done' => 0, 'total' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
                break;
            }

            $queue     = $_SESSION['pop_sync_queue'];
            $total     = (int)($_POST['total'] ?? count($queue));
            $batchSize = 25;
            $batch     = array_slice($queue, 0, $batchSize);
            $remaining = array_slice($queue, $batchSize);

            $_SESSION['pop_sync_queue'] = $remaining;

            $stat = CitySyncer::applyPopularity($batch, $pdo);

            $done    = $total - count($remaining);
            $success = true;
            $msg     = "本批完成：成功 {$stat['updated']}，失败 {$stat['failed']} 个城市";
            $extra   = [
                'finished' => empty($remaining),
                'done'     => $done,
                'total'    => $total,
                'updated'  => $stat['updated'],
                'failed'   => $stat['failed'],
                'errors'   => $stat['errors'],
            ];
            break;

        case 'single':
            $cityId = (int)($_POST['city_id'] ?? 0);
            if ($cityId <= 0) {
                throw new RuntimeException('参数错误');
            }

            $stmt = $pdo->prepare("SELECT id, name, official_area_id FROM cities WHERE id = ?");
            $stmt->execute([$cityId]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                throw new RuntimeException('城市不存在');
            }

            $areaId = $city['official_area_id'] ? (int)$city['official_area_id'] : null;

            // 本地没有缓存 areaId 时，临时拉一次官方区域列表进行匹配
            if (!$areaId) {
                $areas = CitySyncer::fetchAreaList();
                foreach ($areas as $a) {
                    if ($a['name'] === $city['name'] || CitySyncer::normName($a['name']) === CitySyncer::normName($city['name'])) {
                        $areaId = (int)$a['id'];
                        $upd = $pdo->prepare("UPDATE cities SET official_area_id = ? WHERE id = ?");
                        $upd->execute([$areaId, $cityId]);
                        break;
                    }
                }
            }

            if (!$areaId) {
                throw new RuntimeException('未找到该城市的官方 areaId');
            }

            $bal         = CitySyncer::fetchAreaBalance($areaId);
            $circulating = max(0, $bal['points'] - $bal['consume']);

            $upd = $pdo->prepare(
                "UPDATE cities
                    SET popularity = ?, popularity_consume = ?, popularity_balance = ?,
                        popularity_balance2 = ?, popularity_points_id = ?, updated_at = NOW()
                    WHERE id = ?"
            );
            $upd->execute([
                $bal['points'],
                $bal['consume'],
                $bal['balance'],
                $bal['balance2'],
                $bal['pointsId'],
                $cityId,
            ]);

            $updBct = $pdo->prepare("UPDATE city_bct SET circulating_supply = ? WHERE city = ?");
            $updBct->execute([$circulating, $city['name']]);

            $success = true;
            $msg     = $city['name'] . ' 人气值已更新';
            $extra   = ['balance' => $bal];
            break;

        default:
            $msg = '未知操作';
    }
} catch (Exception $e) {
    $success = false;
    $msg     = $e->getMessage();
}

echo json_encode(array_merge(
    ['success' => $success, 'msg' => $msg],
    $extra
));
